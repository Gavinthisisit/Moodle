<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Edit and save a new post to a discussion
 *
 * @package   mod_quora
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$quora   = optional_param('quora', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);

$PAGE->set_url('/mod/quora/post.php', array(
        'reply' => $reply,
        'quora' => $quora,
        'edit'  => $edit,
        'delete'=> $delete,
        'prune' => $prune,
        'name'  => $name,
        'confirm'=>$confirm,
        'groupid'=>$groupid,
        ));
//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'quora'=>$quora, 'edit'=>$edit);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and !get_local_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052
        require_login();
    }

    if (!empty($quora)) {      // User is starting a new discussion in a quora
        if (! $quora = $DB->get_record('quora', array('id' => $quora))) {
            print_error('invalidquoraid', 'quora');
        }
    } else if (!empty($reply)) {      // User is writing a new reply
        if (! $parent = quora_get_post_full($reply)) {
            print_error('invalidparentpostid', 'quora');
        }
        if (! $discussion = $DB->get_record('quora_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'quora');
        }
        if (! $quora = $DB->get_record('quora', array('id' => $discussion->quora))) {
            print_error('invalidquoraid');
        }
    }
    if (! $course = $DB->get_record('course', array('id' => $quora->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $quora);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'quora').'<br /><br />'.get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in

if (!empty($quora)) {      // User is starting a new discussion in a quora
    if (! $quora = $DB->get_record("quora", array("id" => $quora))) {
        print_error('invalidquoraid', 'quora');
    }
    if (! $course = $DB->get_record("course", array("id" => $quora->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("quora", $quora->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! quora_user_can_post_discussion($quora, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                        'returnurl' => '/mod/quora/view.php?f=' . $quora->id)),
                        get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostquora', 'quora');
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    $SESSION->fromurl = get_local_referer(false);

    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->quora         = $quora->id;
    $post->discussion    = 0;           // ie discussion # not defined yet
    $post->parent        = 0;
    $post->subject       = '';
    $post->userid        = $USER->id;
    $post->message       = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust  = 0;

    if (isset($groupid)) {
        $post->groupid = $groupid;
    } else {
        $post->groupid = groups_get_activity_group($cm);
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($reply)) {      // User is writing a new reply

    if (! $parent = quora_get_post_full($reply)) {
        print_error('invalidparentpostid', 'quora');
    }
    if (! $discussion = $DB->get_record("quora_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'quora');
    }
    if (! $quora = $DB->get_record("quora", array("id" => $discussion->quora))) {
        print_error('invalidquoraid', 'quora');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("quora", $quora->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $quora);

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! quora_user_can_post($quora, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/quora/view.php?f=' . $quora->id)),
                    get_string('youneedtoenrol'));
            }
        }
        print_error('nopostquora', 'quora');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostquora', 'quora');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostquora', 'quora');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->quora       = $quora->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'quora');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post

    if (! $post = quora_get_post_full($edit)) {
        print_error('invalidpostid', 'quora');
    }
    if ($post->parent) {
        if (! $parent = quora_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'quora');
        }
    }

    if (! $discussion = $DB->get_record("quora_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'quora');
    }
    if (! $quora = $DB->get_record("quora", array("id" => $discussion->quora))) {
        print_error('invalidquoraid', 'quora');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("quora", $quora->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $quora);

    if (!($quora->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
                    !has_capability('mod/quora:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'quora', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
                !has_capability('mod/quora:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'quora');
    }


    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->quora  = $quora->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

}else if (!empty($delete)) {  // User is deleting a post

    if (! $post = quora_get_post_full($delete)) {
        print_error('invalidpostid', 'quora');
    }
    if (! $discussion = $DB->get_record("quora_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'quora');
    }
    if (! $quora = $DB->get_record("quora", array("id" => $discussion->quora))) {
        print_error('invalidquoraid', 'quora');
    }
    if (!$cm = get_coursemodule_from_instance("quora", $quora->id, $quora->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $quora->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/quora:deleteownpost', $modcontext))
                || has_capability('mod/quora:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'quora');
    }


    $replycount = quora_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        //check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/quora:deleteanypost', $modcontext)) {
            print_error("cannotdeletepost", "quora",
                      quora_go_back_to("discuss.php?d=$post->discussion"));
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratings', 'rating'),
                    quora_go_back_to("discuss.php?d=$post->discussion"));

        } else if ($replycount && !has_capability('mod/quora:deleteanypost', $modcontext)) {
            print_error("couldnotdeletereplies", "quora",
                    quora_go_back_to("discuss.php?d=$post->discussion"));

        } else {
            if (! $post->parent) {  // post is a discussion topic as well, so delete discussion
                if ($quora->type == 'single') {
                    notice("Sorry, but you are not allowed to delete that discussion!",
                            quora_go_back_to("discuss.php?d=$post->discussion"));
                }
                quora_delete_discussion($discussion, false, $course, $cm, $quora);

                $params = array(
                    'objectid' => $discussion->id,
                    'context' => $modcontext,
                    'other' => array(
                        'quoraid' => $quora->id,
                    )
                );

                $event = \mod_quora\event\discussion_deleted::create($params);
                $event->add_record_snapshot('quora_discussions', $discussion);
                $event->trigger();

                redirect("view.php?f=$discussion->quora");

            } else if (quora_delete_post($post, has_capability('mod/quora:deleteanypost', $modcontext),
                $course, $cm, $quora)) {

                if ($quora->type == 'single') {
                    // Single discussion quoras are an exception. We show
                    // the quora itself since it only has one discussion
                    // thread.
                    $discussionurl = "view.php?f=$quora->id";
                } else {
                    $discussionurl = "discuss.php?d=$post->discussion";
                }

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $post->id,
                    'other' => array(
                        'discussionid' => $discussion->id,
                        'quoraid' => $quora->id,
                        'quoratype' => $quora->type,
                    )
                );

                if ($post->userid !== $USER->id) {
                    $params['relateduserid'] = $post->userid;
                }
                $event = \mod_quora\event\post_deleted::create($params);
                $event->add_record_snapshot('quora_posts', $post);
                $event->add_record_snapshot('quora_discussions', $discussion);
                $event->trigger();

                redirect(quora_go_back_to($discussionurl));
            } else {
                print_error('errorwhiledelete', 'quora');
            }
        }


    } else { // User just asked to delete something

        quora_set_return();
        $PAGE->navbar->add(get_string('delete', 'quora'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);

        if ($replycount) {
            if (!has_capability('mod/quora:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "quora",
                      quora_go_back_to("discuss.php?d=$post->discussion"));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($quora->name), 2);
            echo $OUTPUT->confirm(get_string("deletesureplural", "quora", $replycount+1),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'#p'.$post->id);

            quora_print_post($post, $discussion, $quora, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $quoratracked = quora_tp_is_tracked($quora);
                $posts = quora_get_all_discussion_posts($discussion->id, "created ASC", $quoratracked);
                quora_print_posts_nested($course, $cm, $quora, $discussion, $post, false, false, $quoratracked, $posts);
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($quora->name), 2);
            echo $OUTPUT->confirm(get_string("deletesure", "quora", $replycount),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'#p'.$post->id);
            quora_print_post($post, $discussion, $quora, $cm, $course, false, false, false);
        }

    }
    echo $OUTPUT->footer();
    die;


} else if (!empty($prune)) {  // Pruning

    if (!$post = quora_get_post_full($prune)) {
        print_error('invalidpostid', 'quora');
    }
    if (!$discussion = $DB->get_record("quora_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'quora');
    }
    if (!$quora = $DB->get_record("quora", array("id" => $discussion->quora))) {
        print_error('invalidquoraid', 'quora');
    }
    if ($quora->type == 'single') {
        print_error('cannotsplit', 'quora');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'quora');
    }
    if (!$cm = get_coursemodule_from_instance("quora", $quora->id, $quora->course)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/quora:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'quora');
    }

    $PAGE->set_cm($cm);
    $PAGE->set_context($modcontext);

    $prunemform = new mod_quora_prune_form(null, array('prune' => $prune, 'confirm' => $prune));


    if ($prunemform->is_cancelled()) {
        redirect(quora_go_back_to("discuss.php?d=$post->discussion"));
    } else if ($fromform = $prunemform->get_data()) {
        // User submits the data.
        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->quora        = $discussion->quora;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('quora_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;

        $DB->update_record("quora_posts", $newpost);

        quora_change_discussionid($post->id, $newid);

        // Update last post in each discussion.
        quora_discussion_update_last_post($discussion->id);
        quora_discussion_update_last_post($newid);

        // Fire events to reflect the split..
        $params = array(
            'context' => $modcontext,
            'objectid' => $discussion->id,
            'other' => array(
                'quoraid' => $quora->id,
            )
        );
        $event = \mod_quora\event\discussion_updated::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $newid,
            'other' => array(
                'quoraid' => $quora->id,
            )
        );
        $event = \mod_quora\event\discussion_created::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $newid,
                'quoraid' => $quora->id,
                'quoratype' => $quora->type,
            )
        );
        $event = \mod_quora\event\post_updated::create($params);
        $event->add_record_snapshot('quora_discussions', $discussion);
        $event->trigger();

        redirect(quora_go_back_to("discuss.php?d=$newid"));

    } else {
        // Display the prune form.
        $course = $DB->get_record('course', array('id' => $quora->course));
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/quora/discuss.php', array('d'=>$discussion->id)));
        $PAGE->navbar->add(get_string("prune", "quora"));
        $PAGE->set_title(format_string($discussion->name).": ".format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($quora->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'quora'), 3);

        $prunemform->display();

        quora_print_post($post, $discussion, $quora, $cm, $course, false, false, false);
    }

    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($quora->course);
}


// from now on user must be logged on properly

if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($quora->maxattachments)) {  // TODO - delete this once we add a field to the quora table
    $quora->maxattachments = 3;
}

$thresholdwarning = quora_check_throttling($quora, $cm);
$mform_post = new mod_quora_post_form('post.php', array('course' => $course,
                                                        'cm' => $cm,
                                                        'coursecontext' => $coursecontext,
                                                        'modcontext' => $modcontext,
                                                        'quora' => $quora,
                                                        'post' => $post,
                                                        'subscribe' => \mod_quora\subscriptions::is_subscribed($USER->id, $quora,
                                                                null, $cm),
                                                        'thresholdwarning' => $thresholdwarning,
                                                        'edit' => $edit), 'post', '', array('id' => 'mformquora'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_quora', 'attachment', empty($post->id)?null:$post->id, mod_quora_post_form::attachment_options($quora));

//load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p><span class="edited">('.get_string('editedby', 'quora', $data).')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'quora', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string("yourreply", "quora");
    $formheading = get_string('reply', 'quora');
} else {
    if ($quora->type == 'qanda') {
        $heading = get_string('yournewquestion', 'quora');
    } else {
        $heading = get_string('yournewtopic', 'quora');
    }
}

$postid = empty($post->id) ? null : $post->id;
$draftid_editor = file_get_submitted_draft_itemid('message');
$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_quora', 'post', $postid, mod_quora_post_form::editor_options($modcontext, $postid), $post->message);

$manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);
if (\mod_quora\subscriptions::subscription_disabled($quora) && !$manageactivities) {
    // User does not have permission to subscribe to this discussion at all.
    $discussionsubscribe = false;
} else if (\mod_quora\subscriptions::is_forcesubscribed($quora)) {
    // User does not have permission to unsubscribe from this discussion at all.
    $discussionsubscribe = true;
} else {
    if (isset($discussion) && \mod_quora\subscriptions::is_subscribed($USER->id, $quora, $discussion->id, $cm)) {
        // User is subscribed to the discussion - continue the subscription.
        $discussionsubscribe = true;
    } else if (!isset($discussion) && \mod_quora\subscriptions::is_subscribed($USER->id, $quora, null, $cm)) {
        // Starting a new discussion, and the user is subscribed to the quora - subscribe to the discussion.
        $discussionsubscribe = true;
    } else {
        // User is not subscribed to either quora or discussion. Follow user preference.
        $discussionsubscribe = $USER->autosubscribe;
    }
}

$mform_post->set_data(array(        'attachments'=>$draftitemid,
                                    'general'=>$heading,
                                    'subject'=>$post->subject,
                                    'message'=>array(
                                        'text'=>$currenttext,
                                        'format'=>empty($post->messageformat) ? editors_get_preferred_format() : $post->messageformat,
                                        'itemid'=>$draftid_editor
                                    ),
                                    'discussionsubscribe' => $discussionsubscribe,
                                    'mailnow'=>!empty($post->mailnow),
                                    'userid'=>$post->userid,
                                    'parent'=>$post->parent,
                                    'discussion'=>$post->discussion,
                                    'course'=>$course->id) +
                                    $page_params +

                            (isset($post->format)?array(
                                    'format'=>$post->format):
                                array())+

                            (isset($discussion->timestart)?array(
                                    'timestart'=>$discussion->timestart):
                                array())+

                            (isset($discussion->timeend)?array(
                                    'timeend'=>$discussion->timeend):
                                array())+

                            (isset($post->groupid)?array(
                                    'groupid'=>$post->groupid):
                                array())+

                            (isset($discussion->id)?
                                    array('discussion'=>$discussion->id):
                                    array()));

if ($mform_post->is_cancelled()) {
    if (!isset($discussion->id) || $quora->type === 'qanda') {
        // Q and A quoras don't have a discussion page, so treat them like a new thread..
        redirect(new moodle_url('/mod/quora/view.php', array('f' => $quora->id)));
    } else {
        redirect(new moodle_url('/mod/quora/discuss.php', array('d' => $discussion->id)));
    }
} else if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/quora/view.php?f=$quora->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    $contextcheck = isset($fromform->groupinfo) && has_capability('mod/quora:movediscussions', $modcontext);

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        //fix for bug #4314
        if (!$realpost = $DB->get_record('quora_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if ( !(($realpost->userid == $USER->id && (has_capability('mod/quora:replypost', $modcontext)
                            || has_capability('mod/quora:startdiscussion', $modcontext))) ||
                            has_capability('mod/quora:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'quora');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if ($contextcheck) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }
            $DB->set_field('quora_discussions' ,'groupid' , $fromform->groupinfo, array('firstpost' => $fromform->id));
        }

        $updatepost = $fromform; //realpost
        $updatepost->quora = $quora->id;
        if (!quora_update_post($updatepost, $mform_post, $message)) {
            print_error("couldnotupdate", "quora", $errordestination);
        }

        // MDL-11818
        if (($quora->type == 'single') && ($updatepost->parent == '0')){ // updating first post of single discussion type -> updating quora intro
            $quora->intro = $updatepost->message;
            $quora->timemodified = time();
            $DB->update_record("quora", $quora);
        }

        $timemessage = 2;
        if (!empty($message)) { // if we're printing stuff about the file upload
            $timemessage = 4;
        }

        if ($realpost->userid == $USER->id) {
            $message .= '<br />'.get_string("postupdated", "quora");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message .= '<br />'.get_string("editedpostupdated", "quora", fullname($realuser));
        }

        if ($subscribemessage = quora_post_subscription($fromform, $quora, $discussion)) {
            $timemessage = 4;
        }
        if ($quora->type == 'single') {
            // Single discussion quoras are an exception. We show
            // the quora itself since it only has one discussion
            // thread.
            $discussionurl = "view.php?f=$quora->id";
        } else {
            $discussionurl = "discuss.php?d=$discussion->id#p$fromform->id";
        }

        $params = array(
            'context' => $modcontext,
            'objectid' => $fromform->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'quoraid' => $quora->id,
                'quoratype' => $quora->type,
            )
        );

        if ($realpost->userid !== $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        $event = \mod_quora\event\post_updated::create($params);
        $event->add_record_snapshot('quora_discussions', $discussion);
        $event->trigger();

        redirect(quora_go_back_to("$discussionurl"), $message.$subscribemessage, $timemessage);

        exit;


    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        quora_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->quora=$quora->id;
        if ($fromform->id = quora_add_new_post($addpost, $mform_post, $message)) {
            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($subscribemessage = quora_post_subscription($fromform, $quora, $discussion)) {
                $timemessage = 4;
            }

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "quora");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "quora") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "quora", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($quora->type == 'single') {
                // Single discussion quoras are an exception. We show
                // the quora itself since it only has one discussion
                // thread.
                $discussionurl = "view.php?f=$quora->id";
            } else {
                $discussionurl = "discuss.php?d=$discussion->id";
            }

            $params = array(
                'context' => $modcontext,
                'objectid' => $fromform->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'quoraid' => $quora->id,
                    'quoratype' => $quora->type,
                )
            );
            $event = \mod_quora\event\post_created::create($params);
            $event->add_record_snapshot('quora_posts', $fromform);
            $event->add_record_snapshot('quora_discussions', $discussion);
            $event->trigger();

            // Update completion state
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($quora->completionreplies || $quora->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            redirect(quora_go_back_to("$discussionurl#p$fromform->id"), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "quora", $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name = $fromform->subject;

        $newstopic = false;
        if ($quora->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        $allowedgroups = array();
        $groupstopostto = array();

        // If we are posting a copy to all groups the user has access to.
        if (isset($fromform->posttomygroups)) {
            require_capability('mod/quora:canposttomygroups', $modcontext);
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            $groupstopostto = array_keys($allowedgroups);
        } else {
            if ($contextcheck) {
                $fromform->groupid = $fromform->groupinfo;
            }
            if (empty($fromform->groupid)) {
                $fromform->groupid = -1;
            }
            $groupstopostto = array($fromform->groupid);
        }

        // Before we post this we must check that the user will not exceed the blocking threshold.
        quora_check_blocking_threshold($thresholdwarning);

        foreach ($groupstopostto as $group) {
            if (!quora_user_can_post_discussion($quora, $group, -1, $cm, $modcontext)) {
                print_error('cannotcreatediscussion', 'quora');
            }

            $discussion->groupid = $group;
            $message = '';
            if ($discussion->id = quora_add_discussion($discussion, $mform_post, $message)) {

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'quoraid' => $quora->id,
                    )
                );
                $event = \mod_quora\event\discussion_created::create($params);
                $event->add_record_snapshot('quora_discussions', $discussion);
                $event->trigger();

                $timemessage = 2;
                if (!empty($message)) { // If we're printing stuff about the file upload.
                    $timemessage = 4;
                }

                if ($fromform->mailnow) {
                    $message .= get_string("postmailnow", "quora");
                    $timemessage = 4;
                } else {
                    $message .= '<p>'.get_string("postaddedsuccess", "quora") . '</p>';
                    $message .= '<p>'.get_string("postaddedtimeleft", "quora", format_time($CFG->maxeditingtime)) . '</p>';
                }

                if ($subscribemessage = quora_post_subscription($fromform, $quora, $discussion)) {
                    $timemessage = 6;
                }
            } else {
                print_error("couldnotadd", "quora", $errordestination);
            }
        }

        // Update completion status.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
                ($quora->completiondiscussions || $quora->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        redirect(quora_go_back_to("view.php?f=$fromform->quora"), $message.$subscribemessage, $timemessage);

        exit;
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $quora are defined.  $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record("quora_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'quora', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($quora->type == "news") ? get_string("addanewtopic", "quora") :
                                                   get_string("addanewdiscussion", "quora");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $quora->name;
}
if ($quora->type == 'single') {
    // There is only one discussion thread for this quora type. We should
    // not show the discussion name (same as quora name in this case) in
    // the breadcrumbs.
    $strdiscussionname = '';
} else {
    // Show the discussion name in the breadcrumbs.
    $strdiscussionname = format_string($discussion->name).':';
}

$forcefocus = empty($reply) ? NULL : 'message';

if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), "discuss.php?d=$discussion->id");
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'quora'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'quora'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($quora->name), 2);

// checkup
if (!empty($parent) && !quora_user_can_see_post($quora, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'quora');
}
if (empty($parent) && empty($edit) && !quora_user_can_post_discussion($quora, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'quora');
}

if ($quora->type == 'qanda'
            && !has_capability('mod/quora:viewqandawithoutposting', $modcontext)
            && !empty($discussion->id)
            && !quora_user_has_posted($quora->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','quora'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    quora_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('quora_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'quora');
    }

    quora_print_post($parent, $discussion, $quora, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if ($quora->type != 'qanda' || quora_user_can_see_discussion($quora, $discussion, $modcontext)) {
            $quoratracked = quora_tp_is_tracked($quora);
            $posts = quora_get_all_discussion_posts($discussion->id, "created ASC", $quoratracked);
            quora_print_posts_threaded($course, $cm, $quora, $discussion, $parent, 0, false, $quoratracked, $posts);
        }
    }
} else {
    if (!empty($quora->intro)) {
        echo $OUTPUT->box(format_module_intro('quora', $quora, $cm->id), 'generalbox', 'intro');

        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir.'/plagiarismlib.php');
            echo plagiarism_print_disclosure($cm->id);
        }
    }
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 2, array('class' => 'accesshide'));
}
$mform_post->display();

echo $OUTPUT->footer();


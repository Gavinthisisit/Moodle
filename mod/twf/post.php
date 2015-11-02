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
 * @package   mod_twf
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$twf   = optional_param('twf', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);
$syx_teamwork = optional_param('teamwork', null, PARAM_INT);
$syx_instance = optional_param('instance', null, PARAM_INT);
$syx_phase = optional_param('phase', null, PARAM_INT);

//var_dump($syx_teamwork);
//var_dump($syx_phase);
//var_dump($syx_instance);
//die;
$PAGE->set_url('/mod/twf/post.php', array(
        'reply' => $reply,
        'twf' => $twf,
        'edit'  => $edit,
        'delete'=> $delete,
        'prune' => $prune,
        'name'  => $name,
        'confirm'=>$confirm,
        'groupid'=>$groupid
        ));
//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'twf'=>$twf, 'edit'=>$edit);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and !get_local_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052
        require_login();
    }

    if (!empty($twf)) {      // User is starting a new discussion in a twf
        if (! $twf = $DB->get_record('twf', array('id' => $twf))) {
            print_error('invalidtwfid', 'twf');
        }
    } else if (!empty($reply)) {      // User is writing a new reply
        if (! $parent = twf_get_post_full($reply)) {
            print_error('invalidparentpostid', 'twf');
        }
        if (! $discussion = $DB->get_record('twf_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'twf');
        }
        if (! $twf = $DB->get_record('twf', array('id' => $discussion->twf))) {
            print_error('invalidtwfid');
        }
    }
    if (! $course = $DB->get_record('course', array('id' => $twf->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('twf', $twf->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $twf);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'twf').'<br /><br />'.get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in

$syx_newdiscussion = false;
if (!empty($twf)) {
    $syx_newdiscussion = true;
}

if (!empty($twf)) {      // User is starting a new discussion in a twf
    if (! $twf = $DB->get_record("twf", array("id" => $twf))) {
        print_error('invalidtwfid', 'twf');
    }
    if (! $course = $DB->get_record("course", array("id" => $twf->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("twf", $twf->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! twf_user_can_post_discussion($twf, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                        'returnurl' => '/mod/twf/view.php?f=' . $twf->id)),
                        get_string('youneedtoenrol'));
                }
            }
        }
        print_error('noposttwf', 'twf');
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    $SESSION->fromurl = get_local_referer(false);

    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->twf         = $twf->id;
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

    if (! $parent = twf_get_post_full($reply)) {
        print_error('invalidparentpostid', 'twf');
    }
    if (! $discussion = $DB->get_record("twf_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'twf');
    }
    if (! $twf = $DB->get_record("twf", array("id" => $discussion->twf))) {
        print_error('invalidtwfid', 'twf');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("twf", $twf->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $twf);

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! twf_user_can_post($twf, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/twf/view.php?f=' . $twf->id)),
                    get_string('youneedtoenrol'));
            }
        }
        print_error('noposttwf', 'twf');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('noposttwf', 'twf');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('noposttwf', 'twf');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->twf       = $twf->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'twf');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post

    if (! $post = twf_get_post_full($edit)) {
        print_error('invalidpostid', 'twf');
    }
    if ($post->parent) {
        if (! $parent = twf_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'twf');
        }
    }

    if (! $discussion = $DB->get_record("twf_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'twf');
    }
    if (! $twf = $DB->get_record("twf", array("id" => $discussion->twf))) {
        print_error('invalidtwfid', 'twf');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("twf", $twf->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $twf);

    if (!($twf->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
                    !has_capability('mod/twf:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'twf', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
                !has_capability('mod/twf:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'twf');
    }


    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->twf  = $twf->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

}else if (!empty($delete)) {  // User is deleting a post

    if (! $post = twf_get_post_full($delete)) {
        print_error('invalidpostid', 'twf');
    }
    if (! $discussion = $DB->get_record("twf_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'twf');
    }
    if (! $twf = $DB->get_record("twf", array("id" => $discussion->twf))) {
        print_error('invalidtwfid', 'twf');
    }
    if (!$cm = get_coursemodule_from_instance("twf", $twf->id, $twf->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $twf->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/twf:deleteownpost', $modcontext))
                || has_capability('mod/twf:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'twf');
    }


    $replycount = twf_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        //check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/twf:deleteanypost', $modcontext)) {
            print_error("cannotdeletepost", "twf",
                      twf_go_back_to("discuss.php?d=$post->discussion"));
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratings', 'rating'),
                    twf_go_back_to("discuss.php?d=$post->discussion"));

        } else if ($replycount && !has_capability('mod/twf:deleteanypost', $modcontext)) {
            print_error("couldnotdeletereplies", "twf",
                    twf_go_back_to("discuss.php?d=$post->discussion"));

        } else {
            if (! $post->parent) {  // post is a discussion topic as well, so delete discussion
                if ($twf->type == 'single') {
                    notice("Sorry, but you are not allowed to delete that discussion!",
                            twf_go_back_to("discuss.php?d=$post->discussion"));
                }
                twf_delete_discussion($discussion, false, $course, $cm, $twf);

                $params = array(
                    'objectid' => $discussion->id,
                    'context' => $modcontext,
                    'other' => array(
                        'twfid' => $twf->id,
                    )
                );

                $event = \mod_twf\event\discussion_deleted::create($params);
                $event->add_record_snapshot('twf_discussions', $discussion);
                $event->trigger();

                redirect("view.php?f=$discussion->twf");

            } else if (twf_delete_post($post, has_capability('mod/twf:deleteanypost', $modcontext),
                $course, $cm, $twf)) {

                if ($twf->type == 'single') {
                    // Single discussion twfs are an exception. We show
                    // the twf itself since it only has one discussion
                    // thread.
                    $discussionurl = "view.php?f=$twf->id";
                } else {
                    $discussionurl = "discuss.php?d=$post->discussion";
                }

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $post->id,
                    'other' => array(
                        'discussionid' => $discussion->id,
                        'twfid' => $twf->id,
                        'twftype' => $twf->type,
                    )
                );

                if ($post->userid !== $USER->id) {
                    $params['relateduserid'] = $post->userid;
                }
                $event = \mod_twf\event\post_deleted::create($params);
                $event->add_record_snapshot('twf_posts', $post);
                $event->add_record_snapshot('twf_discussions', $discussion);
                $event->trigger();

                redirect(twf_go_back_to($discussionurl));
            } else {
                print_error('errorwhiledelete', 'twf');
            }
        }


    } else { // User just asked to delete something

        twf_set_return();
        $PAGE->navbar->add(get_string('delete', 'twf'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);

        if ($replycount) {
            if (!has_capability('mod/twf:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "twf",
                      twf_go_back_to("discuss.php?d=$post->discussion"));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($twf->name), 2);
            echo $OUTPUT->confirm(get_string("deletesureplural", "twf", $replycount+1),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/twf/discuss.php?d='.$post->discussion.'#p'.$post->id);

            twf_print_post($post, $discussion, $twf, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $twftracked = twf_tp_is_tracked($twf);
                $posts = twf_get_all_discussion_posts($discussion->id, "created ASC", $twftracked);
                twf_print_posts_nested($course, $cm, $twf, $discussion, $post, false, false, $twftracked, $posts);
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($twf->name), 2);
            echo $OUTPUT->confirm(get_string("deletesure", "twf", $replycount),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/twf/discuss.php?d='.$post->discussion.'#p'.$post->id);
            twf_print_post($post, $discussion, $twf, $cm, $course, false, false, false);
        }

    }
    echo $OUTPUT->footer();
    die;


} else if (!empty($prune)) {  // Pruning

    if (!$post = twf_get_post_full($prune)) {
        print_error('invalidpostid', 'twf');
    }
    if (!$discussion = $DB->get_record("twf_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'twf');
    }
    if (!$twf = $DB->get_record("twf", array("id" => $discussion->twf))) {
        print_error('invalidtwfid', 'twf');
    }
    if ($twf->type == 'single') {
        print_error('cannotsplit', 'twf');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'twf');
    }
    if (!$cm = get_coursemodule_from_instance("twf", $twf->id, $twf->course)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/twf:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'twf');
    }

    $PAGE->set_cm($cm);
    $PAGE->set_context($modcontext);

    $prunemform = new mod_twf_prune_form(null, array('prune' => $prune, 'confirm' => $prune));


    if ($prunemform->is_cancelled()) {
        redirect(twf_go_back_to("discuss.php?d=$post->discussion"));
    } else if ($fromform = $prunemform->get_data()) {
        // User submits the data.
        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->twf        = $discussion->twf;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('twf_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;

        $DB->update_record("twf_posts", $newpost);

        twf_change_discussionid($post->id, $newid);

        // Update last post in each discussion.
        twf_discussion_update_last_post($discussion->id);
        twf_discussion_update_last_post($newid);

        // Fire events to reflect the split..
        $params = array(
            'context' => $modcontext,
            'objectid' => $discussion->id,
            'other' => array(
                'twfid' => $twf->id,
            )
        );
        $event = \mod_twf\event\discussion_updated::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $newid,
            'other' => array(
                'twfid' => $twf->id,
            )
        );
        $event = \mod_twf\event\discussion_created::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $newid,
                'twfid' => $twf->id,
                'twftype' => $twf->type,
            )
        );
        $event = \mod_twf\event\post_updated::create($params);
        $event->add_record_snapshot('twf_discussions', $discussion);
        $event->trigger();

        redirect(twf_go_back_to("discuss.php?d=$newid"));

    } else {
        // Display the prune form.
        $course = $DB->get_record('course', array('id' => $twf->course));
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/twf/discuss.php', array('d'=>$discussion->id)));
        $PAGE->navbar->add(get_string("prune", "twf"));
        $PAGE->set_title(format_string($discussion->name).": ".format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($twf->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'twf'), 3);

        $prunemform->display();

        twf_print_post($post, $discussion, $twf, $cm, $course, false, false, false);
    }

    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($twf->course);
}


// from now on user must be logged on properly

if (!$cm = get_coursemodule_from_instance('twf', $twf->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($twf->maxattachments)) {  // TODO - delete this once we add a field to the twf table
    $twf->maxattachments = 3;
}

$thresholdwarning = twf_check_throttling($twf, $cm);
//var_dump($syx_newdiscussion);die;
$mform_post = new mod_twf_post_form('post.php', array('course' => $course,
                                                        'cm' => $cm,
                                                        'coursecontext' => $coursecontext,
                                                        'modcontext' => $modcontext,
                                                        'twf' => $twf,
                                                        'post' => $post,
                                                        'subscribe' => \mod_twf\subscriptions::is_subscribed($USER->id, $twf,
                                                                null, $cm),
                                                        'thresholdwarning' => $thresholdwarning,
                                                        'edit' => $edit,
                                                        'syx_newdiscussion' => $syx_newdiscussion,
                                                        'syx_instance' => $syx_instance,
                                                        'syx_phase' => $syx_phase,
                                                        'syx_teamwork'=> $syx_teamwork), 'post', '', array('id' => 'mformtwf'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_twf', 'attachment', empty($post->id)?null:$post->id, mod_twf_post_form::attachment_options($twf));

//load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p><span class="edited">('.get_string('editedby', 'twf', $data).')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'twf', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string("yourreply", "twf");
    $formheading = get_string('reply', 'twf');
} else {
    if ($twf->type == 'qanda') {
        $heading = get_string('yournewquestion', 'twf');
    } else {
        $heading = get_string('yournewtopic', 'twf');
    }
}

$postid = empty($post->id) ? null : $post->id;
$draftid_editor = file_get_submitted_draft_itemid('message');
$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_twf', 'post', $postid, mod_twf_post_form::editor_options($modcontext, $postid), $post->message);

$manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);
if (\mod_twf\subscriptions::subscription_disabled($twf) && !$manageactivities) {
    // User does not have permission to subscribe to this discussion at all.
    $discussionsubscribe = false;
} else if (\mod_twf\subscriptions::is_forcesubscribed($twf)) {
    // User does not have permission to unsubscribe from this discussion at all.
    $discussionsubscribe = true;
} else {
    if (isset($discussion) && \mod_twf\subscriptions::is_subscribed($USER->id, $twf, $discussion->id, $cm)) {
        // User is subscribed to the discussion - continue the subscription.
        $discussionsubscribe = true;
    } else if (!isset($discussion) && \mod_twf\subscriptions::is_subscribed($USER->id, $twf, null, $cm)) {
        // Starting a new discussion, and the user is subscribed to the twf - subscribe to the discussion.
        $discussionsubscribe = true;
    } else {
        // User is not subscribed to either twf or discussion. Follow user preference.
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
    if (!isset($discussion->id) || $twf->type === 'qanda') {
        // Q and A twfs don't have a discussion page, so treat them like a new thread..
        redirect(new moodle_url('/mod/twf/view.php', array('f' => $twf->id)));
    } else {
        redirect(new moodle_url('/mod/twf/discuss.php', array('d' => $discussion->id)));
    }
} else if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/twf/view.php?f=$twf->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    /*
    $fromform->teamwork      = $syx_teamwork;
    $fromform->phase         = $syx_phase;
    */
    $fromform->instance      = $fromform->instace;
    
    //var_dump($fromform);die;
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        //fix for bug #4314
        if (!$realpost = $DB->get_record('twf_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if ( !(($realpost->userid == $USER->id && (has_capability('mod/twf:replypost', $modcontext)
                            || has_capability('mod/twf:startdiscussion', $modcontext))) ||
                            has_capability('mod/twf:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'twf');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if (isset($fromform->groupinfo) && has_capability('mod/twf:movediscussions', $modcontext)) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }

            if (!twf_user_can_post_discussion($twf, $fromform->groupinfo, null, $cm, $modcontext)) {
                print_error('cannotupdatepost', 'twf');
            }
            $DB->set_field('twf_discussions' ,'groupid' , $fromform->groupinfo, array('firstpost' => $fromform->id));
        }

        $updatepost = $fromform; //realpost
        $updatepost->twf = $twf->id;
        if (!twf_update_post($updatepost, $mform_post, $message)) {
            print_error("couldnotupdate", "twf", $errordestination);
        }

        // MDL-11818
        if (($twf->type == 'single') && ($updatepost->parent == '0')){ // updating first post of single discussion type -> updating twf intro
            $twf->intro = $updatepost->message;
            $twf->timemodified = time();
            $DB->update_record("twf", $twf);
        }

        $timemessage = 2;
        if (!empty($message)) { // if we're printing stuff about the file upload
            $timemessage = 4;
        }

        if ($realpost->userid == $USER->id) {
            $message .= '<br />'.get_string("postupdated", "twf");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message .= '<br />'.get_string("editedpostupdated", "twf", fullname($realuser));
        }

        if ($subscribemessage = twf_post_subscription($fromform, $twf, $discussion)) {
            $timemessage = 4;
        }
        if ($twf->type == 'single') {
            // Single discussion twfs are an exception. We show
            // the twf itself since it only has one discussion
            // thread.
            $discussionurl = "view.php?f=$twf->id";
        } else {
            $discussionurl = "discuss.php?d=$discussion->id#p$fromform->id";
        }

        $params = array(
            'context' => $modcontext,
            'objectid' => $fromform->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'twfid' => $twf->id,
                'twftype' => $twf->type,
            )
        );

        if ($realpost->userid !== $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        $event = \mod_twf\event\post_updated::create($params);
        $event->add_record_snapshot('twf_discussions', $discussion);
        $event->trigger();

        redirect(twf_go_back_to("$discussionurl"), $message.$subscribemessage, $timemessage);

        exit;


    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        twf_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->twf=$twf->id;
        if ($fromform->id = twf_add_new_post($addpost, $mform_post, $message)) {
            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($subscribemessage = twf_post_subscription($fromform, $twf, $discussion)) {
                $timemessage = 4;
            }

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "twf");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "twf") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "twf", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($twf->type == 'single') {
                // Single discussion twfs are an exception. We show
                // the twf itself since it only has one discussion
                // thread.
                $discussionurl = "view.php?f=$twf->id";
            } else {
                $discussionurl = "discuss.php?d=$discussion->id";
            }

            $params = array(
                'context' => $modcontext,
                'objectid' => $fromform->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'twfid' => $twf->id,
                    'twftype' => $twf->type,
                )
            );
            $event = \mod_twf\event\post_created::create($params);
            $event->add_record_snapshot('twf_posts', $fromform);
            $event->add_record_snapshot('twf_discussions', $discussion);
            $event->trigger();

            // Update completion state
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($twf->completionreplies || $twf->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            redirect(twf_go_back_to("$discussionurl#p$fromform->id"), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "twf", $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        // The location to redirect to after successfully posting.
        $redirectto = new moodle_url('view.php', array('f' => $fromform->twf));

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name = $fromform->subject;

        $newstopic = false;
        if ($twf->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        $allowedgroups = array();
        $groupstopostto = array();

        // If we are posting a copy to all groups the user has access to.
        if (isset($fromform->posttomygroups)) {
            // Post to each of my groups.
            require_capability('mod/twf:canposttomygroups', $modcontext);

            // Fetch all of this user's groups.
            // Note: all groups are returned when in visible groups mode so we must manually filter.
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            foreach ($allowedgroups as $groupid => $group) {
                if (twf_user_can_post_discussion($twf, $groupid, -1, $cm, $modcontext)) {
                    $groupstopostto[] = $groupid;
                }
            }
        } else if (isset($fromform->groupinfo)) {
            // Use the value provided in the dropdown group selection.
            $groupstopostto[] = $fromform->groupinfo;
            $redirectto->param('group', $fromform->groupinfo);
        } else if (isset($fromform->groupid) && !empty($fromform->groupid)) {
            // Use the value provided in the hidden form element instead.
            $groupstopostto[] = $fromform->groupid;
            $redirectto->param('group', $fromform->groupid);
        } else {
            // Use the value for all participants instead.
            $groupstopostto[] = -1;
        }

        // Before we post this we must check that the user will not exceed the blocking threshold.
        twf_check_blocking_threshold($thresholdwarning);
        
        foreach ($groupstopostto as $group) {
            if (!twf_user_can_post_discussion($twf, $group, -1, $cm, $modcontext)) {
                print_error('cannotcreatediscussion', 'twf');
            }
            //var_dump($discussion);die;
            $discussion->groupid = $group;
            $message = '';
            if ($discussion->id = twf_add_discussion($discussion, $mform_post, $message)) {

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'twfid' => $twf->id,
                    )
                );

                $event = \mod_twf\event\discussion_created::create($params);
                $event->add_record_snapshot('twf_discussions', $discussion);
                $event->trigger();

                $timemessage = 2;
                if (!empty($message)) { // If we're printing stuff about the file upload.
                    $timemessage = 4;
                }

                if ($fromform->mailnow) {
                    $message .= get_string("postmailnow", "twf");
                    $timemessage = 4;
                } else {
                    $message .= '<p>'.get_string("postaddedsuccess", "twf") . '</p>';
                    $message .= '<p>'.get_string("postaddedtimeleft", "twf", format_time($CFG->maxeditingtime)) . '</p>';
                }

                if ($subscribemessage = twf_post_subscription($fromform, $twf, $discussion)) {
                    $timemessage = 6;
                }
            } else {
                print_error("couldnotadd", "twf", $errordestination);
            }
        }

        // Update completion status.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
                ($twf->completiondiscussions || $twf->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Redirect back to the discussion.
        redirect(twf_go_back_to($redirectto->out()), $message . $subscribemessage, $timemessage);
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $twf are defined.  $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record("twf_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'twf', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($twf->type == "news") ? get_string("addanewtopic", "twf") :
                                                   get_string("addanewdiscussion", "twf");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $twf->name;
}
if ($twf->type == 'single') {
    // There is only one discussion thread for this twf type. We should
    // not show the discussion name (same as twf name in this case) in
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
    $PAGE->navbar->add(get_string('reply', 'twf'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'twf'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($twf->name), 2);

// checkup
if (!empty($parent) && !twf_user_can_see_post($twf, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'twf');
}
if (empty($parent) && empty($edit) && !twf_user_can_post_discussion($twf, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'twf');
}

if ($twf->type == 'qanda'
            && !has_capability('mod/twf:viewqandawithoutposting', $modcontext)
            && !empty($discussion->id)
            && !twf_user_has_posted($twf->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','twf'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    twf_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('twf_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'twf');
    }

    twf_print_post($parent, $discussion, $twf, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if ($twf->type != 'qanda' || twf_user_can_see_discussion($twf, $discussion, $modcontext)) {
            $twftracked = twf_tp_is_tracked($twf);
            $posts = twf_get_all_discussion_posts($discussion->id, "created ASC", $twftracked);
            twf_print_posts_threaded($course, $cm, $twf, $discussion, $parent, 0, false, $twftracked, $posts);
        }
    }
} else {
    if (!empty($twf->intro)) {
        echo $OUTPUT->box(format_module_intro('twf', $twf, $cm->id), 'generalbox', 'intro');

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


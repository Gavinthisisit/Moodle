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
 * @package   mod_teamworkforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$teamworkforum   = optional_param('teamworkforum', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);

$PAGE->set_url('/mod/teamworkforum/post.php', array(
        'reply' => $reply,
        'teamworkforum' => $teamworkforum,
        'edit'  => $edit,
        'delete'=> $delete,
        'prune' => $prune,
        'name'  => $name,
        'confirm'=>$confirm,
        'groupid'=>$groupid,
        ));
//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'teamworkforum'=>$teamworkforum, 'edit'=>$edit);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and !get_local_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052
        require_login();
    }

    if (!empty($teamworkforum)) {      // User is starting a new discussion in a teamworkforum
        if (! $teamworkforum = $DB->get_record('teamworkforum', array('id' => $teamworkforum))) {
            print_error('invalidteamworkforumid', 'teamworkforum');
        }
    } else if (!empty($reply)) {      // User is writing a new reply
        if (! $parent = teamworkforum_get_post_full($reply)) {
            print_error('invalidparentpostid', 'teamworkforum');
        }
        if (! $discussion = $DB->get_record('teamworkforum_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'teamworkforum');
        }
        if (! $teamworkforum = $DB->get_record('teamworkforum', array('id' => $discussion->teamworkforum))) {
            print_error('invalidteamworkforumid');
        }
    }
    if (! $course = $DB->get_record('course', array('id' => $teamworkforum->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $teamworkforum);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'teamworkforum').'<br /><br />'.get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in

if (!empty($teamworkforum)) {      // User is starting a new discussion in a teamworkforum
    if (! $teamworkforum = $DB->get_record("teamworkforum", array("id" => $teamworkforum))) {
        print_error('invalidteamworkforumid', 'teamworkforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $teamworkforum->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("teamworkforum", $teamworkforum->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! teamworkforum_user_can_post_discussion($teamworkforum, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                        'returnurl' => '/mod/teamworkforum/view.php?f=' . $teamworkforum->id)),
                        get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostteamworkforum', 'teamworkforum');
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    $SESSION->fromurl = get_local_referer(false);

    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->teamworkforum         = $teamworkforum->id;
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

    if (! $parent = teamworkforum_get_post_full($reply)) {
        print_error('invalidparentpostid', 'teamworkforum');
    }
    if (! $discussion = $DB->get_record("teamworkforum_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'teamworkforum');
    }
    if (! $teamworkforum = $DB->get_record("teamworkforum", array("id" => $discussion->teamworkforum))) {
        print_error('invalidteamworkforumid', 'teamworkforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("teamworkforum", $teamworkforum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $teamworkforum);

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! teamworkforum_user_can_post($teamworkforum, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/teamworkforum/view.php?f=' . $teamworkforum->id)),
                    get_string('youneedtoenrol'));
            }
        }
        print_error('nopostteamworkforum', 'teamworkforum');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostteamworkforum', 'teamworkforum');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostteamworkforum', 'teamworkforum');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->teamworkforum       = $teamworkforum->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'teamworkforum');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post

    if (! $post = teamworkforum_get_post_full($edit)) {
        print_error('invalidpostid', 'teamworkforum');
    }
    if ($post->parent) {
        if (! $parent = teamworkforum_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'teamworkforum');
        }
    }

    if (! $discussion = $DB->get_record("teamworkforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'teamworkforum');
    }
    if (! $teamworkforum = $DB->get_record("teamworkforum", array("id" => $discussion->teamworkforum))) {
        print_error('invalidteamworkforumid', 'teamworkforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("teamworkforum", $teamworkforum->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $teamworkforum);

    if (!($teamworkforum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
                    !has_capability('mod/teamworkforum:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'teamworkforum', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
                !has_capability('mod/teamworkforum:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'teamworkforum');
    }


    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->teamworkforum  = $teamworkforum->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

}else if (!empty($delete)) {  // User is deleting a post

    if (! $post = teamworkforum_get_post_full($delete)) {
        print_error('invalidpostid', 'teamworkforum');
    }
    if (! $discussion = $DB->get_record("teamworkforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'teamworkforum');
    }
    if (! $teamworkforum = $DB->get_record("teamworkforum", array("id" => $discussion->teamworkforum))) {
        print_error('invalidteamworkforumid', 'teamworkforum');
    }
    if (!$cm = get_coursemodule_from_instance("teamworkforum", $teamworkforum->id, $teamworkforum->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $teamworkforum->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/teamworkforum:deleteownpost', $modcontext))
                || has_capability('mod/teamworkforum:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'teamworkforum');
    }


    $replycount = teamworkforum_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        //check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/teamworkforum:deleteanypost', $modcontext)) {
            print_error("cannotdeletepost", "teamworkforum",
                      teamworkforum_go_back_to("discuss.php?d=$post->discussion"));
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratings', 'rating'),
                    teamworkforum_go_back_to("discuss.php?d=$post->discussion"));

        } else if ($replycount && !has_capability('mod/teamworkforum:deleteanypost', $modcontext)) {
            print_error("couldnotdeletereplies", "teamworkforum",
                    teamworkforum_go_back_to("discuss.php?d=$post->discussion"));

        } else {
            if (! $post->parent) {  // post is a discussion topic as well, so delete discussion
                if ($teamworkforum->type == 'single') {
                    notice("Sorry, but you are not allowed to delete that discussion!",
                            teamworkforum_go_back_to("discuss.php?d=$post->discussion"));
                }
                teamworkforum_delete_discussion($discussion, false, $course, $cm, $teamworkforum);

                $params = array(
                    'objectid' => $discussion->id,
                    'context' => $modcontext,
                    'other' => array(
                        'teamworkforumid' => $teamworkforum->id,
                    )
                );

                $event = \mod_teamworkforum\event\discussion_deleted::create($params);
                $event->add_record_snapshot('teamworkforum_discussions', $discussion);
                $event->trigger();

                redirect("view.php?f=$discussion->teamworkforum");

            } else if (teamworkforum_delete_post($post, has_capability('mod/teamworkforum:deleteanypost', $modcontext),
                $course, $cm, $teamworkforum)) {

                if ($teamworkforum->type == 'single') {
                    // Single discussion teamworkforums are an exception. We show
                    // the teamworkforum itself since it only has one discussion
                    // thread.
                    $discussionurl = "view.php?f=$teamworkforum->id";
                } else {
                    $discussionurl = "discuss.php?d=$post->discussion";
                }

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $post->id,
                    'other' => array(
                        'discussionid' => $discussion->id,
                        'teamworkforumid' => $teamworkforum->id,
                        'teamworkforumtype' => $teamworkforum->type,
                    )
                );

                if ($post->userid !== $USER->id) {
                    $params['relateduserid'] = $post->userid;
                }
                $event = \mod_teamworkforum\event\post_deleted::create($params);
                $event->add_record_snapshot('teamworkforum_posts', $post);
                $event->add_record_snapshot('teamworkforum_discussions', $discussion);
                $event->trigger();

                redirect(teamworkforum_go_back_to($discussionurl));
            } else {
                print_error('errorwhiledelete', 'teamworkforum');
            }
        }


    } else { // User just asked to delete something

        teamworkforum_set_return();
        $PAGE->navbar->add(get_string('delete', 'teamworkforum'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);

        if ($replycount) {
            if (!has_capability('mod/teamworkforum:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "teamworkforum",
                      teamworkforum_go_back_to("discuss.php?d=$post->discussion"));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($teamworkforum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesureplural", "teamworkforum", $replycount+1),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/teamworkforum/discuss.php?d='.$post->discussion.'#p'.$post->id);

            teamworkforum_print_post($post, $discussion, $teamworkforum, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $teamworkforumtracked = teamworkforum_tp_is_tracked($teamworkforum);
                $posts = teamworkforum_get_all_discussion_posts($discussion->id, "created ASC", $teamworkforumtracked);
                teamworkforum_print_posts_nested($course, $cm, $teamworkforum, $discussion, $post, false, false, $teamworkforumtracked, $posts);
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($teamworkforum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesure", "teamworkforum", $replycount),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/teamworkforum/discuss.php?d='.$post->discussion.'#p'.$post->id);
            teamworkforum_print_post($post, $discussion, $teamworkforum, $cm, $course, false, false, false);
        }

    }
    echo $OUTPUT->footer();
    die;


} else if (!empty($prune)) {  // Pruning

    if (!$post = teamworkforum_get_post_full($prune)) {
        print_error('invalidpostid', 'teamworkforum');
    }
    if (!$discussion = $DB->get_record("teamworkforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'teamworkforum');
    }
    if (!$teamworkforum = $DB->get_record("teamworkforum", array("id" => $discussion->teamworkforum))) {
        print_error('invalidteamworkforumid', 'teamworkforum');
    }
    if ($teamworkforum->type == 'single') {
        print_error('cannotsplit', 'teamworkforum');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'teamworkforum');
    }
    if (!$cm = get_coursemodule_from_instance("teamworkforum", $teamworkforum->id, $teamworkforum->course)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/teamworkforum:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'teamworkforum');
    }

    $PAGE->set_cm($cm);
    $PAGE->set_context($modcontext);

    $prunemform = new mod_teamworkforum_prune_form(null, array('prune' => $prune, 'confirm' => $prune));


    if ($prunemform->is_cancelled()) {
        redirect(teamworkforum_go_back_to("discuss.php?d=$post->discussion"));
    } else if ($fromform = $prunemform->get_data()) {
        // User submits the data.
        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->teamworkforum        = $discussion->teamworkforum;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('teamworkforum_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;

        $DB->update_record("teamworkforum_posts", $newpost);

        teamworkforum_change_discussionid($post->id, $newid);

        // Update last post in each discussion.
        teamworkforum_discussion_update_last_post($discussion->id);
        teamworkforum_discussion_update_last_post($newid);

        // Fire events to reflect the split..
        $params = array(
            'context' => $modcontext,
            'objectid' => $discussion->id,
            'other' => array(
                'teamworkforumid' => $teamworkforum->id,
            )
        );
        $event = \mod_teamworkforum\event\discussion_updated::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $newid,
            'other' => array(
                'teamworkforumid' => $teamworkforum->id,
            )
        );
        $event = \mod_teamworkforum\event\discussion_created::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $newid,
                'teamworkforumid' => $teamworkforum->id,
                'teamworkforumtype' => $teamworkforum->type,
            )
        );
        $event = \mod_teamworkforum\event\post_updated::create($params);
        $event->add_record_snapshot('teamworkforum_discussions', $discussion);
        $event->trigger();

        redirect(teamworkforum_go_back_to("discuss.php?d=$newid"));

    } else {
        // Display the prune form.
        $course = $DB->get_record('course', array('id' => $teamworkforum->course));
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/teamworkforum/discuss.php', array('d'=>$discussion->id)));
        $PAGE->navbar->add(get_string("prune", "teamworkforum"));
        $PAGE->set_title(format_string($discussion->name).": ".format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($teamworkforum->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'teamworkforum'), 3);

        $prunemform->display();

        teamworkforum_print_post($post, $discussion, $teamworkforum, $cm, $course, false, false, false);
    }

    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($teamworkforum->course);
}


// from now on user must be logged on properly

if (!$cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($teamworkforum->maxattachments)) {  // TODO - delete this once we add a field to the teamworkforum table
    $teamworkforum->maxattachments = 3;
}

$thresholdwarning = teamworkforum_check_throttling($teamworkforum, $cm);
$mform_post = new mod_teamworkforum_post_form('post.php', array('course' => $course,
                                                        'cm' => $cm,
                                                        'coursecontext' => $coursecontext,
                                                        'modcontext' => $modcontext,
                                                        'teamworkforum' => $teamworkforum,
                                                        'post' => $post,
                                                        'subscribe' => \mod_teamworkforum\subscriptions::is_subscribed($USER->id, $teamworkforum,
                                                                null, $cm),
                                                        'thresholdwarning' => $thresholdwarning,
                                                        'edit' => $edit), 'post', '', array('id' => 'mformteamworkforum'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_teamworkforum', 'attachment', empty($post->id)?null:$post->id, mod_teamworkforum_post_form::attachment_options($teamworkforum));

//load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p><span class="edited">('.get_string('editedby', 'teamworkforum', $data).')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'teamworkforum', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string("yourreply", "teamworkforum");
    $formheading = get_string('reply', 'teamworkforum');
} else {
    if ($teamworkforum->type == 'qanda') {
        $heading = get_string('yournewquestion', 'teamworkforum');
    } else {
        $heading = get_string('yournewtopic', 'teamworkforum');
    }
}

$postid = empty($post->id) ? null : $post->id;
$draftid_editor = file_get_submitted_draft_itemid('message');
$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_teamworkforum', 'post', $postid, mod_teamworkforum_post_form::editor_options($modcontext, $postid), $post->message);

$manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);
if (\mod_teamworkforum\subscriptions::subscription_disabled($teamworkforum) && !$manageactivities) {
    // User does not have permission to subscribe to this discussion at all.
    $discussionsubscribe = false;
} else if (\mod_teamworkforum\subscriptions::is_forcesubscribed($teamworkforum)) {
    // User does not have permission to unsubscribe from this discussion at all.
    $discussionsubscribe = true;
} else {
    if (isset($discussion) && \mod_teamworkforum\subscriptions::is_subscribed($USER->id, $teamworkforum, $discussion->id, $cm)) {
        // User is subscribed to the discussion - continue the subscription.
        $discussionsubscribe = true;
    } else if (!isset($discussion) && \mod_teamworkforum\subscriptions::is_subscribed($USER->id, $teamworkforum, null, $cm)) {
        // Starting a new discussion, and the user is subscribed to the teamworkforum - subscribe to the discussion.
        $discussionsubscribe = true;
    } else {
        // User is not subscribed to either teamworkforum or discussion. Follow user preference.
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
    if (!isset($discussion->id) || $teamworkforum->type === 'qanda') {
        // Q and A teamworkforums don't have a discussion page, so treat them like a new thread..
        redirect(new moodle_url('/mod/teamworkforum/view.php', array('f' => $teamworkforum->id)));
    } else {
        redirect(new moodle_url('/mod/teamworkforum/discuss.php', array('d' => $discussion->id)));
    }
} else if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/teamworkforum/view.php?f=$teamworkforum->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        //fix for bug #4314
        if (!$realpost = $DB->get_record('teamworkforum_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if ( !(($realpost->userid == $USER->id && (has_capability('mod/teamworkforum:replypost', $modcontext)
                            || has_capability('mod/teamworkforum:startdiscussion', $modcontext))) ||
                            has_capability('mod/teamworkforum:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'teamworkforum');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if (isset($fromform->groupinfo) && has_capability('mod/teamworkforum:movediscussions', $modcontext)) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }

            if (!teamworkforum_user_can_post_discussion($teamworkforum, $fromform->groupinfo, null, $cm, $modcontext)) {
                print_error('cannotupdatepost', 'teamworkforum');
            }

            $DB->set_field('teamworkforum_discussions' ,'groupid' , $fromform->groupinfo, array('firstpost' => $fromform->id));
        }

        $updatepost = $fromform; //realpost
        $updatepost->teamworkforum = $teamworkforum->id;
        if (!teamworkforum_update_post($updatepost, $mform_post, $message)) {
            print_error("couldnotupdate", "teamworkforum", $errordestination);
        }

        // MDL-11818
        if (($teamworkforum->type == 'single') && ($updatepost->parent == '0')){ // updating first post of single discussion type -> updating teamworkforum intro
            $teamworkforum->intro = $updatepost->message;
            $teamworkforum->timemodified = time();
            $DB->update_record("teamworkforum", $teamworkforum);
        }

        $timemessage = 2;
        if (!empty($message)) { // if we're printing stuff about the file upload
            $timemessage = 4;
        }

        if ($realpost->userid == $USER->id) {
            $message .= '<br />'.get_string("postupdated", "teamworkforum");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message .= '<br />'.get_string("editedpostupdated", "teamworkforum", fullname($realuser));
        }

        if ($subscribemessage = teamworkforum_post_subscription($fromform, $teamworkforum, $discussion)) {
            $timemessage = 4;
        }
        if ($teamworkforum->type == 'single') {
            // Single discussion teamworkforums are an exception. We show
            // the teamworkforum itself since it only has one discussion
            // thread.
            $discussionurl = "view.php?f=$teamworkforum->id";
        } else {
            $discussionurl = "discuss.php?d=$discussion->id#p$fromform->id";
        }

        $params = array(
            'context' => $modcontext,
            'objectid' => $fromform->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'teamworkforumid' => $teamworkforum->id,
                'teamworkforumtype' => $teamworkforum->type,
            )
        );

        if ($realpost->userid !== $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        $event = \mod_teamworkforum\event\post_updated::create($params);
        $event->add_record_snapshot('teamworkforum_discussions', $discussion);
        $event->trigger();

        redirect(teamworkforum_go_back_to("$discussionurl"), $message.$subscribemessage, $timemessage);

        exit;


    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        teamworkforum_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->teamworkforum=$teamworkforum->id;
        if ($fromform->id = teamworkforum_add_new_post($addpost, $mform_post, $message)) {
            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($subscribemessage = teamworkforum_post_subscription($fromform, $teamworkforum, $discussion)) {
                $timemessage = 4;
            }

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "teamworkforum");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "teamworkforum") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "teamworkforum", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($teamworkforum->type == 'single') {
                // Single discussion teamworkforums are an exception. We show
                // the teamworkforum itself since it only has one discussion
                // thread.
                $discussionurl = "view.php?f=$teamworkforum->id";
            } else {
                $discussionurl = "discuss.php?d=$discussion->id";
            }

            $params = array(
                'context' => $modcontext,
                'objectid' => $fromform->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'teamworkforumid' => $teamworkforum->id,
                    'teamworkforumtype' => $teamworkforum->type,
                )
            );
            $event = \mod_teamworkforum\event\post_created::create($params);
            $event->add_record_snapshot('teamworkforum_posts', $fromform);
            $event->add_record_snapshot('teamworkforum_discussions', $discussion);
            $event->trigger();

            // Update completion state
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($teamworkforum->completionreplies || $teamworkforum->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            redirect(teamworkforum_go_back_to("$discussionurl#p$fromform->id"), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "teamworkforum", $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        // The location to redirect to after successfully posting.
        $redirectto = new moodle_url('view.php', array('f' => $fromform->teamworkforum));

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name = $fromform->subject;

        $newstopic = false;
        if ($teamworkforum->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        $allowedgroups = array();
        $groupstopostto = array();

        // If we are posting a copy to all groups the user has access to.
        if (isset($fromform->posttomygroups)) {
            // Post to each of my groups.
            require_capability('mod/teamworkforum:canposttomygroups', $modcontext);

            // Fetch all of this user's groups.
            // Note: all groups are returned when in visible groups mode so we must manually filter.
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            foreach ($allowedgroups as $groupid => $group) {
                if (teamworkforum_user_can_post_discussion($teamworkforum, $groupid, -1, $cm, $modcontext)) {
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
        teamworkforum_check_blocking_threshold($thresholdwarning);

        foreach ($groupstopostto as $group) {
            if (!teamworkforum_user_can_post_discussion($teamworkforum, $group, -1, $cm, $modcontext)) {
                print_error('cannotcreatediscussion', 'teamworkforum');
            }

            $discussion->groupid = $group;
            $message = '';
            if ($discussion->id = teamworkforum_add_discussion($discussion, $mform_post, $message)) {

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'teamworkforumid' => $teamworkforum->id,
                    )
                );
                $event = \mod_teamworkforum\event\discussion_created::create($params);
                $event->add_record_snapshot('teamworkforum_discussions', $discussion);
                $event->trigger();

                $timemessage = 2;
                if (!empty($message)) { // If we're printing stuff about the file upload.
                    $timemessage = 4;
                }

                if ($fromform->mailnow) {
                    $message .= get_string("postmailnow", "teamworkforum");
                    $timemessage = 4;
                } else {
                    $message .= '<p>'.get_string("postaddedsuccess", "teamworkforum") . '</p>';
                    $message .= '<p>'.get_string("postaddedtimeleft", "teamworkforum", format_time($CFG->maxeditingtime)) . '</p>';
                }

                if ($subscribemessage = teamworkforum_post_subscription($fromform, $teamworkforum, $discussion)) {
                    $timemessage = 6;
                }
            } else {
                print_error("couldnotadd", "teamworkforum", $errordestination);
            }
        }

        // Update completion status.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
                ($teamworkforum->completiondiscussions || $teamworkforum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Redirect back to the discussion.
        redirect(teamworkforum_go_back_to($redirectto->out()), $message . $subscribemessage, $timemessage);
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $teamworkforum are defined.  $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record("teamworkforum_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'teamworkforum', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($teamworkforum->type == "news") ? get_string("addanewtopic", "teamworkforum") :
                                                   get_string("addanewdiscussion", "teamworkforum");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $teamworkforum->name;
}
if ($teamworkforum->type == 'single') {
    // There is only one discussion thread for this teamworkforum type. We should
    // not show the discussion name (same as teamworkforum name in this case) in
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
    $PAGE->navbar->add(get_string('reply', 'teamworkforum'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'teamworkforum'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($teamworkforum->name), 2);

// checkup
if (!empty($parent) && !teamworkforum_user_can_see_post($teamworkforum, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'teamworkforum');
}
if (empty($parent) && empty($edit) && !teamworkforum_user_can_post_discussion($teamworkforum, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'teamworkforum');
}

if ($teamworkforum->type == 'qanda'
            && !has_capability('mod/teamworkforum:viewqandawithoutposting', $modcontext)
            && !empty($discussion->id)
            && !teamworkforum_user_has_posted($teamworkforum->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','teamworkforum'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    teamworkforum_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('teamworkforum_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'teamworkforum');
    }

    teamworkforum_print_post($parent, $discussion, $teamworkforum, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if ($teamworkforum->type != 'qanda' || teamworkforum_user_can_see_discussion($teamworkforum, $discussion, $modcontext)) {
            $teamworkforumtracked = teamworkforum_tp_is_tracked($teamworkforum);
            $posts = teamworkforum_get_all_discussion_posts($discussion->id, "created ASC", $teamworkforumtracked);
            teamworkforum_print_posts_threaded($course, $cm, $teamworkforum, $discussion, $parent, 0, false, $teamworkforumtracked, $posts);
        }
    }
} else {
    if (!empty($teamworkforum->intro)) {
        echo $OUTPUT->box(format_module_intro('teamworkforum', $teamworkforum, $cm->id), 'generalbox', 'intro');

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


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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package   mod_twf
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another twf
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

$url = new moodle_url('/mod/twf/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('twf_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$twf = $DB->get_record('twf', array('id' => $discussion->twf), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('twf', $twf->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/twf/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/twf:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'twf');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->twf_enablerssfeeds) && $twf->rsstype && $twf->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($twf->name);
    rss_add_http_header($modcontext, 'mod_twf', $twf, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/twf/discuss.php?d='.$discussion->id;

    if (!$twfto = $DB->get_record('twf', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'twf', $return);
    }

    require_capability('mod/twf:movediscussions', $modcontext);

    if ($twf->type == 'single') {
        print_error('cannotmovefromsingletwf', 'twf', $return);
    }

    if (!$twfto = $DB->get_record('twf', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'twf', $return);
    }

    if ($twfto->type == 'single') {
        print_error('cannotmovetosingletwf', 'twf', $return);
    }

    // Get target twf cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $twfs = $modinfo->get_instances_of('twf');
    if (!array_key_exists($twfto->id, $twfs)) {
        print_error('cannotmovetonotfound', 'twf', $return);
    }
    $cmto = $twfs[$twfto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'twf', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/twf:startdiscussion', $destinationctx);

    if (!twf_move_attachments($discussion, $twf->id, $twfto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this twf and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_twf\subscriptions::fetch_subscribed_users(
        $twf,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the twf being moved to.
    \mod_twf\subscriptions::fill_subscription_cache($twfto->id);
    // And also for the discussion being moved.
    \mod_twf\subscriptions::fill_subscription_cache($twf->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_twf\subscriptions::is_subscribed($userid, $twfto, null, $cmto);
        $discussionsubscribed = \mod_twf\subscriptions::is_subscribed($userid, $twf, $discussion->id);
        $twfsubscribed = \mod_twf\subscriptions::is_subscribed($userid, $twf);

        if ($twfsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_twf\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$twfsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('twf_discussions', 'twf', $twfto->id, array('id' => $discussion->id));
    $DB->set_field('twf_read', 'twfid', $twfto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('twf_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->twf = $twfto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_twf\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/twf:viewdiscussion', $destinationctx, $userid)) {
                \mod_twf\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_twf\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromtwfid' => $twf->id,
            'totwfid' => $twfto->id,
        )
    );
    $event = \mod_twf\event\discussion_moved::create($params);
    $event->add_record_snapshot('twf_discussions', $discussion);
    $event->add_record_snapshot('twf', $twf);
    $event->add_record_snapshot('twf', $twfto);
    $event->trigger();

    // Delete the RSS files for the 2 twfs to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/twf/rsslib.php');
    twf_rss_delete_file($twf);
    twf_rss_delete_file($twfto);

    redirect($return.'&moved=-1&sesskey='.sesskey());
}

// Trigger discussion viewed event.
twf_discussion_view($modcontext, $twf, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('twf_displaymode', $mode);
}

$displaymode = get_user_preferences('twf_displaymode', $CFG->twf_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
        $displaymode = FORUM_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = twf_get_post_full($parent)) {
    print_error("notexists", 'twf', "$CFG->wwwroot/mod/twf/view.php?f=$twf->id");
}

if (!twf_user_can_see_post($twf, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'twf', "$CFG->wwwroot/mod/twf/view.php?id=$twf->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->twf_usermarksread && twf_tp_can_track_twfs($twf) && twf_tp_is_tracked($twf)) {
        if ($mark == 'read') {
            twf_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            twf_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = twf_search_form($course);

$twfnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($twfnode)) {
    $twfnode = $PAGE->navbar;
} else {
    $twfnode->make_active();
}
$node = $twfnode->add(format_string($discussion->name), new moodle_url('/mod/twf/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_twf');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($twf->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/twf:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_twf\subscriptions::is_subscribable($twf)) {
        echo html_writer::div(
            twf_get_discussion_subscription_icon($twf, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo twf_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this twf
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = twf_user_can_post($twf, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $twf->type !== 'news') {
    if (isguestuser() or !isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too, they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// Output the links to neighbour discussions.
$neighbours = twf_get_discussion_neighbours($cm, $discussion);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix">';

if (!empty($CFG->enableportfolios) && has_capability('mod/twf:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('twf_portfolio_caller', array('discussionid' => $discussion->id), 'mod_twf');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_twf'));
    $buttonextraclass = '';
    if (empty($button)) {
        // no portfolio plugin available.
        $button = '&nbsp;';
        $buttonextraclass = ' noavailable';
    }
    echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
} else {
    echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
}

// groups selector not needed here
echo '<div class="discussioncontrol displaymode">';
twf_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($twf->type != 'single'
            && has_capability('mod/twf:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other twfs. The discussion in a
    // single discussion twf can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['twf'])) {
        $twfmenu = array();
        // Check twf types and eliminate simple discussions.
        $twfcheck = $DB->get_records('twf', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['twf'] as $twfcm) {
            if (!$twfcm->uservisible || !has_capability('mod/twf:startdiscussion',
                context_module::instance($twfcm->id))) {
                continue;
            }
            $section = $twfcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($twfmenu[$section])) {
                $twfmenu[$section] = array($sectionname => array());
            }
            $twfidcompare = $twfcm->instance != $twf->id;
            $twftypecheck = $twfcheck[$twfcm->instance]->type !== 'single';
            if ($twfidcompare and $twftypecheck) {
                $url = "/mod/twf/discuss.php?d=$discussion->id&move=$twfcm->instance&sesskey=".sesskey();
                $twfmenu[$section][$sectionname][$url] = format_string($twfcm->name);
            }
        }
        if (!empty($twfmenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($twfmenu, '',
                    array(''=>get_string("movethisdiscussionto", "twf")),
                    'twfmenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}
echo '<div class="clearfloat">&nbsp;</div>';
echo "</div>";

if (!empty($twf->blockafter) && !empty($twf->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $twf->blockafter;
    $a->blockperiod = get_string('secondstotime'.$twf->blockperiod);
    echo $OUTPUT->notification(get_string('thistwfisthrottled','twf',$a));
}

if ($twf->type == 'qanda' && !has_capability('mod/twf:viewqandawithoutposting', $modcontext) &&
            !twf_user_has_posted($twf->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','twf'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'twf', format_string($twf->name,true)));
}

$canrate = has_capability('mod/twf:rate', $modcontext);
twf_print_discussion($course, $cm, $twf, $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_twf-subscriptiontoggle', 'Y.M.mod_twf.subscriptiontoggle.init');

echo $OUTPUT->footer();

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
 * @package   mod_teamworkforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another teamworkforum
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

$url = new moodle_url('/mod/teamworkforum/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('teamworkforum_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$teamworkforum = $DB->get_record('teamworkforum', array('id' => $discussion->teamworkforum), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id, $course->id, false, MUST_EXIST);

//require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/teamworkforum/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/teamworkforum:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'teamworkforum');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->teamworkforum_enablerssfeeds) && $teamworkforum->rsstype && $teamworkforum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($teamworkforum->name);
    rss_add_http_header($modcontext, 'mod_teamworkforum', $teamworkforum, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/teamworkforum/discuss.php?d='.$discussion->id;

    if (!$teamworkforumto = $DB->get_record('teamworkforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'teamworkforum', $return);
    }

    require_capability('mod/teamworkforum:movediscussions', $modcontext);

    if ($teamworkforum->type == 'single') {
        print_error('cannotmovefromsingleteamworkforum', 'teamworkforum', $return);
    }

    if (!$teamworkforumto = $DB->get_record('teamworkforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'teamworkforum', $return);
    }

    if ($teamworkforumto->type == 'single') {
        print_error('cannotmovetosingleteamworkforum', 'teamworkforum', $return);
    }

    // Get target teamworkforum cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $teamworkforums = $modinfo->get_instances_of('teamworkforum');
    if (!array_key_exists($teamworkforumto->id, $teamworkforums)) {
        print_error('cannotmovetonotfound', 'teamworkforum', $return);
    }
    $cmto = $teamworkforums[$teamworkforumto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'teamworkforum', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/teamworkforum:startdiscussion', $destinationctx);

    if (!teamworkforum_move_attachments($discussion, $teamworkforum->id, $teamworkforumto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this teamworkforum and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_teamworkforum\subscriptions::fetch_subscribed_users(
        $teamworkforum,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the teamworkforum being moved to.
    \mod_teamworkforum\subscriptions::fill_subscription_cache($teamworkforumto->id);
    // And also for the discussion being moved.
    \mod_teamworkforum\subscriptions::fill_subscription_cache($teamworkforum->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_teamworkforum\subscriptions::is_subscribed($userid, $teamworkforumto, null, $cmto);
        $discussionsubscribed = \mod_teamworkforum\subscriptions::is_subscribed($userid, $teamworkforum, $discussion->id);
        $teamworkforumsubscribed = \mod_teamworkforum\subscriptions::is_subscribed($userid, $teamworkforum);

        if ($teamworkforumsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_teamworkforum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$teamworkforumsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('teamworkforum_discussions', 'teamworkforum', $teamworkforumto->id, array('id' => $discussion->id));
    $DB->set_field('teamworkforum_read', 'teamworkforumid', $teamworkforumto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('teamworkforum_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->teamworkforum = $teamworkforumto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_teamworkforum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/teamworkforum:viewdiscussion', $destinationctx, $userid)) {
                \mod_teamworkforum\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_teamworkforum\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromteamworkforumid' => $teamworkforum->id,
            'toteamworkforumid' => $teamworkforumto->id,
        )
    );
    $event = \mod_teamworkforum\event\discussion_moved::create($params);
    $event->add_record_snapshot('teamworkforum_discussions', $discussion);
    $event->add_record_snapshot('teamworkforum', $teamworkforum);
    $event->add_record_snapshot('teamworkforum', $teamworkforumto);
    $event->trigger();

    // Delete the RSS files for the 2 teamworkforums to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/teamworkforum/rsslib.php');
    teamworkforum_rss_delete_file($teamworkforum);
    teamworkforum_rss_delete_file($teamworkforumto);

    redirect($return.'&moved=-1&sesskey='.sesskey());
}

// Trigger discussion viewed event.
teamworkforum_discussion_view($modcontext, $teamworkforum, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('teamworkforum_displaymode', $mode);
}

$displaymode = get_user_preferences('teamworkforum_displaymode', $CFG->teamworkforum_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
        $displaymode = FORUM_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = teamworkforum_get_post_full($parent)) {
    print_error("notexists", 'teamworkforum', "$CFG->wwwroot/mod/teamworkforum/view.php?f=$teamworkforum->id");
}

if (!teamworkforum_user_can_see_post($teamworkforum, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'teamworkforum', "$CFG->wwwroot/mod/teamworkforum/view.php?id=$teamworkforum->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->teamworkforum_usermarksread && teamworkforum_tp_can_track_teamworkforums($teamworkforum) && teamworkforum_tp_is_tracked($teamworkforum)) {
        if ($mark == 'read') {
            teamworkforum_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            teamworkforum_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = teamworkforum_search_form($course);

$teamworkforumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($teamworkforumnode)) {
    $teamworkforumnode = $PAGE->navbar;
} else {
    $teamworkforumnode->make_active();
}
$node = $teamworkforumnode->add(format_string($discussion->name), new moodle_url('/mod/teamworkforum/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_teamworkforum');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($teamworkforum->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/teamworkforum:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_teamworkforum\subscriptions::is_subscribable($teamworkforum)) {
        echo html_writer::div(
            teamworkforum_get_discussion_subscription_icon($teamworkforum, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo teamworkforum_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this teamworkforum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = teamworkforum_user_can_post($teamworkforum, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $teamworkforum->type !== 'news') {
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
$neighbours = teamworkforum_get_discussion_neighbours($cm, $discussion);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix">';

if (!empty($CFG->enableportfolios) && has_capability('mod/teamworkforum:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('teamworkforum_portfolio_caller', array('discussionid' => $discussion->id), 'mod_teamworkforum');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_teamworkforum'));
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
teamworkforum_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($teamworkforum->type != 'single'
            && has_capability('mod/teamworkforum:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other teamworkforums. The discussion in a
    // single discussion teamworkforum can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['teamworkforum'])) {
        $teamworkforummenu = array();
        // Check teamworkforum types and eliminate simple discussions.
        $teamworkforumcheck = $DB->get_records('teamworkforum', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['teamworkforum'] as $teamworkforumcm) {
            if (!$teamworkforumcm->uservisible || !has_capability('mod/teamworkforum:startdiscussion',
                context_module::instance($teamworkforumcm->id))) {
                continue;
            }
            $section = $teamworkforumcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($teamworkforummenu[$section])) {
                $teamworkforummenu[$section] = array($sectionname => array());
            }
            $teamworkforumidcompare = $teamworkforumcm->instance != $teamworkforum->id;
            $teamworkforumtypecheck = $teamworkforumcheck[$teamworkforumcm->instance]->type !== 'single';
            if ($teamworkforumidcompare and $teamworkforumtypecheck) {
                $url = "/mod/teamworkforum/discuss.php?d=$discussion->id&move=$teamworkforumcm->instance&sesskey=".sesskey();
                $teamworkforummenu[$section][$sectionname][$url] = format_string($teamworkforumcm->name);
            }
        }
        if (!empty($teamworkforummenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($teamworkforummenu, '',
                    array(''=>get_string("movethisdiscussionto", "teamworkforum")),
                    'teamworkforummenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}
echo '<div class="clearfloat">&nbsp;</div>';
echo "</div>";

if (!empty($teamworkforum->blockafter) && !empty($teamworkforum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $teamworkforum->blockafter;
    $a->blockperiod = get_string('secondstotime'.$teamworkforum->blockperiod);
    echo $OUTPUT->notification(get_string('thisteamworkforumisthrottled','teamworkforum',$a));
}

if ($teamworkforum->type == 'qanda' && !has_capability('mod/teamworkforum:viewqandawithoutposting', $modcontext) &&
            !teamworkforum_user_has_posted($teamworkforum->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','teamworkforum'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'teamworkforum', format_string($teamworkforum->name,true)));
}

$canrate = has_capability('mod/teamworkforum:rate', $modcontext);
teamworkforum_print_discussion($course, $cm, $teamworkforum, $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_teamworkforum-subscriptiontoggle', 'Y.M.mod_teamworkforum.subscriptiontoggle.init');

echo $OUTPUT->footer();

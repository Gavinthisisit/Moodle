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
 * @package   mod_quora
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another quora
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

$url = new moodle_url('/mod/quora/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('quora_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$quora = $DB->get_record('quora', array('id' => $discussion->quora), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quora', $quora->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/quora/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/quora:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'quora');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->quora_enablerssfeeds) && $quora->rsstype && $quora->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($quora->name);
    rss_add_http_header($modcontext, 'mod_quora', $quora, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/quora/discuss.php?d='.$discussion->id;

    if (!$quorato = $DB->get_record('quora', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'quora', $return);
    }

    require_capability('mod/quora:movediscussions', $modcontext);

    if ($quora->type == 'single') {
        print_error('cannotmovefromsinglequora', 'quora', $return);
    }

    if (!$quorato = $DB->get_record('quora', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'quora', $return);
    }

    if ($quorato->type == 'single') {
        print_error('cannotmovetosinglequora', 'quora', $return);
    }

    // Get target quora cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $quoras = $modinfo->get_instances_of('quora');
    if (!array_key_exists($quorato->id, $quoras)) {
        print_error('cannotmovetonotfound', 'quora', $return);
    }
    $cmto = $quoras[$quorato->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'quora', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/quora:startdiscussion', $destinationctx);

    if (!quora_move_attachments($discussion, $quora->id, $quorato->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this quora and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_quora\subscriptions::fetch_subscribed_users(
        $quora,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the quora being moved to.
    \mod_quora\subscriptions::fill_subscription_cache($quorato->id);
    // And also for the discussion being moved.
    \mod_quora\subscriptions::fill_subscription_cache($quora->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_quora\subscriptions::is_subscribed($userid, $quorato, null, $cmto);
        $discussionsubscribed = \mod_quora\subscriptions::is_subscribed($userid, $quora, $discussion->id);
        $quorasubscribed = \mod_quora\subscriptions::is_subscribed($userid, $quora);

        if ($quorasubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_quora\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$quorasubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('quora_discussions', 'quora', $quorato->id, array('id' => $discussion->id));
    $DB->set_field('quora_read', 'quoraid', $quorato->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('quora_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->quora = $quorato->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_quora\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/quora:viewdiscussion', $destinationctx, $userid)) {
                \mod_quora\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_quora\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromquoraid' => $quora->id,
            'toquoraid' => $quorato->id,
        )
    );
    $event = \mod_quora\event\discussion_moved::create($params);
    $event->add_record_snapshot('quora_discussions', $discussion);
    $event->add_record_snapshot('quora', $quora);
    $event->add_record_snapshot('quora', $quorato);
    $event->trigger();

    // Delete the RSS files for the 2 quoras to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/quora/rsslib.php');
    quora_rss_delete_file($quora);
    quora_rss_delete_file($quorato);

    redirect($return.'&moved=-1&sesskey='.sesskey());
}

// Trigger discussion viewed event.
quora_discussion_view($modcontext, $quora, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('quora_displaymode', $mode);
}

$displaymode = get_user_preferences('quora_displaymode', $CFG->quora_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
        $displaymode = FORUM_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = quora_get_post_full($parent)) {
    print_error("notexists", 'quora', "$CFG->wwwroot/mod/quora/view.php?f=$quora->id");
}

if (!quora_user_can_see_post($quora, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'quora', "$CFG->wwwroot/mod/quora/view.php?id=$quora->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->quora_usermarksread && quora_tp_can_track_quoras($quora) && quora_tp_is_tracked($quora)) {
        if ($mark == 'read') {
            quora_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            quora_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = quora_search_form($course);

$quoranode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($quoranode)) {
    $quoranode = $PAGE->navbar;
} else {
    $quoranode->make_active();
}
$node = $quoranode->add(format_string($discussion->name), new moodle_url('/mod/quora/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_quora');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($quora->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/quora:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_quora\subscriptions::is_subscribable($quora)) {
        echo html_writer::div(
            quora_get_discussion_subscription_icon($quora, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo quora_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this quora
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = quora_user_can_post($quora, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $quora->type !== 'news') {
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
$neighbours = quora_get_discussion_neighbours($cm, $discussion);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix">';

if (!empty($CFG->enableportfolios) && has_capability('mod/quora:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('quora_portfolio_caller', array('discussionid' => $discussion->id), 'mod_quora');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_quora'));
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
quora_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($quora->type != 'single'
            && has_capability('mod/quora:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other quoras. The discussion in a
    // single discussion quora can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['quora'])) {
        $quoramenu = array();
        // Check quora types and eliminate simple discussions.
        $quoracheck = $DB->get_records('quora', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['quora'] as $quoracm) {
            if (!$quoracm->uservisible || !has_capability('mod/quora:startdiscussion',
                context_module::instance($quoracm->id))) {
                continue;
            }
            $section = $quoracm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($quoramenu[$section])) {
                $quoramenu[$section] = array($sectionname => array());
            }
            $quoraidcompare = $quoracm->instance != $quora->id;
            $quoratypecheck = $quoracheck[$quoracm->instance]->type !== 'single';
            if ($quoraidcompare and $quoratypecheck) {
                $url = "/mod/quora/discuss.php?d=$discussion->id&move=$quoracm->instance&sesskey=".sesskey();
                $quoramenu[$section][$sectionname][$url] = format_string($quoracm->name);
            }
        }
        if (!empty($quoramenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($quoramenu, '',
                    array(''=>get_string("movethisdiscussionto", "quora")),
                    'quoramenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}
echo '<div class="clearfloat">&nbsp;</div>';
echo "</div>";

if (!empty($quora->blockafter) && !empty($quora->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $quora->blockafter;
    $a->blockperiod = get_string('secondstotime'.$quora->blockperiod);
    echo $OUTPUT->notification(get_string('thisquoraisthrottled','quora',$a));
}

if ($quora->type == 'qanda' && !has_capability('mod/quora:viewqandawithoutposting', $modcontext) &&
            !quora_user_has_posted($quora->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','quora'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'quora', format_string($quora->name,true)));
}

$canrate = has_capability('mod/quora:rate', $modcontext);
quora_print_discussion($course, $cm, $quora, $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_quora-subscriptiontoggle', 'Y.M.mod_quora.subscriptiontoggle.init');

echo $OUTPUT->footer();

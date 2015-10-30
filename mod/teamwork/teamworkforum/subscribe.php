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
 * Subscribe to or unsubscribe from a teamworkforum or manage teamworkforum subscription mode
 *
 * This script can be used by either individual users to subscribe to or
 * unsubscribe from a teamworkforum (no 'mode' param provided), or by teamworkforum managers
 * to control the subscription mode (by 'mode' param).
 * This script can be called from a link in email so the sesskey is not
 * required parameter. However, if sesskey is missing, the user has to go
 * through a confirmation page that redirects the user back with the
 * sesskey.
 *
 * @package   mod_teamworkforum
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/teamworkforum/lib.php');

$id             = required_param('id', PARAM_INT);             // The teamworkforum to set subscription on.
$mode           = optional_param('mode', null, PARAM_INT);     // The teamworkforum's subscription mode.
$user           = optional_param('user', 0, PARAM_INT);        // The userid of the user to subscribe, defaults to $USER.
$discussionid   = optional_param('d', null, PARAM_INT);        // The discussionid to subscribe.
$sesskey        = optional_param('sesskey', null, PARAM_RAW);
$returnurl      = optional_param('returnurl', null, PARAM_RAW);

$url = new moodle_url('/mod/teamworkforum/subscribe.php', array('id'=>$id));
if (!is_null($mode)) {
    $url->param('mode', $mode);
}
if ($user !== 0) {
    $url->param('user', $user);
}
if (!is_null($sesskey)) {
    $url->param('sesskey', $sesskey);
}
if (!is_null($discussionid)) {
    $url->param('d', $discussionid);
    $discussion = $DB->get_record('teamworkforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
}
$PAGE->set_url($url);

$teamworkforum   = $DB->get_record('teamworkforum', array('id' => $id), '*', MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $teamworkforum->course), '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

if ($user) {
    require_sesskey();
    if (!has_capability('mod/teamworkforum:managesubscriptions', $context)) {
        print_error('nopermissiontosubscribe', 'teamworkforum');
    }
    $user = $DB->get_record('user', array('id' => $user), '*', MUST_EXIST);
} else {
    $user = $USER;
}

if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode = $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

$issubscribed = \mod_teamworkforum\subscriptions::is_subscribed($user->id, $teamworkforum, $discussionid, $cm);

// For a user to subscribe when a groupmode is set, they must have access to at least one group.
if ($groupmode && !$issubscribed && !has_capability('moodle/site:accessallgroups', $context)) {
    if (!groups_get_all_groups($course->id, $USER->id)) {
        print_error('cannotsubscribe', 'teamworkforum');
    }
}

require_login($course, false, $cm);

if (is_null($mode) and !is_enrolled($context, $USER, '', true)) {   // Guests and visitors can't subscribe - only enrolled
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    if (isguestuser()) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('subscribeenrolledonly', 'teamworkforum').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/mod/teamworkforum/view.php', array('f'=>$id)));
        echo $OUTPUT->footer();
        exit;
    } else {
        // there should not be any links leading to this place, just redirect
        redirect(new moodle_url('/mod/teamworkforum/view.php', array('f'=>$id)), get_string('subscribeenrolledonly', 'teamworkforum'));
    }
}

$returnto = optional_param('backtoindex',0,PARAM_INT)
    ? "index.php?id=".$course->id
    : "view.php?f=$id";

if ($returnurl) {
    $returnto = $returnurl;
}

if (!is_null($mode) and has_capability('mod/teamworkforum:managesubscriptions', $context)) {
    require_sesskey();
    switch ($mode) {
        case FORUM_CHOOSESUBSCRIBE : // 0
            \mod_teamworkforum\subscriptions::set_subscription_mode($teamworkforum->id, FORUM_CHOOSESUBSCRIBE);
            redirect($returnto, get_string("everyonecannowchoose", "teamworkforum"), 1);
            break;
        case FORUM_FORCESUBSCRIBE : // 1
            \mod_teamworkforum\subscriptions::set_subscription_mode($teamworkforum->id, FORUM_FORCESUBSCRIBE);
            redirect($returnto, get_string("everyoneisnowsubscribed", "teamworkforum"), 1);
            break;
        case FORUM_INITIALSUBSCRIBE : // 2
            if ($teamworkforum->forcesubscribe <> FORUM_INITIALSUBSCRIBE) {
                $users = \mod_teamworkforum\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email', '');
                foreach ($users as $user) {
                    \mod_teamworkforum\subscriptions::subscribe_user($user->id, $teamworkforum, $context);
                }
            }
            \mod_teamworkforum\subscriptions::set_subscription_mode($teamworkforum->id, FORUM_INITIALSUBSCRIBE);
            redirect($returnto, get_string("everyoneisnowsubscribed", "teamworkforum"), 1);
            break;
        case FORUM_DISALLOWSUBSCRIBE : // 3
            \mod_teamworkforum\subscriptions::set_subscription_mode($teamworkforum->id, FORUM_DISALLOWSUBSCRIBE);
            redirect($returnto, get_string("noonecansubscribenow", "teamworkforum"), 1);
            break;
        default:
            print_error(get_string('invalidforcesubscribe', 'teamworkforum'));
    }
}

if (\mod_teamworkforum\subscriptions::is_forcesubscribed($teamworkforum)) {
    redirect($returnto, get_string("everyoneisnowsubscribed", "teamworkforum"), 1);
}

$info = new stdClass();
$info->name  = fullname($user);
$info->teamworkforum = format_string($teamworkforum->name);

if ($issubscribed) {
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/teamworkforum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->teamworkforum = format_string($teamworkforum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmunsubscribediscussion', 'teamworkforum', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmunsubscribe', 'teamworkforum', format_string($teamworkforum->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid === null) {
        if (\mod_teamworkforum\subscriptions::unsubscribe_user($user->id, $teamworkforum, $context, true)) {
            redirect($returnto, get_string("nownotsubscribed", "teamworkforum", $info), 1);
        } else {
            print_error('cannotunsubscribe', 'teamworkforum', get_local_referer(false));
        }
    } else {
        if (\mod_teamworkforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion, $context)) {
            $info->discussion = $discussion->name;
            redirect($returnto, get_string("discussionnownotsubscribed", "teamworkforum", $info), 1);
        } else {
            print_error('cannotunsubscribe', 'teamworkforum', get_local_referer(false));
        }
    }

} else {  // subscribe
    if (\mod_teamworkforum\subscriptions::subscription_disabled($teamworkforum) && !has_capability('mod/teamworkforum:managesubscriptions', $context)) {
        print_error('disallowsubscribe', 'teamworkforum', get_local_referer(false));
    }
    if (!has_capability('mod/teamworkforum:viewdiscussion', $context)) {
        print_error('noviewdiscussionspermission', 'teamworkforum', get_local_referer(false));
    }
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/teamworkforum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->teamworkforum = format_string($teamworkforum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmsubscribediscussion', 'teamworkforum', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmsubscribe', 'teamworkforum', format_string($teamworkforum->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid == null) {
        \mod_teamworkforum\subscriptions::subscribe_user($user->id, $teamworkforum, $context, true);
        redirect($returnto, get_string("nowsubscribed", "teamworkforum", $info), 1);
    } else {
        $info->discussion = $discussion->name;
        \mod_teamworkforum\subscriptions::subscribe_user_to_discussion($user->id, $discussion, $context);
        redirect($returnto, get_string("discussionnowsubscribed", "teamworkforum", $info), 1);
    }
}

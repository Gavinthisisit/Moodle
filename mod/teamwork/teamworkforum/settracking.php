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
 * Set tracking option for the teamworkforum.
 *
 * @package   mod_teamworkforum
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id         = required_param('id',PARAM_INT);                           // The teamworkforum to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

require_sesskey();

if (! $teamworkforum = $DB->get_record("teamworkforum", array("id" => $id))) {
    print_error('invalidteamworkforumid', 'teamworkforum');
}

if (! $course = $DB->get_record("course", array("id" => $teamworkforum->course))) {
    print_error('invalidcoursemodule');
}

if (! $cm = get_coursemodule_from_instance("teamworkforum", $teamworkforum->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);

$returnto = teamworkforum_go_back_to($returnpage.'?id='.$course->id.'&f='.$teamworkforum->id);

if (!teamworkforum_tp_can_track_teamworkforums($teamworkforum)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->teamworkforum = format_string($teamworkforum->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('teamworkforumid' => $teamworkforum->id),
);

if (teamworkforum_tp_is_tracked($teamworkforum) ) {
    if (teamworkforum_tp_stop_tracking($teamworkforum->id)) {
        $event = \mod_teamworkforum\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nownottracking", "teamworkforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }

} else { // subscribe
    if (teamworkforum_tp_start_tracking($teamworkforum->id)) {
        $event = \mod_teamworkforum\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nowtracking", "teamworkforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}



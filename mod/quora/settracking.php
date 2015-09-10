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
 * Set tracking option for the quora.
 *
 * @package   mod_quora
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id         = required_param('id',PARAM_INT);                           // The quora to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

require_sesskey();

if (! $quora = $DB->get_record("quora", array("id" => $id))) {
    print_error('invalidquoraid', 'quora');
}

if (! $course = $DB->get_record("course", array("id" => $quora->course))) {
    print_error('invalidcoursemodule');
}

if (! $cm = get_coursemodule_from_instance("quora", $quora->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);

$returnto = quora_go_back_to($returnpage.'?id='.$course->id.'&f='.$quora->id);

if (!quora_tp_can_track_quoras($quora)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->quora = format_string($quora->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('quoraid' => $quora->id),
);

if (quora_tp_is_tracked($quora) ) {
    if (quora_tp_stop_tracking($quora->id)) {
        $event = \mod_quora\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nownottracking", "quora", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }

} else { // subscribe
    if (quora_tp_start_tracking($quora->id)) {
        $event = \mod_quora\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nowtracking", "quora", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}



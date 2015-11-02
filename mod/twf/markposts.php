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
 * Set tracking option for the twf.
 *
 * @package   mod_twf
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$f          = required_param('f',PARAM_INT); // The twf to mark
$mark       = required_param('mark',PARAM_ALPHA); // Read or unread?
$d          = optional_param('d',0,PARAM_INT); // Discussion to mark.
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

$url = new moodle_url('/mod/twf/markposts.php', array('f'=>$f, 'mark'=>$mark));
if ($d !== 0) {
    $url->param('d', $d);
}
if ($returnpage !== 'index.php') {
    $url->param('returnpage', $returnpage);
}
$PAGE->set_url($url);

if (! $twf = $DB->get_record("twf", array("id" => $f))) {
    print_error('invalidtwfid', 'twf');
}

if (! $course = $DB->get_record("course", array("id" => $twf->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance("twf", $twf->id, $course->id)) {
    print_error('invalidcoursemodule');
}

$user = $USER;

require_login($course, false, $cm);

if ($returnpage == 'index.php') {
    $returnto = twf_go_back_to($returnpage.'?id='.$course->id);
} else {
    $returnto = twf_go_back_to($returnpage.'?f='.$twf->id);
}

if (isguestuser()) {   // Guests can't change twf
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguesttracking', 'twf').'<br /><br />'.get_string('liketologin'), get_login_url(), $returnto);
    echo $OUTPUT->footer();
    exit;
}

$info = new stdClass();
$info->name  = fullname($user);
$info->twf = format_string($twf->name);

if ($mark == 'read') {
    if (!empty($d)) {
        if (! $discussion = $DB->get_record('twf_discussions', array('id'=> $d, 'twf'=> $twf->id))) {
            print_error('invaliddiscussionid', 'twf');
        }

        twf_tp_mark_discussion_read($user, $d);
    } else {
        // Mark all messages read in current group
        $currentgroup = groups_get_activity_group($cm);
        if(!$currentgroup) {
            // mark_twf_read requires ===false, while get_activity_group
            // may return 0
            $currentgroup=false;
        }
        twf_tp_mark_twf_read($user, $twf->id, $currentgroup);
    }

/// FUTURE - Add ability to mark them as unread.
//    } else { // subscribe
//        if (twf_tp_start_tracking($twf->id, $user->id)) {
//            redirect($returnto, get_string("nowtracking", "twf", $info), 1);
//        } else {
//            print_error("Could not start tracking that twf", get_local_referer());
//        }
}

redirect($returnto);


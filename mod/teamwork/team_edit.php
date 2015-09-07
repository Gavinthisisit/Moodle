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
 * Edit grading form in for a particular instance of teamwork
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mod_form.php');

$teamworkid = required_param('teamworkid', PARAM_INT);
$templetid 	= required_param('templetid', PARAM_INT); 
$update		= optional_param('update', 0, PARAM_INT);

$cm         = get_coursemodule_from_instance('teamwork', $teamworkid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);

$teamwork   = $DB->get_record('teamwork', array('id' => $teamworkid), '*', MUST_EXIST);
$teamwork   = new teamwork($teamwork, $cm, $course);

// todo: check if there already is some assessment done and do not allowed the change of the form
// once somebody already used it to assess
$team_edit_url = new moodle_url('/mod/teamwork/team_edit.php', array('id' => $templetid));
$PAGE->set_url($team_edit_url);
$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('createteam', 'teamwork'));


$mform = new teamwork_teaminfo_form($course->id, $teamworkid, $templetid);


if ($mform->is_cancelled()) {
    redirect($teamwork->view_url());
} elseif ($data = $mform->get_data()) {
    save_templet_data($data);
    redirect($teamwork->view_url());
}

// Output starts here

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($teamwork->name));

$mform->display();

echo $OUTPUT->footer();

////////////////////////////////
function save_templet_data($data){
	global $DB, $USER;
	$newteam = new stdClass();
	$newteam->course = $data->courseid;
	$newteam->teamwork = $data->teamworkid;
	$newteam->name = $data->title;
	$newteam->time = $data->time;
	$newteam->templet = $data->templetid;
	$newteam->leader = (int)$USER->id;
	$newteam->invitedkey = random_string(10);
	//var_dump($newteam); die;
	$DB->insert_record('teamwork_team',$newteam);

	$newmember = new stdClass();
	$newmember->course = $data->courseid;
	$newmember->teamwork = $data->teamworkid;
	$newmember->team = $DB->get_record('teamwork_team', array('templet' => $data->templetid, 'leader' => $USER->id))->id;
	$newmember->userid = $USER->id;
	$newmember->leader = 1;
	$DB->insert_record('teamwork_teammembers', $newmember);
}
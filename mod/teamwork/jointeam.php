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

$cm         = get_coursemodule_from_instance('teamwork', $teamworkid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);

$teamwork   = $DB->get_record('teamwork', array('id' => $teamworkid), '*', MUST_EXIST);
$teamwork   = new teamwork($teamwork, $cm, $course);

// todo: check if there already is some assessment done and do not allowed the change of the form
// once somebody already used it to assess
$jointeam_url = new moodle_url('/mod/teamwork/jointeam.php', array('teamworkid' => $teamworkid));
$PAGE->set_url($jointeam_url);
$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('joininteam', 'teamwork'));


$mform = new teamwork_jointeam_form($course->id, $teamworkid);


if ($mform->is_cancelled()) {
    redirect($teamwork->view_url());
} elseif ($data = $mform->get_data()) {
	save_data($data);
    redirect($teamwork->view_url(),get_string('joinedsuccess','teamwork'),1);
}

// Output starts here

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($teamwork->name));

$mform->display();

echo $OUTPUT->footer();

////////////////////////////////
function save_data($data){
	global $DB, $USER;
	if(strlen($data->invitedkey)!=10){
		redirect("jointeam.php?teamworkid=$data->teamworkid",get_string('invalidinvitedkey','teamwork'),1);
	}
	$newmember = new stdClass();
	$newmember->course = $data->courseid;
	$newmember->teamwork = $data->teamworkid;
	
	$team_joined = $DB->get_record('teamwork_team', array('teamwork' => $data->teamworkid, 'invitedkey' => $data->invitedkey));
	if(!$team_joined){
		redirect("jointeam.php?teamworkid=$data->teamworkid",get_string('invalidinvitedkey','teamwork'),1);
	}
	$membernum = $DB->get_records('teamwork_teammembers', array('teamwork' => $data->teamworkid, 'team' => $team_joined->id));
	$maxmember = $DB->get_record('teamwork_templet', array('teamwork' => $data->teamworkid, 'id' => $team_joined->templet))->teammaxmember;
	if($maxmember <= count($membernum)){
		redirect("jointeam.php?teamworkid=$data->teamworkid",get_string('teamhasfull','teamwork'),1);
	}
	$newmember->team = $team_joined->id;
	$newmember->userid = $USER->id;
	$newmember->leader = 0;
	$newmember->time = time();
	$hasrecord = $DB->get_record('teamwork_teammembers', array('teamwork' => $data->teamworkid, 'team' => $newmember->team,'userid' => $newmember->userid));

	if(!empty($hasrecord)){
		redirect("jointeam.php?teamworkid=$data->teamworkid",get_string('hasjoinedtheteam','teamwork'),1);
	}
	$DB->insert_record('teamwork_teammembers', $newmember);
}
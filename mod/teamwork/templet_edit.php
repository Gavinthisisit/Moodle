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

$id       = required_param('id', PARAM_INT);
$update   = optional_param('update', 0, PARAM_INT);

$cm         = get_coursemodule_from_instance('teamwork', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
require_capability('mod/teamwork:editsettings', $PAGE->context);

$teamwork   = $DB->get_record('teamwork', array('id' => $id), '*', MUST_EXIST);
$teamwork   = new teamwork($teamwork, $cm, $course);

// todo: check if there already is some assessment done and do not allowed the change of the form
// once somebody already used it to assess
$templet_edit_url = new moodle_url('/mod/teamwork/templet_edit.php', array('id' => $id));
$PAGE->set_url($templet_edit_url);
$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('editingprojectsettings', 'teamwork'));


$mform = new teamwork_templet_form($id);


if ($mform->is_cancelled()) {
    redirect($teamwork->view_url());
} elseif ($data = $mform->get_data()) {
    save_templet_data($course,$data);
    redirect($teamwork->view_url());
}

// Output starts here

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($teamwork->name));

$mform->display();

echo $OUTPUT->footer();
////////////////////////////////
function save_templet_data($course,$data){
	global $DB;
	$newtemplet = new stdClass();
	$newtemplet->course = $course->id;
	$newtemplet->teamwork = $data->id;
	$newtemplet->title = $data->title;
	$newtemplet->summary = $data->summary['text'];
	$newtemplet->teamminmember = (int)$data->teamminmembers;
	$newtemplet->teammaxmember = (int)$data->teammaxmembers;
	$newtemplet->teamlimit = (int)$data->teamlimit;
	$newtemplet->role = 0;
	$newtemplet->phase = (int)$data->phasenum;
	$newtemplet->display = (int)$data->displaysubmissions;
	$newtemplet->scoremin = (int)$data->scoremin;
	$newtemplet->scoremax = (int)$data->scoremax;
	$newtemplet->anonymous = (int)$data->assessmentanonymous;
	$newtemplet->assessfirst = (int)$data->assessfirst;
	$DB->insert_record('teamwork_templet',$newtemplet);
	
}
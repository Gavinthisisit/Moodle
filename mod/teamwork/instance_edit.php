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

$teamworkid       = required_param('teamwork', PARAM_INT);
$instanceid   = required_param('instance', PARAM_INT);	//instance id

$cm         = get_coursemodule_from_instance('teamwork', $teamworkid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
require_capability('mod/teamwork:editsettings', $PAGE->context);

$teamwork   = $DB->get_record('teamwork', array('id' => $teamworkid), '*', MUST_EXIST);
$teamwork   = new teamwork($teamwork, $cm, $course);

// todo: check if there already is some assessment done and do not allowed the change of the form
// once somebody already used it to assess
$instance_edit_url = new moodle_url('/mod/teamwork/instance_edit.php', array('teamwork' => $teamworkid, 'instance' => $instanceid));
$PAGE->set_url($instance_edit_url);
$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('editingprojectsettings', 'teamwork'));

$mform = null;
$submit = optional_param('phasenum', 0, PARAM_INT);
$data = $DB->get_record('teamwork_instance', array('id' => $instanceid), '*', MUST_EXIST);
$mform = new teamwork_instance_form($teamworkid,$instanceid,$submit==0?$data->phase:0);
$savedata = new stdClass();
$savedata->title = $data->title;
$savedata->summary['text'] = $data->summary;
$savedata->teamminmembers = $data->teamminmember;
$savedata->teammaxmembers = $data->teammaxmember;
$savedata->teamlimit = $data->teamlimit;
$savedata->role = $data->role;
$savedata->phasenum = $data->phase;
$savedata->displaysubmissions = $data->display;
$savedata->scoremin = $data->scoremin;
$savedata->scoremax = $data->scoremax;
$savedata->assessmentanonymous = $data->anonymous;
$savedata->assessfirst = $data->assessfirst;

for($i=1;$i <= (int)$savedata->phasenum;$i++){
	$data = $DB->get_record('teamwork_instance_phase', array('teamwork' => $teamworkid,'instance' => $instanceid,'orderid' => $i), '*', MUST_EXIST);
	$savedata->{'phasename_'.$i} = $data->name;
	$savedata->{'phasedescription_'.$i}['text'] = $data->description;
	$savedata->{'phasestart_'.$i} = $data->timestart;
	$savedata->{'phaseend_'.$i} = $data->timeend;
}

$mform->set_data($savedata);



if ($mform->is_cancelled()) {
    redirect($teamwork->view_url());
} elseif ($data = $mform->get_data()) {
	if(!empty($data->selectothers)) {
		$others = $data->selectothers;
		$oldinstanceid = $data->instance;
		unset($data->selectothers);
		foreach($others as $other){
    		$data->instance = $other;
    		save_instance_data($course,$data);
    	}
    	$data->instance = $oldinstanceid;
	}else{
		unset($data->selectothers);
	}
	
    save_instance_data($course,$data);
    
    redirect($teamwork->view_url());
}

// Output starts here

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($teamwork->name));

$mform->display();

echo $OUTPUT->footer();
////////////////////////////////
function save_instance_data($course,$data){
	global $DB;
	$newinstance = new stdClass();
	$newinstance->course = $course->id;
	$newinstance->teamwork = $data->teamwork;
	$newinstance->templet = $data->templet;
	$newinstance->title = $data->title;
	$newinstance->summary = $data->summary['text'];
	$newinstance->teamminmember = (int)$data->teamminmembers;
	$newinstance->teammaxmember = (int)$data->teammaxmembers;
	$newinstance->teamlimit = (int)$data->teamlimit;
	$newinstance->role = 0;
	$newinstance->phase = (int)$data->phasenum;
	$newinstance->currentphase = $data->currentphase;
	$newinstance->display = (int)$data->displaysubmissions;
	$newinstance->scoremin = (int)$data->scoremin;
	$newinstance->scoremax = (int)$data->scoremax;
	$newinstance->anonymous = (int)$data->assessmentanonymous;
	$newinstance->assessfirst = (int)$data->assessfirst;	
	$newinstance->id = $data->instance;
	$DB->update_record('teamwork_instance',$newinstance);
	$instanceid = $data->instance;

		
	
	for($i=1;$i<=$data->phasenum;$i++){
		$newphase = new stdClass();
		$newphase->course = $course->id;
		$newphase->teamwork = $data->teamwork;
		$newphase->instance =  $instanceid;
		$newphase->orderid = $i;
		$newphase->name = $data->{'phasename_'.$i};
		$newphase->description = $data->{'phasedescription_'.$i}['text'];
		$newphase->timestart = $data->{'phasestart_'.$i};
		$newphase->timeend = $data->{'phaseend_'.$i};
		$newphase->needassess = (int)$data->needassess;
		$record = $DB->get_record('teamwork_instance_phase',array('instance'=>$instanceid,'orderid'=>$i));
		if(!empty($record)){
			$newphase->id = $record->id;
			$DB->update_record('teamwork_instance_phase',$newphase);
		}else{
			$DB->insert_record('teamwork_instance_phase',$newphase);
		}
	}
	
}

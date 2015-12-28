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
 * Prints a particular instance of teamwork
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id         = optional_param('id', 0, PARAM_INT); // course_module ID, or
$w          = optional_param('w', 0, PARAM_INT);  // teamwork instance ID"D.sd'dsf.'sd;fs';
$templetid  = optional_param('templetid', 0, PARAM_INT);
$teamid  = optional_param('teamid', 0, PARAM_INT);
$memberid  = optional_param('remove', 0, PARAM_INT);
$templetview = optional_param('view', 0, PARAM_INT);

if ($id) {
    $cm             = get_coursemodule_from_id('teamwork', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $teamworkrecord = $DB->get_record('teamwork', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $teamworkrecord = $DB->get_record('teamwork', array('id' => $w), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $teamworkrecord->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('teamwork', $teamworkrecord->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
$teamwork = new teamwork($teamworkrecord, $cm, $course);

if($templetview!=0){
	require_capability('mod/teamwork:editsettings', $PAGE->context);

	$PAGE->set_title(get_string('viewteaminfo', 'teamwork'));
	$PAGE->set_heading($course->fullname);	
	$output = $PAGE->get_renderer('mod_teamwork');
	
	echo $output->header();
	
	$project = $DB->get_record('teamwork_templet',array('id' => $templetview));
	$team_module = html_writer::link($task->link, get_string('teammodule','teamwork'));
	$blank = "\t";
	echo $output->heading($project->title);
	//echo  '<h3 style="text-align:left">'.$project->title;
	//echo '<h3 style="text-align:right">'.$team_module .'</h3>';
	$teams = $DB->get_records('teamwork_team', array('templet' => $templetview, 'teamwork' => $w));
	foreach($teams as $team){
		print_collapsible_region_start('', 'teamwork-info-'.$team->id, $team->name);
		
		$renderable = new teamwork_team_manage($w, $team->id);
		
		echo $output->render($renderable);
		
		$teaminfo = new teamwork_team_info($w, $team->id);
		
		echo $output->render($teaminfo);
		
		print_collapsible_region_end();
		
	}
	echo $output->footer();
}else{ 
	require_capability('mod/teamwork:view', $PAGE->context);
	$teamleader_record = $DB->get_record('teamwork_teammembers', array('userid' => $USER->id, 'teamwork' => $w,'leader' => 1));
	if(empty($teamleader_record)){
		redirect("view.php?id=$cm->id");
	}
	
	if(!empty($teamid) && !empty($memberid) && !empty($teamleader_record)){
		if($teamleader_record->team == $teamid)	{
			if($teamleader_record->userid == $memberid){
				$DB->delete_records('teamwork_team',array('leader'=>$memberid,'teamwork'=> $w));
				$DB->delete_records('teamwork_teammembers',array('team'=>$teamid,'teamwork'=> $w));
			}
			else {
				$DB->delete_records('teamwork_teammembers',array('userid'=>$memberid,'team'=>$teamid,'teamwork'=> $w));
			}
		}
	}
	$teamid = $teamleader_record->team;
	$PAGE->set_title(get_string('editteaminfo', 'teamwork'));
	$PAGE->set_heading($course->fullname);
	
	$output = $PAGE->get_renderer('mod_teamwork');
	
	/// Output starts here
	
	echo $output->header();
	
	print_collapsible_region_start('', 'teamwork-invitedkey', get_string('invitedkey', 'teamwork'));
	$renderable = new teamwork_team_invitedkey($w, $teamid);
	echo $output->render($renderable);
	print_collapsible_region_end();
	
	print_collapsible_region_start('', 'teamwork-editteaminfo', get_string('editteaminfo', 'teamwork'));
	$renderable = new teamwork_team_manage($w, $teamid);
	echo $output->render($renderable);
	print_collapsible_region_end();

	echo $output->footer();
}


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

$uid         = optional_param('userid', 0, PARAM_INT); // course_module ID, or
$w          = optional_param('w', 0, PARAM_INT);  // teamwork instance ID\
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);


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
require_capability('mod/teamwork:view', $PAGE->context);

$teamwork = new teamwork($teamworkrecord, $cm, $course);



if (!is_null($editmode) && $PAGE->user_allowed_editing()) {
    $USER->editing = $editmode;
}

$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_url(new moodle_url("/mod/teamwork/project.php",array('w' => $w,'instance' => $instanceid)));



$output = $PAGE->get_renderer('mod_teamwork');

/// Output starts here

echo $output->header();

$user_record = $DB->get_record('user', array('id' => $uid));
$team_member_record = $DB->get_record('teamwork_teammembers',array('userid' => $uid));
$teamid = $team_member_record->team;
$team_record = $DB->get_record('teamwork_team',array('id' => $teamid));
$project_record = $DB->get_record('teamwork_instance',array('team' => $teamid));
echo $output->heading(format_string($project_record->title.'@'.$team_record->name.':'.$user_record->lastname.' '.$user_record->firstname));

// Output team submissions here
print_collapsible_region_start('', 'teamwork-studentinfo-teaminfo', '所在团队情况');

$teaminfo = new teamwork_team_info($w, $teamid);
echo $output->render($teaminfo);


print_collapsible_region_end();
// Output own submissions here
print_collapsible_region_start('', 'workshop-viewlet-ownsubmission', get_string('studentsubmission', 'teamwork'));
echo $output->box_start('generalbox ownsubmission');

$countsubmissions = $teamwork->count_submissions($uid);
$perpage = get_user_preferences('teamwork_perpage', 10);
$pagingbar = new paging_bar($countsubmissions, $page, $perpage, $PAGE->url, 'page');
$submissions = $teamwork->get_all_submissions($uid, 0, $page * $perpage, $perpage);
$shownames = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
echo $output->render($pagingbar);
foreach ($submissions as $submission) {
    echo $output->render($teamwork->prepare_submission_summary($submission, $shownames));
}
echo $output->render($pagingbar);
echo $output->perpage_selector($perpage);
echo $output->box_end();
print_collapsible_region_end();


print_collapsible_region_start('', 'workshop-viewlet-owncommmit', '用户给出的评价');//get_string('studentsubmission', 'teamwork'));
echo $output->box_start('generalbox ownsubmission');
/*
$associate = $DB->get_record('teamwork_associate_twf', array('course' => $course->id, 'teamwork' => $w));
$countsubmissions = count($DB->get_records('twf_discussions', array('twf' => $associate->twf, 'teamwork' => $w, 'instance' => $instanceid, 'phase' => $phase)));
$perpage = get_user_preferences('teamwork_perpage', 10);
$pagingbar = new paging_bar($countsubmissions, $page, $perpage, $PAGE->url, 'page');
$discussions = $teamwork->get_phase_discussions($associate->twf, $instanceid, $phase, $page * $perpage, $perpage);
$shownames = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
echo $output->render($pagingbar);
foreach ($discussions as $discussion) {
	$discussion->authorid = $discussion->authoridx;
    echo $output->render($teamwork->prepare_discussion_summary($discussion, $shownames));
}
echo $output->render($pagingbar);
echo $output->perpage_selector($perpage);
*/
echo $output->box_end();
print_collapsible_region_end();

echo $output->footer();

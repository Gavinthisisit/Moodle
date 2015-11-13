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
$w          = optional_param('w', 0, PARAM_INT);  // teamwork instance ID
$instanceid  = optional_param('instance', 0, PARAM_INT);
$phase  = optional_param('phase', 0, PARAM_INT);
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

if ($perpage and $perpage > 0 and $perpage <= 1000) {
    require_sesskey();
    set_user_preference('teamwork_perpage', $perpage);
    redirect($PAGE->url);
}

$output = $PAGE->get_renderer('mod_teamwork');

/// Output starts here
$instancerecord = $DB->get_record('teamwork_instance', array('id' => $instanceid));
$teamrecord = $DB->get_record('teamwork_team', array('id' => $instancerecord->team));

if(empty($phase) or $phase > $instancerecord->phase) {
	$phase = $instancerecord->currentphase;
}

echo $output->header();
echo $output->heading(format_string($instancerecord->title.'@'.$teamrecord->name));

$userplan = new teamwork_user_plan($teamwork, $instanceid);
$userplan->showphase = $phase;
echo $output->render($userplan);



// Output team submissions here
print_collapsible_region_start('', 'workshop-viewlet-teamsubmission', get_string('teamsubmission', 'teamwork'));
echo $output->box_start('generalbox teamsubmission');
$countsubmissions = $teamwork->count_instance_submissions($instanceid);
$perpage = get_user_preferences('teamwork_perpage', 10);
$pagingbar = new paging_bar($countsubmissions, $page, $perpage, $PAGE->url, 'page');
$submissions = $teamwork->get_instance_submissions($instanceid, $phase, $page * $perpage, $perpage);
$shownames = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
echo $output->render($pagingbar);
foreach ($submissions as $submission) {
    echo $output->render($teamwork->prepare_submission_summary($submission, $shownames));
}
echo $output->render($pagingbar);
echo $output->perpage_selector($perpage);
echo $output->box_end();
print_collapsible_region_end();


$ismember = $DB->get_record('teamwork_teammembers', array('userid' => $USER->id, 'team' => $teamrecord->id));
if($ismember){
	// Output own submissions here
	print_collapsible_region_start('', 'workshop-viewlet-ownsubmission', get_string('yoursubmission', 'teamwork'));
	echo $output->box_start('generalbox ownsubmission');
	
	$countsubmissions = $teamwork->count_submissions($USER->id);
	$perpage = get_user_preferences('teamwork_perpage', 10);
	$pagingbar = new paging_bar($countsubmissions, $page, $perpage, $PAGE->url, 'page');
	$submissions = $teamwork->get_submissions($USER->id, $phase, 0, $page * $perpage, $perpage);
	$shownames = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
	echo $output->render($pagingbar);
	foreach ($submissions as $submission) {
	    echo $output->render($teamwork->prepare_submission_summary($submission, $shownames));
	}
	echo $output->render($pagingbar);
	echo $output->perpage_selector($perpage);
	echo $output->single_button("submission.php?teamwork=$w&instance=$instancerecord->id", get_string('createsubmission', 'teamwork'), 'get');
	echo $output->box_end();
	print_collapsible_region_end();
}

print_collapsible_region_start('', 'workshop-viewlet-teamforum', get_string('teamforum', 'teamwork'));
echo $output->box_start('generalbox teamforum');

$associate = $DB->get_record('teamwork_associate_twf', array('course' => $course->id, 'teamwork' => $w));
$assessed = $DB->get_record('twf_discussions', array('userid' => $USER->id, 'teamwork' => $w, 'instance' => $instanceid, 'phase' => $instancerecord->currentphase));
if (!$assessed && (!$ismember || has_capability('mod/teamwork:editsettings', $PAGE->context))) {
	//Output the button for add discussion
	echo '<div class="singlebutton forumaddnew">';
    echo "<form id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/mod/twf/post.php\">";
    echo '<div>';
    echo "<input type=\"hidden\" name=\"twf\" value=\"$associate->twf\" />";
    echo "<input type=\"hidden\" name=\"teamwork\" value=\"$w\" />";
    echo "<input type=\"hidden\" name=\"instance\" value=\"$instanceid\" />";
    echo "<input type=\"hidden\" name=\"phase\" value=\"$instancerecord->currentphase\" />";
    switch ($phaseforum->type) {
        case 'news':
        case 'blog':
            $buttonadd = get_string('addanewtopic', 'twf');
            break;
        case 'qanda':
            $buttonadd = get_string('addanewquestion', 'twf');
            break;
        default:
            $buttonadd = get_string('addanewdiscussion', 'twf');
            break;
    }
    echo '<input type="submit" value="'.$buttonadd.'" />';
    echo '</div>';
    echo '</form>';
    echo "</div>\n";
}

if ($ismember || $assessed || has_capability('mod/teamwork:editsettings', $PAGE->context)) {
	//Ouput team phrase assessments here	
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
}

echo $output->box_end();
print_collapsible_region_end();


echo $output->footer();

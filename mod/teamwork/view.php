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
$editmode   = optional_param('editmode', null, PARAM_BOOL);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);
$eval       = optional_param('eval', null, PARAM_PLUGIN);
$templetid  = optional_param('templetid', 0, PARAM_INT);

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

// Mark viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$eventdata = array();
$eventdata['objectid']         = $teamwork->id;
$eventdata['context']          = $teamwork->context;

$PAGE->set_url($teamwork->view_url());
$event = \mod_teamwork\event\course_module_viewed::create($eventdata);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('teamwork', $teamworkrecord);
$event->add_record_snapshot('course_modules', $cm);
$event->trigger();

if ($teamwork->applyover==0 and $teamwork->applyend > 0 and $teamwork->applyend < time()) {
    generate_instanse_from_templet($teamwork->id);
    $DB->set_field('teamwork', 'applyover', 1, array('id' => $teamwork->id));
}

if (!is_null($editmode) && $PAGE->user_allowed_editing()) {
    $USER->editing = $editmode;
}

$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);


$output = $PAGE->get_renderer('mod_teamwork');

/// Output starts here

echo $output->header();

// Output tabs here
ob_start();
include($CFG->dirroot.'/mod/teamwork/tabs.php');
$output_tab = ob_get_contents();
ob_end_clean();
echo $output_tab;

//display templet list
$teammember_records = $DB->get_records('teamwork_teammembers', array('userid' => $USER->id, 'teamwork' => $teamwork->id));
$is_team_leader = false;
$leading_team = null;
foreach ($teammember_records as $recordid => $record) {
    if ($record->leader) {
        $is_team_leader = true;
        $leading_team = $record->team;
    }
}

if (has_capability('mod/teamwork:editsettings', $PAGE->context)) {
    $can_edit_templet = true;
}
else {
    $can_edit_templet = false;
}
$can_join_team = true;
if (count($teammember_records) >= $teamworkrecord->participationnumlimit) {
    $can_join_team = false;
}
if (count($teammember_records) > 0) {
    print_collapsible_region_start('', 'teamwork-myproject', get_string('myproject', 'teamwork'));
    $renderable = new teamwork_myproject($teamwork->id);
    echo $output->render($renderable);
    print_collapsible_region_end();
}
if ($can_edit_templet) {
    $renderable = new teamwork_templet_list_manager($teamwork->id);
}
else if ((!$can_join_team) || $is_team_leader) {
   	$renderable = new teamwork_templet_list_member($teamwork->id);
}
else {
    $renderable = new teamwork_templet_list($teamwork->id); 
}
print_collapsible_region_start('', 'teamwork-templetlist', get_string('templetlist', 'teamwork'));
echo $output->render($renderable);
print_collapsible_region_end();

//display control buttons
$renderable = new teamwork_templet_buttons($teamwork->id, $leading_team, $can_edit_templet, $is_team_leader, $can_join_team);
echo $output->render($renderable);

echo $output->footer();

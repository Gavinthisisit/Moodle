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
 * Various teamwork maintainance utilities
 *
 * @package    mod_teamwork
 * @copyright  2010 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id         = required_param('id', PARAM_INT); // course_module ID
$tool       = required_param('tool', PARAM_ALPHA);

$cm         = get_coursemodule_from_id('teamwork', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamwork   = $DB->get_record('teamwork', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
$teamwork = new teamwork($teamwork, $cm, $course);
require_sesskey();

$params = array(
    'context' => $teamwork->context,
    'courseid' => $course->id,
    'other' => array('teamworkid' => $teamwork->id)
);

switch ($tool) {
case 'clearaggregatedgrades':
    require_capability('mod/teamwork:overridegrades', $teamwork->context);
    $teamwork->clear_submission_grades();
    $teamwork->clear_grading_grades();
    $event = \mod_teamwork\event\assessment_evaluations_reset::create($params);
    $event->trigger();
    break;

case 'clearassessments':
    require_capability('mod/teamwork:overridegrades', $teamwork->context);
    $teamwork->clear_assessments();
    $event = \mod_teamwork\event\assessments_reset::create($params);
    $event->trigger();
    break;
}

redirect($teamwork->view_url());

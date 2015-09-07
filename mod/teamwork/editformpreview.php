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
 * Preview the assessment form.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$cmid     = required_param('cmid', PARAM_INT);
$cm       = get_coursemodule_from_id('teamwork', $cmid, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamwork = $DB->get_record('teamwork', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}
$teamwork = new teamwork($teamwork, $cm, $course);

require_capability('mod/teamwork:editdimensions', $teamwork->context);
$PAGE->set_url($teamwork->previewform_url());
$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('editingassessmentform', 'teamwork'), $teamwork->editform_url(), navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add(get_string('previewassessmentform', 'teamwork'));
$currenttab = 'editform';

// load the grading strategy logic
$strategy = $teamwork->grading_strategy_instance();

// load the assessment form
$mform = $strategy->get_assessment_form($teamwork->editform_url(), 'preview');

// output starts here
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($teamwork->name));
echo $OUTPUT->heading(get_string('assessmentform', 'teamwork'), 3);
$mform->display();
echo $OUTPUT->footer();

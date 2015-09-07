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
 * At this page, teachers allocate submissions to students for a review
 *
 * The allocation logic itself is delegated to allocators - subplugins in ./allocation
 * folder.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/allocation/lib.php');

$cmid       = required_param('cmid', PARAM_INT);                    // course module
$method     = optional_param('method', 'manual', PARAM_ALPHA);      // method to use

$cm         = get_coursemodule_from_id('teamwork', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamwork   = $DB->get_record('teamwork', array('id' => $cm->instance), '*', MUST_EXIST);
$teamwork   = new teamwork($teamwork, $cm, $course);

$PAGE->set_url($teamwork->allocation_url($method));

require_login($course, false, $cm);
$context = $PAGE->context;
require_capability('mod/teamwork:allocate', $context);

$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('allocation', 'teamwork'));

$allocator  = $teamwork->allocator_instance($method);
$initresult = $allocator->init();

//
// Output starts here
//
$output = $PAGE->get_renderer('mod_teamwork');
echo $output->header();
echo $OUTPUT->heading(format_string($teamwork->name));

$allocators = teamwork::installed_allocators();
if (!empty($allocators)) {
    $tabs       = array();
    $row        = array();
    $inactive   = array();
    $activated  = array();
    foreach ($allocators as $methodid => $methodname) {
        $row[] = new tabobject($methodid, $teamwork->allocation_url($methodid)->out(), $methodname);
        if ($methodid == $method) {
            $currenttab = $methodid;
        }
    }
}
$tabs[] = $row;
print_tabs($tabs, $currenttab, $inactive, $activated);

if (is_null($initresult->get_status()) or $initresult->get_status() == teamwork_allocation_result::STATUS_VOID) {
    echo $output->container_start('allocator-ui');
    echo $allocator->ui();
    echo $output->container_end();
} else {
    echo $output->container_start('allocator-init-results');
    echo $output->render($initresult);
    echo $output->continue_button($teamwork->allocation_url($method));
    echo $output->container_end();
}
echo $output->footer();

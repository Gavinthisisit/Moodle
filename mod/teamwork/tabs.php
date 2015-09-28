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
* Sets up the tabs used by the lesson pages for teachers.
*
* This file was adapted from the mod/quiz/tabs.php
*
 * @package mod_lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
*/

defined('MOODLE_INTERNAL') || die();


global $DB;


$row[] = new tabobject('mytasks', "$CFG->wwwroot/mod/teamwork/mytasks.php?w=$teamwork->id", get_string('mytasks', 'teamwork'), get_string('mytasks', 'teamwork'));
$row[] = new tabobject('templetlist', "$CFG->wwwroot/mod/teamwork/view.php?w=$teamwork->id", get_string('templetlist', 'teamwork'), get_string('templetlist', 'teamwork'));
$row[] = new tabobject('teamlist', "$CFG->wwwroot/mod/teamwork/teamlist.php?w=$teamwork->id", get_string('teamlist', 'teamwork'), get_string('teamlist', 'teamwork'));

$tabs[] = $row;
$inactive = $activated = array();
$inactive[] = 'templetlist';
$activated[] = 'templetlist';
if($teamwork->applyover == 0) {
	$inactive[] = 'mytasks';
	$inactive[] = 'teamlist';
}
print_tabs($tabs, $currenttab, $inactive, $activated);

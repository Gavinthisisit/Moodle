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
 * Definition of log events
 *
 * @package    mod_teamwork
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    // teamwork instance log actions
    array('module'=>'teamwork', 'action'=>'add', 'mtable'=>'teamwork', 'field'=>'name'),
    array('module'=>'teamwork', 'action'=>'update', 'mtable'=>'teamwork', 'field'=>'name'),
    array('module'=>'teamwork', 'action'=>'view', 'mtable'=>'teamwork', 'field'=>'name'),
    array('module'=>'teamwork', 'action'=>'view all', 'mtable'=>'teamwork', 'field'=>'name'),
    // submission log actions
    array('module'=>'teamwork', 'action'=>'add submission', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'update submission', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'view submission', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    // assessment log actions
    array('module'=>'teamwork', 'action'=>'add assessment', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'update assessment', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    // example log actions
    array('module'=>'teamwork', 'action'=>'add example', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'update example', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'view example', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    // example assessment log actions
    array('module'=>'teamwork', 'action'=>'add reference assessment', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'update reference assessment', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'add example assessment', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    array('module'=>'teamwork', 'action'=>'update example assessment', 'mtable'=>'teamwork_submissions', 'field'=>'title'),
    // grading evaluation log actions
    array('module'=>'teamwork', 'action'=>'update aggregate grades', 'mtable'=>'teamwork', 'field'=>'name'),
    array('module'=>'teamwork', 'action'=>'update clear aggregated grades', 'mtable'=>'teamwork', 'field'=>'name'),
    array('module'=>'teamwork', 'action'=>'update clear assessments', 'mtable'=>'teamwork', 'field'=>'name'),
);

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
 * @package    mod_teamworkforum
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB; // TODO: this is a hack, we should really do something with the SQL in SQL tables

$logs = array(
    array('module'=>'teamworkforum', 'action'=>'add', 'mtable'=>'teamworkforum', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'update', 'mtable'=>'teamworkforum', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'add discussion', 'mtable'=>'teamworkforum_discussions', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'add post', 'mtable'=>'teamworkforum_posts', 'field'=>'subject'),
    array('module'=>'teamworkforum', 'action'=>'update post', 'mtable'=>'teamworkforum_posts', 'field'=>'subject'),
    array('module'=>'teamworkforum', 'action'=>'user report', 'mtable'=>'user', 'field'=>$DB->sql_concat('firstname', "' '" , 'lastname')),
    array('module'=>'teamworkforum', 'action'=>'move discussion', 'mtable'=>'teamworkforum_discussions', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'view subscribers', 'mtable'=>'teamworkforum', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'view discussion', 'mtable'=>'teamworkforum_discussions', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'view teamworkforum', 'mtable'=>'teamworkforum', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'subscribe', 'mtable'=>'teamworkforum', 'field'=>'name'),
    array('module'=>'teamworkforum', 'action'=>'unsubscribe', 'mtable'=>'teamworkforum', 'field'=>'name'),
);
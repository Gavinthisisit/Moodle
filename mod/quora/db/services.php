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
 * Forum external functions and service definitions.
 *
 * @package    mod_quora
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_quora_get_quoras_by_courses' => array(
        'classname' => 'mod_quora_external',
        'methodname' => 'get_quoras_by_courses',
        'classpath' => 'mod/quora/externallib.php',
        'description' => 'Returns a list of quora instances in a provided set of courses, if
            no courses are provided then all the quora instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/quora:viewdiscussion'
    ),

    'mod_quora_get_quora_discussions' => array(
        'classname' => 'mod_quora_external',
        'methodname' => 'get_quora_discussions',
        'classpath' => 'mod/quora/externallib.php',
        'description' => 'DEPRECATED (use mod_quora_get_quora_discussions_paginated instead):
                            Returns a list of quora discussions contained within a given set of quoras.',
        'type' => 'read',
        'capabilities' => 'mod/quora:viewdiscussion, mod/quora:viewqandawithoutposting'
    ),

    'mod_quora_get_quora_discussion_posts' => array(
        'classname' => 'mod_quora_external',
        'methodname' => 'get_quora_discussion_posts',
        'classpath' => 'mod/quora/externallib.php',
        'description' => 'Returns a list of quora posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/quora:viewdiscussion, mod/quora:viewqandawithoutposting'
    ),

    'mod_quora_get_quora_discussions_paginated' => array(
        'classname' => 'mod_quora_external',
        'methodname' => 'get_quora_discussions_paginated',
        'classpath' => 'mod/quora/externallib.php',
        'description' => 'Returns a list of quora discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/quora:viewdiscussion, mod/quora:viewqandawithoutposting'
    ),

    'mod_quora_view_quora' => array(
        'classname' => 'mod_quora_external',
        'methodname' => 'view_quora',
        'classpath' => 'mod/quora/externallib.php',
        'description' => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type' => 'write',
        'capabilities' => 'mod/quora:viewdiscussion'
    ),

    'mod_quora_view_quora_discussion' => array(
        'classname' => 'mod_quora_external',
        'methodname' => 'view_quora_discussion',
        'classpath' => 'mod/quora/externallib.php',
        'description' => 'Simulate the quora/discuss.php web interface page: trigger events, completion, etc...',
        'type' => 'write',
        'capabilities' => 'mod/quora:viewdiscussion'
    ),
);

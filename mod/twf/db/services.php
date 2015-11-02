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
 * @package    mod_twf
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_twf_get_twfs_by_courses' => array(
        'classname' => 'mod_twf_external',
        'methodname' => 'get_twfs_by_courses',
        'classpath' => 'mod/twf/externallib.php',
        'description' => 'Returns a list of twf instances in a provided set of courses, if
            no courses are provided then all the twf instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/twf:viewdiscussion'
    ),

    'mod_twf_get_twf_discussions' => array(
        'classname' => 'mod_twf_external',
        'methodname' => 'get_twf_discussions',
        'classpath' => 'mod/twf/externallib.php',
        'description' => 'DEPRECATED (use mod_twf_get_twf_discussions_paginated instead):
                            Returns a list of twf discussions contained within a given set of twfs.',
        'type' => 'read',
        'capabilities' => 'mod/twf:viewdiscussion, mod/twf:viewqandawithoutposting'
    ),

    'mod_twf_get_twf_discussion_posts' => array(
        'classname' => 'mod_twf_external',
        'methodname' => 'get_twf_discussion_posts',
        'classpath' => 'mod/twf/externallib.php',
        'description' => 'Returns a list of twf posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/twf:viewdiscussion, mod/twf:viewqandawithoutposting'
    ),

    'mod_twf_get_twf_discussions_paginated' => array(
        'classname' => 'mod_twf_external',
        'methodname' => 'get_twf_discussions_paginated',
        'classpath' => 'mod/twf/externallib.php',
        'description' => 'Returns a list of twf discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/twf:viewdiscussion, mod/twf:viewqandawithoutposting'
    ),

    'mod_twf_view_twf' => array(
        'classname' => 'mod_twf_external',
        'methodname' => 'view_twf',
        'classpath' => 'mod/twf/externallib.php',
        'description' => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type' => 'write',
        'capabilities' => 'mod/twf:viewdiscussion'
    ),

    'mod_twf_view_twf_discussion' => array(
        'classname' => 'mod_twf_external',
        'methodname' => 'view_twf_discussion',
        'classpath' => 'mod/twf/externallib.php',
        'description' => 'Simulate the twf/discuss.php web interface page: trigger events, completion, etc...',
        'type' => 'write',
        'capabilities' => 'mod/twf:viewdiscussion'
    ),
);

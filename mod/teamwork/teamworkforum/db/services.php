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
 * @package    mod_teamworkforum
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_teamworkforum_get_teamworkforums_by_courses' => array(
        'classname' => 'mod_teamworkforum_external',
        'methodname' => 'get_teamworkforums_by_courses',
        'classpath' => 'mod/teamworkforum/externallib.php',
        'description' => 'Returns a list of teamworkforum instances in a provided set of courses, if
            no courses are provided then all the teamworkforum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/teamworkforum:viewdiscussion'
    ),

    'mod_teamworkforum_get_teamworkforum_discussions' => array(
        'classname' => 'mod_teamworkforum_external',
        'methodname' => 'get_teamworkforum_discussions',
        'classpath' => 'mod/teamworkforum/externallib.php',
        'description' => 'DEPRECATED (use mod_teamworkforum_get_teamworkforum_discussions_paginated instead):
                            Returns a list of teamworkforum discussions contained within a given set of teamworkforums.',
        'type' => 'read',
        'capabilities' => 'mod/teamworkforum:viewdiscussion, mod/teamworkforum:viewqandawithoutposting'
    ),

    'mod_teamworkforum_get_teamworkforum_discussion_posts' => array(
        'classname' => 'mod_teamworkforum_external',
        'methodname' => 'get_teamworkforum_discussion_posts',
        'classpath' => 'mod/teamworkforum/externallib.php',
        'description' => 'Returns a list of teamworkforum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/teamworkforum:viewdiscussion, mod/teamworkforum:viewqandawithoutposting'
    ),

    'mod_teamworkforum_get_teamworkforum_discussions_paginated' => array(
        'classname' => 'mod_teamworkforum_external',
        'methodname' => 'get_teamworkforum_discussions_paginated',
        'classpath' => 'mod/teamworkforum/externallib.php',
        'description' => 'Returns a list of teamworkforum discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/teamworkforum:viewdiscussion, mod/teamworkforum:viewqandawithoutposting'
    ),

    'mod_teamworkforum_view_teamworkforum' => array(
        'classname' => 'mod_teamworkforum_external',
        'methodname' => 'view_teamworkforum',
        'classpath' => 'mod/teamworkforum/externallib.php',
        'description' => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type' => 'write',
        'capabilities' => 'mod/teamworkforum:viewdiscussion'
    ),

    'mod_teamworkforum_view_teamworkforum_discussion' => array(
        'classname' => 'mod_teamworkforum_external',
        'methodname' => 'view_teamworkforum_discussion',
        'classpath' => 'mod/teamworkforum/externallib.php',
        'description' => 'Simulate the teamworkforum/discuss.php web interface page: trigger events, completion, etc...',
        'type' => 'write',
        'capabilities' => 'mod/teamworkforum:viewdiscussion'
    ),
);

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
 * @package   mod_teamworkforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forum ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single teamworkforum)
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '', PARAM_CLEAN);// search string

    $params = array();
    if ($id) {
        $params['id'] = $id;
    } else {
        $params['f'] = $f;
    }
    if ($page) {
        $params['page'] = $page;
    }
    if ($search) {
        $params['search'] = $search;
    }
    $PAGE->set_url('/mod/teamwork/teamworkforum/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('teamworkforum', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $teamworkforum = $DB->get_record("teamworkforum", array("id" => $cm->instance))) {
            print_error('invalidteamworkforumid', 'teamworkforum');
        }
        if ($teamworkforum->type == 'single') {
            $PAGE->set_pagetype('mod-teamworkforum-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        //require_course_login($course, true, $cm);
        $strteamworkforums = get_string("modulenameplural", "teamworkforum");
        $strteamworkforum = get_string("modulename", "teamworkforum");
    } else if ($f) {

        if (! $teamworkforum = $DB->get_record("teamworkforum", array("id" => $f))) {
            print_error('invalidteamworkforumid', 'teamworkforum');
        }
        if (! $course = $DB->get_record("course", array("id" => $teamworkforum->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("teamworkforum", $teamworkforum->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strteamworkforums = get_string("modulenameplural", "teamworkforum");
        $strteamworkforum = get_string("modulename", "teamworkforum");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(teamworkforum_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    //$PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->teamworkforum_enablerssfeeds) && $teamworkforum->rsstype && $teamworkforum->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($teamworkforum->name);
        rss_add_http_header($context, 'mod_teamworkforum', $teamworkforum, $rsstitle);
    }

/// Print header.

    $PAGE->set_title($teamworkforum->name);
    $PAGE->add_body_class('teamworkforumtype-'.$teamworkforum->type);
    $PAGE->set_heading($course->fullname);

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/teamworkforum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'teamworkforum'));
    }

    // Mark viewed and trigger the course_module_viewed event.
    teamworkforum_view($teamworkforum, $course, $cm, $context);

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($teamworkforum->name), 2);
    if (!empty($teamworkforum->intro) && $teamworkforum->type != 'single' && $teamworkforum->type != 'teacher') {
        //echo $OUTPUT->box(format_module_intro('teamworkforum', $teamworkforum, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/teamworkforum/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion teamworkforum, we need to print the display
    // mode control.
    if ($teamworkforum->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('teamworkforum_discussions', array('teamworkforum'=>$teamworkforum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("teamworkforum_displaymode", $mode);
            }
            $displaymode = get_user_preferences("teamworkforum_displaymode", $CFG->teamworkforum_displaymode);
            teamworkforum_print_mode_form($teamworkforum->id, $displaymode, $teamworkforum->type);
        }
    }

    if (!empty($teamworkforum->blockafter) && !empty($teamworkforum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $teamworkforum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$teamworkforum->blockperiod);
        echo $OUTPUT->notification(get_string('thisteamworkforumisthrottled', 'teamworkforum', $a));
    }

    if ($teamworkforum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','teamworkforum'));
    }

    switch ($teamworkforum->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'teamworkforum'));
            }
            if (! $post = teamworkforum_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'teamworkforum');
            }
            if ($mode) {
                set_user_preference("teamworkforum_displaymode", $mode);
            }

            $canreply    = teamworkforum_user_can_post($teamworkforum, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/teamworkforum:rate', $context);
            $displaymode = get_user_preferences("teamworkforum_displaymode", $CFG->teamworkforum_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            teamworkforum_print_discussion($course, $cm, $teamworkforum, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (teamworkforum_user_can_post_discussion($teamworkforum, null, -1, $cm)) {
                print_string("allowsdiscussions", "teamworkforum");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                teamworkforum_print_latest_discussions($course, $teamworkforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                teamworkforum_print_latest_discussions($course, $teamworkforum, -1, 'header', '', -1, -1, $page, $CFG->teamworkforum_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                teamworkforum_print_latest_discussions($course, $teamworkforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                teamworkforum_print_latest_discussions($course, $teamworkforum, -1, 'header', '', -1, -1, $page, $CFG->teamworkforum_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                teamworkforum_print_latest_discussions($course, $teamworkforum, 0, 'plain', '', -1, -1, -1, 0, $cm);
            } else {
                teamworkforum_print_latest_discussions($course, $teamworkforum, -1, 'plain', '', -1, -1, $page, $CFG->teamworkforum_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                teamworkforum_print_latest_discussions($course, $teamworkforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                teamworkforum_print_latest_discussions($course, $teamworkforum, -1, 'header', '', -1, -1, $page, $CFG->teamworkforum_manydiscussions, $cm);
            }


            break;
    }

    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_teamworkforum-subscriptiontoggle', 'Y.M.mod_teamworkforum.subscriptiontoggle.init');

    echo $OUTPUT->footer($course);

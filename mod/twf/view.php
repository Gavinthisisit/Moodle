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
 * @package   mod_twf
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forum ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single twf)
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
    $PAGE->set_url('/mod/twf/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('twf', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $twf = $DB->get_record("twf", array("id" => $cm->instance))) {
            print_error('invalidtwfid', 'twf');
        }
        if ($twf->type == 'single') {
            $PAGE->set_pagetype('mod-twf-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strtwfs = get_string("modulenameplural", "twf");
        $strtwf = get_string("modulename", "twf");
    } else if ($f) {

        if (! $twf = $DB->get_record("twf", array("id" => $f))) {
            print_error('invalidtwfid', 'twf');
        }
        if (! $course = $DB->get_record("course", array("id" => $twf->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("twf", $twf->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strtwfs = get_string("modulenameplural", "twf");
        $strtwf = get_string("modulename", "twf");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(twf_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->twf_enablerssfeeds) && $twf->rsstype && $twf->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($twf->name);
        rss_add_http_header($context, 'mod_twf', $twf, $rsstitle);
    }

/// Print header.

    $PAGE->set_title($twf->name);
    $PAGE->add_body_class('twftype-'.$twf->type);
    $PAGE->set_heading($course->fullname);

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/twf:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'twf'));
    }

    // Mark viewed and trigger the course_module_viewed event.
    twf_view($twf, $course, $cm, $context);

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($twf->name), 2);
    if (!empty($twf->intro) && $twf->type != 'single' && $twf->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('twf', $twf, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/twf/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc

/// Print settings and things across the top

    // If it's a simple single discussion twf, we need to print the display
    // mode control.
    if ($twf->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('twf_discussions', array('twf'=>$twf->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("twf_displaymode", $mode);
            }
            $displaymode = get_user_preferences("twf_displaymode", $CFG->twf_displaymode);
            twf_print_mode_form($twf->id, $displaymode, $twf->type);
        }
    }

    if (!empty($twf->blockafter) && !empty($twf->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $twf->blockafter;
        $a->blockperiod = get_string('secondstotime'.$twf->blockperiod);
        echo $OUTPUT->notification(get_string('thistwfisthrottled', 'twf', $a));
    }

    if ($twf->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','twf'));
    }
    echo "该课程模块暂不可见";
/*
    switch ($twf->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'twf'));
            }
            if (! $post = twf_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'twf');
            }
            if ($mode) {
                set_user_preference("twf_displaymode", $mode);
            }

            $canreply    = twf_user_can_post($twf, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/twf:rate', $context);
            $displaymode = get_user_preferences("twf_displaymode", $CFG->twf_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            twf_print_discussion($course, $cm, $twf, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (twf_user_can_post_discussion($twf, null, -1, $cm)) {
                print_string("allowsdiscussions", "twf");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                twf_print_latest_discussions($course, $twf, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                twf_print_latest_discussions($course, $twf, -1, 'header', '', -1, -1, $page, $CFG->twf_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                twf_print_latest_discussions($course, $twf, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                twf_print_latest_discussions($course, $twf, -1, 'header', '', -1, -1, $page, $CFG->twf_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                twf_print_latest_discussions($course, $twf, 0, 'plain', '', -1, -1, -1, 0, $cm);
            } else {
                twf_print_latest_discussions($course, $twf, -1, 'plain', '', -1, -1, $page, $CFG->twf_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                twf_print_latest_discussions($course, $twf, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                twf_print_latest_discussions($course, $twf, -1, 'header', '', -1, -1, $page, $CFG->twf_manydiscussions, $cm);
            }


            break;
    }
*/
    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_twf-subscriptiontoggle', 'Y.M.mod_twf.subscriptiontoggle.init');

    echo $OUTPUT->footer($course);

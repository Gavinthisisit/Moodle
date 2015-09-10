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
 * @package   mod_quora
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forum ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single quora)
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
    $PAGE->set_url('/mod/quora/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('quora', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $quora = $DB->get_record("quora", array("id" => $cm->instance))) {
            print_error('invalidquoraid', 'quora');
        }
        if ($quora->type == 'single') {
            $PAGE->set_pagetype('mod-quora-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strquoras = get_string("modulenameplural", "quora");
        $strquora = get_string("modulename", "quora");
    } else if ($f) {

        if (! $quora = $DB->get_record("quora", array("id" => $f))) {
            print_error('invalidquoraid', 'quora');
        }
        if (! $course = $DB->get_record("course", array("id" => $quora->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("quora", $quora->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strquoras = get_string("modulenameplural", "quora");
        $strquora = get_string("modulename", "quora");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(quora_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->quora_enablerssfeeds) && $quora->rsstype && $quora->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($quora->name);
        rss_add_http_header($context, 'mod_quora', $quora, $rsstitle);
    }

/// Print header.

    $PAGE->set_title($quora->name);
    $PAGE->add_body_class('quoratype-'.$quora->type);
    $PAGE->set_heading($course->fullname);

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/quora:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'quora'));
    }

    // Mark viewed and trigger the course_module_viewed event.
    quora_view($quora, $course, $cm, $context);

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($quora->name), 2);
    if (!empty($quora->intro) && $quora->type != 'single' && $quora->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('quora', $quora, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/quora/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion quora, we need to print the display
    // mode control.
    if ($quora->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('quora_discussions', array('quora'=>$quora->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("quora_displaymode", $mode);
            }
            $displaymode = get_user_preferences("quora_displaymode", $CFG->quora_displaymode);
            quora_print_mode_form($quora->id, $displaymode, $quora->type);
        }
    }

    if (!empty($quora->blockafter) && !empty($quora->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $quora->blockafter;
        $a->blockperiod = get_string('secondstotime'.$quora->blockperiod);
        echo $OUTPUT->notification(get_string('thisquoraisthrottled', 'quora', $a));
    }

    if ($quora->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','quora'));
    }

    switch ($quora->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'quora'));
            }
            if (! $post = quora_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'quora');
            }
            if ($mode) {
                set_user_preference("quora_displaymode", $mode);
            }

            $canreply    = quora_user_can_post($quora, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/quora:rate', $context);
            $displaymode = get_user_preferences("quora_displaymode", $CFG->quora_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            quora_print_discussion($course, $cm, $quora, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (quora_user_can_post_discussion($quora, null, -1, $cm)) {
                print_string("allowsdiscussions", "quora");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                quora_print_latest_discussions($course, $quora, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                quora_print_latest_discussions($course, $quora, -1, 'header', '', -1, -1, $page, $CFG->quora_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                quora_print_latest_discussions($course, $quora, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                quora_print_latest_discussions($course, $quora, -1, 'header', '', -1, -1, $page, $CFG->quora_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                quora_print_latest_discussions($course, $quora, 0, 'plain', '', -1, -1, -1, 0, $cm);
            } else {
                quora_print_latest_discussions($course, $quora, -1, 'plain', '', -1, -1, $page, $CFG->quora_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                quora_print_latest_discussions($course, $quora, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                quora_print_latest_discussions($course, $quora, -1, 'header', '', -1, -1, $page, $CFG->quora_manydiscussions, $cm);
            }


            break;
    }

    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_quora-subscriptiontoggle', 'Y.M.mod_quora.subscriptiontoggle.init');

    echo $OUTPUT->footer($course);

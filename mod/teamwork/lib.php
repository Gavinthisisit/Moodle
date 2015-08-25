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
 * Library of teamwork module functions needed by Moodle core and other subsystems
 *
 * All the functions neeeded by Moodle core, gradebook, file subsystem etc
 * are placed here.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information if the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function teamwork_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:   return true;
        case FEATURE_GROUPS:            return true;
        case FEATURE_GROUPINGS:         return true;
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_BACKUP_MOODLE2:    return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:  return true;
        case FEATURE_PLAGIARISM:        return true;
        default:                        return null;
    }
}

/**
 * Saves a new instance of the teamwork into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will save a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $teamwork An object from the form in mod_form.php
 * @return int The id of the newly inserted teamwork record
 */
function teamwork_add_instance(stdclass $teamwork) {
    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $teamwork->phase                 = teamwork::PHASE_SETUP;
    $teamwork->timecreated           = time();
    $teamwork->timemodified          = $teamwork->timecreated;
    $teamwork->useexamples           = (int)!empty($teamwork->useexamples);
    $teamwork->usepeerassessment     = 1;
    $teamwork->useselfassessment     = (int)!empty($teamwork->useselfassessment);
    $teamwork->latesubmissions       = (int)!empty($teamwork->latesubmissions);
    $teamwork->phaseswitchassessment = (int)!empty($teamwork->phaseswitchassessment);
    $teamwork->evaluation            = 'best';

    // insert the new record so we get the id
    $teamwork->id = $DB->insert_record('teamwork', $teamwork);

    // we need to use context now, so we need to make sure all needed info is already in db
    $cmid = $teamwork->coursemodule;
    $DB->set_field('course_modules', 'instance', $teamwork->id, array('id' => $cmid));
    $context = context_module::instance($cmid);

    // process the custom wysiwyg editors
    if ($draftitemid = $teamwork->instructauthorseditor['itemid']) {
        $teamwork->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_teamwork', 'instructauthors',
                0, teamwork::instruction_editors_options($context), $teamwork->instructauthorseditor['text']);
        $teamwork->instructauthorsformat = $teamwork->instructauthorseditor['format'];
    }

    if ($draftitemid = $teamwork->instructreviewerseditor['itemid']) {
        $teamwork->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_teamwork', 'instructreviewers',
                0, teamwork::instruction_editors_options($context), $teamwork->instructreviewerseditor['text']);
        $teamwork->instructreviewersformat = $teamwork->instructreviewerseditor['format'];
    }

    if ($draftitemid = $teamwork->conclusioneditor['itemid']) {
        $teamwork->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_teamwork', 'conclusion',
                0, teamwork::instruction_editors_options($context), $teamwork->conclusioneditor['text']);
        $teamwork->conclusionformat = $teamwork->conclusioneditor['format'];
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('teamwork', $teamwork);

    // create gradebook items
    teamwork_grade_item_update($teamwork);
    teamwork_grade_item_category_update($teamwork);

    // create calendar events
    teamwork_calendar_update($teamwork, $teamwork->coursemodule);

    return $teamwork->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $teamwork An object from the form in mod_form.php
 * @return bool success
 */
function teamwork_update_instance(stdclass $teamwork) {
    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $teamwork->timemodified          = time();
    $teamwork->id                    = $teamwork->instance;
    $teamwork->useexamples           = (int)!empty($teamwork->useexamples);
    $teamwork->usepeerassessment     = 1;
    $teamwork->useselfassessment     = (int)!empty($teamwork->useselfassessment);
    $teamwork->latesubmissions       = (int)!empty($teamwork->latesubmissions);
    $teamwork->phaseswitchassessment = (int)!empty($teamwork->phaseswitchassessment);

    // todo - if the grading strategy is being changed, we may want to replace all aggregated peer grades with nulls

    $DB->update_record('teamwork', $teamwork);
    $context = context_module::instance($teamwork->coursemodule);

    // process the custom wysiwyg editors
    if ($draftitemid = $teamwork->instructauthorseditor['itemid']) {
        $teamwork->instructauthors = file_save_draft_area_files($draftitemid, $context->id, 'mod_teamwork', 'instructauthors',
                0, teamwork::instruction_editors_options($context), $teamwork->instructauthorseditor['text']);
        $teamwork->instructauthorsformat = $teamwork->instructauthorseditor['format'];
    }

    if ($draftitemid = $teamwork->instructreviewerseditor['itemid']) {
        $teamwork->instructreviewers = file_save_draft_area_files($draftitemid, $context->id, 'mod_teamwork', 'instructreviewers',
                0, teamwork::instruction_editors_options($context), $teamwork->instructreviewerseditor['text']);
        $teamwork->instructreviewersformat = $teamwork->instructreviewerseditor['format'];
    }

    if ($draftitemid = $teamwork->conclusioneditor['itemid']) {
        $teamwork->conclusion = file_save_draft_area_files($draftitemid, $context->id, 'mod_teamwork', 'conclusion',
                0, teamwork::instruction_editors_options($context), $teamwork->conclusioneditor['text']);
        $teamwork->conclusionformat = $teamwork->conclusioneditor['format'];
    }

    // re-save the record with the replaced URLs in editor fields
    $DB->update_record('teamwork', $teamwork);

    // update gradebook items
    teamwork_grade_item_update($teamwork);
    teamwork_grade_item_category_update($teamwork);

    // update calendar events
    teamwork_calendar_update($teamwork, $teamwork->coursemodule);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function teamwork_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (! $teamwork = $DB->get_record('teamwork', array('id' => $id))) {
        return false;
    }

    // delete all associated aggregations
    $DB->delete_records('teamwork_aggregations', array('teamworkid' => $teamwork->id));

    // get the list of ids of all submissions
    $submissions = $DB->get_records('teamwork_submissions', array('teamworkid' => $teamwork->id), '', 'id');

    // get the list of all allocated assessments
    $assessments = $DB->get_records_list('teamwork_assessments', 'submissionid', array_keys($submissions), '', 'id');

    // delete the associated records from the teamwork core tables
    $DB->delete_records_list('teamwork_grades', 'assessmentid', array_keys($assessments));
    $DB->delete_records_list('teamwork_assessments', 'id', array_keys($assessments));
    $DB->delete_records_list('teamwork_submissions', 'id', array_keys($submissions));

    // call the static clean-up methods of all available subplugins
    $strategies = core_component::get_plugin_list('teamworkform');
    foreach ($strategies as $strategy => $path) {
        require_once($path.'/lib.php');
        $classname = 'teamwork_'.$strategy.'_strategy';
        call_user_func($classname.'::delete_instance', $teamwork->id);
    }

    $allocators = core_component::get_plugin_list('teamworkallocation');
    foreach ($allocators as $allocator => $path) {
        require_once($path.'/lib.php');
        $classname = 'teamwork_'.$allocator.'_allocator';
        call_user_func($classname.'::delete_instance', $teamwork->id);
    }

    $evaluators = core_component::get_plugin_list('teamworkeval');
    foreach ($evaluators as $evaluator => $path) {
        require_once($path.'/lib.php');
        $classname = 'teamwork_'.$evaluator.'_evaluation';
        call_user_func($classname.'::delete_instance', $teamwork->id);
    }

    // delete the calendar events
    $events = $DB->get_records('event', array('modulename' => 'teamwork', 'instance' => $teamwork->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // finally remove the teamwork record itself
    $DB->delete_records('teamwork', array('id' => $teamwork->id));

    // gradebook cleanup
    grade_update('mod/teamwork', $teamwork->course, 'mod', 'teamwork', $teamwork->id, 0, null, array('deleted' => true));
    grade_update('mod/teamwork', $teamwork->course, 'mod', 'teamwork', $teamwork->id, 1, null, array('deleted' => true));

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record.
 * @param stdClass $user The user record.
 * @param cm_info|stdClass $mod The course module info object or record.
 * @param stdClass $teamwork The teamwork instance record.
 * @return stdclass|null
 */
function teamwork_user_outline($course, $user, $mod, $teamwork) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $grades = grade_get_grades($course->id, 'mod', 'teamwork', $teamwork->id, $user->id);

    $submissiongrade = null;
    $assessmentgrade = null;

    $info = '';
    $time = 0;

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info .= get_string('submissiongrade', 'teamwork') . ': ' . $submissiongrade->str_long_grade . html_writer::empty_tag('br');
        $time = max($time, $submissiongrade->dategraded);
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info .= get_string('gradinggrade', 'teamwork') . ': ' . $assessmentgrade->str_long_grade;
        $time = max($time, $assessmentgrade->dategraded);
    }

    if (!empty($info) and !empty($time)) {
        $return = new stdclass();
        $return->time = $time;
        $return->info = $info;
        return $return;
    }

    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course The course record.
 * @param stdClass $user The user record.
 * @param cm_info|stdClass $mod The course module info object or record.
 * @param stdClass $teamwork The teamwork instance record.
 * @return string HTML
 */
function teamwork_user_complete($course, $user, $mod, $teamwork) {
    global $CFG, $DB, $OUTPUT;
    require_once(dirname(__FILE__).'/locallib.php');
    require_once($CFG->libdir.'/gradelib.php');

    $teamwork   = new teamwork($teamwork, $mod, $course);
    $grades     = grade_get_grades($course->id, 'mod', 'teamwork', $teamwork->id, $user->id);

    if (!empty($grades->items[0]->grades)) {
        $submissiongrade = reset($grades->items[0]->grades);
        $info = get_string('submissiongrade', 'teamwork') . ': ' . $submissiongrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'submissiongrade'));
    }
    if (!empty($grades->items[1]->grades)) {
        $assessmentgrade = reset($grades->items[1]->grades);
        $info = get_string('gradinggrade', 'teamwork') . ': ' . $assessmentgrade->str_long_grade;
        echo html_writer::tag('li', $info, array('class'=>'gradinggrade'));
    }

    if (has_capability('mod/teamwork:viewallsubmissions', $teamwork->context)) {
        $canviewsubmission = true;
        if (groups_get_activity_groupmode($teamwork->cm) == SEPARATEGROUPS) {
            // user must have accessallgroups or share at least one group with the submission author
            if (!has_capability('moodle/site:accessallgroups', $teamwork->context)) {
                $usersgroups = groups_get_activity_allowed_groups($teamwork->cm);
                $authorsgroups = groups_get_all_groups($teamwork->course->id, $user->id, $teamwork->cm->groupingid, 'g.id');
                $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
                if (empty($sharedgroups)) {
                    $canviewsubmission = false;
                }
            }
        }
        if ($canviewsubmission and $submission = $teamwork->get_submission_by_author($user->id)) {
            $title      = format_string($submission->title);
            $url        = $teamwork->submission_url($submission->id);
            $link       = html_writer::link($url, $title);
            $info       = get_string('submission', 'teamwork').': '.$link;
            echo html_writer::tag('li', $info, array('class'=>'submission'));
        }
    }

    if (has_capability('mod/teamwork:viewallassessments', $teamwork->context)) {
        if ($assessments = $teamwork->get_assessments_by_reviewer($user->id)) {
            foreach ($assessments as $assessment) {
                $a = new stdclass();
                $a->submissionurl = $teamwork->submission_url($assessment->submissionid)->out();
                $a->assessmenturl = $teamwork->assess_url($assessment->id)->out();
                $a->submissiontitle = s($assessment->submissiontitle);
                echo html_writer::tag('li', get_string('assessmentofsubmission', 'teamwork', $a));
            }
        }
    }
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in teamwork activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param stdClass $course
 * @param bool $viewfullnames
 * @param int $timestart
 * @return boolean
 */
function teamwork_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    $authoramefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authoramefields, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, cm.id AS cmid
              FROM {teamwork} w
        INNER JOIN {course_modules} cm ON cm.instance = w.id
        INNER JOIN {modules} md ON md.id = cm.module
        INNER JOIN {teamwork_submissions} s ON s.teamworkid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {teamwork_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
             WHERE cm.course = ?
                   AND md.name = 'teamwork'
                   AND s.example = 0
                   AND (s.timemodified > ? OR a.timemodified > ?)
          ORDER BY s.timemodified";

    $rs = $DB->get_recordset_sql($sql, array($course->id, $timestart, $timestart));

    $modinfo = get_fast_modinfo($course); // reference needed because we might load the groups

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {
        if (!array_key_exists($activity->cmid, $modinfo->cms)) {
            // this should not happen but just in case
            continue;
        }

        $cm = $modinfo->cms[$activity->cmid];
        if (!$cm->uservisible) {
            continue;
        }

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $users[$activity->authorid] = username_load_fields_from_object($u, $activity, 'author');
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $users[$activity->reviewerid] = username_load_fields_from_object($u, $activity, 'reviewer');
        }

        $context = context_module::instance($cm->id);
        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            $s->cmid = $activity->cmid;
            if ($activity->authorid == $USER->id || has_capability('mod/teamwork:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/teamwork:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            $a->cmid = $activity->cmid;
            if ($activity->reviewerid == $USER->id || has_capability('mod/teamwork:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/teamwork:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $shown = false;

    if (!empty($submissions)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentsubmissions', 'teamwork'), 3);
        foreach ($submissions as $id => $submission) {
            $link = new moodle_url('/mod/teamwork/submission.php', array('id'=>$id, 'cmid'=>$submission->cmid));
            if ($submission->authornamevisible) {
                $author = $users[$submission->authorid];
            } else {
                $author = null;
            }
            print_recent_activity_note($submission->timemodified, $author, $submission->title, $link->out(), false, $viewfullnames);
        }
    }

    if (!empty($assessments)) {
        $shown = true;
        echo $OUTPUT->heading(get_string('recentassessments', 'teamwork'), 3);
        core_collator::asort_objects_by_property($assessments, 'timemodified');
        foreach ($assessments as $id => $assessment) {
            $link = new moodle_url('/mod/teamwork/assessment.php', array('asid' => $id));
            if ($assessment->reviewernamevisible) {
                $reviewer = $users[$assessment->reviewerid];
            } else {
                $reviewer = null;
            }
            print_recent_activity_note($assessment->timemodified, $reviewer, $assessment->submissiontitle, $link->out(), false, $viewfullnames);
        }
    }

    if ($shown) {
        return true;
    }

    return false;
}

/**
 * Returns all activity in course teamworks since a given time
 *
 * @param array $activities sequentially indexed array of objects
 * @param int $index
 * @param int $timestart
 * @param int $courseid
 * @param int $cmid
 * @param int $userid defaults to 0
 * @param int $groupid defaults to 0
 * @return void adds items into $activities and increases $index
 */
function teamwork_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $params = array();
    if ($userid) {
        $userselect = "AND (author.id = :authorid OR reviewer.id = :reviewerid)";
        $params['authorid'] = $userid;
        $params['reviewerid'] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND (authorgroupmembership.groupid = :authorgroupid OR reviewergroupmembership.groupid = :reviewergroupid)";
        $groupjoin   = "LEFT JOIN {groups_members} authorgroupmembership ON authorgroupmembership.userid = author.id
                        LEFT JOIN {groups_members} reviewergroupmembership ON reviewergroupmembership.userid = reviewer.id";
        $params['authorgroupid'] = $groupid;
        $params['reviewergroupid'] = $groupid;
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }

    $params['cminstance'] = $cm->instance;
    $params['submissionmodified'] = $timestart;
    $params['assessmentmodified'] = $timestart;

    $authornamefields = get_all_user_name_fields(true, 'author', null, 'author');
    $reviewerfields = get_all_user_name_fields(true, 'reviewer', null, 'reviewer');

    $sql = "SELECT s.id AS submissionid, s.title AS submissiontitle, s.timemodified AS submissionmodified,
                   author.id AS authorid, $authornamefields, author.picture AS authorpicture, author.imagealt AS authorimagealt,
                   author.email AS authoremail, a.id AS assessmentid, a.timemodified AS assessmentmodified,
                   reviewer.id AS reviewerid, $reviewerfields, reviewer.picture AS reviewerpicture,
                   reviewer.imagealt AS reviewerimagealt, reviewer.email AS revieweremail
              FROM {teamwork_submissions} s
        INNER JOIN {teamwork} w ON s.teamworkid = w.id
        INNER JOIN {user} author ON s.authorid = author.id
         LEFT JOIN {teamwork_assessments} a ON a.submissionid = s.id
         LEFT JOIN {user} reviewer ON a.reviewerid = reviewer.id
        $groupjoin
             WHERE w.id = :cminstance
                   AND s.example = 0
                   $userselect $groupselect
                   AND (s.timemodified > :submissionmodified OR a.timemodified > :assessmentmodified)
          ORDER BY s.timemodified ASC, a.timemodified ASC";

    $rs = $DB->get_recordset_sql($sql, $params);

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $context         = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewauthors     = has_capability('mod/teamwork:viewauthornames', $context);
    $viewreviewers   = has_capability('mod/teamwork:viewreviewernames', $context);

    $submissions = array(); // recent submissions indexed by submission id
    $assessments = array(); // recent assessments indexed by assessment id
    $users       = array();

    foreach ($rs as $activity) {

        // remember all user names we can use later
        if (empty($users[$activity->authorid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'author', $additionalfields);
            $users[$activity->authorid] = $u;
        }
        if ($activity->reviewerid and empty($users[$activity->reviewerid])) {
            $u = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $u = username_load_fields_from_object($u, $activity, 'reviewer', $additionalfields);
            $users[$activity->reviewerid] = $u;
        }

        if ($activity->submissionmodified > $timestart and empty($submissions[$activity->submissionid])) {
            $s = new stdclass();
            $s->id = $activity->submissionid;
            $s->title = $activity->submissiontitle;
            $s->authorid = $activity->authorid;
            $s->timemodified = $activity->submissionmodified;
            if ($activity->authorid == $USER->id || has_capability('mod/teamwork:viewauthornames', $context)) {
                $s->authornamevisible = true;
            } else {
                $s->authornamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($s->authorid === $USER->id) {
                    // own submissions always visible
                    $submissions[$activity->submissionid] = $s;
                    break;
                }

                if (has_capability('mod/teamwork:viewallsubmissions', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $authorsgroups = groups_get_all_groups($course->id, $s->authorid, $cm->groupingid);
                        if (is_array($authorsgroups)) {
                            $authorsgroups = array_keys($authorsgroups);
                            $intersect = array_intersect($authorsgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all submissions and shares a group with the author
                                $submissions[$activity->submissionid] = $s;
                                break;
                            }
                        }

                    } else {
                        // can see all submissions from all groups
                        $submissions[$activity->submissionid] = $s;
                    }
                }
            } while (0);
        }

        if ($activity->assessmentmodified > $timestart and empty($assessments[$activity->assessmentid])) {
            $a = new stdclass();
            $a->id = $activity->assessmentid;
            $a->submissionid = $activity->submissionid;
            $a->submissiontitle = $activity->submissiontitle;
            $a->reviewerid = $activity->reviewerid;
            $a->timemodified = $activity->assessmentmodified;
            if ($activity->reviewerid == $USER->id || has_capability('mod/teamwork:viewreviewernames', $context)) {
                $a->reviewernamevisible = true;
            } else {
                $a->reviewernamevisible = false;
            }

            // the following do-while wrapper allows to break from deeply nested if-statements
            do {
                if ($a->reviewerid === $USER->id) {
                    // own assessments always visible
                    $assessments[$activity->assessmentid] = $a;
                    break;
                }

                if (has_capability('mod/teamwork:viewallassessments', $context)) {
                    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                        if (isguestuser()) {
                            // shortcut - guest user does not belong into any group
                            break;
                        }

                        // this might be slow - show only submissions by users who share group with me in this cm
                        if (!$modinfo->get_groups($cm->groupingid)) {
                            break;
                        }
                        $reviewersgroups = groups_get_all_groups($course->id, $a->reviewerid, $cm->groupingid);
                        if (is_array($reviewersgroups)) {
                            $reviewersgroups = array_keys($reviewersgroups);
                            $intersect = array_intersect($reviewersgroups, $modinfo->get_groups($cm->groupingid));
                            if (empty($intersect)) {
                                break;
                            } else {
                                // can see all assessments and shares a group with the reviewer
                                $assessments[$activity->assessmentid] = $a;
                                break;
                            }
                        }

                    } else {
                        // can see all assessments from all groups
                        $assessments[$activity->assessmentid] = $a;
                    }
                }
            } while (0);
        }
    }
    $rs->close();

    $teamworkname = format_string($cm->name, true);

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $grades = grade_get_grades($courseid, 'mod', 'teamwork', $cm->instance, array_keys($users));
    }

    foreach ($submissions as $submission) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'teamwork';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $teamworkname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $submission->timemodified;
        $tmpactivity->subtype       = 'submission';
        $tmpactivity->content       = $submission;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[0]->grades[$submission->authorid]->str_long_grade;
        }
        if ($submission->authornamevisible and !empty($users[$submission->authorid])) {
            $tmpactivity->user      = $users[$submission->authorid];
        }
        $activities[$index++]       = $tmpactivity;
    }

    foreach ($assessments as $assessment) {
        $tmpactivity                = new stdclass();
        $tmpactivity->type          = 'teamwork';
        $tmpactivity->cmid          = $cm->id;
        $tmpactivity->name          = $teamworkname;
        $tmpactivity->sectionnum    = $cm->sectionnum;
        $tmpactivity->timestamp     = $assessment->timemodified;
        $tmpactivity->subtype       = 'assessment';
        $tmpactivity->content       = $assessment;
        if ($grader) {
            $tmpactivity->grade     = $grades->items[1]->grades[$assessment->reviewerid]->str_long_grade;
        }
        if ($assessment->reviewernamevisible and !empty($users[$assessment->reviewerid])) {
            $tmpactivity->user      = $users[$assessment->reviewerid];
        }
        $activities[$index++]       = $tmpactivity;
    }
}

/**
 * Print single activity item prepared by {@see teamwork_get_recent_mod_activity()}
 */
function teamwork_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if (!empty($activity->user)) {
        echo html_writer::tag('div', $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid)),
                array('style' => 'float: left; padding: 7px;'));
    }

    if ($activity->subtype == 'submission') {
        echo html_writer::start_tag('div', array('class'=>'submission', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'teamwork'));
            $url = new moodle_url('/mod/teamwork/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('icon', $activity->type), 'class'=>'icon', 'alt'=>$name));
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/teamwork/submission.php', array('cmid'=>$activity->cmid, 'id'=>$activity->content->id));
        $name = s($activity->content->title);
        echo html_writer::tag('strong', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('submissionby', 'teamwork', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('submission', 'teamwork');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    if ($activity->subtype == 'assessment') {
        echo html_writer::start_tag('div', array('class'=>'assessment', 'style'=>'padding: 7px; float:left;'));

        if ($detail) {
            echo html_writer::start_tag('h4', array('class'=>'teamwork'));
            $url = new moodle_url('/mod/teamwork/view.php', array('id'=>$activity->cmid));
            $name = s($activity->name);
            echo html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('icon', $activity->type), 'class'=>'icon', 'alt'=>$name));
            echo ' ' . $modnames[$activity->type];
            echo html_writer::link($url, $name, array('class'=>'name', 'style'=>'margin-left: 5px'));
            echo html_writer::end_tag('h4');
        }

        echo html_writer::start_tag('div', array('class'=>'title'));
        $url = new moodle_url('/mod/teamwork/assessment.php', array('asid'=>$activity->content->id));
        $name = s($activity->content->submissiontitle);
        echo html_writer::tag('em', html_writer::link($url, $name));
        echo html_writer::end_tag('div');

        if (!empty($activity->user)) {
            echo html_writer::start_tag('div', array('class'=>'user'));
            $url = new moodle_url('/user/view.php', array('id'=>$activity->user->id, 'course'=>$courseid));
            $name = fullname($activity->user);
            $link = html_writer::link($url, $name);
            echo get_string('assessmentbyfullname', 'teamwork', $link);
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::start_tag('div', array('class'=>'anonymous'));
            echo get_string('assessment', 'teamwork');
            echo ' - '.userdate($activity->timestamp);
            echo html_writer::end_tag('div');
        }

        echo html_writer::end_tag('div');
    }

    echo html_writer::empty_tag('br', array('style'=>'clear:both'));
}

/**
 * Regular jobs to execute via cron
 *
 * @return boolean true on success, false otherwise
 */
function teamwork_cron() {
    global $CFG, $DB;

    $now = time();

    mtrace(' processing teamwork subplugins ...');
    cron_execute_plugin_type('teamworkallocation', 'teamwork allocation methods');

    // now when the scheduled allocator had a chance to do its job, check if there
    // are some teamworks to switch into the assessment phase
    $teamworks = $DB->get_records_select("teamwork",
        "phase = 20 AND phaseswitchassessment = 1 AND submissionend > 0 AND submissionend < ?", array($now));

    if (!empty($teamworks)) {
        mtrace('Processing automatic assessment phase switch in '.count($teamworks).' teamwork(s) ... ', '');
        require_once($CFG->dirroot.'/mod/teamwork/locallib.php');
        foreach ($teamworks as $teamwork) {
            $cm = get_coursemodule_from_instance('teamwork', $teamwork->id, $teamwork->course, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $teamwork = new teamwork($teamwork, $cm, $course);
            $teamwork->switch_phase(teamwork::PHASE_ASSESSMENT);

            $params = array(
                'objectid' => $teamwork->id,
                'context' => $teamwork->context,
                'courseid' => $teamwork->course->id,
                'other' => array(
                    'teamworkphase' => $teamwork->phase
                )
            );
            $event = \mod_teamwork\event\phase_switched::create($params);
            $event->trigger();

            // disable the automatic switching now so that it is not executed again by accident
            // if the teacher changes the phase back to the submission one
            $DB->set_field('teamwork', 'phaseswitchassessment', 0, array('id' => $teamwork->id));

            // todo inform the teachers
        }
        mtrace('done');
    }

    return true;
}

/**
 * Is a given scale used by the instance of teamwork?
 *
 * The function asks all installed grading strategy subplugins. The teamwork
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $teamworkid id of teamwork instance
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function teamwork_scale_used($teamworkid, $scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('teamworkform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'teamwork_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func_array(array($classname, 'scale_used'), array($scaleid, $teamworkid))) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Is a given scale used by any instance of teamwork?
 *
 * The function asks all installed grading strategy subplugins. The teamwork
 * core itself does not use scales. Both grade for submission and grade for
 * assessments do not use scales.
 *
 * @param int $scaleid id of the scale to check
 * @return bool
 */
function teamwork_scale_used_anywhere($scaleid) {
    global $CFG; // other files included from here

    $strategies = core_component::get_plugin_list('teamworkform');
    foreach ($strategies as $strategy => $strategypath) {
        $strategylib = $strategypath . '/lib.php';
        if (is_readable($strategylib)) {
            require_once($strategylib);
        } else {
            throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
        }
        $classname = 'teamwork_' . $strategy . '_strategy';
        if (method_exists($classname, 'scale_used')) {
            if (call_user_func(array($classname, 'scale_used'), $scaleid)) {
                // no need to include any other files - scale is used
                return true;
            }
        }
    }

    return false;
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function teamwork_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Creates or updates grade items for the give teamwork instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php. Also used by
 * {@link teamwork_update_grades()}.
 *
 * @param stdClass $teamwork instance object with extra cmidnumber property
 * @param stdClass $submissiongrades data for the first grade item
 * @param stdClass $assessmentgrades data for the second grade item
 * @return void
 */
function teamwork_grade_item_update(stdclass $teamwork, $submissiongrades=null, $assessmentgrades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $a = new stdclass();
    $a->teamworkname = clean_param($teamwork->name, PARAM_NOTAGS);

    $item = array();
    $item['itemname'] = get_string('gradeitemsubmission', 'teamwork', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $teamwork->grade;
    $item['grademin']  = 0;
    grade_update('mod/teamwork', $teamwork->course, 'mod', 'teamwork', $teamwork->id, 0, $submissiongrades , $item);

    $item = array();
    $item['itemname'] = get_string('gradeitemassessment', 'teamwork', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $teamwork->gradinggrade;
    $item['grademin']  = 0;
    grade_update('mod/teamwork', $teamwork->course, 'mod', 'teamwork', $teamwork->id, 1, $assessmentgrades, $item);
}

/**
 * Update teamwork grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @category grade
 * @param stdClass $teamwork instance object with extra cmidnumber and modname property
 * @param int $userid        update grade of specific user only, 0 means all participants
 * @return void
 */
function teamwork_update_grades(stdclass $teamwork, $userid=0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $whereuser = $userid ? ' AND authorid = :userid' : '';
    $params = array('teamworkid' => $teamwork->id, 'userid' => $userid);
    $sql = 'SELECT authorid, grade, gradeover, gradeoverby, feedbackauthor, feedbackauthorformat, timemodified, timegraded
              FROM {teamwork_submissions}
             WHERE teamworkid = :teamworkid AND example=0' . $whereuser;
    $records = $DB->get_records_sql($sql, $params);
    $submissiongrades = array();
    foreach ($records as $record) {
        $grade = new stdclass();
        $grade->userid = $record->authorid;
        if (!is_null($record->gradeover)) {
            $grade->rawgrade = grade_floatval($teamwork->grade * $record->gradeover / 100);
            $grade->usermodified = $record->gradeoverby;
        } else {
            $grade->rawgrade = grade_floatval($teamwork->grade * $record->grade / 100);
        }
        $grade->feedback = $record->feedbackauthor;
        $grade->feedbackformat = $record->feedbackauthorformat;
        $grade->datesubmitted = $record->timemodified;
        $grade->dategraded = $record->timegraded;
        $submissiongrades[$record->authorid] = $grade;
    }

    $whereuser = $userid ? ' AND userid = :userid' : '';
    $params = array('teamworkid' => $teamwork->id, 'userid' => $userid);
    $sql = 'SELECT userid, gradinggrade, timegraded
              FROM {teamwork_aggregations}
             WHERE teamworkid = :teamworkid' . $whereuser;
    $records = $DB->get_records_sql($sql, $params);
    $assessmentgrades = array();
    foreach ($records as $record) {
        $grade = new stdclass();
        $grade->userid = $record->userid;
        $grade->rawgrade = grade_floatval($teamwork->gradinggrade * $record->gradinggrade / 100);
        $grade->dategraded = $record->timegraded;
        $assessmentgrades[$record->userid] = $grade;
    }

    teamwork_grade_item_update($teamwork, $submissiongrades, $assessmentgrades);
}

/**
 * Update the grade items categories if they are changed via mod_form.php
 *
 * We must do it manually here in the teamwork module because modedit supports only
 * single grade item while we use two.
 *
 * @param stdClass $teamwork An object from the form in mod_form.php
 */
function teamwork_grade_item_category_update($teamwork) {

    $gradeitems = grade_item::fetch_all(array(
        'itemtype'      => 'mod',
        'itemmodule'    => 'teamwork',
        'iteminstance'  => $teamwork->id,
        'courseid'      => $teamwork->course));

    if (!empty($gradeitems)) {
        foreach ($gradeitems as $gradeitem) {
            if ($gradeitem->itemnumber == 0) {
                if ($gradeitem->categoryid != $teamwork->gradecategory) {
                    $gradeitem->set_parent($teamwork->gradecategory);
                }
            } else if ($gradeitem->itemnumber == 1) {
                if ($gradeitem->categoryid != $teamwork->gradinggradecategory) {
                    $gradeitem->set_parent($teamwork->gradinggradecategory);
                }
            }
            if (!empty($teamwork->add)) {
                $gradecategory = $gradeitem->get_parent_category();
                if (grade_category::aggregation_uses_aggregationcoef($gradecategory->aggregation)) {
                    if ($gradecategory->aggregation == GRADE_AGGREGATE_WEIGHTED_MEAN) {
                        $gradeitem->aggregationcoef = 1;
                    } else {
                        $gradeitem->aggregationcoef = 0;
                    }
                    $gradeitem->update();
                }
            }
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area teamwork_intro for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @package  mod_teamwork
 * @category files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function teamwork_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['instructauthors']          = get_string('areainstructauthors', 'teamwork');
    $areas['instructreviewers']        = get_string('areainstructreviewers', 'teamwork');
    $areas['submission_content']       = get_string('areasubmissioncontent', 'teamwork');
    $areas['submission_attachment']    = get_string('areasubmissionattachment', 'teamwork');
    $areas['conclusion']               = get_string('areaconclusion', 'teamwork');
    $areas['overallfeedback_content']  = get_string('areaoverallfeedbackcontent', 'teamwork');
    $areas['overallfeedback_attachment'] = get_string('areaoverallfeedbackattachment', 'teamwork');

    return $areas;
}

/**
 * Serves the files from the teamwork file areas
 *
 * Apart from module intro (handled by pluginfile.php automatically), teamwork files may be
 * media inserted into submission content (like images) and submission attachments. For these two,
 * the fileareas submission_content and submission_attachment are used.
 * Besides that, areas instructauthors, instructreviewers and conclusion contain the media
 * embedded using the mod_form.php.
 *
 * @package  mod_teamwork
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the teamwork's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function teamwork_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea === 'instructauthors') {
        array_shift($args); // itemid is ignored here
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_teamwork/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'instructreviewers') {
        array_shift($args); // itemid is ignored here
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_teamwork/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'conclusion') {
        array_shift($args); // itemid is ignored here
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_teamwork/$filearea/0/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);

    } else if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {
        $itemid = (int)array_shift($args);
        if (!$teamwork = $DB->get_record('teamwork', array('id' => $cm->instance))) {
            return false;
        }
        if (!$submission = $DB->get_record('teamwork_submissions', array('id' => $itemid, 'teamworkid' => $teamwork->id))) {
            return false;
        }

        // make sure the user is allowed to see the file
        if (empty($submission->example)) {
            if ($USER->id != $submission->authorid) {
                if ($submission->published == 1 and $teamwork->phase == 50
                        and has_capability('mod/teamwork:viewpublishedsubmissions', $context)) {
                    // Published submission, we can go (teamwork does not take the group mode
                    // into account in this case yet).
                } else if (!$DB->record_exists('teamwork_assessments', array('submissionid' => $submission->id, 'reviewerid' => $USER->id))) {
                    if (!has_capability('mod/teamwork:viewallsubmissions', $context)) {
                        send_file_not_found();
                    } else {
                        $gmode = groups_get_activity_groupmode($cm, $course);
                        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                            // check there is at least one common group with both the $USER
                            // and the submission author
                            $sql = "SELECT 'x'
                                      FROM {teamwork_submissions} s
                                      JOIN {user} a ON (a.id = s.authorid)
                                      JOIN {groups_members} agm ON (a.id = agm.userid)
                                      JOIN {user} u ON (u.id = ?)
                                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                                     WHERE s.example = 0 AND s.teamworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                            $params = array($USER->id, $teamwork->id, $submission->id);
                            if (!$DB->record_exists_sql($sql, $params)) {
                                send_file_not_found();
                            }
                        }
                    }
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_teamwork/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);

    } else if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {
        $itemid = (int)array_shift($args);
        if (!$teamwork = $DB->get_record('teamwork', array('id' => $cm->instance))) {
            return false;
        }
        if (!$assessment = $DB->get_record('teamwork_assessments', array('id' => $itemid))) {
            return false;
        }
        if (!$submission = $DB->get_record('teamwork_submissions', array('id' => $assessment->submissionid, 'teamworkid' => $teamwork->id))) {
            return false;
        }

        if ($USER->id == $assessment->reviewerid) {
            // Reviewers can always see their own files.
        } else if ($USER->id == $submission->authorid and $teamwork->phase == 50) {
            // Authors can see the feedback once the teamwork is closed.
        } else if (!empty($submission->example) and $assessment->weight == 1) {
            // Reference assessments of example submissions can be displayed.
        } else if (!has_capability('mod/teamwork:viewallassessments', $context)) {
            send_file_not_found();
        } else {
            $gmode = groups_get_activity_groupmode($cm, $course);
            if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                // Check there is at least one common group with both the $USER
                // and the submission author.
                $sql = "SELECT 'x'
                          FROM {teamwork_submissions} s
                          JOIN {user} a ON (a.id = s.authorid)
                          JOIN {groups_members} agm ON (a.id = agm.userid)
                          JOIN {user} u ON (u.id = ?)
                          JOIN {groups_members} ugm ON (u.id = ugm.userid)
                         WHERE s.example = 0 AND s.teamworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
                $params = array($USER->id, $teamwork->id, $submission->id);
                if (!$DB->record_exists_sql($sql, $params)) {
                    send_file_not_found();
                }
            }
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_teamwork/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);
    }

    return false;
}

/**
 * File browsing support for teamwork file areas
 *
 * @package  mod_teamwork
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function teamwork_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    /** @var array internal cache for author names */
    static $submissionauthors = array();

    $fs = get_file_storage();

    if ($filearea === 'submission_content' or $filearea === 'submission_attachment') {

        if (!has_capability('mod/teamwork:viewallsubmissions', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // no itemid (submissionid) passed, display the list of all submissions
            require_once($CFG->dirroot . '/mod/teamwork/fileinfolib.php');
            return new teamwork_file_info_submissions_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // make sure the user can see the particular submission in separate groups mode
        $gmode = groups_get_activity_groupmode($cm, $course);

        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // check there is at least one common group with both the $USER
            // and the submission author (this is not expected to be a frequent
            // usecase so we can live with pretty ineffective one query per submission here...)
            $sql = "SELECT 'x'
                      FROM {teamwork_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.teamworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // we are inside some particular submission container

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_teamwork', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_teamwork', $filearea, $itemid);
            } else {
                // not found
                return null;
            }
        }

        // Checks to see if the user can manage files or is the owner.
        // TODO MDL-33805 - Do not use userid here and move the capability check above.
        if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
            return null;
        }

        // let us display the author's name instead of itemid (submission id)

        if (isset($submissionauthors[$itemid])) {
            $topvisiblename = $submissionauthors[$itemid];

        } else {

            $sql = "SELECT s.id, u.lastname, u.firstname
                      FROM {teamwork_submissions} s
                      JOIN {user} u ON (s.authorid = u.id)
                     WHERE s.example = 0 AND s.teamworkid = ?";
            $params = array($cm->instance);
            $rs = $DB->get_recordset_sql($sql, $params);

            foreach ($rs as $submissionauthor) {
                $title = s(fullname($submissionauthor)); // this is generally not unique...
                $submissionauthors[$submissionauthor->id] = $title;
            }
            $rs->close();

            if (!isset($submissionauthors[$itemid])) {
                // should not happen
                return null;
            } else {
                $topvisiblename = $submissionauthors[$itemid];
            }
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        // do not allow manual modification of any files!
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $topvisiblename, true, true, false, false);
    }

    if ($filearea === 'overallfeedback_content' or $filearea === 'overallfeedback_attachment') {

        if (!has_capability('mod/teamwork:viewallassessments', $context)) {
            return null;
        }

        if (is_null($itemid)) {
            // No itemid (assessmentid) passed, display the list of all assessments.
            require_once($CFG->dirroot . '/mod/teamwork/fileinfolib.php');
            return new teamwork_file_info_overallfeedback_container($browser, $course, $cm, $context, $areas, $filearea);
        }

        // Make sure the user can see the particular assessment in separate groups mode.
        $gmode = groups_get_activity_groupmode($cm, $course);
        if ($gmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            // Check there is at least one common group with both the $USER
            // and the submission author.
            $sql = "SELECT 'x'
                      FROM {teamwork_submissions} s
                      JOIN {user} a ON (a.id = s.authorid)
                      JOIN {groups_members} agm ON (a.id = agm.userid)
                      JOIN {user} u ON (u.id = ?)
                      JOIN {groups_members} ugm ON (u.id = ugm.userid)
                     WHERE s.example = 0 AND s.teamworkid = ? AND s.id = ? AND agm.groupid = ugm.groupid";
            $params = array($USER->id, $cm->instance, $itemid);
            if (!$DB->record_exists_sql($sql, $params)) {
                return null;
            }
        }

        // We are inside a particular assessment container.
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_teamwork', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_teamwork', $filearea, $itemid);
            } else {
                // Not found
                return null;
            }
        }

        // Check to see if the user can manage files or is the owner.
        if (!has_capability('moodle/course:managefiles', $context) and $storedfile->get_userid() != $USER->id) {
            return null;
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';

        // Do not allow manual modification of any files.
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
    }

    if ($filearea == 'instructauthors' or $filearea == 'instructreviewers' or $filearea == 'conclusion') {
        // always only itemid 0

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_teamwork', $filearea, 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_teamwork', $filearea, 0);
            } else {
                // not found
                return null;
            }
        }
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $areas[$filearea], false, true, true, false);
    }
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding teamwork nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the teamwork module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function teamwork_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
    global $CFG;

    if (has_capability('mod/teamwork:submit', context_module::instance($cm->id))) {
        $url = new moodle_url('/mod/teamwork/submission.php', array('cmid' => $cm->id));
        $mysubmission = $navref->add(get_string('mysubmission', 'teamwork'), $url);
        $mysubmission->mainnavonly = true;
    }
}

/**
 * Extends the settings navigation with the Teamwork settings
 *
 * This function is called when the context for the page is a teamwork module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $teamworknode {@link navigation_node}
 */
function teamwork_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $teamworknode=null) {
    global $PAGE;

    //$teamworkobject = $DB->get_record("teamwork", array("id" => $PAGE->cm->instance));

    if (has_capability('mod/teamwork:editdimensions', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/teamwork/editform.php', array('cmid' => $PAGE->cm->id));
        $teamworknode->add(get_string('editassessmentform', 'teamwork'), $url, settings_navigation::TYPE_SETTING);
    }
    if (has_capability('mod/teamwork:allocate', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/teamwork/allocation.php', array('cmid' => $PAGE->cm->id));
        $teamworknode->add(get_string('allocate', 'teamwork'), $url, settings_navigation::TYPE_SETTING);
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function teamwork_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-teamwork-*'=>get_string('page-mod-teamwork-x', 'teamwork'));
    return $module_pagetype;
}

////////////////////////////////////////////////////////////////////////////////
// Calendar API                                                               //
////////////////////////////////////////////////////////////////////////////////

/**
 * Updates the calendar events associated to the given teamwork
 *
 * @param stdClass $teamwork the teamwork instance record
 * @param int $cmid course module id
 */
function teamwork_calendar_update(stdClass $teamwork, $cmid) {
    global $DB;

    // get the currently registered events so that we can re-use their ids
    $currentevents = $DB->get_records('event', array('modulename' => 'teamwork', 'instance' => $teamwork->id));

    // the common properties for all events
    $base = new stdClass();
    $base->description  = format_module_intro('teamwork', $teamwork, $cmid, false);
    $base->courseid     = $teamwork->course;
    $base->groupid      = 0;
    $base->userid       = 0;
    $base->modulename   = 'teamwork';
    $base->eventtype    = 'pluginname';
    $base->instance     = $teamwork->id;
    $base->visible      = instance_is_visible('teamwork', $teamwork);
    $base->timeduration = 0;

    if ($teamwork->submissionstart) {
        $event = clone($base);
        $event->name = get_string('submissionstartevent', 'mod_teamwork', $teamwork->name);
        $event->timestart = $teamwork->submissionstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($teamwork->submissionend) {
        $event = clone($base);
        $event->name = get_string('submissionendevent', 'mod_teamwork', $teamwork->name);
        $event->timestart = $teamwork->submissionend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($teamwork->assessmentstart) {
        $event = clone($base);
        $event->name = get_string('assessmentstartevent', 'mod_teamwork', $teamwork->name);
        $event->timestart = $teamwork->assessmentstart;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    if ($teamwork->assessmentend) {
        $event = clone($base);
        $event->name = get_string('assessmentendevent', 'mod_teamwork', $teamwork->name);
        $event->timestart = $teamwork->assessmentend;
        if ($reusedevent = array_shift($currentevents)) {
            $event->id = $reusedevent->id;
        } else {
            // should not be set but just in case
            unset($event->id);
        }
        // update() will reuse a db record if the id field is set
        $eventobj = new calendar_event($event);
        $eventobj->update($event, false);
    }

    // delete any leftover events
    foreach ($currentevents as $oldevent) {
        $oldevent = calendar_event::load($oldevent);
        $oldevent->delete();
    }
}

////////////////////////////////////////////////////////////////////////////////
// Course reset API                                                           //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the course reset form with teamwork specific settings.
 *
 * @param MoodleQuickForm $mform
 */
function teamwork_reset_course_form_definition($mform) {

    $mform->addElement('header', 'teamworkheader', get_string('modulenameplural', 'mod_teamwork'));

    $mform->addElement('advcheckbox', 'reset_teamwork_submissions', get_string('resetsubmissions', 'mod_teamwork'));
    $mform->addHelpButton('reset_teamwork_submissions', 'resetsubmissions', 'mod_teamwork');

    $mform->addElement('advcheckbox', 'reset_teamwork_assessments', get_string('resetassessments', 'mod_teamwork'));
    $mform->addHelpButton('reset_teamwork_assessments', 'resetassessments', 'mod_teamwork');
    $mform->disabledIf('reset_teamwork_assessments', 'reset_teamwork_submissions', 'checked');

    $mform->addElement('advcheckbox', 'reset_teamwork_phase', get_string('resetphase', 'mod_teamwork'));
    $mform->addHelpButton('reset_teamwork_phase', 'resetphase', 'mod_teamwork');
}

/**
 * Provides default values for the teamwork settings in the course reset form.
 *
 * @param stdClass $course The course to be reset.
 */
function teamwork_reset_course_form_defaults(stdClass $course) {

    $defaults = array(
        'reset_teamwork_submissions'    => 1,
        'reset_teamwork_assessments'    => 1,
        'reset_teamwork_phase'          => 1,
    );

    return $defaults;
}

/**
 * Performs the reset of all teamwork instances in the course.
 *
 * @param stdClass $data The actual course reset settings.
 * @return array List of results, each being array[(string)component, (string)item, (string)error]
 */
function teamwork_reset_userdata(stdClass $data) {
    global $CFG, $DB;

    if (empty($data->reset_teamwork_submissions)
            and empty($data->reset_teamwork_assessments)
            and empty($data->reset_teamwork_phase) ) {
        // Nothing to do here.
        return array();
    }

    $teamworkrecords = $DB->get_records('teamwork', array('course' => $data->courseid));

    if (empty($teamworkrecords)) {
        // What a boring course - no teamworks here!
        return array();
    }

    require_once($CFG->dirroot . '/mod/teamwork/locallib.php');

    $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);
    $status = array();

    foreach ($teamworkrecords as $teamworkrecord) {
        $cm = get_coursemodule_from_instance('teamwork', $teamworkrecord->id, $course->id, false, MUST_EXIST);
        $teamwork = new teamwork($teamworkrecord, $cm, $course);
        $status = array_merge($status, $teamwork->reset_userdata($data));
    }

    return $status;
}

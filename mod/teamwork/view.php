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
 * Prints a particular instance of teamwork
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id         = optional_param('id', 0, PARAM_INT); // course_module ID, or
$w          = optional_param('w', 0, PARAM_INT);  // teamwork instance ID
$editmode   = optional_param('editmode', null, PARAM_BOOL);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', null, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);
$eval       = optional_param('eval', null, PARAM_PLUGIN);
$templetid  = optional_param('templetid', 0, PARAM_INT);

if ($id) {
    $cm             = get_coursemodule_from_id('teamwork', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $teamworkrecord = $DB->get_record('teamwork', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $teamworkrecord = $DB->get_record('teamwork', array('id' => $w), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $teamworkrecord->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('teamwork', $teamworkrecord->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
require_capability('mod/teamwork:view', $PAGE->context);

$teamwork = new teamwork($teamworkrecord, $cm, $course);

// Mark viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$eventdata = array();
$eventdata['objectid']         = $teamwork->id;
$eventdata['context']          = $teamwork->context;

$PAGE->set_url($teamwork->view_url());
$event = \mod_teamwork\event\course_module_viewed::create($eventdata);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('teamwork', $teamworkrecord);
$event->add_record_snapshot('course_modules', $cm);
$event->trigger();

// If the phase is to be switched, do it asap. This just has to happen after triggering
// the event so that the scheduled allocator had a chance to allocate submissions.
if ($teamwork->phase == teamwork::PHASE_SUBMISSION and $teamwork->phaseswitchassessment
        and $teamwork->submissionend > 0 and $teamwork->submissionend < time()) {
    $teamwork->switch_phase(teamwork::PHASE_ASSESSMENT);
    // Disable the automatic switching now so that it is not executed again by accident
    // if the teacher changes the phase back to the submission one.
    $DB->set_field('teamwork', 'phaseswitchassessment', 0, array('id' => $teamwork->id));
    $teamwork->phaseswitchassessment = 0;
}

if (!is_null($editmode) && $PAGE->user_allowed_editing()) {
    $USER->editing = $editmode;
}

$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);

if ($perpage and $perpage > 0 and $perpage <= 1000) {
    require_sesskey();
    set_user_preference('teamwork_perpage', $perpage);
    redirect($PAGE->url);
}

if ($eval) {
    require_sesskey();
    require_capability('mod/teamwork:overridegrades', $teamwork->context);
    $teamwork->set_grading_evaluation_method($eval);
    redirect($PAGE->url);
}

$output = $PAGE->get_renderer('mod_teamwork');
$userplan = new teamwork_user_plan($teamwork, $USER->id);

/// Output starts here

echo $output->header();

//display templet list
$teammember_records = $DB->get_records('teamwork_teammembers', array('userid' => $USER->id, 'teamwork' => $teamwork->id));
$is_team_leader = false;
$leading_team = null;
foreach ($teammember_records as $recordid => $record) {
    if ($record->leader) {
        $is_team_leader = true;
        $leading_team = $record->team;
    }
}

if (has_capability('mod/teamwork:editsettings', $PAGE->context)) {
    $can_edit_templet = true;
}
else {
    $can_edit_templet = false;
}

if ($can_edit_templet) {
    $renderable = new teamwork_templet_list_manager($teamwork->id);
}
else if (count($teammember_records) >= $teamworkrecord->participationnumlimit || $is_team_leader) {
   $renderable = new teamwork_templet_list_member($teamwork->id);
}
else {
    $renderable = new teamwork_templet_list($teamwork->id); 
}
echo $output->render($renderable);

//display control buttons
$renderable = new teamwork_templet_buttons($teamwork->id, $leading_team, $can_edit_templet, $is_team_leader);
echo $output->render($renderable);

//echo $output->render($userplan);
/*switch ($teamwork->phase) {
case teamwork::PHASE_SETUP:
    if (trim($teamwork->intro)) {
        print_collapsible_region_start('', 'teamwork-viewlet-intro', get_string('introduction', 'teamwork'));
        echo $output->box(format_module_intro('teamwork', $teamwork, $teamwork->cm->id), 'generalbox');
        print_collapsible_region_end();
    }
    if ($teamwork->useexamples and has_capability('mod/teamwork:manageexamples', $PAGE->context)) {
        print_collapsible_region_start('', 'teamwork-viewlet-allexamples', get_string('examplesubmissions', 'teamwork'));
        echo $output->box_start('generalbox examples');
        if ($teamwork->grading_strategy_instance()->form_ready()) {
            if (! $examples = $teamwork->get_examples_for_manager()) {
                echo $output->container(get_string('noexamples', 'teamwork'), 'noexamples');
            }
            foreach ($examples as $example) {
                $summary = $teamwork->prepare_example_summary($example);
                $summary->editable = true;
                echo $output->render($summary);
            }
            $aurl = new moodle_url($teamwork->exsubmission_url(0), array('edit' => 'on'));
            echo $output->single_button($aurl, '', 'get');
        } else {
            echo $output->container(get_string('noexamplesformready', 'teamwork'));
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    break;
case teamwork::PHASE_SUBMISSION:
    if (trim($teamwork->instructauthors)) {
        $instructions = file_rewrite_pluginfile_urls($teamwork->instructauthors, 'pluginfile.php', $PAGE->context->id,
            'mod_teamwork', 'instructauthors', 0, teamwork::instruction_editors_options($PAGE->context));
        print_collapsible_region_start('', 'teamwork-viewlet-instructauthors', get_string('instructauthors', 'teamwork'));
        echo $output->box(format_text($instructions, $teamwork->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
        print_collapsible_region_end();
    }

    // does the user have to assess examples before submitting their own work?
    $examplesmust = ($teamwork->useexamples and $teamwork->examplesmode == teamwork::EXAMPLES_BEFORE_SUBMISSION);

    // is the assessment of example submissions considered finished?
    $examplesdone = has_capability('mod/teamwork:manageexamples', $teamwork->context);
    if ($teamwork->assessing_examples_allowed()
            and has_capability('mod/teamwork:submit', $teamwork->context)
                    and ! has_capability('mod/teamwork:manageexamples', $teamwork->context)) {
        $examples = $userplan->get_examples();
        $total = count($examples);
        $left = 0;
        // make sure the current user has all examples allocated
        foreach ($examples as $exampleid => $example) {
            if (is_null($example->assessmentid)) {
                $examples[$exampleid]->assessmentid = $teamwork->add_allocation($example, $USER->id, 0);
            }
            if (is_null($example->grade)) {
                $left++;
            }
        }
        if ($left > 0 and $teamwork->examplesmode != teamwork::EXAMPLES_VOLUNTARY) {
            $examplesdone = false;
        } else {
            $examplesdone = true;
        }
        print_collapsible_region_start('', 'teamwork-viewlet-examples', get_string('exampleassessments', 'teamwork'), false, $examplesdone);
        echo $output->box_start('generalbox exampleassessments');
        if ($total == 0) {
            echo $output->heading(get_string('noexamples', 'teamwork'), 3);
        } else {
            foreach ($examples as $example) {
                $summary = $teamwork->prepare_example_summary($example);
                echo $output->render($summary);
            }
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }

    if (has_capability('mod/teamwork:submit', $PAGE->context) and (!$examplesmust or $examplesdone)) {
        print_collapsible_region_start('', 'teamwork-viewlet-ownsubmission', get_string('yoursubmission', 'teamwork'));
        echo $output->box_start('generalbox ownsubmission');
        if ($submission = $teamwork->get_submission_by_author($USER->id)) {
            echo $output->render($teamwork->prepare_submission_summary($submission, true));
            if ($teamwork->modifying_submission_allowed($USER->id)) {
                $btnurl = new moodle_url($teamwork->submission_url(), array('edit' => 'on'));
                $btntxt = get_string('editsubmission', 'teamwork');
            }
        } else {
            echo $output->container(get_string('noyoursubmission', 'teamwork'));
            if ($teamwork->creating_submission_allowed($USER->id)) {
                $btnurl = new moodle_url($teamwork->submission_url(), array('edit' => 'on'));
                $btntxt = get_string('createsubmission', 'teamwork');
            }
        }
        if (!empty($btnurl)) {
            echo $output->single_button($btnurl, $btntxt, 'get');
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }

    if (has_capability('mod/teamwork:viewallsubmissions', $PAGE->context)) {
        $groupmode = groups_get_activity_groupmode($teamwork->cm);
        $groupid = groups_get_activity_group($teamwork->cm, true);

        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $teamwork->context)) {
            $allowedgroups = groups_get_activity_allowed_groups($teamwork->cm);
            if (empty($allowedgroups)) {
                echo $output->container(get_string('groupnoallowed', 'mod_teamwork'), 'groupwidget error');
                break;
            }
            if (! in_array($groupid, array_keys($allowedgroups))) {
                echo $output->container(get_string('groupnotamember', 'core_group'), 'groupwidget error');
                break;
            }
        }

        $countsubmissions = $teamwork->count_submissions('all', $groupid);
        $perpage = get_user_preferences('teamwork_perpage', 10);
        $pagingbar = new paging_bar($countsubmissions, $page, $perpage, $PAGE->url, 'page');

        print_collapsible_region_start('', 'teamwork-viewlet-allsubmissions', get_string('allsubmissions', 'teamwork', $countsubmissions));
        echo $output->box_start('generalbox allsubmissions');
        echo $output->container(groups_print_activity_menu($teamwork->cm, $PAGE->url, true), 'groupwidget');

        if ($countsubmissions == 0) {
            echo $output->container(get_string('nosubmissions', 'teamwork'), 'nosubmissions');

        } else {
            $submissions = $teamwork->get_submissions('all', $groupid, $page * $perpage, $perpage);
            $shownames = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
            echo $output->render($pagingbar);
            foreach ($submissions as $submission) {
                echo $output->render($teamwork->prepare_submission_summary($submission, $shownames));
            }
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
        }

        echo $output->box_end();
        print_collapsible_region_end();
    }

    break;

case teamwork::PHASE_ASSESSMENT:

    $ownsubmissionexists = null;
    if (has_capability('mod/teamwork:submit', $PAGE->context)) {
        if ($ownsubmission = $teamwork->get_submission_by_author($USER->id)) {
            print_collapsible_region_start('', 'teamwork-viewlet-ownsubmission', get_string('yoursubmission', 'teamwork'), false, true);
            echo $output->box_start('generalbox ownsubmission');
            echo $output->render($teamwork->prepare_submission_summary($ownsubmission, true));
            $ownsubmissionexists = true;
        } else {
            print_collapsible_region_start('', 'teamwork-viewlet-ownsubmission', get_string('yoursubmission', 'teamwork'));
            echo $output->box_start('generalbox ownsubmission');
            echo $output->container(get_string('noyoursubmission', 'teamwork'));
            $ownsubmissionexists = false;
            if ($teamwork->creating_submission_allowed($USER->id)) {
                $btnurl = new moodle_url($teamwork->submission_url(), array('edit' => 'on'));
                $btntxt = get_string('createsubmission', 'teamwork');
            }
        }
        if (!empty($btnurl)) {
            echo $output->single_button($btnurl, $btntxt, 'get');
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }

    if (has_capability('mod/teamwork:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('teamwork_perpage', 10);
        $groupid = groups_get_activity_group($teamwork->cm, true);
        $data = $teamwork->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        if ($data) {
            $showauthornames    = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
            $showreviewernames  = has_capability('mod/teamwork:viewreviewernames', $teamwork->context);

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = false;
            $reportopts->showgradinggrade       = false;

            print_collapsible_region_start('', 'teamwork-viewlet-gradereport', get_string('gradesreport', 'teamwork'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($teamwork->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            echo $output->render(new teamwork_grading_report($data, $reportopts));
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (trim($teamwork->instructreviewers)) {
        $instructions = file_rewrite_pluginfile_urls($teamwork->instructreviewers, 'pluginfile.php', $PAGE->context->id,
            'mod_teamwork', 'instructreviewers', 0, teamwork::instruction_editors_options($PAGE->context));
        print_collapsible_region_start('', 'teamwork-viewlet-instructreviewers', get_string('instructreviewers', 'teamwork'));
        echo $output->box(format_text($instructions, $teamwork->instructreviewersformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
        print_collapsible_region_end();
    }

    // does the user have to assess examples before assessing other's work?
    $examplesmust = ($teamwork->useexamples and $teamwork->examplesmode == teamwork::EXAMPLES_BEFORE_ASSESSMENT);

    // is the assessment of example submissions considered finished?
    $examplesdone = has_capability('mod/teamwork:manageexamples', $teamwork->context);

    // can the examples be assessed?
    $examplesavailable = true;

    if (!$examplesdone and $examplesmust and ($ownsubmissionexists === false)) {
        print_collapsible_region_start('', 'teamwork-viewlet-examplesfail', get_string('exampleassessments', 'teamwork'));
        echo $output->box(get_string('exampleneedsubmission', 'teamwork'));
        print_collapsible_region_end();
        $examplesavailable = false;
    }

    if ($teamwork->assessing_examples_allowed()
            and has_capability('mod/teamwork:submit', $teamwork->context)
                and ! has_capability('mod/teamwork:manageexamples', $teamwork->context)
                    and $examplesavailable) {
        $examples = $userplan->get_examples();
        $total = count($examples);
        $left = 0;
        // make sure the current user has all examples allocated
        foreach ($examples as $exampleid => $example) {
            if (is_null($example->assessmentid)) {
                $examples[$exampleid]->assessmentid = $teamwork->add_allocation($example, $USER->id, 0);
            }
            if (is_null($example->grade)) {
                $left++;
            }
        }
        if ($left > 0 and $teamwork->examplesmode != teamwork::EXAMPLES_VOLUNTARY) {
            $examplesdone = false;
        } else {
            $examplesdone = true;
        }
        print_collapsible_region_start('', 'teamwork-viewlet-examples', get_string('exampleassessments', 'teamwork'), false, $examplesdone);
        echo $output->box_start('generalbox exampleassessments');
        if ($total == 0) {
            echo $output->heading(get_string('noexamples', 'teamwork'), 3);
        } else {
            foreach ($examples as $example) {
                $summary = $teamwork->prepare_example_summary($example);
                echo $output->render($summary);
            }
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (!$examplesmust or $examplesdone) {
        print_collapsible_region_start('', 'teamwork-viewlet-assignedassessments', get_string('assignedassessments', 'teamwork'));
        if (! $assessments = $teamwork->get_assessments_by_reviewer($USER->id)) {
            echo $output->box_start('generalbox assessment-none');
            echo $output->notification(get_string('assignedassessmentsnone', 'teamwork'));
            echo $output->box_end();
        } else {
            $shownames = has_capability('mod/teamwork:viewauthornames', $PAGE->context);
            foreach ($assessments as $assessment) {
                $submission                     = new stdClass();
                $submission->id                 = $assessment->submissionid;
                $submission->title              = $assessment->submissiontitle;
                $submission->timecreated        = $assessment->submissioncreated;
                $submission->timemodified       = $assessment->submissionmodified;
                $userpicturefields = explode(',', user_picture::fields());
                foreach ($userpicturefields as $userpicturefield) {
                    $prefixedusernamefield = 'author' . $userpicturefield;
                    $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
                }

                // transform the submission object into renderable component
                $submission = $teamwork->prepare_submission_summary($submission, $shownames);

                if (is_null($assessment->grade)) {
                    $submission->status = 'notgraded';
                    $class = ' notgraded';
                    $buttontext = get_string('assess', 'teamwork');
                } else {
                    $submission->status = 'graded';
                    $class = ' graded';
                    $buttontext = get_string('reassess', 'teamwork');
                }

                echo $output->box_start('generalbox assessment-summary' . $class);
                echo $output->render($submission);
                $aurl = $teamwork->assess_url($assessment->id);
                echo $output->single_button($aurl, $buttontext, 'get');
                echo $output->box_end();
            }
        }
        print_collapsible_region_end();
    }
    break;
case teamwork::PHASE_EVALUATION:
    if (has_capability('mod/teamwork:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('teamwork_perpage', 10);
        $groupid = groups_get_activity_group($teamwork->cm, true);
        $data = $teamwork->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        if ($data) {
            $showauthornames    = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
            $showreviewernames  = has_capability('mod/teamwork:viewreviewernames', $teamwork->context);

            if (has_capability('mod/teamwork:overridegrades', $PAGE->context)) {
                // Print a drop-down selector to change the current evaluation method.
                $selector = new single_select($PAGE->url, 'eval', teamwork::available_evaluators_list(),
                    $teamwork->evaluation, false, 'evaluationmethodchooser');
                $selector->set_label(get_string('evaluationmethod', 'mod_teamwork'));
                $selector->set_help_icon('evaluationmethod', 'mod_teamwork');
                $selector->method = 'post';
                echo $output->render($selector);
                // load the grading evaluator
                $evaluator = $teamwork->grading_evaluation_instance();
                $form = $evaluator->get_settings_form(new moodle_url($teamwork->aggregate_url(),
                        compact('sortby', 'sorthow', 'page')));
                $form->display();
            }

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = true;
            $reportopts->showgradinggrade       = true;

            print_collapsible_region_start('', 'teamwork-viewlet-gradereport', get_string('gradesreport', 'teamwork'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($teamwork->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            echo $output->render(new teamwork_grading_report($data, $reportopts));
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (has_capability('mod/teamwork:overridegrades', $teamwork->context)) {
        print_collapsible_region_start('', 'teamwork-viewlet-cleargrades', get_string('toolbox', 'teamwork'), false, true);
        echo $output->box_start('generalbox toolbox');

        // Clear aggregated grades
        $url = new moodle_url($teamwork->toolbox_url('clearaggregatedgrades'));
        $btn = new single_button($url, get_string('clearaggregatedgrades', 'teamwork'), 'post');
        $btn->add_confirm_action(get_string('clearaggregatedgradesconfirm', 'teamwork'));
        echo $output->container_start('toolboxaction');
        echo $output->render($btn);
        echo $output->help_icon('clearaggregatedgrades', 'teamwork');
        echo $output->container_end();
        // Clear assessments
        $url = new moodle_url($teamwork->toolbox_url('clearassessments'));
        $btn = new single_button($url, get_string('clearassessments', 'teamwork'), 'post');
        $btn->add_confirm_action(get_string('clearassessmentsconfirm', 'teamwork'));
        echo $output->container_start('toolboxaction');
        echo $output->render($btn);
        echo $output->help_icon('clearassessments', 'teamwork');
        echo html_writer::empty_tag('img', array('src' => $output->pix_url('i/risk_dataloss'),
                                                 'title' => get_string('riskdatalossshort', 'admin'),
                                                 'alt' => get_string('riskdatalossshort', 'admin'),
                                                 'class' => 'teamwork-risk-dataloss'));
        echo $output->container_end();

        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (has_capability('mod/teamwork:submit', $PAGE->context)) {
        print_collapsible_region_start('', 'teamwork-viewlet-ownsubmission', get_string('yoursubmission', 'teamwork'));
        echo $output->box_start('generalbox ownsubmission');
        if ($submission = $teamwork->get_submission_by_author($USER->id)) {
            echo $output->render($teamwork->prepare_submission_summary($submission, true));
        } else {
            echo $output->container(get_string('noyoursubmission', 'teamwork'));
        }
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if ($assessments = $teamwork->get_assessments_by_reviewer($USER->id)) {
        print_collapsible_region_start('', 'teamwork-viewlet-assignedassessments', get_string('assignedassessments', 'teamwork'));
        $shownames = has_capability('mod/teamwork:viewauthornames', $PAGE->context);
        foreach ($assessments as $assessment) {
            $submission                     = new stdclass();
            $submission->id                 = $assessment->submissionid;
            $submission->title              = $assessment->submissiontitle;
            $submission->timecreated        = $assessment->submissioncreated;
            $submission->timemodified       = $assessment->submissionmodified;
            $userpicturefields = explode(',', user_picture::fields());
            foreach ($userpicturefields as $userpicturefield) {
                $prefixedusernamefield = 'author' . $userpicturefield;
                $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
            }

            if (is_null($assessment->grade)) {
                $class = ' notgraded';
                $submission->status = 'notgraded';
                $buttontext = get_string('assess', 'teamwork');
            } else {
                $class = ' graded';
                $submission->status = 'graded';
                $buttontext = get_string('reassess', 'teamwork');
            }
            echo $output->box_start('generalbox assessment-summary' . $class);
            echo $output->render($teamwork->prepare_submission_summary($submission, $shownames));
            echo $output->box_end();
        }
        print_collapsible_region_end();
    }
    break;
case teamwork::PHASE_CLOSED:
    if (trim($teamwork->conclusion)) {
        $conclusion = file_rewrite_pluginfile_urls($teamwork->conclusion, 'pluginfile.php', $teamwork->context->id,
            'mod_teamwork', 'conclusion', 0, teamwork::instruction_editors_options($teamwork->context));
        print_collapsible_region_start('', 'teamwork-viewlet-conclusion', get_string('conclusion', 'teamwork'));
        echo $output->box(format_text($conclusion, $teamwork->conclusionformat, array('overflowdiv'=>true)), array('generalbox', 'conclusion'));
        print_collapsible_region_end();
    }
    $finalgrades = $teamwork->get_gradebook_grades($USER->id);
    if (!empty($finalgrades)) {
        print_collapsible_region_start('', 'teamwork-viewlet-yourgrades', get_string('yourgrades', 'teamwork'));
        echo $output->box_start('generalbox grades-yourgrades');
        echo $output->render($finalgrades);
        echo $output->box_end();
        print_collapsible_region_end();
    }
    if (has_capability('mod/teamwork:viewallassessments', $PAGE->context)) {
        $perpage = get_user_preferences('teamwork_perpage', 10);
        $groupid = groups_get_activity_group($teamwork->cm, true);
        $data = $teamwork->prepare_grading_report_data($USER->id, $groupid, $page, $perpage, $sortby, $sorthow);
        if ($data) {
            $showauthornames    = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
            $showreviewernames  = has_capability('mod/teamwork:viewreviewernames', $teamwork->context);

            // prepare paging bar
            $baseurl = new moodle_url($PAGE->url, array('sortby' => $sortby, 'sorthow' => $sorthow));
            $pagingbar = new paging_bar($data->totalcount, $page, $perpage, $baseurl, 'page');

            // grading report display options
            $reportopts                         = new stdclass();
            $reportopts->showauthornames        = $showauthornames;
            $reportopts->showreviewernames      = $showreviewernames;
            $reportopts->sortby                 = $sortby;
            $reportopts->sorthow                = $sorthow;
            $reportopts->showsubmissiongrade    = true;
            $reportopts->showgradinggrade       = true;

            print_collapsible_region_start('', 'teamwork-viewlet-gradereport', get_string('gradesreport', 'teamwork'));
            echo $output->box_start('generalbox gradesreport');
            echo $output->container(groups_print_activity_menu($teamwork->cm, $PAGE->url, true), 'groupwidget');
            echo $output->render($pagingbar);
            echo $output->render(new teamwork_grading_report($data, $reportopts));
            echo $output->render($pagingbar);
            echo $output->perpage_selector($perpage);
            echo $output->box_end();
            print_collapsible_region_end();
        }
    }
    if (has_capability('mod/teamwork:submit', $PAGE->context)) {
        print_collapsible_region_start('', 'teamwork-viewlet-ownsubmission', get_string('yoursubmission', 'teamwork'));
        echo $output->box_start('generalbox ownsubmission');
        if ($submission = $teamwork->get_submission_by_author($USER->id)) {
            echo $output->render($teamwork->prepare_submission_summary($submission, true));
        } else {
            echo $output->container(get_string('noyoursubmission', 'teamwork'));
        }
        echo $output->box_end();

        if (!empty($submission->gradeoverby) and strlen(trim($submission->feedbackauthor)) > 0) {
            echo $output->render(new teamwork_feedback_author($submission));
        }

        print_collapsible_region_end();
    }
    if (has_capability('mod/teamwork:viewpublishedsubmissions', $teamwork->context)) {
        $shownames = has_capability('mod/teamwork:viewauthorpublished', $teamwork->context);
        if ($submissions = $teamwork->get_published_submissions()) {
            print_collapsible_region_start('', 'teamwork-viewlet-publicsubmissions', get_string('publishedsubmissions', 'teamwork'));
            foreach ($submissions as $submission) {
                echo $output->box_start('generalbox submission-summary');
                echo $output->render($teamwork->prepare_submission_summary($submission, $shownames));
                echo $output->box_end();
            }
            print_collapsible_region_end();
        }
    }
    if ($assessments = $teamwork->get_assessments_by_reviewer($USER->id)) {
        print_collapsible_region_start('', 'teamwork-viewlet-assignedassessments', get_string('assignedassessments', 'teamwork'));
        $shownames = has_capability('mod/teamwork:viewauthornames', $PAGE->context);
        foreach ($assessments as $assessment) {
            $submission                     = new stdclass();
            $submission->id                 = $assessment->submissionid;
            $submission->title              = $assessment->submissiontitle;
            $submission->timecreated        = $assessment->submissioncreated;
            $submission->timemodified       = $assessment->submissionmodified;
            $userpicturefields = explode(',', user_picture::fields());
            foreach ($userpicturefields as $userpicturefield) {
                $prefixedusernamefield = 'author' . $userpicturefield;
                $submission->$prefixedusernamefield = $assessment->$prefixedusernamefield;
            }

            if (is_null($assessment->grade)) {
                $class = ' notgraded';
                $submission->status = 'notgraded';
                $buttontext = get_string('assess', 'teamwork');
            } else {
                $class = ' graded';
                $submission->status = 'graded';
                $buttontext = get_string('reassess', 'teamwork');
            }
            echo $output->box_start('generalbox assessment-summary' . $class);
            echo $output->render($teamwork->prepare_submission_summary($submission, $shownames));
            echo $output->box_end();

            if (strlen(trim($assessment->feedbackreviewer)) > 0) {
                echo $output->render(new teamwork_feedback_reviewer($assessment));
            }
        }
        print_collapsible_region_end();
    }
    break;
default:
}*/

echo $output->footer();

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
 * View a single (usually the own) submission, submit own work.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot . '/repository/lib.php');

$cmid   = required_param('cmid', PARAM_INT);            // course module id
$id     = optional_param('id', 0, PARAM_INT);           // submission id
$edit   = optional_param('edit', false, PARAM_BOOL);    // open for editing?
$assess = optional_param('assess', false, PARAM_BOOL);  // instant assessment required

$cm     = get_coursemodule_from_id('teamwork', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}

$teamworkrecord = $DB->get_record('teamwork', array('id' => $cm->instance), '*', MUST_EXIST);
$teamwork = new teamwork($teamworkrecord, $cm, $course);

$PAGE->set_url($teamwork->submission_url(), array('cmid' => $cmid, 'id' => $id));

if ($edit) {
    $PAGE->url->param('edit', $edit);
}

if ($id) { // submission is specified
    $submission = $teamwork->get_submission_by_id($id);

    $params = array(
        'objectid' => $submission->id,
        'context' => $teamwork->context,
        'courseid' => $teamwork->course->id,
        'relateduserid' => $submission->authorid,
        'other' => array(
            'teamworkid' => $teamwork->id
        )
    );

    $event = \mod_teamwork\event\submission_viewed::create($params);
    $event->trigger();

} else { // no submission specified
    if (!$submission = $teamwork->get_submission_by_author($USER->id)) {
        $submission = new stdclass();
        $submission->id = null;
        $submission->authorid = $USER->id;
        $submission->example = 0;
        $submission->grade = null;
        $submission->gradeover = null;
        $submission->published = null;
        $submission->feedbackauthor = null;
        $submission->feedbackauthorformat = editors_get_preferred_format();
    }
}

$ownsubmission  = $submission->authorid == $USER->id;
$canviewall     = has_capability('mod/teamwork:viewallsubmissions', $teamwork->context);
$cansubmit      = has_capability('mod/teamwork:submit', $teamwork->context);
$canallocate    = has_capability('mod/teamwork:allocate', $teamwork->context);
$canpublish     = has_capability('mod/teamwork:publishsubmissions', $teamwork->context);
$canoverride    = (($teamwork->phase == teamwork::PHASE_EVALUATION) and has_capability('mod/teamwork:overridegrades', $teamwork->context));
$userassessment = $teamwork->get_assessment_of_submission_by_user($submission->id, $USER->id);
$isreviewer     = !empty($userassessment);
$editable       = ($cansubmit and $ownsubmission);
$ispublished    = ($teamwork->phase == teamwork::PHASE_CLOSED
                    and $submission->published == 1
                    and has_capability('mod/teamwork:viewpublishedsubmissions', $teamwork->context));

if (empty($submission->id) and !$teamwork->creating_submission_allowed($USER->id)) {
    $editable = false;
}
if ($submission->id and !$teamwork->modifying_submission_allowed($USER->id)) {
    $editable = false;
}

if ($canviewall) {
    // check this flag against the group membership yet
    if (groups_get_activity_groupmode($teamwork->cm) == SEPARATEGROUPS) {
        // user must have accessallgroups or share at least one group with the submission author
        if (!has_capability('moodle/site:accessallgroups', $teamwork->context)) {
            $usersgroups = groups_get_activity_allowed_groups($teamwork->cm);
            $authorsgroups = groups_get_all_groups($teamwork->course->id, $submission->authorid, $teamwork->cm->groupingid, 'g.id');
            $sharedgroups = array_intersect_key($usersgroups, $authorsgroups);
            if (empty($sharedgroups)) {
                $canviewall = false;
            }
        }
    }
}

if ($editable and $teamwork->useexamples and $teamwork->examplesmode == teamwork::EXAMPLES_BEFORE_SUBMISSION
        and !has_capability('mod/teamwork:manageexamples', $teamwork->context)) {
    // check that all required examples have been assessed by the user
    $examples = $teamwork->get_examples_for_reviewer($USER->id);
    foreach ($examples as $exampleid => $example) {
        if (is_null($example->grade)) {
            $editable = false;
            break;
        }
    }
}
$edit = ($editable and $edit);

$seenaspublished = false; // is the submission seen as a published submission?

if ($submission->id and ($ownsubmission or $canviewall or $isreviewer)) {
    // ok you can go
} elseif ($submission->id and $ispublished) {
    // ok you can go
    $seenaspublished = true;
} elseif (is_null($submission->id) and $cansubmit) {
    // ok you can go
} else {
    print_error('nopermissions', 'error', $teamwork->view_url(), 'view or create submission');
}

if ($assess and $submission->id and !$isreviewer and $canallocate and $teamwork->assessing_allowed($USER->id)) {
    require_sesskey();
    $assessmentid = $teamwork->add_allocation($submission, $USER->id);
    redirect($teamwork->assess_url($assessmentid));
}

if ($edit) {
    require_once(dirname(__FILE__).'/submission_form.php');

    $maxfiles       = $teamwork->nattachments;
    $maxbytes       = $teamwork->maxbytes;
    $contentopts    = array(
                        'trusttext' => true,
                        'subdirs'   => false,
                        'maxfiles'  => $maxfiles,
                        'maxbytes'  => $maxbytes,
                        'context'   => $teamwork->context,
                        'return_types' => FILE_INTERNAL | FILE_EXTERNAL
                      );

    $attachmentopts = array('subdirs' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'return_types' => FILE_INTERNAL);
    $submission     = file_prepare_standard_editor($submission, 'content', $contentopts, $teamwork->context,
                                        'mod_teamwork', 'submission_content', $submission->id);
    $submission     = file_prepare_standard_filemanager($submission, 'attachment', $attachmentopts, $teamwork->context,
                                        'mod_teamwork', 'submission_attachment', $submission->id);

    $mform          = new teamwork_submission_form($PAGE->url, array('current' => $submission, 'teamwork' => $teamwork,
                                                    'contentopts' => $contentopts, 'attachmentopts' => $attachmentopts));

    if ($mform->is_cancelled()) {
        redirect($teamwork->view_url());

    } elseif ($cansubmit and $formdata = $mform->get_data()) {
        if ($formdata->example == 0) {
            // this was used just for validation, it must be set to zero when dealing with normal submissions
            unset($formdata->example);
        } else {
            throw new coding_exception('Invalid submission form data value: example');
        }
        $timenow = time();
        if (is_null($submission->id)) {
            $formdata->teamworkid     = $teamwork->id;
            $formdata->example        = 0;
            $formdata->authorid       = $USER->id;
            $formdata->timecreated    = $timenow;
            $formdata->feedbackauthorformat = editors_get_preferred_format();
        }
        $formdata->timemodified       = $timenow;
        $formdata->title              = trim($formdata->title);
        $formdata->content            = '';          // updated later
        $formdata->contentformat      = FORMAT_HTML; // updated later
        $formdata->contenttrust       = 0;           // updated later
        $formdata->late               = 0x0;         // bit mask
        if (!empty($teamwork->submissionend) and ($teamwork->submissionend < time())) {
            $formdata->late = $formdata->late | 0x1;
        }
        if ($teamwork->phase == teamwork::PHASE_ASSESSMENT) {
            $formdata->late = $formdata->late | 0x2;
        }

        // Event information.
        $params = array(
            'context' => $teamwork->context,
            'courseid' => $teamwork->course->id,
            'other' => array(
                'submissiontitle' => $formdata->title
            )
        );
        $logdata = null;
        if (is_null($submission->id)) {
            $submission->id = $formdata->id = $DB->insert_record('teamwork_submissions', $formdata);
            $params['objectid'] = $submission->id;
            $event = \mod_teamwork\event\submission_created::create($params);
            $event->trigger();
        } else {
            if (empty($formdata->id) or empty($submission->id) or ($formdata->id != $submission->id)) {
                throw new moodle_exception('err_submissionid', 'teamwork');
            }
        }
        $params['objectid'] = $submission->id;
        // save and relink embedded images and save attachments
        $formdata = file_postupdate_standard_editor($formdata, 'content', $contentopts, $teamwork->context,
                                                      'mod_teamwork', 'submission_content', $submission->id);
        $formdata = file_postupdate_standard_filemanager($formdata, 'attachment', $attachmentopts, $teamwork->context,
                                                           'mod_teamwork', 'submission_attachment', $submission->id);
        if (empty($formdata->attachment)) {
            // explicit cast to zero integer
            $formdata->attachment = 0;
        }
        // store the updated values or re-save the new submission (re-saving needed because URLs are now rewritten)
        $DB->update_record('teamwork_submissions', $formdata);
        $event = \mod_teamwork\event\submission_updated::create($params);
        $event->add_record_snapshot('teamwork', $teamworkrecord);
        $event->trigger();

        // send submitted content for plagiarism detection
        $fs = get_file_storage();
        $files = $fs->get_area_files($teamwork->context->id, 'mod_teamwork', 'submission_attachment', $submission->id);

        $params['other']['content'] = $formdata->content;
        $params['other']['pathnamehashes'] = array_keys($files);

        $event = \mod_teamwork\event\assessable_uploaded::create($params);
        $event->set_legacy_logdata($logdata);
        $event->trigger();

        redirect($teamwork->submission_url($formdata->id));
    }
}

// load the form to override grade and/or publish the submission and process the submitted data eventually
if (!$edit and ($canoverride or $canpublish)) {
    $options = array(
        'editable' => true,
        'editablepublished' => $canpublish,
        'overridablegrade' => $canoverride);
    $feedbackform = $teamwork->get_feedbackauthor_form($PAGE->url, $submission, $options);
    if ($data = $feedbackform->get_data()) {
        $data = file_postupdate_standard_editor($data, 'feedbackauthor', array(), $teamwork->context);
        $record = new stdclass();
        $record->id = $submission->id;
        if ($canoverride) {
            $record->gradeover = $teamwork->raw_grade_value($data->gradeover, $teamwork->grade);
            $record->gradeoverby = $USER->id;
            $record->feedbackauthor = $data->feedbackauthor;
            $record->feedbackauthorformat = $data->feedbackauthorformat;
        }
        if ($canpublish) {
            $record->published = !empty($data->published);
        }
        $DB->update_record('teamwork_submissions', $record);
        redirect($teamwork->view_url());
    }
}

$PAGE->set_title($teamwork->name);
$PAGE->set_heading($course->fullname);
if ($edit) {
    $PAGE->navbar->add(get_string('mysubmission', 'teamwork'), $teamwork->submission_url(), navigation_node::TYPE_CUSTOM);
    $PAGE->navbar->add(get_string('editingsubmission', 'teamwork'));
} elseif ($ownsubmission) {
    $PAGE->navbar->add(get_string('mysubmission', 'teamwork'));
} else {
    $PAGE->navbar->add(get_string('submission', 'teamwork'));
}

// Output starts here
$output = $PAGE->get_renderer('mod_teamwork');
echo $output->header();
echo $output->heading(format_string($teamwork->name), 2);

// show instructions for submitting as thay may contain some list of questions and we need to know them
// while reading the submitted answer
if (trim($teamwork->instructauthors)) {
    $instructions = file_rewrite_pluginfile_urls($teamwork->instructauthors, 'pluginfile.php', $PAGE->context->id,
        'mod_teamwork', 'instructauthors', 0, teamwork::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'teamwork-viewlet-instructauthors', get_string('instructauthors', 'teamwork'));
    echo $output->box(format_text($instructions, $teamwork->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// if in edit mode, display the form to edit the submission

if ($edit) {
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        echo plagiarism_print_disclosure($cm->id);
    }
    $mform->display();
    echo $output->footer();
    die();
}

// else display the submission

if ($submission->id) {
    if ($seenaspublished) {
        $showauthor = has_capability('mod/teamwork:viewauthorpublished', $teamwork->context);
    } else {
        $showauthor = has_capability('mod/teamwork:viewauthornames', $teamwork->context);
    }
    echo $output->render($teamwork->prepare_submission($submission, $showauthor));
} else {
    echo $output->box(get_string('noyoursubmission', 'teamwork'));
}

if ($editable) {
    if ($submission->id) {
        $btnurl = new moodle_url($PAGE->url, array('edit' => 'on', 'id' => $submission->id));
        $btntxt = get_string('editsubmission', 'teamwork');
    } else {
        $btnurl = new moodle_url($PAGE->url, array('edit' => 'on'));
        $btntxt = get_string('createsubmission', 'teamwork');
    }
    echo $output->single_button($btnurl, $btntxt, 'get');
}

if ($submission->id and !$edit and !$isreviewer and $canallocate and $teamwork->assessing_allowed($USER->id)) {
    $url = new moodle_url($PAGE->url, array('assess' => 1));
    echo $output->single_button($url, get_string('assess', 'teamwork'), 'post');
}

if (($teamwork->phase == teamwork::PHASE_CLOSED) and ($ownsubmission or $canviewall)) {
    if (!empty($submission->gradeoverby) and strlen(trim($submission->feedbackauthor)) > 0) {
        echo $output->render(new teamwork_feedback_author($submission));
    }
}

// and possibly display the submission's review(s)

if ($isreviewer) {
    // user's own assessment
    $strategy   = $teamwork->grading_strategy_instance();
    $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $userassessment, false);
    $options    = array(
        'showreviewer'  => true,
        'showauthor'    => $showauthor,
        'showform'      => !is_null($userassessment->grade),
        'showweight'    => true,
    );
    $assessment = $teamwork->prepare_assessment($userassessment, $mform, $options);
    $assessment->title = get_string('assessmentbyyourself', 'teamwork');

    if ($teamwork->assessing_allowed($USER->id)) {
        if (is_null($userassessment->grade)) {
            $assessment->add_action($teamwork->assess_url($assessment->id), get_string('assess', 'teamwork'));
        } else {
            $assessment->add_action($teamwork->assess_url($assessment->id), get_string('reassess', 'teamwork'));
        }
    }
    if ($canoverride) {
        $assessment->add_action($teamwork->assess_url($assessment->id), get_string('assessmentsettings', 'teamwork'));
    }

    echo $output->render($assessment);

    if ($teamwork->phase == teamwork::PHASE_CLOSED) {
        if (strlen(trim($userassessment->feedbackreviewer)) > 0) {
            echo $output->render(new teamwork_feedback_reviewer($userassessment));
        }
    }
}

if (has_capability('mod/teamwork:viewallassessments', $teamwork->context) or ($ownsubmission and $teamwork->assessments_available())) {
    // other assessments
    $strategy       = $teamwork->grading_strategy_instance();
    $assessments    = $teamwork->get_assessments_of_submission($submission->id);
    $showreviewer   = has_capability('mod/teamwork:viewreviewernames', $teamwork->context);
    foreach ($assessments as $assessment) {
        if ($assessment->reviewerid == $USER->id) {
            // own assessment has been displayed already
            continue;
        }
        if (is_null($assessment->grade) and !has_capability('mod/teamwork:viewallassessments', $teamwork->context)) {
            // students do not see peer-assessment that are not graded yet
            continue;
        }
        $mform      = $strategy->get_assessment_form($PAGE->url, 'assessment', $assessment, false);
        $options    = array(
            'showreviewer'  => $showreviewer,
            'showauthor'    => $showauthor,
            'showform'      => !is_null($assessment->grade),
            'showweight'    => true,
        );
        $displayassessment = $teamwork->prepare_assessment($assessment, $mform, $options);
        if ($canoverride) {
            $displayassessment->add_action($teamwork->assess_url($assessment->id), get_string('assessmentsettings', 'teamwork'));
        }
        echo $output->render($displayassessment);

        if ($teamwork->phase == teamwork::PHASE_CLOSED and has_capability('mod/teamwork:viewallassessments', $teamwork->context)) {
            if (strlen(trim($assessment->feedbackreviewer)) > 0) {
                echo $output->render(new teamwork_feedback_reviewer($assessment));
            }
        }
    }
}

if (!$edit and $canoverride) {
    // display a form to override the submission grade
    $feedbackform->display();
}

echo $output->footer();

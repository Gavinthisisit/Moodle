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

$teamwork   = required_param('teamwork', PARAM_INT);            // course module id
$instanceid = required_param('instance', PARAM_INT);
$id     = optional_param('id', 0, PARAM_INT);           // submission id
$edit   = optional_param('edit', false, PARAM_BOOL);    // open for editing?
$assess = optional_param('assess', false, PARAM_BOOL);  // instant assessment required

$cm     = get_coursemodule_from_instance('teamwork', $teamwork, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}

$teamworkrecord = $DB->get_record('teamwork', array('id' => $cm->instance), '*', MUST_EXIST);
$teamwork = new teamwork($teamworkrecord, $cm, $course);

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

    $event = \mod_workshop\event\submission_viewed::create($params);
    $event->trigger();

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

if (empty($submission->id) and $teamwork->creating_submission_allowed($USER->id)) {
    $editable = true;
}
if ($submission->id and !$teamwork->modifying_submission_allowed($USER->id)) {
    $editable = false;
}

$edit = true;






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

    $mform          = new teamwork_submission_form($PAGE->url, array('current' => $submission, 'teamwork' => $teamwork, 'instanceid' => $instanceid,
                                                    'contentopts' => $contentopts, 'attachmentopts' => $attachmentopts));

    if ($mform->is_cancelled()) {
        redirect($teamwork->view_url());

    } elseif ($cansubmit and $formdata = $mform->get_data()) {

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
        
		$rtn_url = new moodle_url("project.php",array('w' => $formdata->teamworkid, 'instance' => $formdata->instance));
        redirect($rtn_url);
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



if ($edit) {
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        echo plagiarism_print_disclosure($cm->id);
    }
    $mform->display();
    echo $output->footer();
    die();
}



echo $output->footer();

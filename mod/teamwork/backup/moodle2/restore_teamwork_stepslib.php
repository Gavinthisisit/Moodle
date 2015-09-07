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
 * @package   mod_teamwork
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_teamwork_activity_task
 */

/**
 * Structure step to restore one teamwork activity
 */
class restore_teamwork_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();

        $userinfo = $this->get_setting_value('userinfo'); // are we including userinfo?

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - non-user data
        ////////////////////////////////////////////////////////////////////////

        // root element describing teamwork instance
        $teamwork = new restore_path_element('teamwork', '/activity/teamwork');
        $paths[] = $teamwork;

        // Apply for 'teamworkform' subplugins optional paths at teamwork level
        $this->add_subplugin_structure('teamworkform', $teamwork);

        // Apply for 'teamworkeval' subplugins optional paths at teamwork level
        $this->add_subplugin_structure('teamworkeval', $teamwork);

        // example submissions
        $paths[] = new restore_path_element('teamwork_examplesubmission',
                       '/activity/teamwork/examplesubmissions/examplesubmission');

        // reference assessment of the example submission
        $referenceassessment = new restore_path_element('teamwork_referenceassessment',
                                   '/activity/teamwork/examplesubmissions/examplesubmission/referenceassessment');
        $paths[] = $referenceassessment;

        // Apply for 'teamworkform' subplugins optional paths at referenceassessment level
        $this->add_subplugin_structure('teamworkform', $referenceassessment);

        // End here if no-user data has been selected
        if (!$userinfo) {
            return $this->prepare_activity_structure($paths);
        }

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - user data
        ////////////////////////////////////////////////////////////////////////

        // assessments of example submissions
        $exampleassessment = new restore_path_element('teamwork_exampleassessment',
                                 '/activity/teamwork/examplesubmissions/examplesubmission/exampleassessments/exampleassessment');
        $paths[] = $exampleassessment;

        // Apply for 'teamworkform' subplugins optional paths at exampleassessment level
        $this->add_subplugin_structure('teamworkform', $exampleassessment);

        // submissions
        $paths[] = new restore_path_element('teamwork_submission', '/activity/teamwork/submissions/submission');

        // allocated assessments
        $assessment = new restore_path_element('teamwork_assessment',
                          '/activity/teamwork/submissions/submission/assessments/assessment');
        $paths[] = $assessment;

        // Apply for 'teamworkform' subplugins optional paths at assessment level
        $this->add_subplugin_structure('teamworkform', $assessment);

        // aggregations of grading grades in this teamwork
        $paths[] = new restore_path_element('teamwork_aggregation', '/activity/teamwork/aggregations/aggregation');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_teamwork($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->submissionstart = $this->apply_date_offset($data->submissionstart);
        $data->submissionend = $this->apply_date_offset($data->submissionend);
        $data->assessmentstart = $this->apply_date_offset($data->assessmentstart);
        $data->assessmentend = $this->apply_date_offset($data->assessmentend);

        // insert the teamwork record
        $newitemid = $DB->insert_record('teamwork', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_teamwork_examplesubmission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->teamworkid = $this->get_new_parentid('teamwork');
        $data->example = 1;
        $data->authorid = $this->task->get_userid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('teamwork_submissions', $data);
        $this->set_mapping('teamwork_examplesubmission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_teamwork_referenceassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('teamwork_examplesubmission');
        $data->reviewerid = $this->task->get_userid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('teamwork_assessments', $data);
        $this->set_mapping('teamwork_referenceassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_teamwork_exampleassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('teamwork_examplesubmission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('teamwork_assessments', $data);
        $this->set_mapping('teamwork_exampleassessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_teamwork_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->teamworkid = $this->get_new_parentid('teamwork');
        $data->example = 0;
        $data->authorid = $this->get_mappingid('user', $data->authorid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('teamwork_submissions', $data);
        $this->set_mapping('teamwork_submission', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_teamwork_assessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('teamwork_submission');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('teamwork_assessments', $data);
        $this->set_mapping('teamwork_assessment', $oldid, $newitemid, true); // Mapping with files
    }

    protected function process_teamwork_aggregation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->teamworkid = $this->get_new_parentid('teamwork');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timegraded = $this->apply_date_offset($data->timegraded);

        $newitemid = $DB->insert_record('teamwork_aggregations', $data);
    }

    protected function after_execute() {
        // Add teamwork related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_teamwork', 'intro', null);
        $this->add_related_files('mod_teamwork', 'instructauthors', null);
        $this->add_related_files('mod_teamwork', 'instructreviewers', null);
        $this->add_related_files('mod_teamwork', 'conclusion', null);

        // Add example submission related files, matching by 'teamwork_examplesubmission' itemname
        $this->add_related_files('mod_teamwork', 'submission_content', 'teamwork_examplesubmission');
        $this->add_related_files('mod_teamwork', 'submission_attachment', 'teamwork_examplesubmission');

        // Add reference assessment related files, matching by 'teamwork_referenceassessment' itemname
        $this->add_related_files('mod_teamwork', 'overallfeedback_content', 'teamwork_referenceassessment');
        $this->add_related_files('mod_teamwork', 'overallfeedback_attachment', 'teamwork_referenceassessment');

        // Add example assessment related files, matching by 'teamwork_exampleassessment' itemname
        $this->add_related_files('mod_teamwork', 'overallfeedback_content', 'teamwork_exampleassessment');
        $this->add_related_files('mod_teamwork', 'overallfeedback_attachment', 'teamwork_exampleassessment');

        // Add submission related files, matching by 'teamwork_submission' itemname
        $this->add_related_files('mod_teamwork', 'submission_content', 'teamwork_submission');
        $this->add_related_files('mod_teamwork', 'submission_attachment', 'teamwork_submission');

        // Add assessment related files, matching by 'teamwork_assessment' itemname
        $this->add_related_files('mod_teamwork', 'overallfeedback_content', 'teamwork_assessment');
        $this->add_related_files('mod_teamwork', 'overallfeedback_attachment', 'teamwork_assessment');
    }
}

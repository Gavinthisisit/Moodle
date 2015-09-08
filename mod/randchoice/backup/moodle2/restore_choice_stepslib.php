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
 * @package    mod_randchoice
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_randchoice_activity_task
 */

/**
 * Structure step to restore one randchoice activity
 */
class restore_randchoice_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('randchoice', '/activity/randchoice');
        $paths[] = new restore_path_element('randchoice_option', '/activity/randchoice/options/option');
        if ($userinfo) {
            $paths[] = new restore_path_element('randchoice_answer', '/activity/randchoice/answers/answer');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_randchoice($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the randchoice record
        $newitemid = $DB->insert_record('randchoice', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_randchoice_option($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->randchoiceid = $this->get_new_parentid('randchoice');
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('randchoice_options', $data);
        $this->set_mapping('randchoice_option', $oldid, $newitemid);
    }

    protected function process_randchoice_answer($data) {
        global $DB;

        $data = (object)$data;

        $data->randchoiceid = $this->get_new_parentid('randchoice');
        $data->optionid = $this->get_mappingid('randchoice_option', $data->optionid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('randchoice_answers', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function after_execute() {
        // Add randchoice related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_randchoice', 'intro', null);
    }
}

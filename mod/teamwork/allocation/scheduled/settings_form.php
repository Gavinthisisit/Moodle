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
 * Scheduled allocator's settings
 *
 * @package     teamworkallocation_scheduled
 * @subpackage  mod_teamwork
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');
require_once(dirname(dirname(__FILE__)) . '/random/settings_form.php'); // parent form

/**
 * Allocator settings form
 *
 * This is used by {@see teamwork_scheduled_allocator::ui()} to set up allocation parameters.
 */
class teamwork_scheduled_allocator_form extends teamwork_random_allocator_form {

    /**
     * Definition of the setting form elements
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $teamwork = $this->_customdata['teamwork'];
        $current = $this->_customdata['current'];

        if (!empty($teamwork->submissionend)) {
            $strtimeexpected = teamwork::timestamp_formats($teamwork->submissionend);
        }

        if (!empty($current->timeallocated)) {
            $strtimeexecuted = teamwork::timestamp_formats($current->timeallocated);
        }

        $mform->addElement('header', 'scheduledallocationsettings', get_string('scheduledallocationsettings', 'teamworkallocation_scheduled'));
        $mform->addHelpButton('scheduledallocationsettings', 'scheduledallocationsettings', 'teamworkallocation_scheduled');

        $mform->addElement('checkbox', 'enablescheduled', get_string('enablescheduled', 'teamworkallocation_scheduled'), get_string('enablescheduledinfo', 'teamworkallocation_scheduled'), 1);

        $mform->addElement('header', 'scheduledallocationinfo', get_string('currentstatus', 'teamworkallocation_scheduled'));

        if ($current === false) {
            $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'teamworkallocation_scheduled'),
                get_string('resultdisabled', 'teamworkallocation_scheduled').' '.
                html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))));

        } else {
            if (!empty($current->timeallocated)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'teamworkallocation_scheduled'),
                    get_string('currentstatusexecution1', 'teamworkallocation_scheduled', $strtimeexecuted).' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/valid'))));

                if ($current->resultstatus == teamwork_allocation_result::STATUS_EXECUTED) {
                    $strstatus = get_string('resultexecuted', 'teamworkallocation_scheduled').' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/valid')));
                } else if ($current->resultstatus == teamwork_allocation_result::STATUS_FAILED) {
                    $strstatus = get_string('resultfailed', 'teamworkallocation_scheduled').' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid')));
                } else {
                    $strstatus = get_string('resultvoid', 'teamworkallocation_scheduled').' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid')));
                }

                if (!empty($current->resultmessage)) {
                    $strstatus .= html_writer::empty_tag('br').$current->resultmessage; // yes, this is ugly. better solution suggestions are welcome.
                }
                $mform->addElement('static', 'inforesult', get_string('currentstatusresult', 'teamworkallocation_scheduled'), $strstatus);

                if ($current->timeallocated < $teamwork->submissionend) {
                    $mform->addElement('static', 'infoexpected', get_string('currentstatusnext', 'teamworkallocation_scheduled'),
                        get_string('currentstatusexecution2', 'teamworkallocation_scheduled', $strtimeexpected).' '.
                        html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/caution'))));
                    $mform->addHelpButton('infoexpected', 'currentstatusnext', 'teamworkallocation_scheduled');
                } else {
                    $mform->addElement('checkbox', 'reenablescheduled', get_string('currentstatusreset', 'teamworkallocation_scheduled'),
                       get_string('currentstatusresetinfo', 'teamworkallocation_scheduled'));
                    $mform->addHelpButton('reenablescheduled', 'currentstatusreset', 'teamworkallocation_scheduled');
                }

            } else if (empty($current->enabled)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'teamworkallocation_scheduled'),
                    get_string('resultdisabled', 'teamworkallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))));

            } else if ($teamwork->phase != teamwork::PHASE_SUBMISSION) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'teamworkallocation_scheduled'),
                    get_string('resultfailed', 'teamworkallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))).
                    html_writer::empty_tag('br').
                    get_string('resultfailedphase', 'teamworkallocation_scheduled'));

            } else if (empty($teamwork->submissionend)) {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'teamworkallocation_scheduled'),
                    get_string('resultfailed', 'teamworkallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/invalid'))).
                    html_writer::empty_tag('br').
                    get_string('resultfaileddeadline', 'teamworkallocation_scheduled'));

            } else if ($teamwork->submissionend < time()) {
                // next cron will execute it
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'teamworkallocation_scheduled'),
                    get_string('currentstatusexecution4', 'teamworkallocation_scheduled').' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/caution'))));

            } else {
                $mform->addElement('static', 'infostatus', get_string('currentstatusexecution', 'teamworkallocation_scheduled'),
                    get_string('currentstatusexecution3', 'teamworkallocation_scheduled', $strtimeexpected).' '.
                    html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/caution'))));
            }
        }

        parent::definition();

        $mform->addHelpButton('randomallocationsettings', 'randomallocationsettings', 'teamworkallocation_scheduled');
    }
}

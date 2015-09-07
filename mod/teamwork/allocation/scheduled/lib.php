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
 * Scheduled allocator that internally executes the random allocation later
 *
 * @package     teamworkallocation_scheduled
 * @subpackage  mod_teamwork
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/lib.php');                  // interface definition
require_once(dirname(dirname(dirname(__FILE__))) . '/locallib.php');    // teamwork internal API
require_once(dirname(dirname(__FILE__)) . '/random/lib.php');           // random allocator
require_once(dirname(__FILE__) . '/settings_form.php');                 // our settings form

/**
 * Allocates the submissions randomly in a cronjob task
 */
class teamwork_scheduled_allocator implements teamwork_allocator {

    /** teamwork instance */
    protected $teamwork;

    /** teamwork_scheduled_allocator_form with settings for the random allocator */
    protected $mform;

    /**
     * @param teamwork $teamwork Teamwork API object
     */
    public function __construct(teamwork $teamwork) {
        $this->teamwork = $teamwork;
    }

    /**
     * Save the settings for the random allocator to execute it later
     */
    public function init() {
        global $PAGE, $DB;

        $result = new teamwork_allocation_result($this);

        $customdata = array();
        $customdata['teamwork'] = $this->teamwork;

        $current = $DB->get_record('teamworkallocation_scheduled',
            array('teamworkid' => $this->teamwork->id), '*', IGNORE_MISSING);

        $customdata['current'] = $current;

        $this->mform = new teamwork_scheduled_allocator_form($PAGE->url, $customdata);

        if ($this->mform->is_cancelled()) {
            redirect($this->teamwork->view_url());
        } else if ($settings = $this->mform->get_data()) {
            if (empty($settings->enablescheduled)) {
                $enabled = false;
            } else {
                $enabled = true;
            }
            if (empty($settings->reenablescheduled)) {
                $reset = false;
            } else {
                $reset = true;
            }
            $settings = teamwork_random_allocator_setting::instance_from_object($settings);
            $this->store_settings($enabled, $reset, $settings, $result);
            if ($enabled) {
                $msg = get_string('resultenabled', 'teamworkallocation_scheduled');
            } else {
                $msg = get_string('resultdisabled', 'teamworkallocation_scheduled');
            }
            $result->set_status(teamwork_allocation_result::STATUS_CONFIGURED, $msg);
            return $result;
        } else {
            // this branch is executed if the form is submitted but the data
            // doesn't validate and the form should be redisplayed
            // or on the first display of the form.

            if ($current !== false) {
                $data = teamwork_random_allocator_setting::instance_from_text($current->settings);
                $data->enablescheduled = $current->enabled;
                $this->mform->set_data($data);
            }

            $result->set_status(teamwork_allocation_result::STATUS_VOID);
            return $result;
        }
    }

    /**
     * Returns the HTML code to print the user interface
     */
    public function ui() {
        global $PAGE;

        $output = $PAGE->get_renderer('mod_teamwork');

        $out = $output->container_start('scheduled-allocator');
        // the nasty hack follows to bypass the sad fact that moodle quickforms do not allow to actually
        // return the HTML content, just to display it
        ob_start();
        $this->mform->display();
        $out .= ob_get_contents();
        ob_end_clean();
        $out .= $output->container_end();

        return $out;
    }

    /**
     * Executes the allocation
     *
     * @return teamwork_allocation_result
     */
    public function execute() {
        global $DB;

        $result = new teamwork_allocation_result($this);

        // make sure the teamwork itself is at the expected state

        if ($this->teamwork->phase != teamwork::PHASE_SUBMISSION) {
            $result->set_status(teamwork_allocation_result::STATUS_FAILED,
                get_string('resultfailedphase', 'teamworkallocation_scheduled'));
            return $result;
        }

        if (empty($this->teamwork->submissionend)) {
            $result->set_status(teamwork_allocation_result::STATUS_FAILED,
                get_string('resultfaileddeadline', 'teamworkallocation_scheduled'));
            return $result;
        }

        if ($this->teamwork->submissionend > time()) {
            $result->set_status(teamwork_allocation_result::STATUS_VOID,
                get_string('resultvoiddeadline', 'teamworkallocation_scheduled'));
            return $result;
        }

        $current = $DB->get_record('teamworkallocation_scheduled',
            array('teamworkid' => $this->teamwork->id, 'enabled' => 1), '*', IGNORE_MISSING);

        if ($current === false) {
            $result->set_status(teamwork_allocation_result::STATUS_FAILED,
                get_string('resultfailedconfig', 'teamworkallocation_scheduled'));
            return $result;
        }

        if (!$current->enabled) {
            $result->set_status(teamwork_allocation_result::STATUS_VOID,
                get_string('resultdisabled', 'teamworkallocation_scheduled'));
            return $result;
        }

        if (!is_null($current->timeallocated) and $current->timeallocated >= $this->teamwork->submissionend) {
            $result->set_status(teamwork_allocation_result::STATUS_VOID,
                get_string('resultvoidexecuted', 'teamworkallocation_scheduled'));
            return $result;
        }

        // so now we know that we are after the submissions deadline and either the scheduled allocation was not
        // executed yet or it was but the submissions deadline has been prolonged (and hence we should repeat the
        // allocations)

        $settings = teamwork_random_allocator_setting::instance_from_text($current->settings);
        $randomallocator = $this->teamwork->allocator_instance('random');
        $randomallocator->execute($settings, $result);

        // store the result in the instance's table
        $update = new stdClass();
        $update->id = $current->id;
        $update->timeallocated = $result->get_timeend();
        $update->resultstatus = $result->get_status();
        $update->resultmessage = $result->get_message();
        $update->resultlog = json_encode($result->get_logs());

        $DB->update_record('teamworkallocation_scheduled', $update);

        return $result;
    }

    /**
     * Delete all data related to a given teamwork module instance
     *
     * @see teamwork_delete_instance()
     * @param int $teamworkid id of the teamwork module instance being deleted
     * @return void
     */
    public static function delete_instance($teamworkid) {
        // TODO
        return;
    }

    /**
     * Stores the pre-defined random allocation settings for later usage
     *
     * @param bool $enabled is the scheduled allocation enabled
     * @param bool $reset reset the recent execution info
     * @param teamwork_random_allocator_setting $settings settings form data
     * @param teamwork_allocation_result $result logger
     */
    protected function store_settings($enabled, $reset, teamwork_random_allocator_setting $settings, teamwork_allocation_result $result) {
        global $DB;


        $data = new stdClass();
        $data->teamworkid = $this->teamwork->id;
        $data->enabled = $enabled;
        $data->submissionend = $this->teamwork->submissionend;
        $data->settings = $settings->export_text();

        if ($reset) {
            $data->timeallocated = null;
            $data->resultstatus = null;
            $data->resultmessage = null;
            $data->resultlog = null;
        }

        $result->log($data->settings, 'debug');

        $current = $DB->get_record('teamworkallocation_scheduled', array('teamworkid' => $data->teamworkid), '*', IGNORE_MISSING);

        if ($current === false) {
            $DB->insert_record('teamworkallocation_scheduled', $data);

        } else {
            $data->id = $current->id;
            $DB->update_record('teamworkallocation_scheduled', $data);
        }
    }
}

/**
 * Regular jobs to execute via cron
 */
function teamworkallocation_scheduled_cron() {
    global $CFG, $DB;

    $sql = "SELECT w.*
              FROM {teamworkallocation_scheduled} a
              JOIN {teamwork} w ON a.teamworkid = w.id
             WHERE a.enabled = 1
                   AND w.phase = 20
                   AND w.submissionend > 0
                   AND w.submissionend < ?
                   AND (a.timeallocated IS NULL OR a.timeallocated < w.submissionend)";

    $teamworks = $DB->get_records_sql($sql, array(time()));

    if (empty($teamworks)) {
        mtrace('... no teamworks awaiting scheduled allocation. ', '');
        return;
    }

    mtrace('... executing scheduled allocation in '.count($teamworks).' teamwork(s) ... ', '');

    // let's have some fun!
    require_once($CFG->dirroot.'/mod/teamwork/locallib.php');

    foreach ($teamworks as $teamwork) {
        $cm = get_coursemodule_from_instance('teamwork', $teamwork->id, $teamwork->course, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $teamwork = new teamwork($teamwork, $cm, $course);
        $allocator = $teamwork->allocator_instance('scheduled');
        $result = $allocator->execute();

        // todo inform the teachers about the results
    }
}

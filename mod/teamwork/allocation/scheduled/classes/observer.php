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
 * Event observers for teamworkallocation_scheduled.
 *
 * @package teamworkallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace teamworkallocation_scheduled;
defined('MOODLE_INTERNAL') || die();

/**
 * Class for teamworkallocation_scheduled observers.
 *
 * @package teamworkallocation_scheduled
 * @copyright 2013 Adrian Greeve <adrian@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Triggered when the '\mod_teamwork\event\course_module_viewed' event is triggered.
     *
     * This does the same job as {@link teamworkallocation_scheduled_cron()} but for the
     * single teamwork. The idea is that we do not need to wait for cron to execute.
     * Displaying the teamwork main view.php can trigger the scheduled allocation, too.
     *
     * @param \mod_teamwork\event\course_module_viewed $event
     * @return bool
     */
    public static function teamwork_viewed($event) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/teamwork/locallib.php');

        $teamwork = $event->get_record_snapshot('teamwork', $event->objectid);
        $course   = $event->get_record_snapshot('course', $event->courseid);
        $cm       = $event->get_record_snapshot('course_modules', $event->contextinstanceid);

        $teamwork = new \teamwork($teamwork, $cm, $course);
        $now = time();

        // Non-expensive check to see if the scheduled allocation can even happen.
        if ($teamwork->phase == \teamwork::PHASE_SUBMISSION and $teamwork->submissionend > 0 and $teamwork->submissionend < $now) {

            // Make sure the scheduled allocation has been configured for this teamwork, that it has not
            // been executed yet and that the passed teamwork record is still valid.
            $sql = "SELECT a.id
                      FROM {teamworkallocation_scheduled} a
                      JOIN {teamwork} w ON a.teamworkid = w.id
                     WHERE w.id = :teamworkid
                           AND a.enabled = 1
                           AND w.phase = :phase
                           AND w.submissionend > 0
                           AND w.submissionend < :now
                           AND (a.timeallocated IS NULL OR a.timeallocated < w.submissionend)";
            $params = array('teamworkid' => $teamwork->id, 'phase' => \teamwork::PHASE_SUBMISSION, 'now' => $now);

            if ($DB->record_exists_sql($sql, $params)) {
                // Allocate submissions for assessments.
                $allocator = $teamwork->allocator_instance('scheduled');
                $result = $allocator->execute();
                // Todo inform the teachers about the results.
            }
        }
        return true;
    }
}

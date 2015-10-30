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
 * The mod_teamworkforum post deleted event.
 *
 * @package    mod_teamworkforum
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teamworkforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_teamworkforum post deleted event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int discussionid: The discussion id the post is part of.
 *      - int teamworkforumid: The teamworkforum id the post is part of.
 *      - string teamworkforumtype: The type of teamworkforum the post is part of.
 * }
 *
 * @package    mod_teamworkforum
 * @since      Moodle 2.7
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_deleted extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'teamworkforum_posts';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has deleted the post with id '$this->objectid' in the discussion with " .
            "id '{$this->other['discussionid']}' in the teamworkforum with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpostdeleted', 'mod_teamworkforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        if ($this->other['teamworkforumtype'] == 'single') {
            // Single discussion teamworkforums are an exception. We show
            // the teamworkforum itself since it only has one discussion
            // thread.
            $url = new \moodle_url('/mod/teamworkforum/view.php', array('f' => $this->other['teamworkforumid']));
        } else {
            $url = new \moodle_url('/mod/teamworkforum/discuss.php', array('d' => $this->other['discussionid']));
        }
        return $url;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        // The legacy log table expects a relative path to /mod/teamworkforum/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/mod/teamworkforum/'));

        return array($this->courseid, 'teamworkforum', 'delete post', $logurl, $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['discussionid'])) {
            throw new \coding_exception('The \'discussionid\' value must be set in other.');
        }

        if (!isset($this->other['teamworkforumid'])) {
            throw new \coding_exception('The \'teamworkforumid\' value must be set in other.');
        }

        if (!isset($this->other['teamworkforumtype'])) {
            throw new \coding_exception('The \'teamworkforumtype\' value must be set in other.');
        }

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }
}
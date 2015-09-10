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
 * Defines backup_quora_activity_task class
 *
 * @package   mod_quora
 * @category  backup
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quora/backup/moodle2/backup_quora_stepslib.php');
require_once($CFG->dirroot . '/mod/quora/backup/moodle2/backup_quora_settingslib.php');

/**
 * Provides the steps to perform one complete backup of the Forum instance
 */
class backup_quora_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the quora.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_quora_activity_structure_step('quora structure', 'quora.xml'));
    }

    /**
     * Encodes URLs to the index.php, view.php and discuss.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of quoras
        $search="/(".$base."\/mod\/quora\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@FORUMINDEX*$2@$', $content);

        // Link to quora view by moduleid
        $search="/(".$base."\/mod\/quora\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@FORUMVIEWBYID*$2@$', $content);

        // Link to quora view by quoraid
        $search="/(".$base."\/mod\/quora\/view.php\?f\=)([0-9]+)/";
        $content= preg_replace($search, '$@FORUMVIEWBYF*$2@$', $content);

        // Link to quora discussion with parent syntax
        $search="/(".$base."\/mod\/quora\/discuss.php\?d\=)([0-9]+)\&parent\=([0-9]+)/";
        $content= preg_replace($search, '$@FORUMDISCUSSIONVIEWPARENT*$2*$3@$', $content);

        // Link to quora discussion with relative syntax
        $search="/(".$base."\/mod\/quora\/discuss.php\?d\=)([0-9]+)\#([0-9]+)/";
        $content= preg_replace($search, '$@FORUMDISCUSSIONVIEWINSIDE*$2*$3@$', $content);

        // Link to quora discussion by discussionid
        $search="/(".$base."\/mod\/quora\/discuss.php\?d\=)([0-9]+)/";
        $content= preg_replace($search, '$@FORUMDISCUSSIONVIEW*$2@$', $content);

        return $content;
    }
}

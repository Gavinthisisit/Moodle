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
 * @package    mod_quora
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quora/backup/moodle2/restore_quora_stepslib.php'); // Because it exists (must)

/**
 * quora restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_quora_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_quora_activity_structure_step('quora_structure', 'quora.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('quora', array('intro'), 'quora');
        $contents[] = new restore_decode_content('quora_posts', array('message'), 'quora_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of quoras in course
        $rules[] = new restore_decode_rule('FORUMINDEX', '/mod/quora/index.php?id=$1', 'course');
        // Forum by cm->id and quora->id
        $rules[] = new restore_decode_rule('FORUMVIEWBYID', '/mod/quora/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('FORUMVIEWBYF', '/mod/quora/view.php?f=$1', 'quora');
        // Link to quora discussion
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEW', '/mod/quora/discuss.php?d=$1', 'quora_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWPARENT', '/mod/quora/discuss.php?d=$1&parent=$2',
                                           array('quora_discussion', 'quora_post'));
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWINSIDE', '/mod/quora/discuss.php?d=$1#$2',
                                           array('quora_discussion', 'quora_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * quora logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('quora', 'add', 'view.php?id={course_module}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'update', 'view.php?id={course_module}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'view', 'view.php?id={course_module}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'view quora', 'view.php?id={course_module}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'mark read', 'view.php?f={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'start tracking', 'view.php?f={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'stop tracking', 'view.php?f={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'subscribe', 'view.php?f={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'unsubscribe', 'view.php?f={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'subscriber', 'subscribers.php?id={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'subscribers', 'subscribers.php?id={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'view subscribers', 'subscribers.php?id={quora}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'add discussion', 'discuss.php?d={quora_discussion}', '{quora_discussion}');
        $rules[] = new restore_log_rule('quora', 'view discussion', 'discuss.php?d={quora_discussion}', '{quora_discussion}');
        $rules[] = new restore_log_rule('quora', 'move discussion', 'discuss.php?d={quora_discussion}', '{quora_discussion}');
        $rules[] = new restore_log_rule('quora', 'delete discussi', 'view.php?id={course_module}', '{quora}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('quora', 'delete discussion', 'view.php?id={course_module}', '{quora}');
        $rules[] = new restore_log_rule('quora', 'add post', 'discuss.php?d={quora_discussion}&parent={quora_post}', '{quora_post}');
        $rules[] = new restore_log_rule('quora', 'update post', 'discuss.php?d={quora_discussion}#p{quora_post}&parent={quora_post}', '{quora_post}');
        $rules[] = new restore_log_rule('quora', 'update post', 'discuss.php?d={quora_discussion}&parent={quora_post}', '{quora_post}');
        $rules[] = new restore_log_rule('quora', 'prune post', 'discuss.php?d={quora_discussion}', '{quora_post}');
        $rules[] = new restore_log_rule('quora', 'delete post', 'discuss.php?d={quora_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('quora', 'view quoras', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('quora', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('quora', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('quora', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('quora', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }
}

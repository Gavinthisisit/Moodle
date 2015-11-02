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
 * @package    mod_twf
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/twf/backup/moodle2/restore_twf_stepslib.php'); // Because it exists (must)

/**
 * twf restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_twf_activity_task extends restore_activity_task {

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
        $this->add_step(new restore_twf_activity_structure_step('twf_structure', 'twf.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('twf', array('intro'), 'twf');
        $contents[] = new restore_decode_content('twf_posts', array('message'), 'twf_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of twfs in course
        $rules[] = new restore_decode_rule('FORUMINDEX', '/mod/twf/index.php?id=$1', 'course');
        // Forum by cm->id and twf->id
        $rules[] = new restore_decode_rule('FORUMVIEWBYID', '/mod/twf/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('FORUMVIEWBYF', '/mod/twf/view.php?f=$1', 'twf');
        // Link to twf discussion
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEW', '/mod/twf/discuss.php?d=$1', 'twf_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWPARENT', '/mod/twf/discuss.php?d=$1&parent=$2',
                                           array('twf_discussion', 'twf_post'));
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWINSIDE', '/mod/twf/discuss.php?d=$1#$2',
                                           array('twf_discussion', 'twf_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * twf logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('twf', 'add', 'view.php?id={course_module}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'update', 'view.php?id={course_module}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'view', 'view.php?id={course_module}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'view twf', 'view.php?id={course_module}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'mark read', 'view.php?f={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'start tracking', 'view.php?f={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'stop tracking', 'view.php?f={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'subscribe', 'view.php?f={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'unsubscribe', 'view.php?f={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'subscriber', 'subscribers.php?id={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'subscribers', 'subscribers.php?id={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'view subscribers', 'subscribers.php?id={twf}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'add discussion', 'discuss.php?d={twf_discussion}', '{twf_discussion}');
        $rules[] = new restore_log_rule('twf', 'view discussion', 'discuss.php?d={twf_discussion}', '{twf_discussion}');
        $rules[] = new restore_log_rule('twf', 'move discussion', 'discuss.php?d={twf_discussion}', '{twf_discussion}');
        $rules[] = new restore_log_rule('twf', 'delete discussi', 'view.php?id={course_module}', '{twf}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('twf', 'delete discussion', 'view.php?id={course_module}', '{twf}');
        $rules[] = new restore_log_rule('twf', 'add post', 'discuss.php?d={twf_discussion}&parent={twf_post}', '{twf_post}');
        $rules[] = new restore_log_rule('twf', 'update post', 'discuss.php?d={twf_discussion}#p{twf_post}&parent={twf_post}', '{twf_post}');
        $rules[] = new restore_log_rule('twf', 'update post', 'discuss.php?d={twf_discussion}&parent={twf_post}', '{twf_post}');
        $rules[] = new restore_log_rule('twf', 'prune post', 'discuss.php?d={twf_discussion}', '{twf_post}');
        $rules[] = new restore_log_rule('twf', 'delete post', 'discuss.php?d={twf_discussion}', '[post]');

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

        $rules[] = new restore_log_rule('twf', 'view twfs', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('twf', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('twf', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('twf', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('twf', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }
}

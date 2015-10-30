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
 * @package    mod_teamworkforum
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamworkforum/backup/moodle2/restore_teamworkforum_stepslib.php'); // Because it exists (must)

/**
 * teamworkforum restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_teamworkforum_activity_task extends restore_activity_task {

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
        $this->add_step(new restore_teamworkforum_activity_structure_step('teamworkforum_structure', 'teamworkforum.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('teamworkforum', array('intro'), 'teamworkforum');
        $contents[] = new restore_decode_content('teamworkforum_posts', array('message'), 'teamworkforum_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of teamworkforums in course
        $rules[] = new restore_decode_rule('FORUMINDEX', '/mod/teamworkforum/index.php?id=$1', 'course');
        // Forum by cm->id and teamworkforum->id
        $rules[] = new restore_decode_rule('FORUMVIEWBYID', '/mod/teamworkforum/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('FORUMVIEWBYF', '/mod/teamworkforum/view.php?f=$1', 'teamworkforum');
        // Link to teamworkforum discussion
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEW', '/mod/teamworkforum/discuss.php?d=$1', 'teamworkforum_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWPARENT', '/mod/teamworkforum/discuss.php?d=$1&parent=$2',
                                           array('teamworkforum_discussion', 'teamworkforum_post'));
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWINSIDE', '/mod/teamworkforum/discuss.php?d=$1#$2',
                                           array('teamworkforum_discussion', 'teamworkforum_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * teamworkforum logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('teamworkforum', 'add', 'view.php?id={course_module}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'update', 'view.php?id={course_module}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'view', 'view.php?id={course_module}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'view teamworkforum', 'view.php?id={course_module}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'mark read', 'view.php?f={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'start tracking', 'view.php?f={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'stop tracking', 'view.php?f={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'subscribe', 'view.php?f={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'unsubscribe', 'view.php?f={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'subscriber', 'subscribers.php?id={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'subscribers', 'subscribers.php?id={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'view subscribers', 'subscribers.php?id={teamworkforum}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'add discussion', 'discuss.php?d={teamworkforum_discussion}', '{teamworkforum_discussion}');
        $rules[] = new restore_log_rule('teamworkforum', 'view discussion', 'discuss.php?d={teamworkforum_discussion}', '{teamworkforum_discussion}');
        $rules[] = new restore_log_rule('teamworkforum', 'move discussion', 'discuss.php?d={teamworkforum_discussion}', '{teamworkforum_discussion}');
        $rules[] = new restore_log_rule('teamworkforum', 'delete discussi', 'view.php?id={course_module}', '{teamworkforum}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('teamworkforum', 'delete discussion', 'view.php?id={course_module}', '{teamworkforum}');
        $rules[] = new restore_log_rule('teamworkforum', 'add post', 'discuss.php?d={teamworkforum_discussion}&parent={teamworkforum_post}', '{teamworkforum_post}');
        $rules[] = new restore_log_rule('teamworkforum', 'update post', 'discuss.php?d={teamworkforum_discussion}#p{teamworkforum_post}&parent={teamworkforum_post}', '{teamworkforum_post}');
        $rules[] = new restore_log_rule('teamworkforum', 'update post', 'discuss.php?d={teamworkforum_discussion}&parent={teamworkforum_post}', '{teamworkforum_post}');
        $rules[] = new restore_log_rule('teamworkforum', 'prune post', 'discuss.php?d={teamworkforum_discussion}', '{teamworkforum_post}');
        $rules[] = new restore_log_rule('teamworkforum', 'delete post', 'discuss.php?d={teamworkforum_discussion}', '[post]');

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

        $rules[] = new restore_log_rule('teamworkforum', 'view teamworkforums', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('teamworkforum', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('teamworkforum', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('teamworkforum', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('teamworkforum', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }
}

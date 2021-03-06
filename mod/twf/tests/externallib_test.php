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
 * The module twfs external functions unit tests
 *
 * @package    mod_twf
 * @category   external
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class mod_twf_external_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG;

        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_twf\subscriptions::reset_twf_cache();

        require_once($CFG->dirroot . '/mod/twf/externallib.php');
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_twf\subscriptions::reset_twf_cache();
    }

    /**
     * Test get twfs
     */
    public function test_mod_twf_get_twfs_by_courses() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Create a user.
        $user = self::getDataGenerator()->create_user();

        // Set to the user.
        self::setUser($user);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First twf.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course1->id;
        $twf1 = self::getDataGenerator()->create_module('twf', $record);

        // Second twf.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course2->id;
        $twf2 = self::getDataGenerator()->create_module('twf', $record);

        // Add discussions to the twfs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->twf = $twf1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);
        // Expect one discussion.
        $twf1->numdiscussions = 1;

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user->id;
        $record->twf = $twf2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);
        $discussion3 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);
        // Expect two discussions.
        $twf2->numdiscussions = 2;

        // Check the twf was correctly created.
        $this->assertEquals(2, $DB->count_records_select('twf', 'id = :twf1 OR id = :twf2',
                array('twf1' => $twf1->id, 'twf2' => $twf2->id)));

        // Enrol the user in two courses.
        // DataGenerator->enrol_user automatically sets a role for the user with the permission mod/form:viewdiscussion.
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, null, 'manual');
        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user->id);

        // Assign capabilities to view twfs for twf 2.
        $cm2 = get_coursemodule_from_id('twf', $twf2->cmid, 0, false, MUST_EXIST);
        $context2 = context_module::instance($cm2->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $roleid2 = $this->assignUserCapability('mod/twf:viewdiscussion', $context2->id, $newrole);

        // Create what we expect to be returned when querying the two courses.
        unset($twf1->displaywordcount);
        unset($twf2->displaywordcount);

        $expectedtwfs = array();
        $expectedtwfs[$twf1->id] = (array) $twf1;
        $expectedtwfs[$twf2->id] = (array) $twf2;

        // Call the external function passing course ids.
        $twfs = mod_twf_external::get_twfs_by_courses(array($course1->id, $course2->id));
        $twfs = external_api::clean_returnvalue(mod_twf_external::get_twfs_by_courses_returns(), $twfs);
        $this->assertCount(2, $twfs);
        foreach ($twfs as $twf) {
            $this->assertEquals($expectedtwfs[$twf['id']], $twf);
        }

        // Call the external function without passing course id.
        $twfs = mod_twf_external::get_twfs_by_courses();
        $twfs = external_api::clean_returnvalue(mod_twf_external::get_twfs_by_courses_returns(), $twfs);
        $this->assertCount(2, $twfs);
        foreach ($twfs as $twf) {
            $this->assertEquals($expectedtwfs[$twf['id']], $twf);
        }

        // Unenrol user from second course and alter expected twfs.
        $enrol->unenrol_user($instance2, $user->id);
        unset($expectedtwfs[$twf2->id]);

        // Call the external function without passing course id.
        $twfs = mod_twf_external::get_twfs_by_courses();
        $twfs = external_api::clean_returnvalue(mod_twf_external::get_twfs_by_courses_returns(), $twfs);
        $this->assertCount(1, $twfs);
        $this->assertEquals($expectedtwfs[$twf1->id], $twfs[0]);

        // Call for the second course we unenrolled the user from.
        $twfs = mod_twf_external::get_twfs_by_courses(array($course2->id));
        $twfs = external_api::clean_returnvalue(mod_twf_external::get_twfs_by_courses_returns(), $twfs);
        $this->assertCount(0, $twfs);
    }

    /**
     * Test get twf discussions
     */
    public function test_mod_twf_get_twf_discussions() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track twfs.
        $CFG->twf_trackreadposts = true;

        // Create a user who can track twfs.
        $record = new stdClass();
        $record->tracktwfs = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First twf with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = FORUM_TRACKING_OFF;
        $twf1 = self::getDataGenerator()->create_module('twf', $record);

        // Second twf of type 'qanda' with tracking enabled.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->type = 'qanda';
        $record->trackingtype = FORUM_TRACKING_FORCED;
        $twf2 = self::getDataGenerator()->create_module('twf', $record);

        // Add discussions to the twfs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->twf = $twf1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user2->id;
        $record->twf = $twf2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        // Add two replies to discussion 2 from different users.
        $record = new stdClass();
        $record->discussion = $discussion2->id;
        $record->parent = $discussion2->firstpost;
        $record->userid = $user1->id;
        $discussion2reply1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        $record->parent = $discussion2reply1->id;
        $record->userid = $user3->id;
        $discussion2reply2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        // Check the twfs were correctly created.
        $this->assertEquals(2, $DB->count_records_select('twf', 'id = :twf1 OR id = :twf2',
                array('twf1' => $twf1->id, 'twf2' => $twf2->id)));

        // Check the discussions were correctly created.
        $this->assertEquals(2, $DB->count_records_select('twf_discussions', 'twf = :twf1 OR twf = :twf2',
                                                            array('twf1' => $twf1->id, 'twf2' => $twf2->id)));

        // Check the posts were correctly created, don't forget each discussion created also creates a post.
        $this->assertEquals(7, $DB->count_records_select('twf_posts', 'discussion = :discussion1 OR discussion = :discussion2',
                array('discussion1' => $discussion1->id, 'discussion2' => $discussion2->id)));

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/twf:viewdiscussion on all twfs of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        // Now enrol into the second course.
        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user1->id);

        // Assign capabilities to view discussions for twf 2.
        $cm = get_coursemodule_from_id('twf', $twf2->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/twf:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the twfs.
        $expecteddiscussions = array();
        $expecteddiscussions[] = array(
                'id' => $discussion1->id,
                'course' => $discussion1->course,
                'twf' => $discussion1->twf,
                'name' => $discussion1->name,
                'firstpost' => $discussion1->firstpost,
                'userid' => $discussion1->userid,
                'groupid' => $discussion1->groupid,
                'assessed' => $discussion1->assessed,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'firstuserfullname' => fullname($user1),
                'firstuserimagealt' => $user1->imagealt,
                'firstuserpicture' => $user1->picture,
                'firstuseremail' => $user1->email,
                'subject' => $discussion1->name,
                'numreplies' => 3,
                'numunread' => '',
                'lastpost' => $discussion1reply3->id,
                'lastuserid' => $user4->id,
                'lastuserfullname' => fullname($user4),
                'lastuserimagealt' => $user4->imagealt,
                'lastuserpicture' => $user4->picture,
                'lastuseremail' => $user4->email
            );
        $expecteddiscussions[] = array(
                'id' => $discussion2->id,
                'course' => $discussion2->course,
                'twf' => $discussion2->twf,
                'name' => $discussion2->name,
                'firstpost' => $discussion2->firstpost,
                'userid' => $discussion2->userid,
                'groupid' => $discussion2->groupid,
                'assessed' => $discussion2->assessed,
                'timemodified' => $discussion2reply2->created,
                'usermodified' => $discussion2reply2->userid,
                'timestart' => $discussion2->timestart,
                'timeend' => $discussion2->timeend,
                'firstuserfullname' => fullname($user2),
                'firstuserimagealt' => $user2->imagealt,
                'firstuserpicture' => $user2->picture,
                'firstuseremail' => $user2->email,
                'subject' => $discussion2->name,
                'numreplies' => 2,
                'numunread' => 3,
                'lastpost' => $discussion2reply2->id,
                'lastuserid' => $user3->id,
                'lastuserfullname' => fullname($user3),
                'lastuserimagealt' => $user3->imagealt,
                'lastuserpicture' => $user3->picture,
                'lastuseremail' => $user3->email
            );

        // Call the external function passing twf ids.
        $discussions = mod_twf_external::get_twf_discussions(array($twf1->id, $twf2->id));
        $discussions = external_api::clean_returnvalue(mod_twf_external::get_twf_discussions_returns(), $discussions);
        $this->assertEquals($expecteddiscussions, $discussions);
        // Some debugging is going to be produced, this is because we switch PAGE contexts in the get_twf_discussions function,
        // the switch happens when the validate_context function is called inside a foreach loop.
        // See MDL-41746 for more information.
        $this->assertDebuggingCalled();

        // Remove the users post from the qanda twf and ensure they can still see the discussion.
        $DB->delete_records('twf_posts', array('id' => $discussion2reply1->id));
        $discussions = mod_twf_external::get_twf_discussions(array($twf2->id));
        $discussions = external_api::clean_returnvalue(mod_twf_external::get_twf_discussions_returns(), $discussions);
        $this->assertEquals(1, count($discussions));

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/twf:viewdiscussion', null, null, $course1->id);
        try {
            mod_twf_external::get_twf_discussions(array($twf1->id));
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        $this->assertDebuggingCalled();

        // Unenrol user from second course.
        $enrol->unenrol_user($instance2, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_twf_external::get_twf_discussions(array($twf2->id));
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Test get twf posts
     */
    public function test_mod_twf_get_twf_discussion_posts() {
        global $CFG;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track twfs.
        $CFG->twf_trackreadposts = true;

        // Create a user who can track twfs.
        $record = new stdClass();
        $record->tracktwfs = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();

        // Forum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = FORUM_TRACKING_OFF;
        $twf1 = self::getDataGenerator()->create_module('twf', $record);
        $twf1context = context_module::instance($twf1->cmid);

        // Add discussions to the twfs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->twf = $twf1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->twf = $twf1->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);

        // Add 2 replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        // Enrol the user in the  course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/twf:viewdiscussion on all twfs of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Delete one user, to test that we still receive posts by this user.
        delete_user($user3);

        // Create what we expect to be returned when querying the discussion.
        $expectedposts = array(
            'posts' => array(),
            'warnings' => array(),
        );

        // Empty picture since it's a user deleted (user3).
        $userpictureurl = '';

        $expectedposts['posts'][] = array(
            'id' => $discussion1reply2->id,
            'discussion' => $discussion1reply2->discussion,
            'parent' => $discussion1reply2->parent,
            'userid' => (int) $discussion1reply2->userid,
            'created' => $discussion1reply2->created,
            'modified' => $discussion1reply2->modified,
            'mailed' => $discussion1reply2->mailed,
            'subject' => $discussion1reply2->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply2->message, 'pluginfile.php',
                    $twf1context->id, 'mod_twf', 'post', $discussion1reply2->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply2->messagetrust,
            'attachment' => $discussion1reply2->attachment,
            'totalscore' => $discussion1reply2->totalscore,
            'mailnow' => $discussion1reply2->mailnow,
            'children' => array(),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user3),
            'userpictureurl' => $userpictureurl
        );

        $userpictureurl = moodle_url::make_webservice_pluginfile_url(
            context_user::instance($discussion1reply1->userid)->id, 'user', 'icon', null, '/', 'f1')->out(false);

        $expectedposts['posts'][] = array(
            'id' => $discussion1reply1->id,
            'discussion' => $discussion1reply1->discussion,
            'parent' => $discussion1reply1->parent,
            'userid' => (int) $discussion1reply1->userid,
            'created' => $discussion1reply1->created,
            'modified' => $discussion1reply1->modified,
            'mailed' => $discussion1reply1->mailed,
            'subject' => $discussion1reply1->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply1->message, 'pluginfile.php',
                    $twf1context->id, 'mod_twf', 'post', $discussion1reply1->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply1->messagetrust,
            'attachment' => $discussion1reply1->attachment,
            'totalscore' => $discussion1reply1->totalscore,
            'mailnow' => $discussion1reply1->mailnow,
            'children' => array($discussion1reply2->id),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user2),
            'userpictureurl' => $userpictureurl
        );

        // Test a discussion with two additional posts (total 3 posts).
        $posts = mod_twf_external::get_twf_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_twf_external::get_twf_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));

        // Unset the initial discussion post.
        array_pop($posts['posts']);
        $this->assertEquals($expectedposts, $posts);

        // Test discussion without additional posts. There should be only one post (the one created by the discussion).
        $posts = mod_twf_external::get_twf_discussion_posts($discussion2->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_twf_external::get_twf_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

    }

    /**
     * Test get twf posts (qanda twf)
     */
    public function test_mod_twf_get_twf_discussion_posts_qanda() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        $record = new stdClass();
        $user1 = self::getDataGenerator()->create_user($record);
        $user2 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Forum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->type = 'qanda';
        $twf1 = self::getDataGenerator()->create_module('twf', $record);
        $twf1context = context_module::instance($twf1->cmid);

        // Add discussions to the twfs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->twf = $twf1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);

        // Add 1 reply (not the actual user).
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        // We still see only the original post.
        $posts = mod_twf_external::get_twf_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_twf_external::get_twf_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

        // Add a new reply, the user is going to be able to see only the original post and their new post.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user1->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        $posts = mod_twf_external::get_twf_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_twf_external::get_twf_discussion_posts_returns(), $posts);
        $this->assertEquals(2, count($posts['posts']));

        // Now, we can fake the time of the user post, so he can se the rest of the discussion posts.
        $discussion1reply2->created -= $CFG->maxeditingtime * 2;
        $DB->update_record('twf_posts', $discussion1reply2);

        $posts = mod_twf_external::get_twf_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_twf_external::get_twf_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));
    }

    /**
     * Test get twf discussions paginated
     */
    public function test_mod_twf_get_twf_discussions_paginated() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track twfs.
        $CFG->twf_trackreadposts = true;

        // Create a user who can track twfs.
        $record = new stdClass();
        $record->tracktwfs = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();

        // First twf with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = FORUM_TRACKING_OFF;
        $twf1 = self::getDataGenerator()->create_module('twf', $record);

        // Add discussions to the twfs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->twf = $twf1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_post($record);

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');

        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course1->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance1 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance1, $user1->id);

        // Delete one user.
        delete_user($user4);

        // Assign capabilities to view discussions for twf 1.
        $cm = get_coursemodule_from_id('twf', $twf1->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/twf:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the twfs.

        $post1 = $DB->get_record('twf_posts', array('id' => $discussion1->firstpost), '*', MUST_EXIST);
        $userpictureurl = moodle_url::make_webservice_pluginfile_url(
                    context_user::instance($user1->id)->id, 'user', 'icon', null, '/', 'f1');

        // We expect an empty URL since we deleted the user4.
        $usermodifiedpictureurl = '';

        $expecteddiscussions = array(
                'id' => $discussion1->firstpost,
                'name' => $discussion1->name,
                'groupid' => $discussion1->groupid,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'discussion' => $discussion1->id,
                'parent' => 0,
                'userid' => $discussion1->userid,
                'created' => $post1->created,
                'modified' => $post1->modified,
                'mailed' => $post1->mailed,
                'subject' => $post1->subject,
                'message' => $post1->message,
                'messageformat' => $post1->messageformat,
                'messagetrust' => $post1->messagetrust,
                'attachment' => $post1->attachment,
                'totalscore' => $post1->totalscore,
                'mailnow' => $post1->mailnow,
                'userfullname' => fullname($user1),
                'usermodifiedfullname' => fullname($user4),
                'userpictureurl' => $userpictureurl,
                'usermodifiedpictureurl' => $usermodifiedpictureurl,
                'numreplies' => 3,
                'numunread' => 0
            );

        // Call the external function passing twf id.
        $discussions = mod_twf_external::get_twf_discussions_paginated($twf1->id);
        $discussions = external_api::clean_returnvalue(mod_twf_external::get_twf_discussions_paginated_returns(), $discussions);
        $expectedreturn = array(
            'discussions' => array($expecteddiscussions),
            'warnings' => array()
        );
        $this->assertEquals($expectedreturn, $discussions);

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/twf:viewdiscussion', $context->id, $newrole);
        try {
            mod_twf_external::get_twf_discussions_paginated($twf1->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('noviewdiscussionspermission', $e->errorcode);
        }

        // Unenrol user from second course.
        $enrol->unenrol_user($instance1, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_twf_external::get_twf_discussions_paginated($twf1->id);
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Test get twf discussions paginated (qanda twfs)
     */
    public function test_mod_twf_get_twf_discussions_paginated_qanda() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // First twf with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'qanda';
        $twf = self::getDataGenerator()->create_module('twf', $record);

        // Add discussions to the twfs.
        $discussionrecord = new stdClass();
        $discussionrecord->course = $course->id;
        $discussionrecord->userid = $user2->id;
        $discussionrecord->twf = $twf->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_twf')->create_discussion($discussionrecord);

        self::setAdminUser();
        $discussions = mod_twf_external::get_twf_discussions_paginated($twf->id);
        $discussions = external_api::clean_returnvalue(mod_twf_external::get_twf_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

        self::setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        $discussions = mod_twf_external::get_twf_discussions_paginated($twf->id);
        $discussions = external_api::clean_returnvalue(mod_twf_external::get_twf_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

    }
}

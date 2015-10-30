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
 * The module teamworkforums tests
 *
 * @package    mod_teamworkforum
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/teamworkforum/lib.php');
require_once($CFG->dirroot . '/rating/lib.php');

class mod_teamworkforum_lib_testcase extends advanced_testcase {

    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_teamworkforum\subscriptions::reset_teamworkforum_cache();
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_teamworkforum\subscriptions::reset_teamworkforum_cache();
    }

    public function test_teamworkforum_trigger_content_uploaded_event() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', array('course' => $course->id));
        $context = context_module::instance($teamworkforum->cmid);

        $this->setUser($user->id);
        $fakepost = (object) array('id' => 123, 'message' => 'Yay!', 'discussion' => 100);
        $cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id);

        $fs = get_file_storage();
        $dummy = (object) array(
            'contextid' => $context->id,
            'component' => 'mod_teamworkforum',
            'filearea' => 'attachment',
            'itemid' => $fakepost->id,
            'filepath' => '/',
            'filename' => 'myassignmnent.pdf'
        );
        $fi = $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);

        $data = new stdClass();
        $sink = $this->redirectEvents();
        teamworkforum_trigger_content_uploaded_event($fakepost, $cm, 'some triggered from value');
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_teamworkforum\event\assessable_uploaded', $event);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($fakepost->id, $event->objectid);
        $this->assertEquals($fakepost->message, $event->other['content']);
        $this->assertEquals($fakepost->discussion, $event->other['discussionid']);
        $this->assertCount(1, $event->other['pathnamehashes']);
        $this->assertEquals($fi->get_pathnamehash(), $event->other['pathnamehashes'][0]);
        $expected = new stdClass();
        $expected->modulename = 'teamworkforum';
        $expected->name = 'some triggered from value';
        $expected->cmid = $teamworkforum->cmid;
        $expected->itemid = $fakepost->id;
        $expected->courseid = $course->id;
        $expected->userid = $user->id;
        $expected->content = $fakepost->message;
        $expected->pathnamehashes = array($fi->get_pathnamehash());
        $this->assertEventLegacyData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_teamworkforum_get_courses_user_posted_in() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        // Create 3 teamworkforums, one in each course.
        $record = new stdClass();
        $record->course = $course1->id;
        $teamworkforum1 = $this->getDataGenerator()->create_module('teamworkforum', $record);

        $record = new stdClass();
        $record->course = $course2->id;
        $teamworkforum2 = $this->getDataGenerator()->create_module('teamworkforum', $record);

        $record = new stdClass();
        $record->course = $course3->id;
        $teamworkforum3 = $this->getDataGenerator()->create_module('teamworkforum', $record);

        // Add a second teamworkforum in course 1.
        $record = new stdClass();
        $record->course = $course1->id;
        $teamworkforum4 = $this->getDataGenerator()->create_module('teamworkforum', $record);

        // Add discussions to course 1 started by user1.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->teamworkforum = $teamworkforum1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->teamworkforum = $teamworkforum4->id;
        $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_discussion($record);

        // Add discussions to course2 started by user1.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user1->id;
        $record->teamworkforum = $teamworkforum2->id;
        $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_discussion($record);

        // Add discussions to course 3 started by user2.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user2->id;
        $record->teamworkforum = $teamworkforum3->id;
        $discussion3 = $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_discussion($record);

        // Add post to course 3 by user1.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user1->id;
        $record->teamworkforum = $teamworkforum3->id;
        $record->discussion = $discussion3->id;
        $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_post($record);

        // User 3 hasn't posted anything, so shouldn't get any results.
        $user3courses = teamworkforum_get_courses_user_posted_in($user3);
        $this->assertEmpty($user3courses);

        // User 2 has only posted in course3.
        $user2courses = teamworkforum_get_courses_user_posted_in($user2);
        $this->assertCount(1, $user2courses);
        $user2course = array_shift($user2courses);
        $this->assertEquals($course3->id, $user2course->id);
        $this->assertEquals($course3->shortname, $user2course->shortname);

        // User 1 has posted in all 3 courses.
        $user1courses = teamworkforum_get_courses_user_posted_in($user1);
        $this->assertCount(3, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id, $course3->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname,
                $course3->shortname));

        }

        // User 1 has only started a discussion in course 1 and 2 though.
        $user1courses = teamworkforum_get_courses_user_posted_in($user1, true);
        $this->assertCount(2, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname));
        }
    }

    /**
     * Test the logic in the teamworkforum_tp_can_track_teamworkforums() function.
     */
    public function test_teamworkforum_tp_can_track_teamworkforums() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $teamworkforumoff = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $teamworkforumforce = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $teamworkforumoptional = $this->getDataGenerator()->create_module('teamworkforum', $options);

        // Allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 1;

        // User on, teamworkforum off, should be off.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, teamworkforum on, should be on.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, teamworkforum optional, should be on.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, teamworkforum off, should be off.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, teamworkforum force, should be on.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, teamworkforum optional, should be off.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 0;

        // User on, teamworkforum off, should be off.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, teamworkforum on, should be on.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, teamworkforum optional, should be on.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, teamworkforum off, should be off.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, teamworkforum force, should be off.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, teamworkforum optional, should be off.
        $result = teamworkforum_tp_can_track_teamworkforums($teamworkforumoptional, $useroff);
        $this->assertEquals(false, $result);

    }

    /**
     * Test the logic in the test_teamworkforum_tp_is_tracked() function.
     */
    public function test_teamworkforum_tp_is_tracked() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $teamworkforumoff = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $teamworkforumforce = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $teamworkforumoptional = $this->getDataGenerator()->create_module('teamworkforum', $options);

        // Allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 1;

        // User on, teamworkforum off, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, teamworkforum force, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, teamworkforum optional, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, teamworkforum off, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, teamworkforum force, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, teamworkforum optional, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 0;

        // User on, teamworkforum off, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, teamworkforum force, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, teamworkforum optional, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, teamworkforum off, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, teamworkforum force, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, teamworkforum optional, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Stop tracking so we can test again.
        teamworkforum_tp_stop_tracking($teamworkforumforce->id, $useron->id);
        teamworkforum_tp_stop_tracking($teamworkforumoptional->id, $useron->id);
        teamworkforum_tp_stop_tracking($teamworkforumforce->id, $useroff->id);
        teamworkforum_tp_stop_tracking($teamworkforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 1;

        // User on, preference off, teamworkforum force, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, preference off, teamworkforum optional, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, teamworkforum force, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, preference off, teamworkforum optional, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 0;

        // User on, preference off, teamworkforum force, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useron);
        $this->assertEquals(false, $result);

        // User on, preference off, teamworkforum optional, should be on.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, teamworkforum force, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, preference off, teamworkforum optional, should be off.
        $result = teamworkforum_tp_is_tracked($teamworkforumoptional, $useroff);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in the teamworkforum_tp_get_course_unread_posts() function.
     */
    public function test_teamworkforum_tp_get_course_unread_posts() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $teamworkforumoff = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $teamworkforumforce = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $teamworkforumoptional = $this->getDataGenerator()->create_module('teamworkforum', $options);

        // Add discussions to the tracking off teamworkforum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->teamworkforum = $teamworkforumoff->id;
        $discussionoff = $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_discussion($record);

        // Add discussions to the tracking forced teamworkforum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->teamworkforum = $teamworkforumforce->id;
        $discussionforce = $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_discussion($record);

        // Add post to the tracking forced discussion.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useroff->id;
        $record->teamworkforum = $teamworkforumforce->id;
        $record->discussion = $discussionforce->id;
        $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_post($record);

        // Add discussions to the tracking optional teamworkforum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->teamworkforum = $teamworkforumoptional->id;
        $discussionoptional = $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum')->create_discussion($record);

        // Allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 1;

        $result = teamworkforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(2, $result[$teamworkforumforce->id]->unread);
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));
        $this->assertEquals(1, $result[$teamworkforumoptional->id]->unread);

        $result = teamworkforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(2, $result[$teamworkforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$teamworkforumoptional->id]));

        // Don't allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 0;

        $result = teamworkforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(2, $result[$teamworkforumforce->id]->unread);
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));
        $this->assertEquals(1, $result[$teamworkforumoptional->id]->unread);

        $result = teamworkforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(false, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(false, isset($result[$teamworkforumoptional->id]));

        // Stop tracking so we can test again.
        teamworkforum_tp_stop_tracking($teamworkforumforce->id, $useron->id);
        teamworkforum_tp_stop_tracking($teamworkforumoptional->id, $useron->id);
        teamworkforum_tp_stop_tracking($teamworkforumforce->id, $useroff->id);
        teamworkforum_tp_stop_tracking($teamworkforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 1;

        $result = teamworkforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(2, $result[$teamworkforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$teamworkforumoptional->id]));

        $result = teamworkforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(2, $result[$teamworkforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$teamworkforumoptional->id]));

        // Don't allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 0;

        $result = teamworkforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(false, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(false, isset($result[$teamworkforumoptional->id]));

        $result = teamworkforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(false, isset($result[$teamworkforumforce->id]));
        $this->assertEquals(false, isset($result[$teamworkforumoptional->id]));
    }

    /**
     * Test the logic in the test_teamworkforum_tp_get_untracked_teamworkforums() function.
     */
    public function test_teamworkforum_tp_get_untracked_teamworkforums() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackteamworkforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $teamworkforumoff = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $teamworkforumforce = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $teamworkforumoptional = $this->getDataGenerator()->create_module('teamworkforum', $options);

        // Allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 1;

        // On user with force on.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));

        // Off user with force on.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));

        // Don't allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 0;

        // On user with force off.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));

        // Off user with force off.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));

        // Stop tracking so we can test again.
        teamworkforum_tp_stop_tracking($teamworkforumforce->id, $useron->id);
        teamworkforum_tp_stop_tracking($teamworkforumoptional->id, $useron->id);
        teamworkforum_tp_stop_tracking($teamworkforumforce->id, $useroff->id);
        teamworkforum_tp_stop_tracking($teamworkforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 1;

        // On user with force on.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));

        // Off user with force on.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));

        // Don't allow force.
        $CFG->teamworkforum_allowforcedreadtracking = 0;

        // On user with force off.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useron->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));

        // Off user with force off.
        $result = teamworkforum_tp_get_untracked_teamworkforums($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$teamworkforumoff->id]));
        $this->assertEquals(true, isset($result[$teamworkforumoptional->id]));
        $this->assertEquals(true, isset($result[$teamworkforumforce->id]));
    }

    /**
     * Test subscription using automatic subscription on create.
     */
    public function test_teamworkforum_auto_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_INITIALSUBSCRIBE); // Automatic Subscription.
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $result = \mod_teamworkforum\subscriptions::fetch_subscribed_users($teamworkforum);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_teamworkforum\subscriptions::is_subscribed($user->id, $teamworkforum));
        }
    }

    /**
     * Test subscription using forced subscription on create.
     */
    public function test_teamworkforum_forced_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_FORCESUBSCRIBE); // Forced subscription.
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $result = \mod_teamworkforum\subscriptions::fetch_subscribed_users($teamworkforum);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_teamworkforum\subscriptions::is_subscribed($user->id, $teamworkforum));
        }
    }

    /**
     * Test subscription using optional subscription on create.
     */
    public function test_teamworkforum_optional_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE); // Subscription optional.
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $result = \mod_teamworkforum\subscriptions::fetch_subscribed_users($teamworkforum);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_teamworkforum\subscriptions::is_subscribed($user->id, $teamworkforum));
        }
    }

    /**
     * Test subscription using disallow subscription on create.
     */
    public function test_teamworkforum_disallow_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_DISALLOWSUBSCRIBE); // Subscription prevented.
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', $options);

        $result = \mod_teamworkforum\subscriptions::fetch_subscribed_users($teamworkforum);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_teamworkforum\subscriptions::is_subscribed($user->id, $teamworkforum));
        }
    }

    /**
     * Test that context fetching returns the appropriate context.
     */
    public function test_teamworkforum_get_context() {
        global $DB, $PAGE;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', $options);
        $teamworkforumcm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id);
        $teamworkforumcontext = \context_module::instance($teamworkforumcm->id);

        // First check that specifying the context results in the correct context being returned.
        // Do this before we set up the page object and we should return from the coursemodule record.
        // There should be no DB queries here because the context type was correct.
        $startcount = $DB->perf_get_reads();
        $result = teamworkforum_get_context($teamworkforum->id, $teamworkforumcontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($teamworkforumcontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // And a context which is not the correct type.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = teamworkforum_get_context($teamworkforum->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($teamworkforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = teamworkforum_get_context($teamworkforum->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($teamworkforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Set up the default page event to use the teamworkforum.
        $PAGE = new moodle_page();
        $PAGE->set_context($teamworkforumcontext);
        $PAGE->set_cm($teamworkforumcm, $course, $teamworkforum);

        // Now specify a context which is not a context_module.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = teamworkforum_get_context($teamworkforum->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($teamworkforumcontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now do not specify a context at all.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = teamworkforum_get_context($teamworkforum->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($teamworkforumcontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now specify the page context of the course instead..
        $PAGE = new moodle_page();
        $PAGE->set_context($coursecontext);

        // Now specify a context which is not a context_module.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = teamworkforum_get_context($teamworkforum->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($teamworkforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = teamworkforum_get_context($teamworkforum->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($teamworkforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_teamworkforum_get_neighbours() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Setup test data.
        $teamworkforumgen = $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id);
        $context = context_module::instance($cm->id);

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->teamworkforum = $teamworkforum->id;
        $disc1 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $disc2 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $disc3 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $disc4 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $disc5 = $teamworkforumgen->create_discussion($record);

        // Getting the neighbours.
        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc2);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc3);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEquals($disc4->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc4);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEquals($disc5->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc5);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Post in some discussions. We manually update the discussion record because
        // the data generator plays with timemodified in a way that would break this test.
        sleep(1);
        $disc1->timemodified = time();
        $DB->update_record('teamworkforum_discussions', $disc1);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc5);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEquals($disc1->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc1);
        $this->assertEquals($disc5->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // After some discussions were created.
        sleep(1);
        $disc6 = $teamworkforumgen->create_discussion($record);
        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc6);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        sleep(1);
        $disc7 = $teamworkforumgen->create_discussion($record);
        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc7);
        $this->assertEquals($disc6->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Adding timed discussions.
        $CFG->teamworkforum_enabletimedposts = true;
        $now = time();
        $past = $now - 60;
        $future = $now + 60;

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->teamworkforum = $teamworkforum->id;
        $record->timestart = $past;
        $record->timeend = $future;
        sleep(1);
        $disc8 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $record->timestart = $future;
        $record->timeend = 0;
        $disc9 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $record->timestart = 0;
        $record->timeend = 0;
        $disc10 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $record->timestart = 0;
        $record->timeend = $past;
        $disc11 = $teamworkforumgen->create_discussion($record);
        sleep(1);
        $record->timestart = $past;
        $record->timeend = $future;
        $disc12 = $teamworkforumgen->create_discussion($record);

        // Admin user ignores the timed settings of discussions.
        $this->setAdminUser();
        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc8);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc9);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc10);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc11);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user can see their own timed discussions.
        $this->setUser($user);
        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc8);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc9);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc10);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc11);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user does not ignore timed settings.
        $this->setUser($user2);
        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc8);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc10);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc12);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Reset to normal mode.
        $CFG->teamworkforum_enabletimedposts = false;
        $this->setAdminUser();

        // Two discussions with identical timemodified ignore each other.
        sleep(1);
        $now = time();
        $DB->update_record('teamworkforum_discussions', (object) array('id' => $disc3->id, 'timemodified' => $now));
        $DB->update_record('teamworkforum_discussions', (object) array('id' => $disc2->id, 'timemodified' => $now));
        $disc2 = $DB->get_record('teamworkforum_discussions', array('id' => $disc2->id));
        $disc3 = $DB->get_record('teamworkforum_discussions', array('id' => $disc3->id));

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc2);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $neighbours = teamworkforum_get_discussion_neighbours($cm, $disc3);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_teamworkforum_get_neighbours_with_groups() {
        $this->resetAfterTest();

        // Setup test data.
        $teamworkforumgen = $this->getDataGenerator()->get_plugin_generator('mod_teamworkforum');
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $user1->id, 'groupid' => $group1->id));

        $teamworkforum1 = $this->getDataGenerator()->create_module('teamworkforum', array('course' => $course->id, 'groupmode' => VISIBLEGROUPS));
        $teamworkforum2 = $this->getDataGenerator()->create_module('teamworkforum', array('course' => $course->id, 'groupmode' => SEPARATEGROUPS));
        $cm1 = get_coursemodule_from_instance('teamworkforum', $teamworkforum1->id);
        $cm2 = get_coursemodule_from_instance('teamworkforum', $teamworkforum2->id);
        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);

        // Creating discussions in both teamworkforums.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user1->id;
        $record->teamworkforum = $teamworkforum1->id;
        $record->groupid = $group1->id;
        $disc11 = $teamworkforumgen->create_discussion($record);
        $record->teamworkforum = $teamworkforum2->id;
        $disc21 = $teamworkforumgen->create_discussion($record);

        sleep(1);
        $record->userid = $user2->id;
        $record->teamworkforum = $teamworkforum1->id;
        $record->groupid = $group2->id;
        $disc12 = $teamworkforumgen->create_discussion($record);
        $record->teamworkforum = $teamworkforum2->id;
        $disc22 = $teamworkforumgen->create_discussion($record);

        sleep(1);
        $record->userid = $user1->id;
        $record->teamworkforum = $teamworkforum1->id;
        $record->groupid = null;
        $disc13 = $teamworkforumgen->create_discussion($record);
        $record->teamworkforum = $teamworkforum2->id;
        $disc23 = $teamworkforumgen->create_discussion($record);

        sleep(1);
        $record->userid = $user2->id;
        $record->teamworkforum = $teamworkforum1->id;
        $record->groupid = $group2->id;
        $disc14 = $teamworkforumgen->create_discussion($record);
        $record->teamworkforum = $teamworkforum2->id;
        $disc24 = $teamworkforumgen->create_discussion($record);

        sleep(1);
        $record->userid = $user1->id;
        $record->teamworkforum = $teamworkforum1->id;
        $record->groupid = $group1->id;
        $disc15 = $teamworkforumgen->create_discussion($record);
        $record->teamworkforum = $teamworkforum2->id;
        $disc25 = $teamworkforumgen->create_discussion($record);

        // Admin user can see all groups.
        $this->setAdminUser();
        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc11);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc12->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc21);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc22->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc22);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc23);
        $this->assertEquals($disc22->id, $neighbours['prev']->id);
        $this->assertEquals($disc24->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc14);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc24);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc15);
        $this->assertEquals($disc14->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc25);
        $this->assertEquals($disc24->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Admin user is only viewing group 1.
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc11);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc21);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc23);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc15);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc25);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user viewing non-grouped posts (this is only possible in visible groups).
        $this->setUser($user1);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm1, true));

        // They can see anything in visible groups.
        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);

        // Normal user, orphan of groups, can only see non-grouped posts in separate groups.
        $this->setUser($user2);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm2, true));

        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc23);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEmpty($neighbours['next']);

        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc22);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc24);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Switching to viewing group 1.
        $this->setUser($user1);
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        // They can see non-grouped or same group.
        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc11);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc21);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc23);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = teamworkforum_get_discussion_neighbours($cm1, $disc15);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = teamworkforum_get_discussion_neighbours($cm2, $disc25);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Querying the neighbours of a discussion passing the wrong CM.
        $this->setExpectedException('coding_exception');
        teamworkforum_get_discussion_neighbours($cm2, $disc11);
    }

    public function test_count_discussion_replies_basic() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);

        // Count the discussion replies in the teamworkforum.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_limited() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits shouldn't make a difference.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id, "", 20);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding paging shouldn't make any difference.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id, "", -1, 0, 100);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated_sorted() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Specifying the teamworkforumsort should also give a good result. This follows a different path.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id, "d.id asc", -1, 0, 100);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a teamworkforumsort shouldn't make a difference.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id, "d.id asc", 20);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id, "d.id asc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small_reverse() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id, "d.id desc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted_small_reverse() {
        list($teamworkforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a teamworkforumsort shouldn't make a difference.
        $result = teamworkforum_count_discussion_replies($teamworkforum->id, "d.id desc", 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_teamworkforum_view() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', array('course' => $course->id),
                                                            array('completion' => 2, 'completionview' => 1));
        $context = context_module::instance($teamworkforum->cmid);
        $cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        teamworkforum_view($teamworkforum, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_teamworkforum\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/teamworkforum/view.php', array('f' => $teamworkforum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);

    }

    /**
     * Test teamworkforum_discussion_view.
     */
    public function test_teamworkforum_discussion_view() {
        global $CFG, $USER;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', array('course' => $course->id));
        $discussion = $this->create_single_discussion_with_replies($teamworkforum, $USER, 2);

        $context = context_module::instance($teamworkforum->cmid);
        $cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        teamworkforum_discussion_view($context, $teamworkforum, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_teamworkforum\event\discussion_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'teamworkforum', 'view discussion', "discuss.php?d={$discussion->id}",
            $discussion->id, $teamworkforum->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());

    }

    /**
     * Create a new course, teamworkforum, and user with a number of discussions and replies.
     *
     * @param int $discussioncount The number of discussions to create
     * @param int $replycount The number of replies to create in each discussion
     * @return array Containing the created teamworkforum object, and the ids of the created discussions.
     */
    protected function create_multiple_discussions_with_replies($discussioncount, $replycount) {
        $this->resetAfterTest();

        // Setup the content.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $record = new stdClass();
        $record->course = $course->id;
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', $record);

        // Create 10 discussions with replies.
        $discussionids = array();
        for ($i = 0; $i < $discussioncount; $i++) {
            $discussion = $this->create_single_discussion_with_replies($teamworkforum, $user, $replycount);
            $discussionids[] = $discussion->id;
        }
        return array($teamworkforum, $discussionids);
    }

    /**
     * Create a discussion with a number of replies.
     *
     * @param object $teamworkforum The teamworkforum which has been created
     * @param object $user The user making the discussion and replies
     * @param int $replycount The number of replies
     * @return object $discussion
     */
    protected function create_single_discussion_with_replies($teamworkforum, $user, $replycount) {
        global $DB;

        $generator = self::getDataGenerator()->get_plugin_generator('mod_teamworkforum');

        $record = new stdClass();
        $record->course = $teamworkforum->course;
        $record->teamworkforum = $teamworkforum->id;
        $record->userid = $user->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $replyto = $DB->get_record('teamworkforum_posts', array('discussion' => $discussion->id));

        // Create the replies.
        $post = new stdClass();
        $post->userid = $user->id;
        $post->discussion = $discussion->id;
        $post->parent = $replyto->id;

        for ($i = 0; $i < $replycount; $i++) {
            $generator->create_post($post);
        }

        return $discussion;
    }

    /**
     * Tests for mod_teamworkforum_rating_can_see_item_ratings().
     *
     * @throws coding_exception
     * @throws rating_exception
     */
    public function test_mod_teamworkforum_rating_can_see_item_ratings() {
        global $DB;

        $this->resetAfterTest();

        // Setup test data.
        $course = new stdClass();
        $course->groupmode = SEPARATEGROUPS;
        $course->groupmodeforce = true;
        $course = $this->getDataGenerator()->create_course($course);
        $teamworkforum = $this->getDataGenerator()->create_module('teamworkforum', array('course' => $course->id));
        $generator = self::getDataGenerator()->get_plugin_generator('mod_teamworkforum');
        $cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id);
        $context = context_module::instance($cm->id);

        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        // Groups and stuff.
        $role = $DB->get_record('role', array('shortname' => 'teacher'), '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user4->id, $course->id, $role->id);

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group1, $user1);
        groups_add_member($group1, $user2);
        groups_add_member($group2, $user3);
        groups_add_member($group2, $user4);

        $record = new stdClass();
        $record->course = $teamworkforum->course;
        $record->teamworkforum = $teamworkforum->id;
        $record->userid = $user1->id;
        $record->groupid = $group1->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $post = $DB->get_record('teamworkforum_posts', array('discussion' => $discussion->id));

        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->component = 'mod_teamworkforum';
        $ratingoptions->itemid  = $post->id;
        $ratingoptions->scaleid = 2;
        $ratingoptions->userid  = $user2->id;
        $rating = new rating($ratingoptions);
        $rating->update_rating(2);

        // Now try to access it as various users.
        unassign_capability('moodle/site:accessallgroups', $role->id);
        $params = array('contextid' => 2,
                        'component' => 'mod_teamworkforum',
                        'ratingarea' => 'post',
                        'itemid' => $post->id,
                        'scaleid' => 2);
        $this->setUser($user1);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertFalse(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertFalse(mod_teamworkforum_rating_can_see_item_ratings($params));

        // Now try with accessallgroups cap and make sure everything is visible.
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role->id, $context->id);
        $this->setUser($user1);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));

        // Change group mode and verify visibility.
        $course->groupmode = VISIBLEGROUPS;
        $DB->update_record('course', $course);
        unassign_capability('moodle/site:accessallgroups', $role->id);
        $this->setUser($user1);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertTrue(mod_teamworkforum_rating_can_see_item_ratings($params));

    }

}

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
 * The module quoras tests
 *
 * @package    mod_quora
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quora/lib.php');

class mod_quora_lib_testcase extends advanced_testcase {

    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_quora\subscriptions::reset_quora_cache();
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_quora\subscriptions::reset_quora_cache();
    }

    public function test_quora_trigger_content_uploaded_event() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);

        $this->setUser($user->id);
        $fakepost = (object) array('id' => 123, 'message' => 'Yay!', 'discussion' => 100);
        $cm = get_coursemodule_from_instance('quora', $quora->id);

        $fs = get_file_storage();
        $dummy = (object) array(
            'contextid' => $context->id,
            'component' => 'mod_quora',
            'filearea' => 'attachment',
            'itemid' => $fakepost->id,
            'filepath' => '/',
            'filename' => 'myassignmnent.pdf'
        );
        $fi = $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);

        $data = new stdClass();
        $sink = $this->redirectEvents();
        quora_trigger_content_uploaded_event($fakepost, $cm, 'some triggered from value');
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_quora\event\assessable_uploaded', $event);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($fakepost->id, $event->objectid);
        $this->assertEquals($fakepost->message, $event->other['content']);
        $this->assertEquals($fakepost->discussion, $event->other['discussionid']);
        $this->assertCount(1, $event->other['pathnamehashes']);
        $this->assertEquals($fi->get_pathnamehash(), $event->other['pathnamehashes'][0]);
        $expected = new stdClass();
        $expected->modulename = 'quora';
        $expected->name = 'some triggered from value';
        $expected->cmid = $quora->cmid;
        $expected->itemid = $fakepost->id;
        $expected->courseid = $course->id;
        $expected->userid = $user->id;
        $expected->content = $fakepost->message;
        $expected->pathnamehashes = array($fi->get_pathnamehash());
        $this->assertEventLegacyData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_quora_get_courses_user_posted_in() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        // Create 3 quoras, one in each course.
        $record = new stdClass();
        $record->course = $course1->id;
        $quora1 = $this->getDataGenerator()->create_module('quora', $record);

        $record = new stdClass();
        $record->course = $course2->id;
        $quora2 = $this->getDataGenerator()->create_module('quora', $record);

        $record = new stdClass();
        $record->course = $course3->id;
        $quora3 = $this->getDataGenerator()->create_module('quora', $record);

        // Add a second quora in course 1.
        $record = new stdClass();
        $record->course = $course1->id;
        $quora4 = $this->getDataGenerator()->create_module('quora', $record);

        // Add discussions to course 1 started by user1.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->quora = $quora1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->quora = $quora4->id;
        $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add discussions to course2 started by user1.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user1->id;
        $record->quora = $quora2->id;
        $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add discussions to course 3 started by user2.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user2->id;
        $record->quora = $quora3->id;
        $discussion3 = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add post to course 3 by user1.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user1->id;
        $record->quora = $quora3->id;
        $record->discussion = $discussion3->id;
        $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // User 3 hasn't posted anything, so shouldn't get any results.
        $user3courses = quora_get_courses_user_posted_in($user3);
        $this->assertEmpty($user3courses);

        // User 2 has only posted in course3.
        $user2courses = quora_get_courses_user_posted_in($user2);
        $this->assertCount(1, $user2courses);
        $user2course = array_shift($user2courses);
        $this->assertEquals($course3->id, $user2course->id);
        $this->assertEquals($course3->shortname, $user2course->shortname);

        // User 1 has posted in all 3 courses.
        $user1courses = quora_get_courses_user_posted_in($user1);
        $this->assertCount(3, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id, $course3->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname,
                $course3->shortname));

        }

        // User 1 has only started a discussion in course 1 and 2 though.
        $user1courses = quora_get_courses_user_posted_in($user1, true);
        $this->assertCount(2, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname));
        }
    }

    /**
     * Test the logic in the quora_tp_can_track_quoras() function.
     */
    public function test_quora_tp_can_track_quoras() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackquoras' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackquoras' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $quoraoff = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $quoraforce = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $quoraoptional = $this->getDataGenerator()->create_module('quora', $options);

        // Allow force.
        $CFG->quora_allowforcedreadtracking = 1;

        // User on, quora off, should be off.
        $result = quora_tp_can_track_quoras($quoraoff, $useron);
        $this->assertEquals(false, $result);

        // User on, quora on, should be on.
        $result = quora_tp_can_track_quoras($quoraforce, $useron);
        $this->assertEquals(true, $result);

        // User on, quora optional, should be on.
        $result = quora_tp_can_track_quoras($quoraoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, quora off, should be off.
        $result = quora_tp_can_track_quoras($quoraoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, quora force, should be on.
        $result = quora_tp_can_track_quoras($quoraforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, quora optional, should be off.
        $result = quora_tp_can_track_quoras($quoraoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->quora_allowforcedreadtracking = 0;

        // User on, quora off, should be off.
        $result = quora_tp_can_track_quoras($quoraoff, $useron);
        $this->assertEquals(false, $result);

        // User on, quora on, should be on.
        $result = quora_tp_can_track_quoras($quoraforce, $useron);
        $this->assertEquals(true, $result);

        // User on, quora optional, should be on.
        $result = quora_tp_can_track_quoras($quoraoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, quora off, should be off.
        $result = quora_tp_can_track_quoras($quoraoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, quora force, should be off.
        $result = quora_tp_can_track_quoras($quoraforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, quora optional, should be off.
        $result = quora_tp_can_track_quoras($quoraoptional, $useroff);
        $this->assertEquals(false, $result);

    }

    /**
     * Test the logic in the test_quora_tp_is_tracked() function.
     */
    public function test_quora_tp_is_tracked() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackquoras' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackquoras' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $quoraoff = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $quoraforce = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $quoraoptional = $this->getDataGenerator()->create_module('quora', $options);

        // Allow force.
        $CFG->quora_allowforcedreadtracking = 1;

        // User on, quora off, should be off.
        $result = quora_tp_is_tracked($quoraoff, $useron);
        $this->assertEquals(false, $result);

        // User on, quora force, should be on.
        $result = quora_tp_is_tracked($quoraforce, $useron);
        $this->assertEquals(true, $result);

        // User on, quora optional, should be on.
        $result = quora_tp_is_tracked($quoraoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, quora off, should be off.
        $result = quora_tp_is_tracked($quoraoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, quora force, should be on.
        $result = quora_tp_is_tracked($quoraforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, quora optional, should be off.
        $result = quora_tp_is_tracked($quoraoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->quora_allowforcedreadtracking = 0;

        // User on, quora off, should be off.
        $result = quora_tp_is_tracked($quoraoff, $useron);
        $this->assertEquals(false, $result);

        // User on, quora force, should be on.
        $result = quora_tp_is_tracked($quoraforce, $useron);
        $this->assertEquals(true, $result);

        // User on, quora optional, should be on.
        $result = quora_tp_is_tracked($quoraoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, quora off, should be off.
        $result = quora_tp_is_tracked($quoraoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, quora force, should be off.
        $result = quora_tp_is_tracked($quoraforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, quora optional, should be off.
        $result = quora_tp_is_tracked($quoraoptional, $useroff);
        $this->assertEquals(false, $result);

        // Stop tracking so we can test again.
        quora_tp_stop_tracking($quoraforce->id, $useron->id);
        quora_tp_stop_tracking($quoraoptional->id, $useron->id);
        quora_tp_stop_tracking($quoraforce->id, $useroff->id);
        quora_tp_stop_tracking($quoraoptional->id, $useroff->id);

        // Allow force.
        $CFG->quora_allowforcedreadtracking = 1;

        // User on, preference off, quora force, should be on.
        $result = quora_tp_is_tracked($quoraforce, $useron);
        $this->assertEquals(true, $result);

        // User on, preference off, quora optional, should be on.
        $result = quora_tp_is_tracked($quoraoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, quora force, should be on.
        $result = quora_tp_is_tracked($quoraforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, preference off, quora optional, should be off.
        $result = quora_tp_is_tracked($quoraoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->quora_allowforcedreadtracking = 0;

        // User on, preference off, quora force, should be on.
        $result = quora_tp_is_tracked($quoraforce, $useron);
        $this->assertEquals(false, $result);

        // User on, preference off, quora optional, should be on.
        $result = quora_tp_is_tracked($quoraoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, quora force, should be off.
        $result = quora_tp_is_tracked($quoraforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, preference off, quora optional, should be off.
        $result = quora_tp_is_tracked($quoraoptional, $useroff);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in the quora_tp_get_course_unread_posts() function.
     */
    public function test_quora_tp_get_course_unread_posts() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackquoras' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackquoras' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $quoraoff = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $quoraforce = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $quoraoptional = $this->getDataGenerator()->create_module('quora', $options);

        // Add discussions to the tracking off quora.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->quora = $quoraoff->id;
        $discussionoff = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add discussions to the tracking forced quora.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->quora = $quoraforce->id;
        $discussionforce = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add post to the tracking forced discussion.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useroff->id;
        $record->quora = $quoraforce->id;
        $record->discussion = $discussionforce->id;
        $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // Add discussions to the tracking optional quora.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->quora = $quoraoptional->id;
        $discussionoptional = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Allow force.
        $CFG->quora_allowforcedreadtracking = 1;

        $result = quora_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));
        $this->assertEquals(2, $result[$quoraforce->id]->unread);
        $this->assertEquals(true, isset($result[$quoraoptional->id]));
        $this->assertEquals(1, $result[$quoraoptional->id]->unread);

        $result = quora_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));
        $this->assertEquals(2, $result[$quoraforce->id]->unread);
        $this->assertEquals(false, isset($result[$quoraoptional->id]));

        // Don't allow force.
        $CFG->quora_allowforcedreadtracking = 0;

        $result = quora_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));
        $this->assertEquals(2, $result[$quoraforce->id]->unread);
        $this->assertEquals(true, isset($result[$quoraoptional->id]));
        $this->assertEquals(1, $result[$quoraoptional->id]->unread);

        $result = quora_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(false, isset($result[$quoraforce->id]));
        $this->assertEquals(false, isset($result[$quoraoptional->id]));

        // Stop tracking so we can test again.
        quora_tp_stop_tracking($quoraforce->id, $useron->id);
        quora_tp_stop_tracking($quoraoptional->id, $useron->id);
        quora_tp_stop_tracking($quoraforce->id, $useroff->id);
        quora_tp_stop_tracking($quoraoptional->id, $useroff->id);

        // Allow force.
        $CFG->quora_allowforcedreadtracking = 1;

        $result = quora_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));
        $this->assertEquals(2, $result[$quoraforce->id]->unread);
        $this->assertEquals(false, isset($result[$quoraoptional->id]));

        $result = quora_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));
        $this->assertEquals(2, $result[$quoraforce->id]->unread);
        $this->assertEquals(false, isset($result[$quoraoptional->id]));

        // Don't allow force.
        $CFG->quora_allowforcedreadtracking = 0;

        $result = quora_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(false, isset($result[$quoraforce->id]));
        $this->assertEquals(false, isset($result[$quoraoptional->id]));

        $result = quora_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$quoraoff->id]));
        $this->assertEquals(false, isset($result[$quoraforce->id]));
        $this->assertEquals(false, isset($result[$quoraoptional->id]));
    }

    /**
     * Test the logic in the test_quora_tp_get_untracked_quoras() function.
     */
    public function test_quora_tp_get_untracked_quoras() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackquoras' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackquoras' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF); // Off.
        $quoraoff = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED); // On.
        $quoraforce = $this->getDataGenerator()->create_module('quora', $options);

        $options = array('course' => $course->id, 'trackingtype' => FORUM_TRACKING_OPTIONAL); // Optional.
        $quoraoptional = $this->getDataGenerator()->create_module('quora', $options);

        // Allow force.
        $CFG->quora_allowforcedreadtracking = 1;

        // On user with force on.
        $result = quora_tp_get_untracked_quoras($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));

        // Off user with force on.
        $result = quora_tp_get_untracked_quoras($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraoptional->id]));

        // Don't allow force.
        $CFG->quora_allowforcedreadtracking = 0;

        // On user with force off.
        $result = quora_tp_get_untracked_quoras($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));

        // Off user with force off.
        $result = quora_tp_get_untracked_quoras($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraoptional->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));

        // Stop tracking so we can test again.
        quora_tp_stop_tracking($quoraforce->id, $useron->id);
        quora_tp_stop_tracking($quoraoptional->id, $useron->id);
        quora_tp_stop_tracking($quoraforce->id, $useroff->id);
        quora_tp_stop_tracking($quoraoptional->id, $useroff->id);

        // Allow force.
        $CFG->quora_allowforcedreadtracking = 1;

        // On user with force on.
        $result = quora_tp_get_untracked_quoras($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraoptional->id]));

        // Off user with force on.
        $result = quora_tp_get_untracked_quoras($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraoptional->id]));

        // Don't allow force.
        $CFG->quora_allowforcedreadtracking = 0;

        // On user with force off.
        $result = quora_tp_get_untracked_quoras($useron->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraoptional->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));

        // Off user with force off.
        $result = quora_tp_get_untracked_quoras($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$quoraoff->id]));
        $this->assertEquals(true, isset($result[$quoraoptional->id]));
        $this->assertEquals(true, isset($result[$quoraforce->id]));
    }

    /**
     * Test subscription using automatic subscription on create.
     */
    public function test_quora_auto_subscribe_on_create() {
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
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        $result = \mod_quora\subscriptions::fetch_subscribed_users($quora);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_quora\subscriptions::is_subscribed($user->id, $quora));
        }
    }

    /**
     * Test subscription using forced subscription on create.
     */
    public function test_quora_forced_subscribe_on_create() {
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
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        $result = \mod_quora\subscriptions::fetch_subscribed_users($quora);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_quora\subscriptions::is_subscribed($user->id, $quora));
        }
    }

    /**
     * Test subscription using optional subscription on create.
     */
    public function test_quora_optional_subscribe_on_create() {
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
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        $result = \mod_quora\subscriptions::fetch_subscribed_users($quora);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_quora\subscriptions::is_subscribed($user->id, $quora));
        }
    }

    /**
     * Test subscription using disallow subscription on create.
     */
    public function test_quora_disallow_subscribe_on_create() {
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
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        $result = \mod_quora\subscriptions::fetch_subscribed_users($quora);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_quora\subscriptions::is_subscribed($user->id, $quora));
        }
    }

    /**
     * Test that context fetching returns the appropriate context.
     */
    public function test_quora_get_context() {
        global $DB, $PAGE;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);
        $quoracm = get_coursemodule_from_instance('quora', $quora->id);
        $quoracontext = \context_module::instance($quoracm->id);

        // First check that specifying the context results in the correct context being returned.
        // Do this before we set up the page object and we should return from the coursemodule record.
        // There should be no DB queries here because the context type was correct.
        $startcount = $DB->perf_get_reads();
        $result = quora_get_context($quora->id, $quoracontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($quoracontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // And a context which is not the correct type.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = quora_get_context($quora->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($quoracontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = quora_get_context($quora->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($quoracontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Set up the default page event to use the quora.
        $PAGE = new moodle_page();
        $PAGE->set_context($quoracontext);
        $PAGE->set_cm($quoracm, $course, $quora);

        // Now specify a context which is not a context_module.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = quora_get_context($quora->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($quoracontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now do not specify a context at all.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = quora_get_context($quora->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($quoracontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now specify the page context of the course instead..
        $PAGE = new moodle_page();
        $PAGE->set_context($coursecontext);

        // Now specify a context which is not a context_module.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = quora_get_context($quora->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($quoracontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = quora_get_context($quora->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($quoracontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_quora_get_neighbours() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Setup test data.
        $quoragen = $this->getDataGenerator()->get_plugin_generator('mod_quora');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('quora', $quora->id);
        $context = context_module::instance($cm->id);

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->quora = $quora->id;
        $disc1 = $quoragen->create_discussion($record);
        sleep(1);
        $disc2 = $quoragen->create_discussion($record);
        sleep(1);
        $disc3 = $quoragen->create_discussion($record);
        sleep(1);
        $disc4 = $quoragen->create_discussion($record);
        sleep(1);
        $disc5 = $quoragen->create_discussion($record);

        // Getting the neighbours.
        $neighbours = quora_get_discussion_neighbours($cm, $disc1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc2);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc3);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEquals($disc4->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc4);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEquals($disc5->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc5);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Post in some discussions. We manually update the discussion record because
        // the data generator plays with timemodified in a way that would break this test.
        sleep(1);
        $disc1->timemodified = time();
        $DB->update_record('quora_discussions', $disc1);

        $neighbours = quora_get_discussion_neighbours($cm, $disc5);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEquals($disc1->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc1);
        $this->assertEquals($disc5->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // After some discussions were created.
        sleep(1);
        $disc6 = $quoragen->create_discussion($record);
        $neighbours = quora_get_discussion_neighbours($cm, $disc6);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        sleep(1);
        $disc7 = $quoragen->create_discussion($record);
        $neighbours = quora_get_discussion_neighbours($cm, $disc7);
        $this->assertEquals($disc6->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Adding timed discussions.
        $CFG->quora_enabletimedposts = true;
        $now = time();
        $past = $now - 60;
        $future = $now + 60;

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->quora = $quora->id;
        $record->timestart = $past;
        $record->timeend = $future;
        sleep(1);
        $disc8 = $quoragen->create_discussion($record);
        sleep(1);
        $record->timestart = $future;
        $record->timeend = 0;
        $disc9 = $quoragen->create_discussion($record);
        sleep(1);
        $record->timestart = 0;
        $record->timeend = 0;
        $disc10 = $quoragen->create_discussion($record);
        sleep(1);
        $record->timestart = 0;
        $record->timeend = $past;
        $disc11 = $quoragen->create_discussion($record);
        sleep(1);
        $record->timestart = $past;
        $record->timeend = $future;
        $disc12 = $quoragen->create_discussion($record);

        // Admin user ignores the timed settings of discussions.
        $this->setAdminUser();
        $neighbours = quora_get_discussion_neighbours($cm, $disc8);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc9);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc10);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc11);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user can see their own timed discussions.
        $this->setUser($user);
        $neighbours = quora_get_discussion_neighbours($cm, $disc8);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc9);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc10);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc11);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user does not ignore timed settings.
        $this->setUser($user2);
        $neighbours = quora_get_discussion_neighbours($cm, $disc8);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc10);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm, $disc12);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Reset to normal mode.
        $CFG->quora_enabletimedposts = false;
        $this->setAdminUser();

        // Two discussions with identical timemodified ignore each other.
        sleep(1);
        $now = time();
        $DB->update_record('quora_discussions', (object) array('id' => $disc3->id, 'timemodified' => $now));
        $DB->update_record('quora_discussions', (object) array('id' => $disc2->id, 'timemodified' => $now));
        $disc2 = $DB->get_record('quora_discussions', array('id' => $disc2->id));
        $disc3 = $DB->get_record('quora_discussions', array('id' => $disc3->id));

        $neighbours = quora_get_discussion_neighbours($cm, $disc2);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $neighbours = quora_get_discussion_neighbours($cm, $disc3);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_quora_get_neighbours_with_groups() {
        $this->resetAfterTest();

        // Setup test data.
        $quoragen = $this->getDataGenerator()->get_plugin_generator('mod_quora');
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $user1->id, 'groupid' => $group1->id));

        $quora1 = $this->getDataGenerator()->create_module('quora', array('course' => $course->id, 'groupmode' => VISIBLEGROUPS));
        $quora2 = $this->getDataGenerator()->create_module('quora', array('course' => $course->id, 'groupmode' => SEPARATEGROUPS));
        $cm1 = get_coursemodule_from_instance('quora', $quora1->id);
        $cm2 = get_coursemodule_from_instance('quora', $quora2->id);
        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);

        // Creating discussions in both quoras.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user1->id;
        $record->quora = $quora1->id;
        $record->groupid = $group1->id;
        $disc11 = $quoragen->create_discussion($record);
        $record->quora = $quora2->id;
        $disc21 = $quoragen->create_discussion($record);

        sleep(1);
        $record->userid = $user2->id;
        $record->quora = $quora1->id;
        $record->groupid = $group2->id;
        $disc12 = $quoragen->create_discussion($record);
        $record->quora = $quora2->id;
        $disc22 = $quoragen->create_discussion($record);

        sleep(1);
        $record->userid = $user1->id;
        $record->quora = $quora1->id;
        $record->groupid = null;
        $disc13 = $quoragen->create_discussion($record);
        $record->quora = $quora2->id;
        $disc23 = $quoragen->create_discussion($record);

        sleep(1);
        $record->userid = $user2->id;
        $record->quora = $quora1->id;
        $record->groupid = $group2->id;
        $disc14 = $quoragen->create_discussion($record);
        $record->quora = $quora2->id;
        $disc24 = $quoragen->create_discussion($record);

        sleep(1);
        $record->userid = $user1->id;
        $record->quora = $quora1->id;
        $record->groupid = $group1->id;
        $disc15 = $quoragen->create_discussion($record);
        $record->quora = $quora2->id;
        $disc25 = $quoragen->create_discussion($record);

        // Admin user can see all groups.
        $this->setAdminUser();
        $neighbours = quora_get_discussion_neighbours($cm1, $disc11);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc12->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc21);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc22->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc22);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc23);
        $this->assertEquals($disc22->id, $neighbours['prev']->id);
        $this->assertEquals($disc24->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc14);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc24);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc15);
        $this->assertEquals($disc14->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc25);
        $this->assertEquals($disc24->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Admin user is only viewing group 1.
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        $neighbours = quora_get_discussion_neighbours($cm1, $disc11);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc21);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc23);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc15);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc25);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user viewing non-grouped posts (this is only possible in visible groups).
        $this->setUser($user1);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm1, true));

        // They can see anything in visible groups.
        $neighbours = quora_get_discussion_neighbours($cm1, $disc12);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);

        // Normal user, orphan of groups, can only see non-grouped posts in separate groups.
        $this->setUser($user2);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm2, true));

        $neighbours = quora_get_discussion_neighbours($cm2, $disc23);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEmpty($neighbours['next']);

        $neighbours = quora_get_discussion_neighbours($cm2, $disc22);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm2, $disc24);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Switching to viewing group 1.
        $this->setUser($user1);
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        // They can see non-grouped or same group.
        $neighbours = quora_get_discussion_neighbours($cm1, $disc11);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc21);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc13);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc23);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = quora_get_discussion_neighbours($cm1, $disc15);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = quora_get_discussion_neighbours($cm2, $disc25);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Querying the neighbours of a discussion passing the wrong CM.
        $this->setExpectedException('coding_exception');
        quora_get_discussion_neighbours($cm2, $disc11);
    }

    public function test_count_discussion_replies_basic() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);

        // Count the discussion replies in the quora.
        $result = quora_count_discussion_replies($quora->id);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_limited() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits shouldn't make a difference.
        $result = quora_count_discussion_replies($quora->id, "", 20);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding paging shouldn't make any difference.
        $result = quora_count_discussion_replies($quora->id, "", -1, 0, 100);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated_sorted() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Specifying the quorasort should also give a good result. This follows a different path.
        $result = quora_count_discussion_replies($quora->id, "d.id asc", -1, 0, 100);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a quorasort shouldn't make a difference.
        $result = quora_count_discussion_replies($quora->id, "d.id asc", 20);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = quora_count_discussion_replies($quora->id, "d.id asc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small_reverse() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = quora_count_discussion_replies($quora->id, "d.id desc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted_small_reverse() {
        list($quora, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a quorasort shouldn't make a difference.
        $result = quora_count_discussion_replies($quora->id, "d.id desc", 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_quora_view() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id),
                                                            array('completion' => 2, 'completionview' => 1));
        $context = context_module::instance($quora->cmid);
        $cm = get_coursemodule_from_instance('quora', $quora->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        quora_view($quora, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/quora/view.php', array('f' => $quora->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);

    }

    /**
     * Test quora_discussion_view.
     */
    public function test_quora_discussion_view() {
        global $CFG, $USER;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $discussion = $this->create_single_discussion_with_replies($quora, $USER, 2);

        $context = context_module::instance($quora->cmid);
        $cm = get_coursemodule_from_instance('quora', $quora->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        quora_discussion_view($context, $quora, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'view discussion', "discuss.php?d={$discussion->id}",
            $discussion->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());

    }

    /**
     * Create a new course, quora, and user with a number of discussions and replies.
     *
     * @param int $discussioncount The number of discussions to create
     * @param int $replycount The number of replies to create in each discussion
     * @return array Containing the created quora object, and the ids of the created discussions.
     */
    protected function create_multiple_discussions_with_replies($discussioncount, $replycount) {
        $this->resetAfterTest();

        // Setup the content.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $record = new stdClass();
        $record->course = $course->id;
        $quora = $this->getDataGenerator()->create_module('quora', $record);

        // Create 10 discussions with replies.
        $discussionids = array();
        for ($i = 0; $i < $discussioncount; $i++) {
            $discussion = $this->create_single_discussion_with_replies($quora, $user, $replycount);
            $discussionids[] = $discussion->id;
        }
        return array($quora, $discussionids);
    }

    /**
     * Create a discussion with a number of replies.
     *
     * @param object $quora The quora which has been created
     * @param object $user The user making the discussion and replies
     * @param int $replycount The number of replies
     * @return object $discussion
     */
    protected function create_single_discussion_with_replies($quora, $user, $replycount) {
        global $DB;

        $generator = self::getDataGenerator()->get_plugin_generator('mod_quora');

        $record = new stdClass();
        $record->course = $quora->course;
        $record->quora = $quora->id;
        $record->userid = $user->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $replyto = $DB->get_record('quora_posts', array('discussion' => $discussion->id));

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

}

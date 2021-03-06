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
 * Tests for quora events.
 *
 * @package    mod_quora
 * @category   test
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for quora events.
 *
 * @package    mod_quora
 * @category   test
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quora_events_testcase extends advanced_testcase {

    /**
     * Tests set up.
     */
    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_quora\subscriptions::reset_quora_cache();

        $this->resetAfterTest();
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_quora\subscriptions::reset_quora_cache();
    }

    /**
     * Ensure course_searched event validates that searchterm is set.
     */
    public function test_course_searched_searchterm_validation() {
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $params = array(
            'context' => $coursectx,
        );

        $this->setExpectedException('coding_exception', 'The \'searchterm\' value must be set in other.');
        \mod_quora\event\course_searched::create($params);
    }

    /**
     * Ensure course_searched event validates that context is the correct level.
     */
    public function test_course_searched_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);
        $params = array(
            'context' => $context,
            'other' => array('searchterm' => 'testing'),
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_COURSE.');
        \mod_quora\event\course_searched::create($params);
    }

    /**
     * Test course_searched event.
     */
    public function test_course_searched() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $searchterm = 'testing123';

        $params = array(
            'context' => $coursectx,
            'other' => array('searchterm' => $searchterm),
        );

        // Create event.
        $event = \mod_quora\event\course_searched::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

         // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\course_searched', $event);
        $this->assertEquals($coursectx, $event->get_context());
        $expected = array($course->id, 'quora', 'search', "search.php?id={$course->id}&amp;search={$searchterm}", $searchterm);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_created event validates that quoraid is set.
     */
    public function test_discussion_created_quoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\discussion_created::create($params);
    }

    /**
     * Ensure discussion_created event validates that the context is the correct level.
     */
    public function test_discussion_created_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_created::create($params);
    }

    /**
     * Test discussion_created event.
     */
    public function test_discussion_created() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('quoraid' => $quora->id),
        );

        // Create the event.
        $event = \mod_quora\event\discussion_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'add discussion', "discuss.php?d={$discussion->id}", $discussion->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_updated event validates that quoraid is set.
     */
    public function test_discussion_updated_quoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\discussion_updated::create($params);
    }

    /**
     * Ensure discussion_created event validates that the context is the correct level.
     */
    public function test_discussion_updated_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_updated::create($params);
    }

    /**
     * Test discussion_created event.
     */
    public function test_discussion_updated() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('quoraid' => $quora->id),
        );

        // Create the event.
        $event = \mod_quora\event\discussion_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_deleted event validates that quoraid is set.
     */
    public function test_discussion_deleted_quoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\discussion_deleted::create($params);
    }

    /**
     * Ensure discussion_deleted event validates that context is of the correct level.
     */
    public function test_discussion_deleted_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_deleted::create($params);
    }

    /**
     * Test discussion_deleted event.
     */
    public function test_discussion_deleted() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('quoraid' => $quora->id),
        );

        $event = \mod_quora\event\discussion_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'delete discussion', "view.php?id={$quora->cmid}", $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_moved event validates that fromquoraid is set.
     */
    public function test_discussion_moved_fromquoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $toquora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $context = context_module::instance($toquora->cmid);

        $params = array(
            'context' => $context,
            'other' => array('toquoraid' => $toquora->id)
        );

        $this->setExpectedException('coding_exception', 'The \'fromquoraid\' value must be set in other.');
        \mod_quora\event\discussion_moved::create($params);
    }

    /**
     * Ensure discussion_moved event validates that toquoraid is set.
     */
    public function test_discussion_moved_toquoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $fromquora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $toquora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($toquora->cmid);

        $params = array(
            'context' => $context,
            'other' => array('fromquoraid' => $fromquora->id)
        );

        $this->setExpectedException('coding_exception', 'The \'toquoraid\' value must be set in other.');
        \mod_quora\event\discussion_moved::create($params);
    }

    /**
     * Ensure discussion_moved event validates that the context level is correct.
     */
    public function test_discussion_moved_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $fromquora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $toquora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $fromquora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $discussion->id,
            'other' => array('fromquoraid' => $fromquora->id, 'toquoraid' => $toquora->id)
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_moved::create($params);
    }

    /**
     * Test discussion_moved event.
     */
    public function test_discussion_moved() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $fromquora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $toquora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $fromquora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $context = context_module::instance($toquora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('fromquoraid' => $fromquora->id, 'toquoraid' => $toquora->id)
        );

        $event = \mod_quora\event\discussion_moved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_moved', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'move discussion', "discuss.php?d={$discussion->id}",
            $discussion->id, $toquora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }


    /**
     * Ensure discussion_viewed event validates that the contextlevel is correct.
     */
    public function test_discussion_viewed_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $discussion->id,
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_viewed::create($params);
    }

    /**
     * Test discussion_viewed event.
     */
    public function test_discussion_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
        );

        $event = \mod_quora\event\discussion_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

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
     * Ensure course_module_viewed event validates that the contextlevel is correct.
     */
    public function test_course_module_viewed_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $quora->id,
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\course_module_viewed::create($params);
    }

    /**
     * Test the course_module_viewed event.
     */
    public function test_course_module_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $quora->id,
        );

        $event = \mod_quora\event\course_module_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'view quora', "view.php?f={$quora->id}", $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/view.php', array('f' => $quora->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure subscription_created event validates that the quoraid is set.
     */
    public function test_subscription_created_quoraid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\subscription_created::create($params);
    }

    /**
     * Ensure subscription_created event validates that the relateduserid is set.
     */
    public function test_subscription_created_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $quora->id,
        );

        $this->setExpectedException('coding_exception', 'The \'relateduserid\' must be set.');
        \mod_quora\event\subscription_created::create($params);
    }

    /**
     * Ensure subscription_created event validates that the contextlevel is correct.
     */
    public function test_subscription_created_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\subscription_created::create($params);
    }

    /**
     * Test the subscription_created event.
     */
    public function test_subscription_created() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $context = context_module::instance($quora->cmid);

        // Add a subscription.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $subscription = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_subscription($record);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_quora\event\subscription_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'subscribe', "view.php?f={$quora->id}", $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/subscribers.php', array('id' => $quora->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure subscription_deleted event validates that the quoraid is set.
     */
    public function test_subscription_deleted_quoraid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\subscription_deleted::create($params);
    }

    /**
     * Ensure subscription_deleted event validates that the relateduserid is set.
     */
    public function test_subscription_deleted_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $quora->id,
        );

        $this->setExpectedException('coding_exception', 'The \'relateduserid\' must be set.');
        \mod_quora\event\subscription_deleted::create($params);
    }

    /**
     * Ensure subscription_deleted event validates that the contextlevel is correct.
     */
    public function test_subscription_deleted_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\subscription_deleted::create($params);
    }

    /**
     * Test the subscription_deleted event.
     */
    public function test_subscription_deleted() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $context = context_module::instance($quora->cmid);

        // Add a subscription.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $subscription = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_subscription($record);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_quora\event\subscription_deleted::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'unsubscribe', "view.php?f={$quora->id}", $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/subscribers.php', array('id' => $quora->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure readtracking_enabled event validates that the quoraid is set.
     */
    public function test_readtracking_enabled_quoraid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\readtracking_enabled::create($params);
    }

    /**
     * Ensure readtracking_enabled event validates that the relateduserid is set.
     */
    public function test_readtracking_enabled_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $quora->id,
        );

        $this->setExpectedException('coding_exception', 'The \'relateduserid\' must be set.');
        \mod_quora\event\readtracking_enabled::create($params);
    }

    /**
     * Ensure readtracking_enabled event validates that the contextlevel is correct.
     */
    public function test_readtracking_enabled_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\readtracking_enabled::create($params);
    }

    /**
     * Test the readtracking_enabled event.
     */
    public function test_readtracking_enabled() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_quora\event\readtracking_enabled::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\readtracking_enabled', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'start tracking', "view.php?f={$quora->id}", $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/view.php', array('f' => $quora->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure readtracking_disabled event validates that the quoraid is set.
     */
    public function test_readtracking_disabled_quoraid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\readtracking_disabled::create($params);
    }

    /**
     *  Ensure readtracking_disabled event validates that the relateduserid is set.
     */
    public function test_readtracking_disabled_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $quora->id,
        );

        $this->setExpectedException('coding_exception', 'The \'relateduserid\' must be set.');
        \mod_quora\event\readtracking_disabled::create($params);
    }

    /**
     *  Ensure readtracking_disabled event validates that the contextlevel is correct
     */
    public function test_readtracking_disabled_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\readtracking_disabled::create($params);
    }

    /**
     *  Test the readtracking_disabled event.
     */
    public function test_readtracking_disabled() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_quora\event\readtracking_disabled::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\readtracking_disabled', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'stop tracking', "view.php?f={$quora->id}", $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/view.php', array('f' => $quora->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure subscribers_viewed event validates that the quoraid is set.
     */
    public function test_subscribers_viewed_quoraid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\subscribers_viewed::create($params);
    }

    /**
     *  Ensure subscribers_viewed event validates that the contextlevel is correct.
     */
    public function test_subscribers_viewed_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('quoraid' => $quora->id),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\subscribers_viewed::create($params);
    }

    /**
     *  Test the subscribers_viewed event.
     */
    public function test_subscribers_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'other' => array('quoraid' => $quora->id),
        );

        $event = \mod_quora\event\subscribers_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscribers_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'view subscribers', "subscribers.php?id={$quora->id}", $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure user_report_viewed event validates that the reportmode is set.
     */
    public function test_user_report_viewed_reportmode_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $params = array(
            'context' => context_course::instance($course->id),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception', 'The \'reportmode\' value must be set in other.');
        \mod_quora\event\user_report_viewed::create($params);
    }

    /**
     *  Ensure user_report_viewed event validates that the contextlevel is correct.
     */
    public function test_user_report_viewed_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'other' => array('reportmode' => 'posts'),
            'relateduserid' => $user->id,
        );

        $this->setExpectedException('coding_exception',
                'Context level must be either CONTEXT_SYSTEM, CONTEXT_COURSE or CONTEXT_USER.');
        \mod_quora\event\user_report_viewed::create($params);
    }

    /**
     *  Ensure user_report_viewed event validates that the relateduserid is set.
     */
    public function test_user_report_viewed_relateduserid_validation() {

        $params = array(
            'context' => context_system::instance(),
            'other' => array('reportmode' => 'posts'),
        );

        $this->setExpectedException('coding_exception', 'The \'relateduserid\' must be set.');
        \mod_quora\event\user_report_viewed::create($params);
    }

    /**
     * Test the user_report_viewed event.
     */
    public function test_user_report_viewed() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $params = array(
            'context' => $context,
            'relateduserid' => $user->id,
            'other' => array('reportmode' => 'discussions'),
        );

        $event = \mod_quora\event\user_report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\user_report_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'user report',
            "user.php?id={$user->id}&amp;mode=discussions&amp;course={$course->id}", $user->id);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure post_created event validates that the postid is set.
     */
    public function test_post_created_postid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'other' => array('quoraid' => $quora->id, 'quoratype' => $quora->type, 'discussionid' => $discussion->id)
        );

        \mod_quora\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the discussionid is set.
     */
    public function test_post_created_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'The \'discussionid\' value must be set in other.');
        \mod_quora\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the quoraid is set.
     */
    public function test_post_created_quoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the quoratype is set.
     */
    public function test_post_created_quoratype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id)
        );

        $this->setExpectedException('coding_exception', 'The \'quoratype\' value must be set in other.');
        \mod_quora\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the contextlevel is correct.
     */
    public function test_post_created_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE');
        \mod_quora\event\post_created::create($params);
    }

    /**
     * Test the post_created event.
     */
    public function test_post_created() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $event = \mod_quora\event\post_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\post_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'add post', "discuss.php?d={$discussion->id}#p{$post->id}",
            $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/discuss.php', array('d' => $discussion->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test the post_created event for a single discussion quora.
     */
    public function test_post_created_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $event = \mod_quora\event\post_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\post_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'add post', "view.php?f={$quora->id}#p{$post->id}",
            $quora->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/view.php', array('f' => $quora->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure post_deleted event validates that the postid is set.
     */
    public function test_post_deleted_postid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'other' => array('quoraid' => $quora->id, 'quoratype' => $quora->type, 'discussionid' => $discussion->id)
        );

        \mod_quora\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the discussionid is set.
     */
    public function test_post_deleted_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'The \'discussionid\' value must be set in other.');
        \mod_quora\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the quoraid is set.
     */
    public function test_post_deleted_quoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the quoratype is set.
     */
    public function test_post_deleted_quoratype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id)
        );

        $this->setExpectedException('coding_exception', 'The \'quoratype\' value must be set in other.');
        \mod_quora\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the contextlevel is correct.
     */
    public function test_post_deleted_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE');
        \mod_quora\event\post_deleted::create($params);
    }

    /**
     * Test post_deleted event.
     */
    public function test_post_deleted() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $event = \mod_quora\event\post_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\post_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'delete post', "discuss.php?d={$discussion->id}", $post->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/discuss.php', array('d' => $discussion->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test post_deleted event for a single discussion quora.
     */
    public function test_post_deleted_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $event = \mod_quora\event\post_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\post_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'delete post', "view.php?f={$quora->id}", $post->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/view.php', array('f' => $quora->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure post_updated event validates that the discussionid is set.
     */
    public function test_post_updated_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'The \'discussionid\' value must be set in other.');
        \mod_quora\event\post_updated::create($params);
    }

    /**
     *  Ensure post_updated event validates that the quoraid is set.
     */
    public function test_post_updated_quoraid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\post_updated::create($params);
    }

    /**
     *  Ensure post_updated event validates that the quoratype is set.
     */
    public function test_post_updated_quoratype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id)
        );

        $this->setExpectedException('coding_exception', 'The \'quoratype\' value must be set in other.');
        \mod_quora\event\post_updated::create($params);
    }

    /**
     *  Ensure post_updated event validates that the contextlevel is correct.
     */
    public function test_post_updated_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE');
        \mod_quora\event\post_updated::create($params);
    }

    /**
     * Test post_updated event.
     */
    public function test_post_updated() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $event = \mod_quora\event\post_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\post_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'update post', "discuss.php?d={$discussion->id}#p{$post->id}",
            $post->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/discuss.php', array('d' => $discussion->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test post_updated event.
     */
    public function test_post_updated_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $quora = $this->getDataGenerator()->create_module('quora', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'quoraid' => $quora->id, 'quoratype' => $quora->type)
        );

        $event = \mod_quora\event\post_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\post_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'quora', 'update post', "view.php?f={$quora->id}#p{$post->id}",
            $post->id, $quora->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/quora/view.php', array('f' => $quora->id));
        $url->set_anchor('p'.$post->id);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test discussion_subscription_created event.
     */
    public function test_discussion_subscription_created() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by subscribing the user to the quora discussion.
        \mod_quora\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_created', $event);


        $cm = get_coursemodule_from_instance('quora', $discussion->quora);
        $context = \context_module::instance($cm->id);
        $this->assertEquals($context, $event->get_context());

        $url = new \moodle_url('/mod/quora/subscribe.php', array(
            'id' => $quora->id,
            'd' => $discussion->id
        ));

        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test validation of discussion_subscription_created event.
     */
    public function test_discussion_subscription_created_validation() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'quoraid' => $quora->id,
                'discussion' => $discussion->id,
            )
        );

        $event = \mod_quora\event\discussion_subscription_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
    }

    /**
     * Test contextlevel validation of discussion_subscription_created event.
     */
    public function test_discussion_subscription_created_validation_contextlevel() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => \context_course::instance($course->id),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'quoraid' => $quora->id,
                'discussion' => $discussion->id,
            )
        );

        // Without an invalid context.
        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_subscription_created::create($params);
    }

    /**
     * Test discussion validation of discussion_subscription_created event.
     */
    public function test_discussion_subscription_created_validation_discussion() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        // Without the discussion.
        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'quoraid' => $quora->id,
            )
        );

        $this->setExpectedException('coding_exception', "The 'discussion' value must be set in other.");
        \mod_quora\event\discussion_subscription_created::create($params);
    }

    /**
     * Test quoraid validation of discussion_subscription_created event.
     */
    public function test_discussion_subscription_created_validation_quoraid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        // Without the quoraid.
        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'discussion' => $discussion->id,
            )
        );

        $this->setExpectedException('coding_exception', "The 'quoraid' value must be set in other.");
        \mod_quora\event\discussion_subscription_created::create($params);
    }

    /**
     * Test relateduserid validation of discussion_subscription_created event.
     */
    public function test_discussion_subscription_created_validation_relateduserid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        $context = context_module::instance($quora->cmid);

        // Without the relateduserid.
        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $subscription->id,
            'other' => array(
                'quoraid' => $quora->id,
                'discussion' => $discussion->id,
            )
        );

        $this->setExpectedException('coding_exception', "The 'relateduserid' must be set.");
        \mod_quora\event\discussion_subscription_created::create($params);
    }

    /**
     * Test discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by unsubscribing the user to the quora discussion.
        \mod_quora\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_deleted', $event);


        $cm = get_coursemodule_from_instance('quora', $discussion->quora);
        $context = \context_module::instance($cm->id);
        $this->assertEquals($context, $event->get_context());

        $url = new \moodle_url('/mod/quora/subscribe.php', array(
            'id' => $quora->id,
            'd' => $discussion->id
        ));

        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test validation of discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted_validation() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = \mod_quora\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED;

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'quoraid' => $quora->id,
                'discussion' => $discussion->id,
            )
        );

        $event = \mod_quora\event\discussion_subscription_deleted::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Without an invalid context.
        $params['context'] = \context_course::instance($course->id);
        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_deleted::create($params);

        // Without the discussion.
        unset($params['discussion']);
        $this->setExpectedException('coding_exception', 'The \'discussion\' value must be set in other.');
        \mod_quora\event\discussion_deleted::create($params);

        // Without the quoraid.
        unset($params['quoraid']);
        $this->setExpectedException('coding_exception', 'The \'quoraid\' value must be set in other.');
        \mod_quora\event\discussion_deleted::create($params);

        // Without the relateduserid.
        unset($params['relateduserid']);
        $this->setExpectedException('coding_exception', 'The \'relateduserid\' value must be set in other.');
        \mod_quora\event\discussion_deleted::create($params);
    }

    /**
     * Test contextlevel validation of discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted_validation_contextlevel() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        $context = context_module::instance($quora->cmid);

        $params = array(
            'context' => \context_course::instance($course->id),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'quoraid' => $quora->id,
                'discussion' => $discussion->id,
            )
        );

        // Without an invalid context.
        $this->setExpectedException('coding_exception', 'Context level must be CONTEXT_MODULE.');
        \mod_quora\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test discussion validation of discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted_validation_discussion() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        // Without the discussion.
        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'quoraid' => $quora->id,
            )
        );

        $this->setExpectedException('coding_exception', "The 'discussion' value must be set in other.");
        \mod_quora\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test quoraid validation of discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted_validation_quoraid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        // Without the quoraid.
        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'discussion' => $discussion->id,
            )
        );

        $this->setExpectedException('coding_exception', "The 'quoraid' value must be set in other.");
        \mod_quora\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test relateduserid validation of discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted_validation_relateduserid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // The user is not subscribed to the quora. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->quora = $quora->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);

        $context = context_module::instance($quora->cmid);

        // Without the relateduserid.
        $params = array(
            'context' => context_module::instance($quora->cmid),
            'objectid' => $subscription->id,
            'other' => array(
                'quoraid' => $quora->id,
                'discussion' => $discussion->id,
            )
        );

        $this->setExpectedException('coding_exception', "The 'relateduserid' must be set.");
        \mod_quora\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test that the correct context is used in the events when subscribing
     * users.
     */
    public function test_quora_subscription_page_context_valid() {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);
        $quiz = $this->getDataGenerator()->create_module('quiz', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $quora->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_quora')->create_post($record);

        // Set up the default page event to use this quora.
        $PAGE = new moodle_page();
        $cm = get_coursemodule_from_instance('quora', $discussion->quora);
        $context = \context_module::instance($cm->id);
        $PAGE->set_context($context);
        $PAGE->set_cm($cm, $course, $quora);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by subscribing the user to the quora.
        \mod_quora\subscriptions::subscribe_user($user->id, $quora);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user to the quora.
        \mod_quora\subscriptions::unsubscribe_user($user->id, $quora);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by subscribing the user to the discussion.
        \mod_quora\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user from the discussion.
        \mod_quora\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Now try with the context for a different module (quiz).
        $PAGE = new moodle_page();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $quizcontext = \context_module::instance($cm->id);
        $PAGE->set_context($quizcontext);
        $PAGE->set_cm($cm, $course, $quiz);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by subscribing the user to the quora.
        \mod_quora\subscriptions::subscribe_user($user->id, $quora);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user to the quora.
        \mod_quora\subscriptions::unsubscribe_user($user->id, $quora);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by subscribing the user to the discussion.
        \mod_quora\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user from the discussion.
        \mod_quora\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Now try with the course context - the module context should still be used.
        $PAGE = new moodle_page();
        $coursecontext = \context_course::instance($course->id);
        $PAGE->set_context($coursecontext);

        // Trigger the event by subscribing the user to the quora.
        \mod_quora\subscriptions::subscribe_user($user->id, $quora);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user to the quora.
        \mod_quora\subscriptions::unsubscribe_user($user->id, $quora);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by subscribing the user to the discussion.
        \mod_quora\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user from the discussion.
        \mod_quora\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_quora\event\discussion_subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

    }

    /**
     * Test mod_quora_observer methods.
     */
    public function test_observers() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quora/lib.php');

        $quoragen = $this->getDataGenerator()->get_plugin_generator('mod_quora');

        $course = $this->getDataGenerator()->create_course();
        $trackedrecord = array('course' => $course->id, 'type' => 'general', 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $untrackedrecord = array('course' => $course->id, 'type' => 'general');
        $trackedquora = $this->getDataGenerator()->create_module('quora', $trackedrecord);
        $untrackedquora = $this->getDataGenerator()->create_module('quora', $untrackedrecord);

        // Used functions don't require these settings; adding
        // them just in case there are APIs changes in future.
        $user = $this->getDataGenerator()->create_user(array(
            'maildigest' => 1,
            'trackquoras' => 1
        ));

        $manplugin = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'));
        $student = $DB->get_record('role', array('shortname' => 'student'));

        // The role_assign observer does it's job adding the quora_subscriptions record.
        $manplugin->enrol_user($manualenrol, $user->id, $student->id);

        // They are not required, but in a real environment they are supposed to be required;
        // adding them just in case there are APIs changes in future.
        set_config('quora_trackingtype', 1);
        set_config('quora_trackreadposts', 1);

        $record = array();
        $record['course'] = $course->id;
        $record['quora'] = $trackedquora->id;
        $record['userid'] = $user->id;
        $discussion = $quoragen->create_discussion($record);

        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $quoragen->create_post($record);

        quora_tp_add_read_record($user->id, $post->id);
        quora_set_user_maildigest($trackedquora, 2, $user);
        quora_tp_stop_tracking($untrackedquora->id, $user->id);

        $this->assertEquals(1, $DB->count_records('quora_subscriptions'));
        $this->assertEquals(1, $DB->count_records('quora_digests'));
        $this->assertEquals(1, $DB->count_records('quora_track_prefs'));
        $this->assertEquals(1, $DB->count_records('quora_read'));

        // The course_module_created observer does it's job adding a subscription.
        $quorarecord = array('course' => $course->id, 'type' => 'general', 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $extraquora = $this->getDataGenerator()->create_module('quora', $quorarecord);
        $this->assertEquals(2, $DB->count_records('quora_subscriptions'));

        $manplugin->unenrol_user($manualenrol, $user->id);

        $this->assertEquals(0, $DB->count_records('quora_digests'));
        $this->assertEquals(0, $DB->count_records('quora_subscriptions'));
        $this->assertEquals(0, $DB->count_records('quora_track_prefs'));
        $this->assertEquals(0, $DB->count_records('quora_read'));
    }

}

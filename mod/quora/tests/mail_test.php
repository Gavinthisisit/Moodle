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
 * The quora module mail generation tests.
 *
 * @package    mod_quora
 * @category   external
 * @copyright  2013 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

class mod_quora_mail_testcase extends advanced_testcase {

    protected $helper;

    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_quora\subscriptions::reset_quora_cache();
        \mod_quora\subscriptions::reset_discussion_cache();

        global $CFG;
        require_once($CFG->dirroot . '/mod/quora/lib.php');

        $helper = new stdClass();

        // Messaging is not compatible with transactions...
        $this->preventResetByRollback();

        // Catch all messages.
        $helper->messagesink = $this->redirectMessages();
        $helper->mailsink = $this->redirectEmails();

        // Confirm that we have an empty message sink so far.
        $messages = $helper->messagesink->get_messages();
        $this->assertEquals(0, count($messages));

        $messages = $helper->mailsink->get_messages();
        $this->assertEquals(0, count($messages));

        // Forcibly reduce the maxeditingtime to a second in the past to
        // ensure that messages are sent out.
        $CFG->maxeditingtime = -1;

        $this->helper = $helper;
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_quora\subscriptions::reset_quora_cache();

        $this->helper->messagesink->clear();
        $this->helper->messagesink->close();

        $this->helper->mailsink->clear();
        $this->helper->mailsink->close();
    }

    /**
     * Perform message inbound setup for the mod_quora reply handler.
     */
    protected function helper_spoof_message_inbound_setup() {
        global $CFG, $DB;
        // Setup the default Inbound Message mailbox settings.
        $CFG->messageinbound_domain = 'example.com';
        $CFG->messageinbound_enabled = true;

        // Must be no longer than 15 characters.
        $CFG->messageinbound_mailbox = 'moodlemoodle123';

        $record = $DB->get_record('messageinbound_handlers', array('classname' => '\mod_quora\message\inbound\reply_handler'));
        $record->enabled = true;
        $record->id = $DB->update_record('messageinbound_handlers', $record);
    }

    /**
     * Helper to create the required number of users in the specified
     * course.
     * Users are enrolled as students.
     *
     * @param stdClass $course The course object
     * @param integer $count The number of users to create
     * @return array The users created
     */
    protected function helper_create_users($course, $count) {
        $users = array();

        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Create a new discussion and post within the specified quora, as the
     * specified author.
     *
     * @param stdClass $quora The quora to post in
     * @param stdClass $author The author to post as
     * @param array $fields any other fields in discussion (name, message, messageformat, ...)
     * @param array An array containing the discussion object, and the post object
     */
    protected function helper_post_to_quora($quora, $author, $fields = array()) {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quora');

        // Create a discussion in the quora, and then add a post to that discussion.
        $record = (object)$fields;
        $record->course = $quora->course;
        $record->userid = $author->id;
        $record->quora = $quora->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the post which was created by create_discussion.
        $post = $DB->get_record('quora_posts', array('discussion' => $discussion->id));

        return array($discussion, $post);
    }

    /**
     * Update the post time for the specified post by $factor.
     *
     * @param stdClass $post The post to update
     * @param int $factor The amount to update by
     */
    protected function helper_update_post_time($post, $factor) {
        global $DB;

        // Update the post to have a created in the past.
        $DB->set_field('quora_posts', 'created', $post->created + $factor, array('id' => $post->id));
    }

    /**
     * Update the subscription time for the specified user/discussion by $factor.
     *
     * @param stdClass $user The user to update
     * @param stdClass $discussion The discussion to update for this user
     * @param int $factor The amount to update by
     */
    protected function helper_update_subscription_time($user, $discussion, $factor) {
        global $DB;

        $sub = $DB->get_record('quora_discussion_subs', array('userid' => $user->id, 'discussion' => $discussion->id));

        // Update the subscription to have a preference in the past.
        $DB->set_field('quora_discussion_subs', 'preference', $sub->preference + $factor, array('id' => $sub->id));
    }

    /**
     * Create a new post within an existing discussion, as the specified author.
     *
     * @param stdClass $quora The quora to post in
     * @param stdClass $discussion The discussion to post in
     * @param stdClass $author The author to post as
     * @return stdClass The quora post
     */
    protected function helper_post_to_discussion($quora, $discussion, $author) {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quora');

        // Add a post to the discussion.
        $record = new stdClass();
        $record->course = $quora->course;
        $record->userid = $author->id;
        $record->quora = $quora->id;
        $record->discussion = $discussion->id;
        $record->mailnow = 1;

        $post = $generator->create_post($record);

        return $post;
    }

    /**
     * Run the quora cron, and check that the specified post was sent the
     * specified number of times.
     *
     * @param stdClass $post The quora post object
     * @param integer $expected The number of times that the post should have been sent
     * @return array An array of the messages caught by the message sink
     */
    protected function helper_run_cron_check_count($post, $expected) {

        // Clear the sinks before running cron.
        $this->helper->messagesink->clear();
        $this->helper->mailsink->clear();

        // Cron daily uses mtrace, turn on buffering to silence output.
        $this->expectOutputRegex("/{$expected} users were sent post {$post->id}, '{$post->subject}'/");
        quora_cron();

        // Now check the results in the message sink.
        $messages = $this->helper->messagesink->get_messages();

        // There should be the expected number of messages.
        $this->assertEquals($expected, count($messages));

        return $messages;
    }

    /**
     * Run the quora cron, and check that the specified posts were sent the
     * specified number of times.
     *
     * @param stdClass $post The quora post object
     * @param integer $expected The number of times that the post should have been sent
     * @return array An array of the messages caught by the message sink
     */
    protected function helper_run_cron_check_counts($posts, $expected) {

        // Clear the sinks before running cron.
        $this->helper->messagesink->clear();
        $this->helper->mailsink->clear();

        // Cron daily uses mtrace, turn on buffering to silence output.
        foreach ($posts as $post) {
            $this->expectOutputRegex("/{$post['count']} users were sent post {$post['id']}, '{$post['subject']}'/");
        }
        quora_cron();

        // Now check the results in the message sink.
        $messages = $this->helper->messagesink->get_messages();

        // There should be the expected number of messages.
        $this->assertEquals($expected, count($messages));

        return $messages;
    }

    public function test_forced_subscription() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_FORCESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // We expect both users to receive this post.
        $expected = 2;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we saw messages for both users.
        $this->assertTrue($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_subscription_disabled() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_DISALLOWSUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // We expect both users to receive this post.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // A user with the manageactivities capability within the course can subscribe.
        $expected = 1;
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleids['student'], context_course::instance($course->id));
        \mod_quora\subscriptions::subscribe_user($recipient->id, $quora);

        $this->assertEquals($expected, $DB->count_records('quora_subscriptions', array(
            'userid'        => $recipient->id,
            'quora'         => $quora->id,
        )));

        // Run cron and check that the expected number of users received the notification.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $recipient);
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // Unsubscribe the user again.
        \mod_quora\subscriptions::unsubscribe_user($recipient->id, $quora);

        $expected = 0;
        $this->assertEquals($expected, $DB->count_records('quora_subscriptions', array(
            'userid'        => $recipient->id,
            'quora'         => $quora->id,
        )));

        // Run cron and check that the expected number of users received the notification.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // Subscribe the user to the discussion.
        \mod_quora\subscriptions::subscribe_user_to_discussion($recipient->id, $discussion);
        $this->helper_update_subscription_time($recipient, $discussion, -60);

        $reply = $this->helper_post_to_discussion($quora, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        $messages = $this->helper_run_cron_check_count($reply, $expected);
    }

    public function test_automatic() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // We expect both users to receive this post.
        $expected = 2;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we saw messages for both users.
        $this->assertTrue($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // We expect both users to receive this post.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);
    }

    public function test_automatic_with_unsubscribed_user() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Unsubscribe the 'author' user from the quora.
        \mod_quora\subscriptions::unsubscribe_user($author->id, $quora);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional_with_subscribed_user() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Subscribe the 'recipient' user from the quora.
        \mod_quora\subscriptions::subscribe_user($recipient->id, $quora);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_automatic_with_unsubscribed_discussion() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // Unsubscribe the 'author' user from the discussion.
        \mod_quora\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        $this->assertFalse(\mod_quora\subscriptions::is_subscribed($author->id, $quora, $discussion->id));
        $this->assertTrue(\mod_quora\subscriptions::is_subscribed($recipient->id, $quora, $discussion->id));

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional_with_subscribed_discussion() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);
        $this->helper_update_post_time($post, -90);

        // Subscribe the 'recipient' user to the discussion.
        \mod_quora\subscriptions::subscribe_user_to_discussion($recipient->id, $discussion);
        $this->helper_update_subscription_time($recipient, $discussion, -60);

        // Initially we don't expect any user to receive this post as you cannot subscribe to a discussion until after
        // you have read it.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // Have a user reply to the discussion.
        $reply = $this->helper_post_to_discussion($quora, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($reply, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_automatic_with_subscribed_discussion_in_unsubscribed_quora() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_INITIALSUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);
        $this->helper_update_post_time($post, -90);

        // Unsubscribe the 'author' user from the quora.
        \mod_quora\subscriptions::unsubscribe_user($author->id, $quora);

        // Then re-subscribe them to the discussion.
        \mod_quora\subscriptions::subscribe_user_to_discussion($author->id, $discussion);
        $this->helper_update_subscription_time($author, $discussion, -60);

        // We expect just the user subscribed to the quora to receive this post at the moment as the discussion
        // subscription time is after the post time.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);

        // Now post a reply to the original post.
        $reply = $this->helper_post_to_discussion($quora, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        // We expect two users to receive this post.
        $expected = 2;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($reply, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we saw both users.
        $this->assertTrue($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional_with_unsubscribed_discussion_in_subscribed_quora() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);

        // Unsubscribe the 'recipient' user from the discussion.
        \mod_quora\subscriptions::subscribe_user($recipient->id, $quora);

        // Then unsubscribe them from the discussion.
        \mod_quora\subscriptions::unsubscribe_user_from_discussion($recipient->id, $discussion);

        // We don't expect any users to receive this post.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);
    }

    /**
     * Test that a user unsubscribed from a quora who has subscribed to a discussion, only receives posts made after
     * they subscribed to the discussion.
     */
    public function test_quora_discussion_subscription_quora_unsubscribed_discussion_subscribed_after_post() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        $expectedmessages = array();

        // Create a user enrolled in the course as a student.
        list($author) = $this->helper_create_users($course, 1);

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);
        $this->helper_update_post_time($post, -90);

        $expectedmessages[] = array(
            'id' => $post->id,
            'subject' => $post->subject,
            'count' => 0,
        );

        // Then subscribe the user to the discussion.
        $this->assertTrue(\mod_quora\subscriptions::subscribe_user_to_discussion($author->id, $discussion));
        $this->helper_update_subscription_time($author, $discussion, -60);

        // Then post a reply to the first discussion.
        $reply = $this->helper_post_to_discussion($quora, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        $expectedmessages[] = array(
            'id' => $reply->id,
            'subject' => $reply->subject,
            'count' => 1,
        );

        $expectedcount = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_counts($expectedmessages, $expectedcount);
    }

    public function test_quora_message_inbound_multiple_posts() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_FORCESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create a user enrolled in the course as a student.
        list($author) = $this->helper_create_users($course, 1);

        $expectedmessages = array();

        // Post a discussion to the quora.
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author);
        $this->helper_update_post_time($post, -90);

        $expectedmessages[] = array(
            'id' => $post->id,
            'subject' => $post->subject,
            'count' => 0,
        );

        // Then post a reply to the first discussion.
        $reply = $this->helper_post_to_discussion($quora, $discussion, $author);
        $this->helper_update_post_time($reply, -60);

        $expectedmessages[] = array(
            'id' => $reply->id,
            'subject' => $reply->subject,
            'count' => 1,
        );

        $expectedcount = 2;

        // Ensure that messageinbound is enabled and configured for the quora handler.
        $this->helper_spoof_message_inbound_setup();

        $author->emailstop = '0';
        set_user_preference('message_provider_mod_quora_posts_loggedoff', 'email', $author);
        set_user_preference('message_provider_mod_quora_posts_loggedin', 'email', $author);

        // Run cron and check that the expected number of users received the notification.
        // Clear the mailsink, and close the messagesink.
        $this->helper->mailsink->clear();
        $this->helper->messagesink->close();

        // Cron daily uses mtrace, turn on buffering to silence output.
        foreach ($expectedmessages as $post) {
            $this->expectOutputRegex("/{$post['count']} users were sent post {$post['id']}, '{$post['subject']}'/");
        }

        quora_cron();
        $messages = $this->helper->mailsink->get_messages();

        // There should be the expected number of messages.
        $this->assertEquals($expectedcount, count($messages));

        foreach ($messages as $message) {
            $this->assertRegExp('/Reply-To: moodlemoodle123\+[^@]*@example.com/', $message->header);
        }
    }

    public function test_long_subject() {
        $this->resetAfterTest(true);

        // Create a course, with a quora.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => FORUM_FORCESUBSCRIBE);
        $quora = $this->getDataGenerator()->create_module('quora', $options);

        // Create a user enrolled in the course as student.
        list($author) = $this->helper_create_users($course, 1);

        // Post a discussion to the quora.
        $subject = 'This is the very long quora post subject that somebody was very kind of leaving, it is intended to check if long subject comes in mail correctly. Thank you.';
        $a = (object)array('courseshortname' => $course->shortname, 'quoraname' => $quora->name, 'subject' => $subject);
        $expectedsubject = get_string('postmailsubject', 'quora', $a);
        list($discussion, $post) = $this->helper_post_to_quora($quora, $author, array('name' => $subject));

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, 1);
        $message = reset($messages);
        $this->assertEquals($author->id, $message->useridfrom);
        $this->assertEquals($expectedsubject, $message->subject);
    }
}

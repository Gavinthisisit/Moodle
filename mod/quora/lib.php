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
 * @package   mod_quora
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once(__DIR__ . '/deprecatedlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/eventslib.php');

/// CONSTANTS ///////////////////////////////////////////////////////////

define('FORUM_MODE_FLATOLDEST', 1);
define('FORUM_MODE_FLATNEWEST', -1);
define('FORUM_MODE_THREADED', 2);
define('FORUM_MODE_NESTED', 3);

define('FORUM_CHOOSESUBSCRIBE', 0);
define('FORUM_FORCESUBSCRIBE', 1);
define('FORUM_INITIALSUBSCRIBE', 2);
define('FORUM_DISALLOWSUBSCRIBE',3);

/**
 * FORUM_TRACKING_OFF - Tracking is not available for this quora.
 */
define('FORUM_TRACKING_OFF', 0);

/**
 * FORUM_TRACKING_OPTIONAL - Tracking is based on user preference.
 */
define('FORUM_TRACKING_OPTIONAL', 1);

/**
 * FORUM_TRACKING_FORCED - Tracking is on, regardless of user setting.
 * Treated as FORUM_TRACKING_OPTIONAL if $CFG->quora_allowforcedreadtracking is off.
 */
define('FORUM_TRACKING_FORCED', 2);

define('FORUM_MAILED_PENDING', 0);
define('FORUM_MAILED_SUCCESS', 1);
define('FORUM_MAILED_ERROR', 2);

if (!defined('FORUM_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in quora cron. */
    define('FORUM_CRON_USER_CACHE', 5000);
}

/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $quora add quora instance
 * @param mod_quora_mod_form $mform
 * @return int intance id
 */
function quora_add_instance($quora, $mform = null) {
    global $CFG, $DB;

    $quora->timemodified = time();

    if (empty($quora->assessed)) {
        $quora->assessed = 0;
    }

    if (empty($quora->ratingtime) or empty($quora->assessed)) {
        $quora->assesstimestart  = 0;
        $quora->assesstimefinish = 0;
    }

    $quora->id = $DB->insert_record('quora', $quora);
    $modcontext = context_module::instance($quora->coursemodule);

    if ($quora->type == 'single') {  // Create related discussion.
        $discussion = new stdClass();
        $discussion->course        = $quora->course;
        $discussion->quora         = $quora->id;
        $discussion->name          = $quora->name;
        $discussion->assessed      = $quora->assessed;
        $discussion->message       = $quora->intro;
        $discussion->messageformat = $quora->introformat;
        $discussion->messagetrust  = trusttext_trusted(context_course::instance($quora->course));
        $discussion->mailnow       = false;
        $discussion->groupid       = -1;

        $message = '';

        $discussion->id = quora_add_discussion($discussion, null, $message);

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB->get_record('quora_discussions', array('id'=>$discussion->id), '*', MUST_EXIST);
            $post = $DB->get_record('quora_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);

            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_quora', 'post', $post->id, $options, $post->message);
            $DB->set_field('quora_posts', 'message', $post->message, array('id'=>$post->id));
        }
    }

    quora_grade_item_update($quora);

    return $quora->id;
}

/**
 * Handle changes following the creation of a quora instance.
 * This function is typically called by the course_module_created observer.
 *
 * @param object $context the quora context
 * @param stdClass $quora The quora object
 * @return void
 */
function quora_instance_created($context, $quora) {
    if ($quora->forcesubscribe == FORUM_INITIALSUBSCRIBE) {
        $users = \mod_quora\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email');
        foreach ($users as $user) {
            \mod_quora\subscriptions::subscribe_user($user->id, $quora, $context);
        }
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $quora quora instance (with magic quotes)
 * @return bool success
 */
function quora_update_instance($quora, $mform) {
    global $DB, $OUTPUT, $USER;

    $quora->timemodified = time();
    $quora->id           = $quora->instance;

    if (empty($quora->assessed)) {
        $quora->assessed = 0;
    }

    if (empty($quora->ratingtime) or empty($quora->assessed)) {
        $quora->assesstimestart  = 0;
        $quora->assesstimefinish = 0;
    }

    $oldquora = $DB->get_record('quora', array('id'=>$quora->id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire quora
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldquora->assessed<>$quora->assessed) or ($oldquora->scale<>$quora->scale)) {
        quora_update_grades($quora); // recalculate grades for the quora
    }

    if ($quora->type == 'single') {  // Update related discussion and post.
        $discussions = $DB->get_records('quora_discussions', array('quora'=>$quora->id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'quora'));
            }
            $discussion = array_pop($discussions);
        } else {
            // try to recover by creating initial discussion - MDL-16262
            $discussion = new stdClass();
            $discussion->course          = $quora->course;
            $discussion->quora           = $quora->id;
            $discussion->name            = $quora->name;
            $discussion->assessed        = $quora->assessed;
            $discussion->message         = $quora->intro;
            $discussion->messageformat   = $quora->introformat;
            $discussion->messagetrust    = true;
            $discussion->mailnow         = false;
            $discussion->groupid         = -1;

            $message = '';

            quora_add_discussion($discussion, null, $message);

            if (! $discussion = $DB->get_record('quora_discussions', array('quora'=>$quora->id))) {
                print_error('cannotadd', 'quora');
            }
        }
        if (! $post = $DB->get_record('quora_posts', array('id'=>$discussion->firstpost))) {
            print_error('cannotfindfirstpost', 'quora');
        }

        $cm         = get_coursemodule_from_instance('quora', $quora->id);
        $modcontext = context_module::instance($cm->id, MUST_EXIST);

        $post = $DB->get_record('quora_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);
        $post->subject       = $quora->name;
        $post->message       = $quora->intro;
        $post->messageformat = $quora->introformat;
        $post->messagetrust  = trusttext_trusted($modcontext);
        $post->modified      = $quora->timemodified;
        $post->userid        = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_quora', 'post', $post->id, $options, $post->message);
        }

        $DB->update_record('quora_posts', $post);
        $discussion->name = $quora->name;
        $DB->update_record('quora_discussions', $discussion);
    }

    $DB->update_record('quora', $quora);

    $modcontext = context_module::instance($quora->coursemodule);
    if (($quora->forcesubscribe == FORUM_INITIALSUBSCRIBE) && ($oldquora->forcesubscribe <> $quora->forcesubscribe)) {
        $users = \mod_quora\subscriptions::get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            \mod_quora\subscriptions::subscribe_user($user->id, $quora, $modcontext);
        }
    }

    quora_grade_item_update($quora);

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id quora instance id
 * @return bool success
 */
function quora_delete_instance($id) {
    global $DB;

    if (!$quora = $DB->get_record('quora', array('id'=>$id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('quora', $quora->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        return false;
    }

    $context = context_module::instance($cm->id);

    // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $result = true;

    // Delete digest and subscription preferences.
    $DB->delete_records('quora_digests', array('quora' => $quora->id));
    $DB->delete_records('quora_subscriptions', array('quora'=>$quora->id));
    $DB->delete_records('quora_discussion_subs', array('quora' => $quora->id));

    if ($discussions = $DB->get_records('quora_discussions', array('quora'=>$quora->id))) {
        foreach ($discussions as $discussion) {
            if (!quora_delete_discussion($discussion, true, $course, $cm, $quora)) {
                $result = false;
            }
        }
    }

    quora_tp_delete_read_records(-1, -1, -1, $quora->id);

    if (!$DB->delete_records('quora', array('id'=>$quora->id))) {
        $result = false;
    }

    quora_grade_item_delete($quora);

    return $result;
}


/**
 * Indicates API features that the quora supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function quora_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_RATE:                    return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_PLAGIARISM:              return true;

        default: return null;
    }
}


/**
 * Obtains the automatic completion state for this quora based on any conditions
 * in quora settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function quora_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get quora details
    if (!($quora=$DB->get_record('quora',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find quora {$cm->instance}");
    }

    $result=$type; // Default return value

    $postcountparams=array('userid'=>$userid,'quoraid'=>$quora->id);
    $postcountsql="
SELECT
    COUNT(1)
FROM
    {quora_posts} fp
    INNER JOIN {quora_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.quora=:quoraid";

    if ($quora->completiondiscussions) {
        $value = $quora->completiondiscussions <=
                 $DB->count_records('quora_discussions',array('quora'=>$quora->id,'userid'=>$userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($quora->completionreplies) {
        $value = $quora->completionreplies <=
                 $DB->get_field_sql( $postcountsql.' AND fp.parent<>0',$postcountparams);
        if ($type==COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($quora->completionposts) {
        $value = $quora->completionposts <= $DB->get_field_sql($postcountsql,$postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

/**
 * Create a message-id string to use in the custom headers of quora notification emails
 *
 * message-id is used by email clients to identify emails and to nest conversations
 *
 * @param int $postid The ID of the quora post we are notifying the user about
 * @param int $usertoid The ID of the user being notified
 * @param string $hostname The server's hostname
 * @return string A unique message-id
 */
function quora_get_email_message_id($postid, $usertoid, $hostname) {
    return '<'.hash('sha256',$postid.'to'.$usertoid).'@'.$hostname.'>';
}

/**
 * Removes properties from user record that are not necessary
 * for sending post notifications.
 * @param stdClass $user
 * @return void, $user parameter is modified
 */
function quora_cron_minimise_user_record(stdClass $user) {

    // We store large amount of users in one huge array,
    // make sure we do not store info there we do not actually need
    // in mail generation code or messaging.

    unset($user->institution);
    unset($user->department);
    unset($user->address);
    unset($user->city);
    unset($user->url);
    unset($user->currentlogin);
    unset($user->description);
    unset($user->descriptionformat);
}

/**
 * Function to be run periodically according to the scheduled task.
 *
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers as well as other maintance tasks.
 *
 * NOTE: Since 2.7.2 this function is run by scheduled task rather
 * than standard cron.
 *
 * @todo MDL-44734 The function will be split up into seperate tasks.
 */
function quora_cron() {
    global $CFG, $USER, $DB;

    $site = get_site();

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0; // Cached user counter - count($users) in PHP is horribly slow!!!

    // Status arrays.
    $mailcount  = array();
    $errorcount = array();

    // caches
    $discussions        = array();
    $quoras             = array();
    $courses            = array();
    $coursemodules      = array();
    $subscribedusers    = array();
    $messageinboundhandlers = array();

    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow   = time();
    $endtime   = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier

    // Get the list of quora subscriptions for per-user per-quora maildigest settings.
    $digestsset = $DB->get_recordset('quora_digests', null, '', 'id, userid, quora, maildigest');
    $digests = array();
    foreach ($digestsset as $thisrow) {
        if (!isset($digests[$thisrow->quora])) {
            $digests[$thisrow->quora] = array();
        }
        $digests[$thisrow->quora][$thisrow->userid] = $thisrow->maildigest;
    }
    $digestsset->close();

    // Create the generic messageinboundgenerator.
    $messageinboundgenerator = new \core\message\inbound\address_manager();
    $messageinboundgenerator->set_handler('\mod_quora\message\inbound\reply_handler');

    if ($posts = quora_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!quora_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('quora_discussions', array('id'=> $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                    \mod_quora\subscriptions::fill_subscription_cache($discussion->quora);
                    \mod_quora\subscriptions::fill_discussion_subscription_cache($discussion->quora);

                } else {
                    mtrace('Could not find discussion ' . $discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $quoraid = $discussions[$discussionid]->quora;
            if (!isset($quoras[$quoraid])) {
                if ($quora = $DB->get_record('quora', array('id' => $quoraid))) {
                    $quoras[$quoraid] = $quora;
                } else {
                    mtrace('Could not find quora '.$quoraid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $quoras[$quoraid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course '.$courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$quoraid])) {
                if ($cm = get_coursemodule_from_instance('quora', $quoraid, $courseid)) {
                    $coursemodules[$quoraid] = $cm;
                } else {
                    mtrace('Could not find course module for quora '.$quoraid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            // Save the Inbound Message datakey here to reduce DB queries later.
            $messageinboundgenerator->set_data($pid);
            $messageinboundhandlers[$pid] = $messageinboundgenerator->fetch_data_key();

            // Caching subscribed users of each quora.
            if (!isset($subscribedusers[$quoraid])) {
                $modcontext = context_module::instance($coursemodules[$quoraid]->id);
                if ($subusers = \mod_quora\subscriptions::fetch_subscribed_users($quoras[$quoraid], 0, $modcontext, 'u.*', true)) {

                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this quora
                        $subscribedusers[$quoraid][$postuser->id] = $postuser->id;
                        $userscount++;
                        if ($userscount > FORUM_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser->id = $postuser->id;
                            $users[$postuser->id] = $minuser;
                        } else {
                            // Cache full user record.
                            quora_cron_minimise_user_record($postuser);
                            $users[$postuser->id] = $postuser;
                        }
                    }
                    // Release memory.
                    unset($subusers);
                    unset($postuser);
                }
            }
            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {
            // Terminate if processing of any account takes longer than 2 minutes.
            core_php_time_limit::raise(120);

            mtrace('Processing user ' . $userto->id);

            // Init user caches - we keep the cache for one cycle only, otherwise it could consume too much memory.
            if (isset($userto->username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB->get_record('user', array('id' => $userto->id));
                quora_cron_minimise_user_record($userto);
            }
            $userto->viewfullnames = array();
            $userto->canpost       = array();
            $userto->markposts     = array();

            // Setup this user so that the capabilities are cached, and environment matches receiving user.
            cron_setup_user($userto);

            // Reset the caches.
            foreach ($coursemodules as $quoraid => $unused) {
                $coursemodules[$quoraid]->cache       = new stdClass();
                $coursemodules[$quoraid]->cache->caps = array();
                unset($coursemodules[$quoraid]->uservisible);
            }

            foreach ($posts as $pid => $post) {
                $discussion = $discussions[$post->discussion];
                $quora      = $quoras[$discussion->quora];
                $course     = $courses[$quora->course];
                $cm         =& $coursemodules[$quora->id];

                // Do some checks to see if we can bail out now.

                // Only active enrolled users are in the list of subscribers.
                // This does not necessarily mean that the user is subscribed to the quora or to the discussion though.
                if (!isset($subscribedusers[$quora->id][$userto->id])) {
                    // The user does not subscribe to this quora.
                    continue;
                }

                if (!\mod_quora\subscriptions::is_subscribed($userto->id, $quora, $post->discussion, $coursemodules[$quora->id])) {
                    // The user does not subscribe to this quora, or to this specific discussion.
                    continue;
                }

                if ($subscriptiontime = \mod_quora\subscriptions::fetch_discussion_subscription($quora->id, $userto->id)) {
                    // Skip posts if the user subscribed to the discussion after it was created.
                    if (isset($subscriptiontime[$post->discussion]) && ($subscriptiontime[$post->discussion] > $post->created)) {
                        continue;
                    }
                }

                // Don't send email if the quora is Q&A and the user has not posted.
                // Initial topics are still mailed.
                if ($quora->type == 'qanda' && !quora_get_user_posted_time($discussion->id, $userto->id) && $pid != $discussion->firstpost) {
                    mtrace('Did not email ' . $userto->id.' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user.
                if (array_key_exists($post->userid, $users)) {
                    // We might know the user already.
                    $userfrom = $users[$post->userid];
                    if (!isset($userfrom->idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                        quora_cron_minimise_user_record($userfrom);
                    }

                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    quora_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= FORUM_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom->id] = $userfrom;
                    }
                } else {
                    mtrace('Could not find user ' . $post->userid . ', author of post ' . $post->id . '. Unable to send message.');
                    continue;
                }

                // Note: If we want to check that userto and userfrom are not the same person this is probably the spot to do it.

                // Setup global $COURSE properly - needed for roles and languages.
                cron_setup_user($userto, $course);

                // Fill caches.
                if (!isset($userto->viewfullnames[$quora->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->viewfullnames[$quora->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->canpost[$discussion->id] = quora_user_can_post($quora, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$quora->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        if (isset($users[$userfrom->id])) {
                            $users[$userfrom->id]->groups = array();
                        }
                    }
                    $userfrom->groups[$quora->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    if (isset($users[$userfrom->id])) {
                        $users[$userfrom->id]->groups[$quora->id] = $userfrom->groups[$quora->id];
                    }
                }

                // Make sure groups allow this user to see this email.
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {
                    // Groups are being used.
                    if (!groups_group_exists($discussion->groupid)) {
                        // Can't find group - be safe and don't this message.
                        continue;
                    }

                    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // Do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS.
                        continue;
                    }
                }

                // Make sure we're allowed to see the post.
                if (!quora_user_can_see_post($quora, $discussion, $post, null, $cm)) {
                    mtrace('User ' . $userto->id .' can not see ' . $post->id . '. Not sending message.');
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                $maildigest = quora_get_user_maildigest_bulk($digests, $userto, $quora->id);

                if ($maildigest > 0) {
                    // This user wants the mails to be in digest form.
                    $queue = new stdClass();
                    $queue->userid       = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid       = $post->id;
                    $queue->timemodified = $post->created;
                    $DB->insert_record('quora_queue', $queue);
                    continue;
                }

                // Prepare to actually send the post now, and build up the content.

                $cleanquoraname = str_replace('"', "'", strip_tags(format_string($quora->name)));

                $userfrom->customheaders = array (
                    // Headers to make emails easier to track.
                    'List-Id: "'        . $cleanquoraname . '" <moodlequora' . $quora->id . '@' . $hostname.'>',
                    'List-Help: '       . $CFG->wwwroot . '/mod/quora/view.php?f=' . $quora->id,
                    'Message-ID: '      . quora_get_email_message_id($post->id, $userto->id, $hostname),
                    'X-Course-Id: '     . $course->id,
                    'X-Course-Name: '   . format_string($course->fullname, true),

                    // Headers to help prevent auto-responders.
                    'Precedence: Bulk',
                    'X-Auto-Response-Suppress: All',
                    'Auto-Submitted: auto-generated',
                );

                if ($post->parent) {
                    // This post is a reply, so add headers for threading (see MDL-22551).
                    $userfrom->customheaders[] = 'In-Reply-To: ' . quora_get_email_message_id($post->parent, $userto->id, $hostname);
                    $userfrom->customheaders[] = 'References: '  . quora_get_email_message_id($post->parent, $userto->id, $hostname);
                }

                $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                // Generate a reply-to address from using the Inbound Message handler.
                $replyaddress = null;
                if ($userto->canpost[$discussion->id] && array_key_exists($post->id, $messageinboundhandlers)) {
                    $messageinboundgenerator->set_data($post->id, $messageinboundhandlers[$post->id]);
                    $replyaddress = $messageinboundgenerator->generate($userto->id);
                }

                $a = new stdClass();
                $a->courseshortname = $shortname;
                $a->quoraname = $cleanquoraname;
                $a->subject = format_string($post->subject, true);
                $postsubject = html_to_text(get_string('postmailsubject', 'quora', $a), 0);
                $posttext = quora_make_mail_text($course, $cm, $quora, $discussion, $post, $userfrom, $userto, false,
                        $replyaddress);
                $posthtml = quora_make_mail_html($course, $cm, $quora, $discussion, $post, $userfrom, $userto,
                        $replyaddress);

                // Send the post now!
                mtrace('Sending ', '');

                $eventdata = new \core\message\message();
                $eventdata->component           = 'mod_quora';
                $eventdata->name                = 'posts';
                $eventdata->userfrom            = $userfrom;
                $eventdata->userto              = $userto;
                $eventdata->subject             = $postsubject;
                $eventdata->fullmessage         = $posttext;
                $eventdata->fullmessageformat   = FORMAT_PLAIN;
                $eventdata->fullmessagehtml     = $posthtml;
                $eventdata->notification        = 1;
                $eventdata->replyto             = $replyaddress;
                if (!empty($replyaddress)) {
                    // Add extra text to email messages if they can reply back.
                    $textfooter = "\n\n" . get_string('replytopostbyemail', 'mod_quora');
                    $htmlfooter = html_writer::tag('p', get_string('replytopostbyemail', 'mod_quora'));
                    $additionalcontent = array('fullmessage' => array('footer' => $textfooter),
                                     'fullmessagehtml' => array('footer' => $htmlfooter));
                    $eventdata->set_additional_content('email', $additionalcontent);
                }

                // If quora_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG->quora_replytouser)) {
                    $eventdata->userfrom = core_user::get_noreply_user();
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings->user          = fullname($userfrom);
                $smallmessagestrings->quoraname     = "$shortname: " . format_string($quora->name, true) . ": " . $discussion->name;
                $smallmessagestrings->message       = $post->message;

                // Make sure strings are in message recipients language.
                $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'quora', $smallmessagestrings, $userto->lang);

                $contexturl = new moodle_url('/mod/quora/discuss.php', array('d' => $discussion->id), 'p' . $post->id);
                $eventdata->contexturl = $contexturl->out();
                $eventdata->contexturlname = $discussion->name;

                $mailresult = message_send($eventdata);
                if (!$mailresult) {
                    mtrace("Error: mod/quora/lib.php quora_cron(): Could not send out mail for id $post->id to user $userto->id".
                            " ($userto->email) .. not trying again.");
                    $errorcount[$post->id]++;
                } else {
                    $mailcount[$post->id]++;

                    // Mark post as read if quora_usermarksread is set off.
                    if (!$CFG->quora_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post ' . $post->id . ': ' . $post->subject);
            }

            // Mark processed posts as read.
            quora_tp_mark_posts_read($userto, $userto->markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id]." users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                $DB->set_field('quora_posts', 'mailed', FORUM_MAILED_ERROR, array('id' => $post->id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = core_date::get_server_timezone();

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    core_php_time_limit::raise(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->digestmailtimelast)) {    // To catch the first time
        set_config('digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB->delete_records_select('quora_queue', "timemodified < ?", array($weekago));
    mtrace ('Cleaned old digest records');

    if ($CFG->digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending quora digests: '.userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB->get_recordset_select('quora_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs->valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost->postid])) {
                    if ($post = $DB->get_record('quora_posts', array('id' => $digestpost->postid))) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB->get_record('quora_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $quoraid = $discussions[$discussionid]->quora;
                if (!isset($quoras[$quoraid])) {
                    if ($quora = $DB->get_record('quora', array('id' => $quoraid))) {
                        $quoras[$quoraid] = $quora;
                    } else {
                        continue;
                    }
                }

                $courseid = $quoras[$quoraid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB->get_record('course', array('id' => $courseid))) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$quoraid])) {
                    if ($cm = get_coursemodule_from_instance('quora', $quoraid, $courseid)) {
                        $coursemodules[$quoraid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            $digestposts_rs->close(); /// Finished iteration, let's close the resultset

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                core_php_time_limit::raise(120); // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'quora', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB->delete_records_select('quora_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) and isset($users[$userid]->username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB->get_record('user', array('id' => $userid));
                    quora_cron_minimise_user_record($userto);
                }
                $userto->viewfullnames = array();
                $userto->canpost       = array();
                $userto->markposts     = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'quora', format_string($site->shortname, true));

                $headerdata = new stdClass();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot.'/user/edit.php?id='.$userid.'&amp;course='.$site->id;

                $posttext = get_string('digestmailheader', 'quora', $headerdata)."\n\n";
                $headerdata->userprefs = '<a target="_blank" href="'.$headerdata->userprefs.'">'.get_string('digestmailprefs', 'quora').'</a>';

                $posthtml = "<head>";
/*                foreach ($CFG->stylesheets as $stylesheet) {
                    //TODO: MDL-21120
                    $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
                }*/
                $posthtml .= "</head>\n<body id=\"email\">\n";
                $posthtml .= '<p>'.get_string('digestmailheader', 'quora', $headerdata).'</p><br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    core_php_time_limit::raise(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $quora      = $quoras[$discussion->quora];
                    $course     = $courses[$quora->course];
                    $cm         = $coursemodules[$quora->id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$quora->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->viewfullnames[$quora->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->canpost[$discussion->id] = quora_user_can_post($quora, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strquoras      = get_string('quoras', 'quora');
                    $canunsubscribe = ! \mod_quora\subscriptions::is_forcesubscribed($quora);
                    $canreply       = $userto->canpost[$discussion->id];
                    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strquoras -> ".format_string($quora->name,true);
                    if ($discussion->name != $quora->name) {
                        $posttext  .= " -> ".format_string($discussion->name,true);
                    }
                    $posttext .= "\n";
                    $posttext .= $CFG->wwwroot.'/mod/quora/discuss.php?d='.$discussion->id;
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/quora/index.php?id=$course->id\">$strquoras</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/quora/view.php?f=$quora->id\">".format_string($quora->name,true)."</a>";
                    if ($discussion->name == $quora->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/quora/discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                            if (!isset($userfrom->idnumber)) {
                                $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                                quora_cron_minimise_user_record($userfrom);
                            }

                        } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                            quora_cron_minimise_user_record($userfrom);
                            if ($userscount <= FORUM_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom->id] = $userfrom;
                            }

                        } else {
                            mtrace('Could not find user '.$post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$quora->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                if (isset($users[$userfrom->id])) {
                                    $users[$userfrom->id]->groups = array();
                                }
                            }
                            $userfrom->groups[$quora->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            if (isset($users[$userfrom->id])) {
                                $users[$userfrom->id]->groups[$quora->id] = $userfrom->groups[$quora->id];
                            }
                        }

                        // Headers to help prevent auto-responders.
                        $userfrom->customheaders = array(
                                "Precedence: Bulk",
                                'X-Auto-Response-Suppress: All',
                                'Auto-Submitted: auto-generated',
                            );

                        $maildigest = quora_get_user_maildigest_bulk($digests, $userto, $quora->id);
                        if ($maildigest == 2) {
                            // Subjects and link only
                            $posttext .= "\n";
                            $posttext .= $CFG->wwwroot.'/mod/quora/discuss.php?d='.$discussion->id;
                            $by = new stdClass();
                            $by->name = fullname($userfrom);
                            $by->date = userdate($post->modified);
                            $posttext .= "\n".format_string($post->subject,true).' '.get_string("bynameondate", "quora", $by);
                            $posttext .= "\n---------------------------------------------------------------------";

                            $by->name = "<a target=\"_blank\" href=\"$CFG->wwwroot/user/view.php?id=$userfrom->id&amp;course=$course->id\">$by->name</a>";
                            $posthtml .= '<div><a target="_blank" href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$discussion->id.'#p'.$post->id.'">'.format_string($post->subject,true).'</a> '.get_string("bynameondate", "quora", $by).'</div>';

                        } else {
                            // The full treatment
                            $posttext .= quora_make_mail_text($course, $cm, $quora, $discussion, $post, $userfrom, $userto, true);
                            $posthtml .= quora_make_mail_post($course, $cm, $quora, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

                        // Create an array of postid's for this user to mark as read.
                            if (!$CFG->quora_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                    }
                    $footerlinks = array();
                    if ($canunsubscribe) {
                        $footerlinks[] = "<a href=\"$CFG->wwwroot/mod/quora/subscribe.php?id=$quora->id\">" . get_string("unsubscribe", "quora") . "</a>";
                    } else {
                        $footerlinks[] = get_string("everyoneissubscribed", "quora");
                    }
                    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/quora/index.php?id={$quora->course}'>" . get_string("digestmailpost", "quora") . '</a>';
                    $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . implode('&nbsp;', $footerlinks) . '</font></div>';
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }
                $posthtml .= '</body>';

                if (empty($userto->mailformat) || $userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $attachment = $attachname='';
                // Directly email quora digests rather than sending them via messaging, use the
                // site shortname as 'from name', the noreply address will be used by email_to_user.
                $mailresult = email_to_user($userto, $site->shortname, $postsubject, $posttext, $posthtml, $attachment, $attachname);

                if (!$mailresult) {
                    mtrace("ERROR: mod/quora/cron.php: Could not send out digest mail to user $userto->id ".
                        "($userto->email)... not trying again.");
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if quora_usermarksread is set off
                    quora_tp_mark_posts_read($userto, $userto->markposts);
                }
            }
        }
    /// We have finishied all digest emails, update $CFG->digestmailtimelast
        set_config('digestmailtimelast', $timenow);
    }

    cron_setup_user();

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'quora', $usermailcount));
    }

    if (!empty($CFG->quora_lastreadclean)) {
        $timenow = time();
        if ($CFG->quora_lastreadclean + (24*3600) < $timenow) {
            set_config('quora_lastreadclean', $timenow);
            mtrace('Removing old quora read tracking info...');
            quora_tp_clean_read_records();
        }
    } else {
        set_config('quora_lastreadclean', time());
    }

    return true;
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $course
 * @param object $cm
 * @param object $quora
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email body in plain text format.
 */
function quora_make_mail_text($course, $cm, $quora, $discussion, $post, $userfrom, $userto, $bare = false, $replyaddress = null) {
    global $CFG, $USER;

    $modcontext = context_module::instance($cm->id);

    if (!isset($userto->viewfullnames[$quora->id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$quora->id];
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = quora_user_can_post($quora, $discussion, $userto, $cm, $course, $modcontext);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $by = New stdClass;
    $by->name = fullname($userfrom, $viewfullnames);
    $by->date = userdate($post->modified, "", core_date::get_user_timezone($userto));

    $strbynameondate = get_string('bynameondate', 'quora', $by);

    $strquoras = get_string('quoras', 'quora');

    $canunsubscribe = !\mod_quora\subscriptions::is_forcesubscribed($quora);

    $posttext = '';

    if (!$bare) {
        $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
        $posttext  .= "$shortname -> $strquoras -> ".format_string($quora->name,true);

        if ($discussion->name != $quora->name) {
            $posttext  .= " -> ".format_string($discussion->name,true);
        }
    }

    // add absolute file links
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_quora', 'post', $post->id);

    $posttext .= "\n";
    $posttext .= $CFG->wwwroot.'/mod/quora/discuss.php?d='.$discussion->id;
    $posttext .= "\n";
    $posttext .= format_string($post->subject,true);
    if ($bare) {
        $posttext .= " ($CFG->wwwroot/mod/quora/discuss.php?d=$discussion->id#p$post->id)";
    }
    $posttext .= "\n".$strbynameondate."\n";
    $posttext .= "---------------------------------------------------------------------\n";
    $posttext .= format_text_email($post->message, $post->messageformat);
    $posttext .= "\n\n";
    $posttext .= quora_print_attachments($post, $cm, "text");
    $posttext .= "\n---------------------------------------------------------------------\n";

    if (!$bare) {
        if ($canreply) {
            $posttext .= get_string("postmailinfo", "quora", $shortname)."\n";
            $posttext .= "$CFG->wwwroot/mod/quora/post.php?reply=$post->id\n";
        }

        if ($canunsubscribe) {
            if (\mod_quora\subscriptions::is_subscribed($userto->id, $quora, null, $cm)) {
                // If subscribed to this quora, offer the unsubscribe link.
                $posttext .= get_string("unsubscribe", "quora");
                $posttext .= ": $CFG->wwwroot/mod/quora/subscribe.php?id=$quora->id\n";
            }
            // Always offer the unsubscribe from discussion link.
            $posttext .= get_string("unsubscribediscussion", "quora");
            $posttext .= ": $CFG->wwwroot/mod/quora/subscribe.php?id=$quora->id&d=$discussion->id\n";
        }
    }

    $posttext .= get_string("digestmailpost", "quora");
    $posttext .= ": {$CFG->wwwroot}/mod/quora/index.php?id={$quora->course}\n";

    return $posttext;
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $quora
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email text in HTML format
 */
function quora_make_mail_html($course, $cm, $quora, $discussion, $post, $userfrom, $userto, $replyaddress = null) {
    global $CFG;

    if ($userto->mailformat != 1) {  // Needs to be HTML
        return '';
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = quora_user_can_post($quora, $discussion, $userto, $cm, $course);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $strquoras = get_string('quoras', 'quora');
    $canunsubscribe = ! \mod_quora\subscriptions::is_forcesubscribed($quora);
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

    $posthtml = '<head>';
/*    foreach ($CFG->stylesheets as $stylesheet) {
        //TODO: MDL-21120
        $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
    }*/
    $posthtml .= '</head>';
    $posthtml .= "\n<body id=\"email\">\n\n";

    $posthtml .= '<div class="navbar">'.
    '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$shortname.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/quora/index.php?id='.$course->id.'">'.$strquoras.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/quora/view.php?f='.$quora->id.'">'.format_string($quora->name,true).'</a>';
    if ($discussion->name == $quora->name) {
        $posthtml .= '</div>';
    } else {
        $posthtml .= ' &raquo; <a target="_blank" href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$discussion->id.'">'.
                     format_string($discussion->name,true).'</a></div>';
    }
    $posthtml .= quora_make_mail_post($course, $cm, $quora, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

    $footerlinks = array();
    if ($canunsubscribe) {
        if (\mod_quora\subscriptions::is_subscribed($userto->id, $quora, null, $cm)) {
            // If subscribed to this quora, offer the unsubscribe link.
            $unsublink = new moodle_url('/mod/quora/subscribe.php', array('id' => $quora->id));
            $footerlinks[] = html_writer::link($unsublink, get_string('unsubscribe', 'mod_quora'));
        }
        // Always offer the unsubscribe from discussion link.
        $unsublink = new moodle_url('/mod/quora/subscribe.php', array(
                'id' => $quora->id,
                'd' => $discussion->id,
            ));
        $footerlinks[] = html_writer::link($unsublink, get_string('unsubscribediscussion', 'mod_quora'));

        $footerlinks[] = '<a href="' . $CFG->wwwroot . '/mod/quora/unsubscribeall.php">' . get_string('unsubscribeall', 'quora') . '</a>';
    }
    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/quora/index.php?id={$quora->course}'>" . get_string('digestmailpost', 'quora') . '</a>';
    $posthtml .= '<hr /><div class="mdl-align unsubscribelink">' . implode('&nbsp;', $footerlinks) . '</div>';

    $posthtml .= '</body>';

    return $posthtml;
}


/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $quora
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function quora_user_outline($course, $user, $mod, $quora) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'quora', $quora->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = quora_count_user_posts($quora->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new stdClass();
        $result->info = get_string("numposts", "quora", $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }

        return $result;
    }
    return NULL;
}


/**
 * @global object
 * @global object
 * @param object $coure
 * @param object $user
 * @param object $mod
 * @param object $quora
 */
function quora_user_complete($course, $user, $mod, $quora) {
    global $CFG,$USER, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'quora', $quora->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    if ($posts = quora_get_user_posts($quora->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = quora_get_user_involved_discussions($quora->id, $user->id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];

            quora_print_post($post, $discussion, $quora, $cm, $course, $is_assessed, false, false, false);
        }
    } else {
        echo "<p>".get_string("noposts", "quora")."</p>";
    }
}

/**
 * Filters the quora discussions according to groups membership and config.
 *
 * @since  Moodle 2.8, 2.7.1, 2.6.4
 * @param  array $discussions Discussions with new posts array
 * @return array Forums with the number of new posts
 */
function quora_filter_user_groups_discussions($discussions) {

    // Group the remaining discussions posts by their quoraid.
    $filteredquoras = array();

    // Discard not visible groups.
    foreach ($discussions as $discussion) {

        // Course data is already cached.
        $instances = get_fast_modinfo($discussion->course)->get_instances();
        $quora = $instances['quora'][$discussion->quora];

        // Continue if the user should not see this discussion.
        if (!quora_is_user_group_discussion($quora, $discussion->groupid)) {
            continue;
        }

        // Grouping results by quora.
        if (empty($filteredquoras[$quora->instance])) {
            $filteredquoras[$quora->instance] = new stdClass();
            $filteredquoras[$quora->instance]->id = $quora->id;
            $filteredquoras[$quora->instance]->count = 0;
        }
        $filteredquoras[$quora->instance]->count += $discussion->count;

    }

    return $filteredquoras;
}

/**
 * Returns whether the discussion group is visible by the current user or not.
 *
 * @since Moodle 2.8, 2.7.1, 2.6.4
 * @param cm_info $cm The discussion course module
 * @param int $discussiongroupid The discussion groupid
 * @return bool
 */
function quora_is_user_group_discussion(cm_info $cm, $discussiongroupid) {

    if ($discussiongroupid == -1 || $cm->effectivegroupmode != SEPARATEGROUPS) {
        return true;
    }

    if (isguestuser()) {
        return false;
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id)) ||
            in_array($discussiongroupid, $cm->get_modinfo()->get_groups($cm->groupingid))) {
        return true;
    }

    return false;
}

/**
 * @global object
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray
 */
function quora_print_overview($courses,&$htmlarray) {
    global $USER, $CFG, $DB, $SESSION;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$quoras = get_all_instances_in_courses('quora',$courses)) {
        return;
    }

    // Courses to search for new posts
    $coursessqls = array();
    $params = array();
    foreach ($courses as $course) {

        // If the user has never entered into the course all posts are pending
        if ($course->lastaccess == 0) {
            $coursessqls[] = '(d.course = ?)';
            $params[] = $course->id;

        // Only posts created after the course last access
        } else {
            $coursessqls[] = '(d.course = ? AND p.created > ?)';
            $params[] = $course->id;
            $params[] = $course->lastaccess;
        }
    }
    $params[] = $USER->id;
    $coursessql = implode(' OR ', $coursessqls);

    $sql = "SELECT d.id, d.quora, d.course, d.groupid, COUNT(*) as count "
                .'FROM {quora_discussions} d '
                .'JOIN {quora_posts} p ON p.discussion = d.id '
                ."WHERE ($coursessql) "
                .'AND p.userid != ? '
                .'GROUP BY d.id, d.quora, d.course, d.groupid '
                .'ORDER BY d.course, d.quora';

    // Avoid warnings.
    if (!$discussions = $DB->get_records_sql($sql, $params)) {
        $discussions = array();
    }

    $quorasnewposts = quora_filter_user_groups_discussions($discussions);

    // also get all quora tracking stuff ONCE.
    $trackingquoras = array();
    foreach ($quoras as $quora) {
        if (quora_tp_can_track_quoras($quora)) {
            $trackingquoras[$quora->id] = $quora;
        }
    }

    if (count($trackingquoras) > 0) {
        $cutoffdate = isset($CFG->quora_oldpostdays) ? (time() - ($CFG->quora_oldpostdays*24*60*60)) : 0;
        $sql = 'SELECT d.quora,d.course,COUNT(p.id) AS count '.
            ' FROM {quora_posts} p '.
            ' JOIN {quora_discussions} d ON p.discussion = d.id '.
            ' LEFT JOIN {quora_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER->id);

        foreach ($trackingquoras as $track) {
            $sql .= '(d.quora = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
            $params[] = $track->id;
            if (isset($SESSION->currentgroup[$track->course])) {
                $groupid =  $SESSION->currentgroup[$track->course];
            } else {
                // get first groupid
                $groupids = groups_get_all_groups($track->course, $USER->id);
                if ($groupids) {
                    reset($groupids);
                    $groupid = key($groupids);
                    $SESSION->currentgroup[$track->course] = $groupid;
                } else {
                    $groupid = 0;
                }
                unset($groupids);
            }
            $params[] = $groupid;
        }
        $sql = substr($sql,0,-3); // take off the last OR
        $sql .= ') AND p.modified >= ? AND r.id is NULL GROUP BY d.quora,d.course';
        $params[] = $cutoffdate;

        if (!$unread = $DB->get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($quorasnewposts)) {
        return;
    }

    $strquora = get_string('modulename','quora');

    foreach ($quoras as $quora) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($quora->id, $quorasnewposts) && !empty($quorasnewposts[$quora->id])) {
            $count = $quorasnewposts[$quora->id]->count;
        }
        if (array_key_exists($quora->id,$unread)) {
            $thisunread = $unread[$quora->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview quora"><div class="name">'.$strquora.': <a title="'.$strquora.'" href="'.$CFG->wwwroot.'/mod/quora/view.php?f='.$quora->id.'">'.
                $quora->name.'</a></div>';
            $str .= '<div class="info"><span class="postsincelogin">';
            $str .= get_string('overviewnumpostssince', 'quora', $count)."</span>";
            if (!empty($showunread)) {
                $str .= '<div class="unreadposts">'.get_string('overviewnumunread', 'quora', $thisunread).'</div>';
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($quora->course,$htmlarray)) {
                $htmlarray[$quora->course] = array();
            }
            if (!array_key_exists('quora',$htmlarray[$quora->course])) {
                $htmlarray[$quora->course]['quora'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$quora->course]['quora'] .= $str;
        }
    }
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function quora_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge and is expensive to join with other tables

    $allnamefields = user_picture::fields('u', null, 'duserid');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS quoratype, d.quora, d.groupid,
                                              d.timestart, d.timeend, $allnamefields
                                         FROM {quora_posts} p
                                              JOIN {quora_discussions} d ON d.id = p.discussion
                                              JOIN {quora} f             ON f.id = d.quora
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ?
                                     ORDER BY p.id ASC", array($timestart, $course->id))) { // order by initial posting date
         return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms    = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['quora'][$post->quora])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['quora'][$post->quora];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/quora:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->quora_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!has_capability('mod/quora:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        // Check that the user can see the discussion.
        if (quora_is_user_group_discussion($cm, $post->groupid)) {
            $printposts[] = $post;
        }

    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newquoraposts', 'quora').':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        echo '<li><div class="head">'.
               '<div class="date">'.userdate($post->modified, $strftimerecent).'</div>'.
               '<div class="name">'.fullname($post, $viewfullnames).'</div>'.
             '</div>';
        echo '<div class="info'.$subjectclass.'">';
        if (empty($post->parent)) {
            echo '"<a href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'">';
        } else {
            echo '"<a href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'&amp;parent='.$post->parent.'#p'.$post->id.'">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @global object
 * @param object $quora
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function quora_get_user_grades($quora, $userid = 0) {
    global $CFG;

    require_once($CFG->dirroot.'/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions->component = 'mod_quora';
    $ratingoptions->ratingarea = 'post';

    //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions->modulename = 'quora';
    $ratingoptions->moduleid   = $quora->id;
    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $quora->assessed;
    $ratingoptions->scaleid = $quora->scale;
    $ratingoptions->itemtable = 'quora_posts';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
}

/**
 * Update activity grades
 *
 * @category grade
 * @param object $quora
 * @param int $userid specific user only, 0 means all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 */
function quora_update_grades($quora, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (!$quora->assessed) {
        quora_grade_item_update($quora);

    } else if ($grades = quora_get_user_grades($quora, $userid)) {
        quora_grade_item_update($quora, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = NULL;
        quora_grade_item_update($quora, $grade);

    } else {
        quora_grade_item_update($quora);
    }
}

/**
 * Create/update grade item for given quora
 *
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_SCALE
 * @param stdClass $quora Forum object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function quora_grade_item_update($quora, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname'=>$quora->name, 'idnumber'=>$quora->cmidnumber);

    if (!$quora->assessed or $quora->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($quora->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $quora->scale;
        $params['grademin']  = 0;

    } else if ($quora->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$quora->scale;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/quora', $quora->course, 'mod', 'quora', $quora->id, 0, $grades, $params);
}

/**
 * Delete grade item for given quora
 *
 * @category grade
 * @param stdClass $quora Forum object
 * @return grade_item
 */
function quora_grade_item_delete($quora) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/quora', $quora->course, 'mod', 'quora', $quora->id, 0, NULL, array('deleted'=>1));
}


/**
 * This function returns if a scale is being used by one quora
 *
 * @global object
 * @param int $quoraid
 * @param int $scaleid negative number
 * @return bool
 */
function quora_scale_used ($quoraid,$scaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record("quora",array("id" => "$quoraid","scale" => "-$scaleid"));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of quora
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any quora
 */
function quora_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('quora', array('scale' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

// SQL FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Gets a post with all info ready for quora_print_post
 * Most of these joins are just to get the quora id
 *
 * @global object
 * @global object
 * @param int $postid
 * @return mixed array of posts or false
 */
function quora_get_post_full($postid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_record_sql("SELECT p.*, d.quora, $allnames, u.email, u.picture, u.imagealt
                             FROM {quora_posts} p
                                  JOIN {quora_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?", array($postid));
}

/**
 * Gets all posts in discussion including top parent.
 *
 * @global object
 * @global object
 * @global object
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the quora?
 * @return array of posts
 */
function quora_get_all_discussion_posts($discussionid, $sort, $tracking=false) {
    global $CFG, $DB, $USER;

    $tr_sel  = "";
    $tr_join = "";
    $params = array();

    if ($tracking) {
        $now = time();
        $cutoffdate = $now - ($CFG->quora_oldpostdays * 24 * 3600);
        $tr_sel  = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {quora_read} fr ON (fr.postid = p.id AND fr.userid = ?)";
        $params[] = $USER->id;
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $params[] = $discussionid;
    if (!$posts = $DB->get_records_sql("SELECT p.*, $allnames, u.email, u.picture, u.imagealt $tr_sel
                                     FROM {quora_posts} p
                                          LEFT JOIN {user} u ON p.userid = u.id
                                          $tr_join
                                    WHERE p.discussion = ?
                                 ORDER BY $sort", $params)) {
        return array();
    }

    foreach ($posts as $pid=>$p) {
        if ($tracking) {
            if (quora_tp_is_post_old($p)) {
                 $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }

    // Start with the last child of the first post.
    $post = &$posts[reset($posts)->id];

    $lastpost = false;
    while (!$lastpost) {
        if (!isset($post->children)) {
            $post->lastpost = true;
            $lastpost = true;
        } else {
             // Go to the last child of this post.
            $post = &$posts[end($post->children)->id];
        }
    }

    return $posts;
}

/**
 * An array of quora objects that the user is allowed to read/search through.
 *
 * @global object
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid if 0, we look for quoras throughout the whole site.
 * @return array of quora objects, or false if no matches
 *         Forum objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function quora_get_readable_quoras($userid, $courseid=0) {

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot.'/course/lib.php');

    if (!$quoramod = $DB->get_record('modules', array('name' => 'quora'))) {
        print_error('notinstalled', 'quora');
    }

    if ($courseid) {
        $courses = $DB->get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB->get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readablequoras = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->instances['quora'])) {
            // hmm, no quoras?
            continue;
        }

        $coursequoras = $DB->get_records('quora', array('course' => $course->id));

        foreach ($modinfo->instances['quora'] as $quoraid => $cm) {
            if (!$cm->uservisible or !isset($coursequoras[$quoraid])) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $quora = $coursequoras[$quoraid];
            $quora->context = $context;
            $quora->cm = $cm;

            if (!has_capability('mod/quora:viewdiscussion', $context)) {
                continue;
            }

         /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {

                $quora->onlygroups = $modinfo->get_groups($cm->groupingid);
                $quora->onlygroups[] = -1;
            }

        /// hidden timed discussions
            $quora->viewhiddentimedposts = true;
            if (!empty($CFG->quora_enabletimedposts)) {
                if (!has_capability('mod/quora:viewhiddentimedposts', $context)) {
                    $quora->viewhiddentimedposts = false;
                }
            }

        /// qanda access
            if ($quora->type == 'qanda'
                    && !has_capability('mod/quora:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda quora.
                $quora->onlydiscussions = array();  // Holds discussion ids for the discussions
                                                    // the user is allowed to see in this quora.
                if ($discussionspostedin = quora_discussions_user_has_posted_in($quora->id, $USER->id)) {
                    foreach ($discussionspostedin as $d) {
                        $quora->onlydiscussions[] = $d->id;
                    }
                }
            }

            $readablequoras[$quora->id] = $quora;
        }

        unset($modinfo);

    } // End foreach $courses

    return $readablequoras;
}

/**
 * Returns a list of posts found using an array of search terms.
 *
 * @global object
 * @global object
 * @global object
 * @param array $searchterms array of search terms, e.g. word +word -word
 * @param int $courseid if 0, we search through the whole site
 * @param int $limitfrom
 * @param int $limitnum
 * @param int &$totalcount
 * @param string $extrasql
 * @return array|bool Array of posts found or false
 */
function quora_search_posts($searchterms, $courseid=0, $limitfrom=0, $limitnum=50,
                            &$totalcount, $extrasql='') {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir.'/searchlib.php');

    $quoras = quora_get_readable_quoras($USER->id, $courseid);

    if (count($quoras) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // db friendly

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($quoras as $quoraid => $quora) {
        $select = array();

        if (!$quora->viewhiddentimedposts) {
            $select[] = "(d.userid = :userid{$quoraid} OR (d.timestart < :timestart{$quoraid} AND (d.timeend = 0 OR d.timeend > :timeend{$quoraid})))";
            $params = array_merge($params, array('userid'.$quoraid=>$USER->id, 'timestart'.$quoraid=>$now, 'timeend'.$quoraid=>$now));
        }

        $cm = $quora->cm;
        $context = $quora->context;

        if ($quora->type == 'qanda'
            && !has_capability('mod/quora:viewqandawithoutposting', $context)) {
            if (!empty($quora->onlydiscussions)) {
                list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($quora->onlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$quoraid.'_');
                $params = array_merge($params, $discussionid_params);
                $select[] = "(d.id $discussionid_sql OR p.parent = 0)";
            } else {
                $select[] = "p.parent = 0";
            }
        }

        if (!empty($quora->onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($quora->onlygroups, SQL_PARAMS_NAMED, 'grps'.$quoraid.'_');
            $params = array_merge($params, $groupid_params);
            $select[] = "d.groupid $groupid_sql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.quora = :quora{$quoraid} AND $selects)";
            $params['quora'.$quoraid] = $quoraid;
        } else {
            $fullaccess[] = $quoraid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = "(d.quora $fullid_sql)";
    }

    $selectdiscussion = "(".implode(" OR ", $where).")";

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach($searchterms as $searchterm){
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"","\"",$searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
        list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject',
                                                              'p.userid', 'u.id', 'u.firstname',
                                                              'u.lastname', 'p.modified', 'd.quora');
        $params = array_merge($params, $msparams);
    }

    $fromsql = "{quora_posts} p,
                  {quora_discussions} d,
                  {user} u";

    $selectsql = " $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

    $allnames = get_all_user_name_fields(true, 'u');
    $searchsql = "SELECT p.*,
                         d.quora,
                         $allnames,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);

    return $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function quora_get_unmailed_posts($starttime, $endtime, $now=null) {
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = FORUM_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG->quora_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.quora
                                 FROM {quora_posts} p
                                 JOIN {quora_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 AND p.created >= :ptimestart
                                 AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                                 $timedsql
                                 ORDER BY p.modified ASC", $params);
}

/**
 * Marks posts before a certain time as being mailed already
 *
 * @global object
 * @global object
 * @param int $endtime
 * @param int $now Defaults to time()
 * @return bool
 */
function quora_mark_old_posts_as_mailed($endtime, $now=null) {
    global $CFG, $DB;

    if (empty($now)) {
        $now = time();
    }

    $params = array();
    $params['mailedsuccess'] = FORUM_MAILED_SUCCESS;
    $params['now'] = $now;
    $params['endtime'] = $endtime;
    $params['mailnow'] = 1;
    $params['mailedpending'] = FORUM_MAILED_PENDING;

    if (empty($CFG->quora_enabletimedposts)) {
        return $DB->execute("UPDATE {quora_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    } else {
        return $DB->execute("UPDATE {quora_posts}
                             SET mailed = :mailedsuccess
                             WHERE discussion NOT IN (SELECT d.id
                                                      FROM {quora_discussions} d
                                                      WHERE d.timestart > :now)
                             AND (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    }
}

/**
 * Get all the posts for a user in a quora suitable for quora_print_post
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return array
 */
function quora_get_user_posts($quoraid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($quoraid, $userid);

    if (!empty($CFG->quora_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('quora', $quoraid);
        if (!has_capability('mod/quora:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.quora, $allnames, u.email, u.picture, u.imagealt
                              FROM {quora} f
                                   JOIN {quora_discussions} d ON d.quora = f.id
                                   JOIN {quora_posts} p       ON p.discussion = d.id
                                   JOIN {user} u              ON u.id = p.userid
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 * Get all the discussions user participated in
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $quoraid
 * @param int $userid
 * @return array Array or false
 */
function quora_get_user_involved_discussions($quoraid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($quoraid, $userid);
    if (!empty($CFG->quora_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('quora', $quoraid);
        if (!has_capability('mod/quora:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_records_sql("SELECT DISTINCT d.*
                              FROM {quora} f
                                   JOIN {quora_discussions} d ON d.quora = f.id
                                   JOIN {quora_posts} p       ON p.discussion = d.id
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql", $params);
}

/**
 * Get all the posts for a user in a quora suitable for quora_print_post
 *
 * @global object
 * @global object
 * @param int $quoraid
 * @param int $userid
 * @return array of counts or false
 */
function quora_count_user_posts($quoraid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($quoraid, $userid);
    if (!empty($CFG->quora_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('quora', $quoraid);
        if (!has_capability('mod/quora:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {quora} f
                                  JOIN {quora_discussions} d ON d.quora = f.id
                                  JOIN {quora_posts} p       ON p.discussion = d.id
                                  JOIN {user} u              ON u.id = p.userid
                            WHERE f.id = ?
                                  AND p.userid = ?
                                  $timedsql", $params);
}

/**
 * Given a log entry, return the quora post details for it.
 *
 * @global object
 * @global object
 * @param object $log
 * @return array|null
 */
function quora_get_post_from_log($log) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if ($log->action == "add post") {

        return $DB->get_record_sql("SELECT p.*, f.type AS quoratype, d.quora, d.groupid, $allnames, u.email, u.picture
                                 FROM {quora_discussions} d,
                                      {quora_posts} p,
                                      {quora} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.quora", array($log->info));


    } else if ($log->action == "add discussion") {

        return $DB->get_record_sql("SELECT p.*, f.type AS quoratype, d.quora, d.groupid, $allnames, u.email, u.picture
                                 FROM {quora_discussions} d,
                                      {quora_posts} p,
                                      {quora} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.quora", array($log->info));
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 *
 * @global object
 * @global object
 * @param int $dicsussionid
 * @return array
 */
function quora_get_firstpost_from_discussion($discussionid) {
    global $CFG, $DB;

    return $DB->get_record_sql("SELECT p.*
                             FROM {quora_discussions} d,
                                  {quora_posts} p
                            WHERE d.id = ?
                              AND d.firstpost = p.id ", array($discussionid));
}

/**
 * Returns an array of counts of replies to each discussion
 *
 * @global object
 * @global object
 * @param int $quoraid
 * @param string $quorasort
 * @param int $limit
 * @param int $page
 * @param int $perpage
 * @return array
 */
function quora_count_discussion_replies($quoraid, $quorasort="", $limit=-1, $page=-1, $perpage=0) {
    global $CFG, $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    if ($quorasort == "") {
        $orderby = "";
        $groupby = "";

    } else {
        $orderby = "ORDER BY $quorasort";
        $groupby = ", ".strtolower($quorasort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $quorasort == "") {
        $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {quora_posts} p
                       JOIN {quora_discussions} d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.quora = ?
              GROUP BY p.discussion";
        return $DB->get_records_sql($sql, array($quoraid));

    } else {
        $sql = "SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {quora_posts} p
                       JOIN {quora_discussions} d ON p.discussion = d.id
                 WHERE d.quora = ?
              GROUP BY p.discussion $groupby $orderby";
        return $DB->get_records_sql($sql, array($quoraid), $limitfrom, $limitnum);
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @staticvar array $cache
 * @param object $quora
 * @param object $cm
 * @param object $course
 * @return mixed
 */
function quora_count_discussions($quora, $cm, $course) {
    global $CFG, $DB, $USER;

    static $cache = array();

    $now = round(time(), -2); // db cache friendliness

    $params = array($course->id);

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->quora_enabletimedposts)) {
            $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
            $params[] = $now;
            $params[] = $now;
        } else {
            $timedsql = "";
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {quora} f
                       JOIN {quora_discussions} d ON d.quora = f.id
                 WHERE f.course = ?
                       $timedsql
              GROUP BY f.id";

        if ($counts = $DB->get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$quora->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$quora->id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $cache[$course->id][$quora->id];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list($mygroups_sql, $params) = $DB->get_in_or_equal($mygroups);
    $params[] = $quora->id;

    if (!empty($CFG->quora_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(d.id)
              FROM {quora_discussions} d
             WHERE d.groupid $mygroups_sql AND d.quora = ?
                   $timedsql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Get all discussions in a quora
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @param string $quorasort
 * @param bool $fullpost
 * @param int $unused
 * @param int $limit
 * @param bool $userlastmodified
 * @param int $page
 * @param int $perpage
 * @return array
 */
function quora_get_discussions($cm, $quorasort="d.timemodified DESC", $fullpost=true, $unused=-1, $limit=-1, $userlastmodified=false, $page=-1, $perpage=0) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    $now = round(time(), -2);
    $params = array($cm->instance);

    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/quora:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    if (!empty($CFG->quora_enabletimedposts)) { /// Users must fulfill timed posts

        if (!has_capability('mod/quora:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm->id);
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }


    if (empty($quorasort)) {
        $quorasort = "d.timemodified DESC";
    }
    if (empty($fullpost)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {  // We don't need to know this
        $umfields = "";
        $umtable  = "";
    } else {
        $umfields = ', ' . get_all_user_name_fields(true, 'um', null, 'um');
        $umtable  = " LEFT JOIN {user} um ON (d.usermodified = um.id)";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $sql = "SELECT $postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, $allnames,
                   u.email, u.picture, u.imagealt $umfields
              FROM {quora_discussions} d
                   JOIN {quora_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $umtable
             WHERE d.quora = ? AND p.parent = 0
                   $timelimit $groupselect
          ORDER BY $quorasort";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Gets the neighbours (previous and next) of a discussion.
 *
 * The calculation is based on the timemodified of the discussion and does not handle
 * the neighbours having an identical timemodified. The reason is that we do not have any
 * other mean to sort the records, e.g. we cannot use IDs as a greater ID can have a lower
 * timemodified.
 *
 * Please note that this does not check whether or not the discussion passed is accessible
 * by the user, it simply uses it as a reference to find the neighbours. On the other hand,
 * the returned neighbours are checked and are accessible to the current user.
 *
 * @param object $cm The CM record.
 * @param object $discussion The discussion record.
 * @return array That always contains the keys 'prev' and 'next'. When there is a result
 *               they contain the record with minimal information such as 'id' and 'name'.
 *               When the neighbour is not found the value is false.
 */
function quora_get_discussion_neighbours($cm, $discussion) {
    global $CFG, $DB, $USER;

    if ($cm->instance != $discussion->quora) {
        throw new coding_exception('Discussion is not part of the same quora.');
    }

    $neighbours = array('prev' => false, 'next' => false);
    $now = round(time(), -2);
    $params = array();

    $modcontext = context_module::instance($cm->id);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    // Users must fulfill timed posts.
    $timelimit = '';
    if (!empty($CFG->quora_enabletimedposts)) {
        if (!has_capability('mod/quora:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= :tltimestart AND (d.timeend = 0 OR d.timeend > :tltimeend))';
            $params['tltimestart'] = $now;
            $params['tltimeend'] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = :tluserid';
                $params['tluserid'] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    // Limiting to posts accessible according to groups.
    $groupselect = '';
    if ($groupmode) {
        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            }
        } else {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    }

    $params['quoraid'] = $cm->instance;
    $params['discid'] = $discussion->id;
    $params['disctimemodified'] = $discussion->timemodified;

    $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
              FROM {quora_discussions} d
             WHERE d.quora = :quoraid
               AND d.id <> :discid
                   $timelimit
                   $groupselect";

    $prevsql = $sql . " AND d.timemodified < :disctimemodified
                   ORDER BY d.timemodified DESC";

    $nextsql = $sql . " AND d.timemodified > :disctimemodified
                   ORDER BY d.timemodified ASC";

    $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
    $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);

    return $neighbours;
}

/**
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function quora_get_discussions_unread($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG->quora_oldpostdays*24*60*60);

    $params = array();
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //separate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    if (!empty($CFG->quora_enabletimedposts)) {
        $timedsql = "AND d.timestart < :now1 AND (d.timeend = 0 OR d.timeend > :now2)";
        $params['now1'] = $now;
        $params['now2'] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {quora_discussions} d
                   JOIN {quora_posts} p     ON p.discussion = d.id
                   LEFT JOIN {quora_read} r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.quora = {$cm->instance}
                   AND p.modified >= :cutoffdate AND r.id is NULL
                   $groupselect
                   $timedsql
          GROUP BY d.id";
    $params['cutoffdate'] = $cutoffdate;

    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function quora_get_discussions_count($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm->instance);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $cutoffdate = $now - ($CFG->quora_oldpostdays*24*60*60);

    $timelimit = "";

    if (!empty($CFG->quora_enabletimedposts)) {

        $modcontext = context_module::instance($cm->id);

        if (!has_capability('mod/quora:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {quora_discussions} d
                   JOIN {quora_posts} p ON p.discussion = d.id
             WHERE d.quora = ? AND p.parent = 0
                   $groupselect $timelimit";

    return $DB->get_field_sql($sql, $params);
}


// OTHER FUNCTIONS ///////////////////////////////////////////////////////////


/**
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type
 */
function quora_get_course_quora($courseid, $type) {
// How to set up special 1-per-course quoras
    global $CFG, $DB, $OUTPUT, $USER;

    if ($quoras = $DB->get_records_select("quora", "course = ? AND type = ?", array($courseid, $type), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($quoras as $quora) {
            return $quora;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $quora = new stdClass();
    $quora->course = $courseid;
    $quora->type = "$type";
    if (!empty($USER->htmleditor)) {
        $quora->introformat = $USER->htmleditor;
    }
    switch ($quora->type) {
        case "news":
            $quora->name  = get_string("namenews", "quora");
            $quora->intro = get_string("intronews", "quora");
            $quora->forcesubscribe = FORUM_FORCESUBSCRIBE;
            $quora->assessed = 0;
            if ($courseid == SITEID) {
                $quora->name  = get_string("sitenews");
                $quora->forcesubscribe = 0;
            }
            break;
        case "social":
            $quora->name  = get_string("namesocial", "quora");
            $quora->intro = get_string("introsocial", "quora");
            $quora->assessed = 0;
            $quora->forcesubscribe = 0;
            break;
        case "blog":
            $quora->name = get_string('blogquora', 'quora');
            $quora->intro = get_string('introblog', 'quora');
            $quora->assessed = 0;
            $quora->forcesubscribe = 0;
            break;
        default:
            echo $OUTPUT->notification("That quora type doesn't exist!");
            return false;
            break;
    }

    $quora->timemodified = time();
    $quora->id = $DB->insert_record("quora", $quora);

    if (! $module = $DB->get_record("modules", array("name" => "quora"))) {
        echo $OUTPUT->notification("Could not find quora module!!");
        return false;
    }
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $quora->id;
    $mod->section = 0;
    include_once("$CFG->dirroot/course/lib.php");
    if (! $mod->coursemodule = add_course_module($mod) ) {
        echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);
    return $DB->get_record("quora", array("id" => "$quora->id"));
}


/**
 * Given the data about a posting, builds up the HTML to display it and
 * returns the HTML in a string.  This is designed for sending via HTML email.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $quora
 * @param object $discussion
 * @param object $post
 * @param object $userform
 * @param object $userto
 * @param bool $ownpost
 * @param bool $reply
 * @param bool $link
 * @param bool $rate
 * @param string $footer
 * @return string
 */
function quora_make_mail_post($course, $cm, $quora, $discussion, $post, $userfrom, $userto,
                              $ownpost=false, $reply=false, $link=false, $rate=false, $footer="") {

    global $CFG, $OUTPUT;

    $modcontext = context_module::instance($cm->id);

    if (!isset($userto->viewfullnames[$quora->id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$quora->id];
    }

    // add absolute file links
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_quora', 'post', $post->id);

    // format the post body
    $options = new stdClass();
    $options->para = true;
    $formattedtext = format_text($post->message, $post->messageformat, $options, $course->id);

    $output = '<table border="0" cellpadding="3" cellspacing="0" class="quorapost">';

    $output .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $output .= $OUTPUT->user_picture($userfrom, array('courseid'=>$course->id));
    $output .= '</td>';

    if ($post->parent) {
        $output .= '<td class="topic">';
    } else {
        $output .= '<td class="topic starter">';
    }
    $output .= '<div class="subject">'.format_string($post->subject).'</div>';

    $fullname = fullname($userfrom, $viewfullnames);
    $by = new stdClass();
    $by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userfrom->id.'&amp;course='.$course->id.'">'.$fullname.'</a>';
    $by->date = userdate($post->modified, '', core_date::get_user_timezone($userto));
    $output .= '<div class="author">'.get_string('bynameondate', 'quora', $by).'</div>';

    $output .= '</td></tr>';

    $output .= '<tr><td class="left side" valign="top">';

    if (isset($userfrom->groups)) {
        $groups = $userfrom->groups[$quora->id];
    } else {
        $groups = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
    }

    if ($groups) {
        $output .= print_group_picture($groups, $course->id, false, true, true);
    } else {
        $output .= '&nbsp;';
    }

    $output .= '</td><td class="content">';

    $attachments = quora_print_attachments($post, $cm, 'html');
    if ($attachments !== '') {
        $output .= '<div class="attachments">';
        $output .= $attachments;
        $output .= '</div>';
    }

    $output .= $formattedtext;

// Commands
    $commands = array();

    if ($post->parent) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.
                      $post->discussion.'&amp;parent='.$post->parent.'">'.get_string('parent', 'quora').'</a>';
    }

    if ($reply) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/quora/post.php?reply='.$post->id.'">'.
                      get_string('reply', 'quora').'</a>';
    }

    $output .= '<div class="commands">';
    $output .= implode(' | ', $commands);
    $output .= '</div>';

// Context link to post if required
    if ($link) {
        $output .= '<div class="link">';
        $output .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'#p'.$post->id.'">'.
                     get_string('postincontext', 'quora').'</a>';
        $output .= '</div>';
    }

    if ($footer) {
        $output .= '<div class="footer">'.$footer.'</div>';
    }
    $output .= '</td></tr></table>'."\n\n";

    return $output;
}

/**
 * Print a quora post
 *
 * @global object
 * @global object
 * @uses FORUM_MODE_THREADED
 * @uses PORTFOLIO_FORMAT_PLAINHTML
 * @uses PORTFOLIO_FORMAT_FILE
 * @uses PORTFOLIO_FORMAT_RICHHTML
 * @uses PORTFOLIO_ADD_TEXT_LINK
 * @uses CONTEXT_MODULE
 * @param object $post The post to print.
 * @param object $discussion
 * @param object $quora
 * @param object $cm
 * @param object $course
 * @param boolean $ownpost Whether this post belongs to the current user.
 * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
 * @param boolean $link Just print a shortened version of the post as a link to the full post.
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $post_read true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 * @param boolean $dummyifcantsee When quora_user_can_see_post says that
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all.
 * @param bool|null $istracked
 * @return void
 */
function quora_print_post($post, $discussion, $quora, &$cm, $course, $is_assessed, $ownpost=false, $reply=false, $link=false,
                          $footer="", $highlight="", $postisread=null, $dummyifcantsee=true, $istracked=null, $return=false) {
    global $USER, $CFG, $OUTPUT, $DB;

    require_once($CFG->libdir . '/filelib.php');

    // String cache
    static $str;

    $modcontext = context_module::instance($cm->id);

    $post->course = $course->id;
    $post->quora  = $quora->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_quora', 'post', $post->id);
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        $post->message .= plagiarism_get_links(array('userid' => $post->userid,
            'content' => $post->message,
            'cmid' => $cm->id,
            'course' => $post->course,
            'quora' => $post->quora));
    }

    // caching
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }

    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/quora:viewdiscussion']   = has_capability('mod/quora:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames']  = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/quora:editanypost']      = has_capability('mod/quora:editanypost', $modcontext);
        $cm->cache->caps['mod/quora:splitdiscussions'] = has_capability('mod/quora:splitdiscussions', $modcontext);
        $cm->cache->caps['mod/quora:deleteownpost']    = has_capability('mod/quora:deleteownpost', $modcontext);
        $cm->cache->caps['mod/quora:deleteanypost']    = has_capability('mod/quora:deleteanypost', $modcontext);
        $cm->cache->caps['mod/quora:viewanyrating']    = has_capability('mod/quora:viewanyrating', $modcontext);
        $cm->cache->caps['mod/quora:exportpost']       = has_capability('mod/quora:exportpost', $modcontext);
        $cm->cache->caps['mod/quora:exportownpost']    = has_capability('mod/quora:exportownpost', $modcontext);
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = \core_availability\info_module::is_user_visible($cm, 0, false);
    }

    if ($istracked && is_null($postisread)) {
        $postisread = quora_tp_is_post_read($USER->id, $post);
    }

    if (!quora_user_can_see_post($quora, $discussion, $post, NULL, $cm)) {
        $output = '';
        if (!$dummyifcantsee) {
            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }
        $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
        $output .= html_writer::start_tag('div', array('class'=>'forumpost clearfix',
                                                       'role' => 'region',
                                                       'aria-label' => get_string('hiddenquorapost', 'quora')));
        $output .= html_writer::start_tag('div', array('class'=>'row header'));
        $output .= html_writer::tag('div', '', array('class'=>'left picture')); // Picture
        if ($post->parent) {
            $output .= html_writer::start_tag('div', array('class'=>'topic'));
        } else {
            $output .= html_writer::start_tag('div', array('class'=>'topic starter'));
        }
        $output .= html_writer::tag('div', get_string('quorasubjecthidden','quora'), array('class' => 'subject',
                                                                                           'role' => 'header')); // Subject.
        $output .= html_writer::tag('div', get_string('quoraauthorhidden', 'quora'), array('class' => 'author',
                                                                                           'role' => 'header')); // Author.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::start_tag('div', array('class'=>'row'));
        $output .= html_writer::tag('div', '&nbsp;', array('class'=>'left side')); // Groups
        $output .= html_writer::tag('div', get_string('quorabodyhidden','quora'), array('class'=>'content')); // Content
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::end_tag('div'); // quorapost

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    if (empty($str)) {
        $str = new stdClass;
        $str->edit         = get_string('edit', 'quora');
        $str->delete       = get_string('delete', 'quora');
        $str->reply        = get_string('reply', 'quora');
        $str->parent       = get_string('parent', 'quora');
        $str->pruneheading = get_string('pruneheading', 'quora');
        $str->prune        = get_string('prune', 'quora');
        $str->displaymode     = get_user_preferences('quora_displaymode', $CFG->quora_displaymode);
        $str->markread     = get_string('markread', 'quora');
        $str->markunread   = get_string('markunread', 'quora');
    }

    $discussionlink = new moodle_url('/mod/quora/discuss.php', array('d'=>$post->discussion));

    // Build an object that represents the posting user
    $postuser = new stdClass;
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    $postuser->fullname    = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
    $postuser->profilelink = new moodle_url('/user/view.php', array('id'=>$post->userid, 'course'=>$course->id));

    // Prepare the groups the posting user belongs to
    if (isset($cm->cache->usersgroups)) {
        $groups = array();
        if (isset($cm->cache->usersgroups[$post->userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm->cache->groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
    }

    // Prepare the attachements for the post, files then images
    list($attachments, $attachedimages) = quora_print_attachments($post, $cm, 'separateimages');

    // Determine if we need to shorten this post
    $shortenpost = ($link && (strlen(strip_tags($post->message)) > $CFG->quora_longpost));


    // Prepare an array of commands
    $commands = array();

    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($istracked && $CFG->quora_usermarksread && isloggedin()) {
        $url = new moodle_url($discussionlink, array('postid'=>$post->id, 'mark'=>'unread'));
        $text = $str->markunread;
        if (!$postisread) {
            $url->param('mark', 'read');
            $text = $str->markread;
        }
        if ($str->displaymode == FORUM_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->id);
        }
        $commands[] = array('url'=>$url, 'text'=>$text);
    }

    // Zoom in to the parent specifically
    if ($post->parent) {
        $url = new moodle_url($discussionlink);
        if ($str->displaymode == FORUM_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->parent);
        }
        $commands[] = array('url'=>$url, 'text'=>$str->parent);
    }

    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post->created;
    if (!$post->parent && $quora->type == 'news' && $discussion->timestart > time()) {
        $age = 0;
    }

    if ($quora->type == 'single' and $discussion->firstpost == $post->id) {
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // The first post in single simple is the quora description.
            $commands[] = array('url'=>new moodle_url('/course/modedit.php', array('update'=>$cm->id, 'sesskey'=>sesskey(), 'return'=>1)), 'text'=>$str->edit);
        }
    } else if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/quora:editanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/quora/post.php', array('edit'=>$post->id)), 'text'=>$str->edit);
    }

    if ($cm->cache->caps['mod/quora:splitdiscussions'] && $post->parent && $quora->type != 'single') {
        $commands[] = array('url'=>new moodle_url('/mod/quora/post.php', array('prune'=>$post->id)), 'text'=>$str->prune, 'title'=>$str->pruneheading);
    }

    if ($quora->type == 'single' and $discussion->firstpost == $post->id) {
        // Do not allow deleting of first post in single simple type.
    } else if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/quora:deleteownpost']) || $cm->cache->caps['mod/quora:deleteanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/quora/post.php', array('delete'=>$post->id)), 'text'=>$str->delete);
    }

    if ($reply) {
        $commands[] = array('url'=>new moodle_url('/mod/quora/post.php#mformquora', array('reply'=>$post->id)), 'text'=>$str->reply);
    }

    if ($CFG->enableportfolios && ($cm->cache->caps['mod/quora:exportpost'] || ($ownpost && $cm->cache->caps['mod/quora:exportownpost']))) {
        $p = array('postid' => $post->id);
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('quora_portfolio_caller', array('postid' => $post->id), 'mod_quora');
        if (empty($attachments)) {
            $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
        } else {
            $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
        }

        $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
        if (!empty($porfoliohtml)) {
            $commands[] = $porfoliohtml;
        }
    }
    // Finished building commands


    // Begin output

    $output  = '';

    if ($istracked) {
        if ($postisread) {
            $quorapostclass = ' read';
        } else {
            $quorapostclass = ' unread';
            $output .= html_writer::tag('a', '', array('name'=>'unread'));
        }
    } else {
        // ignore trackign status if not tracked or tracked param missing
        $quorapostclass = '';
    }

    $topicclass = '';
    if (empty($post->parent)) {
        $topicclass = ' firstpost starter';
    }

    if (!empty($post->lastpost)) {
        $quorapostclass .= ' lastpost';
    }

    $postbyuser = new stdClass;
    $postbyuser->post = $post->subject;
    $postbyuser->user = $postuser->fullname;
    $discussionbyuser = get_string('postbyuser', 'quora', $postbyuser);
    $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
    $output .= html_writer::start_tag('div', array('class'=>'forumpost clearfix'.$quorapostclass.$topicclass,
                                                   'role' => 'region',
                                                   'aria-label' => $discussionbyuser));
    $output .= html_writer::start_tag('div', array('class'=>'row header clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left picture'));
    $output .= $OUTPUT->user_picture($postuser, array('courseid'=>$course->id));
    $output .= html_writer::end_tag('div');


    $output .= html_writer::start_tag('div', array('class'=>'topic'.$topicclass));
    //NightCool
    if ($post->parent) {
        $postsubject = null;
    }
    else {
        $postsubject = $post->subject;
    }
    if (empty($post->subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    $output .= html_writer::tag('div', $postsubject, array('class'=>'subject',
                                                           'role' => 'heading',
                                                           'aria-level' => '2'));

    $by = new stdClass();
    $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
    $by->date = userdate($post->modified);
    $output .= html_writer::tag('div', get_string('bynameondate', 'quora', $by), array('class'=>'author',
                                                                                       'role' => 'heading',
                                                                                       'aria-level' => '2'));

    $output .= html_writer::end_tag('div'); //topic
    $output .= html_writer::end_tag('div'); //row

    $output .= html_writer::start_tag('div', array('class'=>'row maincontent clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left'));

    $groupoutput = '';
    if ($groups) {
        $groupoutput = print_group_picture($groups, $course->id, false, true, true);
    }
    if (empty($groupoutput)) {
        $groupoutput = '&nbsp;';
    }
    $output .= html_writer::tag('div', $groupoutput, array('class'=>'grouppictures'));

    $output .= html_writer::end_tag('div'); //left side
    $output .= html_writer::start_tag('div', array('class'=>'no-overflow'));
    $output .= html_writer::start_tag('div', array('class'=>'content'));

    $options = new stdClass;
    $options->para    = false;
    $options->trusted = $post->messagetrust;
    $options->context = $modcontext;
    if ($shortenpost) {
        // Prepare shortened version by filtering the text then shortening it.
        $postclass    = 'shortenedpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options);
        $postcontent  = shorten_text($postcontent, $CFG->quora_shortpost);
        $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'quora'));
        $postcontent .= html_writer::tag('div', '('.get_string('numwords', 'moodle', count_words($post->message)).')',
            array('class'=>'post-word-count'));
    } else {
        // Prepare whole post
        $postclass    = 'fullpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options, $course->id);
        if (!empty($highlight)) {
            $postcontent = highlight($highlight, $postcontent);
        }
        if (!empty($quora->displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post->message)),
                array('class'=>'post-word-count'));
        }
        $postcontent .= html_writer::tag('div', $attachedimages, array('class'=>'attachedimages'));
    }

    // Output the post content
    $output .= html_writer::tag('div', $postcontent, array('class'=>'posting '.$postclass));
    $output .= html_writer::end_tag('div'); // Content
    $output .= html_writer::end_tag('div'); // Content mask
    $output .= html_writer::end_tag('div'); // Row

    $output .= html_writer::start_tag('div', array('class'=>'row side'));
    $output .= html_writer::tag('div','&nbsp;', array('class'=>'left'));
    $output .= html_writer::start_tag('div', array('class'=>'options clearfix'));

    if (!empty($attachments)) {
        $output .= html_writer::tag('div', $attachments, array('class' => 'attachments'));
    }

    // Output ratings
    if (!empty($post->rating)) {
        $output .= html_writer::tag('div', $OUTPUT->render($post->rating), array('class'=>'quora-post-rating'));
    }

    // Output assess button
    if (($post->parent == $discussion->firstpost) && ($post->userid != $USER->id) && ((empty($is_assessed)) || (!in_array($post->id, $is_assessed)))) {
        $target = new moodle_url('/mod/quora/discuss.php', array('d'=>$post->discussion));
        $layoutclass = 'horizontal';
        $attributes = array('method'=>'POST', 'action'=>$target, 'class'=> $layoutclass);
        $output .= html_writer::start_tag('form', $attributes);
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cid', 'value' => $course->id));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'qid', 'value' => $post->quora));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $post->id));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('praise', 'quora'), 'class' => 'button'));
        $output .= html_writer::end_tag('form');
    }

    //Output the number of praise
    if ($post->parent == 1) {
        $output .= html_writer::start_tag('p');
        $output .= get_string('praisecount', 'quora');
        $as = $DB->get_records('quora_post_assessments', array('post' => $post->id));
        $output .= count($as);
        $output .= get_string('praisecounted', 'quora');
        $output .= html_writer::end_tag('p');
    }

    // Output the commands
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            //NightCool
            if ($command['text'] == '显示父帖子') {
                continue;
            }
            $commandhtml[] = html_writer::link($command['url'], $command['text']);
        } else {
            $commandhtml[] = $command;
        }
    }
    $output .= html_writer::tag('div', implode(' | ', $commandhtml), array('class'=>'commands'));

    // Output link to post if required
    if ($link && quora_user_can_post($quora, $discussion, $USER, $cm, $course, $modcontext)) {
        if ($post->replies == 1) {
            $replystring = get_string('repliesone', 'quora', $post->replies);
        } else {
            $replystring = get_string('repliesmany', 'quora', $post->replies);
        }

        $output .= html_writer::start_tag('div', array('class'=>'link'));
        $output .= html_writer::link($discussionlink, get_string('discussthistopic', 'quora'));
        $output .= '&nbsp;('.$replystring.')';
        $output .= html_writer::end_tag('div'); // link
    }

    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class'=>'footer'));
    }

    // Close remaining open divs
    $output .= html_writer::end_tag('div'); // content
    $output .= html_writer::end_tag('div'); // row
    $output .= html_writer::end_tag('div'); // quorapost

    // Mark the quora post as read if required
    if ($istracked && !$CFG->quora_usermarksread && !$postisread) {
        quora_tp_mark_post_read($USER->id, $post, $quora->id);
    }

    if ($return) {
        return $output;
    }
    echo $output;
    return;
}

/**
 * Return rating related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's rating permissions
 */
function quora_rating_permissions($contextid, $component, $ratingarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_quora' || $ratingarea != 'post') {
        // We don't know about this component/ratingarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array(
        'view'    => has_capability('mod/quora:viewrating', $context),
        'viewany' => has_capability('mod/quora:viewanyrating', $context),
        'viewall' => has_capability('mod/quora:viewallratings', $context),
        'rate'    => has_capability('mod/quora:rate', $context)
    );
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            component => The component for this module - should always be mod_quora [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating [required]
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function quora_rating_validate($params) {
    global $DB, $USER;

    // Check the component is mod_quora
    if ($params['component'] != 'mod_quora') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in quora)
    if ($params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own posts
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    // Fetch all the related records ... we need to do this anyway to call quora_user_can_see_post
    $post = $DB->get_record('quora_posts', array('id' => $params['itemid'], 'userid' => $params['rateduserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('quora_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $quora = $DB->get_record('quora', array('id' => $discussion->quora), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $quora->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('quora', $quora->id, $course->id , false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the quora
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    if ($quora->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($quora->assesstimestart) && !empty($quora->assesstimefinish)) {
        if ($post->created < $quora->assesstimestart || $post->created > $quora->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0  && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($quora->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$quora->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $quora->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're rating
    if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new rating_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new rating_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!quora_user_can_see_post($quora, $discussion, $post, $USER, $cm)) {
        throw new rating_exception('nopermissiontorate');
    }

    return true;
}


/**
 * This function prints the overview of a discussion in the quora listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: quora_print_latest_discussions()
 *
 * @global object
 * @global object
 * @param object $post The post object (passed by reference for speed).
 * @param object $quora The quora object.
 * @param int $group Current group.
 * @param string $datestring Format to use for the dates.
 * @param boolean $cantrack Is tracking enabled for this quora.
 * @param boolean $quoratracked Is the user tracking this quora.
 * @param boolean $canviewparticipants True if user has the viewparticipants permission for this course
 */
function quora_print_discussion_header(&$post, $quora, $group=-1, $datestring="",
                                        $cantrack=true, $quoratracked=true, $canviewparticipants=true, $modcontext=NULL) {

    global $COURSE, $USER, $CFG, $OUTPUT;

    static $rowcount;
    static $strmarkalldread;

    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course)) {
            print_error('invalidcoursemodule');
        }
        $modcontext = context_module::instance($cm->id);
    }

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'quora');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    $post->subject = format_string($post->subject,true);

    echo "\n\n";
    echo '<tr class="discussion r'.$rowcount.'">';

    // Topic
    echo '<td class="topic starter">';
    echo '<a href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'">'.$post->subject.'</a>';
    echo "</td>\n";

    // Picture
    $postuser = new stdClass();
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    echo '<td class="picture">';
    echo $OUTPUT->user_picture($postuser, array('courseid'=>$quora->course));
    echo "</td>\n";

    // User name
    $fullname = fullname($postuser, has_capability('moodle/site:viewfullnames', $modcontext));
    echo '<td class="author">';
    echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->userid.'&amp;course='.$quora->course.'">'.$fullname.'</a>';
    echo "</td>\n";

    // Group picture
    if ($group !== -1) {  // Groups are active - group is a group data object or NULL
        echo '<td class="picture group">';
        if (!empty($group->picture) and empty($group->hidepicture)) {
            if ($canviewparticipants && $COURSE->groupmode) {
                $picturelink = true;
            } else {
                $picturelink = false;
            }
            print_group_picture($group, $quora->course, false, false, $picturelink);
        } else if (isset($group->id)) {
            if ($canviewparticipants && $COURSE->groupmode) {
                echo '<a href="'.$CFG->wwwroot.'/user/index.php?id='.$quora->course.'&amp;group='.$group->id.'">'.$group->name.'</a>';
            } else {
                echo $group->name;
            }
        }
        echo "</td>\n";
    }

    if (has_capability('mod/quora:viewdiscussion', $modcontext)) {   // Show the column with replies
        echo '<td class="replies">';
        echo '<a href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'">';
        echo $post->replies.'</a>';
        echo "</td>\n";

        if ($cantrack) {
            echo '<td class="replies">';
            if ($quoratracked) {
                if ($post->unread > 0) {
                    echo '<span class="unread">';
                    echo '<a href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.'#unread">';
                    echo $post->unread;
                    echo '</a>';
                    echo '<a title="'.$strmarkalldread.'" href="'.$CFG->wwwroot.'/mod/quora/markposts.php?f='.
                         $quora->id.'&amp;d='.$post->discussion.'&amp;mark=read&amp;returnpage=view.php">' .
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.$strmarkalldread.'" /></a>';
                    echo '</span>';
                } else {
                    echo '<span class="read">';
                    echo $post->unread;
                    echo '</span>';
                }
            } else {
                echo '<span class="read">';
                echo '-';
                echo '</span>';
            }
            echo "</td>\n";
        }
    }

    echo '<td class="lastpost">';
    $usedate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;  // Just in case
    $parenturl = '';
    $usermodified = new stdClass();
    $usermodified->id = $post->usermodified;
    $usermodified = username_load_fields_from_object($usermodified, $post, 'um');

    // In QA quoras we check that the user can view participants.
    if ($quora->type !== 'qanda' || $canviewparticipants) {
        echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->usermodified.'&amp;course='.$quora->course.'">'.
             fullname($usermodified).'</a><br />';
        $parenturl = (empty($post->lastpostid)) ? '' : '&amp;parent='.$post->lastpostid;
    }

    echo '<a href="'.$CFG->wwwroot.'/mod/quora/discuss.php?d='.$post->discussion.$parenturl.'">'.
          userdate($usedate, $datestring).'</a>';
    echo "</td>\n";

    // is_guest should be used here as this also checks whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/quora:viewdiscussion', $modcontext)) {
        // Discussion subscription.
        if (\mod_quora\subscriptions::is_subscribable($quora)) {
            echo '<td class="discussionsubscription">';
            echo quora_get_discussion_subscription_icon($quora, $post->discussion);
            echo '</td>';
        }
    }

    echo "</tr>\n\n";

}

/**
 * Return the markup for the discussion subscription toggling icon.
 *
 * @param stdClass $quora The quora object.
 * @param int $discussionid The discussion to create an icon for.
 * @return string The generated markup.
 */
function quora_get_discussion_subscription_icon($quora, $discussionid, $returnurl = null, $includetext = false) {
    global $USER, $OUTPUT, $PAGE;

    if ($returnurl === null && $PAGE->url) {
        $returnurl = $PAGE->url->out();
    }

    $o = '';
    $subscriptionstatus = \mod_quora\subscriptions::is_subscribed($USER->id, $quora, $discussionid);
    $subscriptionlink = new moodle_url('/mod/quora/subscribe.php', array(
        'sesskey' => sesskey(),
        'id' => $quora->id,
        'd' => $discussionid,
        'returnurl' => $returnurl,
    ));

    if ($includetext) {
        $o .= $subscriptionstatus ? get_string('subscribed', 'mod_quora') : get_string('notsubscribed', 'mod_quora');
    }

    if ($subscriptionstatus) {
        $output = $OUTPUT->pix_icon('t/subscribed', get_string('clicktounsubscribe', 'quora'), 'mod_quora');
        if ($includetext) {
            $output .= get_string('subscribed', 'mod_quora');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktounsubscribe', 'quora'),
                'class' => 'discussiontoggle iconsmall',
                'data-quoraid' => $quora->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));

    } else {
        $output = $OUTPUT->pix_icon('t/unsubscribed', get_string('clicktosubscribe', 'quora'), 'mod_quora');
        if ($includetext) {
            $output .= get_string('notsubscribed', 'mod_quora');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktosubscribe', 'quora'),
                'class' => 'discussiontoggle iconsmall',
                'data-quoraid' => $quora->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));
    }
}

/**
 * Return a pair of spans containing classes to allow the subscribe and
 * unsubscribe icons to be pre-loaded by a browser.
 *
 * @return string The generated markup
 */
function quora_get_discussion_subscription_icon_preloaders() {
    $o = '';
    $o .= html_writer::span('&nbsp;', 'preload-subscribe');
    $o .= html_writer::span('&nbsp;', 'preload-unsubscribe');
    return $o;
}

/**
 * Print the drop down that allows the user to select how they want to have
 * the discussion displayed.
 *
 * @param int $id quora id if $quoratype is 'single',
 *              discussion id for any other quora type
 * @param mixed $mode quora layout mode
 * @param string $quoratype optional
 */
function quora_print_mode_form($id, $mode, $quoratype='') {
    global $OUTPUT;
    if ($quoratype == 'single') {
        $select = new single_select(new moodle_url("/mod/quora/view.php", array('f'=>$id)), 'mode', quora_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'quora'), array('class' => 'accesshide'));
        $select->class = "quoramode";
    } else {
        $select = new single_select(new moodle_url("/mod/quora/discuss.php", array('d'=>$id)), 'mode', quora_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'quora'), array('class' => 'accesshide'));
    }
    echo $OUTPUT->render($select);
}

/**
 * @global object
 * @param object $course
 * @param string $search
 * @return string
 */
function quora_search_form($course, $search='') {
    global $CFG, $OUTPUT;

    $output  = '<div class="quorasearch">';
    $output .= '<form action="'.$CFG->wwwroot.'/mod/quora/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= $OUTPUT->help_icon('search');
    $output .= '<label class="accesshide" for="search" >'.get_string('search', 'quora').'</label>';
    $output .= '<input id="search" name="search" type="text" size="18" value="'.s($search, true).'" />';
    $output .= '<label class="accesshide" for="searchquoras" >'.get_string('searchquoras', 'quora').'</label>';
    $output .= '<input id="searchquoras" value="'.get_string('searchquoras', 'quora').'" type="submit" />';
    $output .= '<input name="id" type="hidden" value="'.$course->id.'" />';
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}


/**
 * @global object
 * @global object
 */
function quora_set_return() {
    global $CFG, $SESSION;

    if (! isset($SESSION->fromdiscussion)) {
        $referer = get_local_referer(false);
        // If the referer is NOT a login screen then save it.
        if (! strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $referer;
        }
    }
}


/**
 * @global object
 * @param string $default
 * @return string
 */
function quora_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Given a discussion object that is being moved to $quorato,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new quora directory.
 *
 * @global object
 * @param object $discussion
 * @param int $quorafrom source quora id
 * @param int $quorato target quora id
 * @return bool success
 */
function quora_move_attachments($discussion, $quorafrom, $quorato) {
    global $DB;

    $fs = get_file_storage();

    $newcm = get_coursemodule_from_instance('quora', $quorato);
    $oldcm = get_coursemodule_from_instance('quora', $quorafrom);

    $newcontext = context_module::instance($newcm->id);
    $oldcontext = context_module::instance($oldcm->id);

    // loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB->get_records('quora_posts', array('discussion'=>$discussion->id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_quora', 'post', $post->id);
            $attachmentsmoved = $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_quora', 'attachment', $post->id);
            if ($attachmentsmoved > 0 && $post->attachment != '1') {
                // Weird - let's fix it
                $post->attachment = '1';
                $DB->update_record('quora_posts', $post);
            } else if ($attachmentsmoved == 0 && $post->attachment != '') {
                // Weird - let's fix it
                $post->attachment = '';
                $DB->update_record('quora_posts', $post);
            }
        }
    }

    return true;
}

/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 */
function quora_print_attachments($post, $cm, $type) {
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($post->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'quora');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG->enableportfolios) && (has_capability('mod/quora:exportpost', $context) || ($post->userid == $USER->id && has_capability('mod/quora:exportownpost', $context)));

    if ($canexport) {
        require_once($CFG->libdir.'/portfoliolib.php');
    }

    // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
    // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
    $files = $fs->get_area_files($context->id, 'mod_quora', 'attachment', $post->id, "filename", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_quora/attachment/'.$post->id.'/'.$filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">".s($filename)."</a>";
                if ($canexport) {
                    $button->set_callback_options('quora_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_quora');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment ".s($filename).":\n$path\n";

            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button->set_callback_options('quora_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_quora');
                        $button->set_format_by_file($file);
                        $imagereturn .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context'=>$context));
                    if ($canexport) {
                        $button->set_callback_options('quora_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_quora');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br />';
                }
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post->userid,
                    'file' => $file,
                    'cmid' => $cm->id,
                    'course' => $cm->course,
                    'quora' => $cm->instance));
                $output .= '<br />';
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;

    } else {
        return array($output, $imagereturn);
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_quora
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function quora_get_file_areas($course, $cm, $context) {
    return array(
        'attachment' => get_string('areaattachment', 'mod_quora'),
        'post' => get_string('areapost', 'mod_quora'),
    );
}

/**
 * File browsing support for quora module.
 *
 * @package  mod_quora
 * @category files
 * @param stdClass $browser file browser object
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context module
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function quora_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return null;
    }

    // Note that quora_user_can_see_post() additionally allows access for parent roles
    // and it explicitly checks qanda quora type, too. One day, when we stop requiring
    // course:managefiles, we will need to extend this.
    if (!has_capability('mod/quora:viewdiscussion', $context)) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot.'/mod/quora/locallib.php');
        return new quora_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and quora. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post']->id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB->get_record('quora_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion']->id == $post->discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB->get_record('quora_discussions', array('id' => $post->discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['quora']) && $cached['quora']->id == $cm->instance) {
        $quora = $cached['quora'];
    } else if ($quora = $DB->get_record('quora', array('id' => $cm->instance))) {
        $cached['quora'] = $quora;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_quora', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion->groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!quora_user_can_see_post($quora, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the quora attachments. Implements needed access control ;-)
 *
 * @package  mod_quora
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function quora_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = quora_get_file_areas($course, $cm, $context);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int)array_shift($args);

    if (!$post = $DB->get_record('quora_posts', array('id'=>$postid))) {
        return false;
    }

    if (!$discussion = $DB->get_record('quora_discussions', array('id'=>$post->discussion))) {
        return false;
    }

    if (!$quora = $DB->get_record('quora', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_quora/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!quora_user_can_see_post($quora, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}

/**
 * If successful, this function returns the name of the file
 *
 * @global object
 * @param object $post is a full post record, including course and quora
 * @param object $quora
 * @param object $cm
 * @param mixed $mform
 * @param string $unused
 * @return bool
 */
function quora_add_attachment($post, $quora, $cm, $mform=null, $unused=null) {
    global $DB;

    if (empty($mform)) {
        return false;
    }

    if (empty($post->attachments)) {
        return true;   // Nothing to do
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount']>0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_quora', 'attachment', $post->id,
            mod_quora_post_form::attachment_options($quora));

    $DB->set_field('quora_posts', 'attachment', $present, array('id'=>$post->id));

    return true;
}

/**
 * Add a new post in an existing discussion.
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $unused formerly $message, renamed in 2.8 as it was unused.
 * @return int
 */
function quora_add_new_post($post, $mform, $unused = null) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('quora_discussions', array('id' => $post->discussion));
    $quora      = $DB->get_record('quora', array('id' => $discussion->quora));
    $cm         = get_coursemodule_from_instance('quora', $quora->id);
    $context    = context_module::instance($cm->id);

    $post->created    = $post->modified = time();
    $post->mailed     = FORUM_MAILED_PENDING;
    $post->userid     = $USER->id;
    $post->attachment = "";
    if (!isset($post->totalscore)) {
        $post->totalscore = 0;
    }
    if (!isset($post->mailnow)) {
        $post->mailnow    = 0;
    }

    $post->id = $DB->insert_record("quora_posts", $post);
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_quora', 'post', $post->id,
            mod_quora_post_form::editor_options($context, null), $post->message);
    $DB->set_field('quora_posts', 'message', $post->message, array('id'=>$post->id));
    quora_add_attachment($post, $quora, $cm, $mform);

    // Update discussion modified date
    $DB->set_field("quora_discussions", "timemodified", $post->modified, array("id" => $post->discussion));
    $DB->set_field("quora_discussions", "usermodified", $post->userid, array("id" => $post->discussion));

    if (quora_tp_can_track_quoras($quora) && quora_tp_is_tracked($quora)) {
        quora_tp_mark_post_read($post->userid, $post, $post->quora);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    quora_trigger_content_uploaded_event($post, $cm, 'quora_add_new_post');

    return $post->id;
}

/**
 * Update a post
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return bool
 */
function quora_update_post($post, $mform, &$message) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('quora_discussions', array('id' => $post->discussion));
    $quora      = $DB->get_record('quora', array('id' => $discussion->quora));
    $cm         = get_coursemodule_from_instance('quora', $quora->id);
    $context    = context_module::instance($cm->id);

    $post->modified = time();

    $DB->update_record('quora_posts', $post);

    $discussion->timemodified = $post->modified; // last modified tracking
    $discussion->usermodified = $post->userid;   // last modified tracking

    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $discussion->name      = $post->subject;
        $discussion->timestart = $post->timestart;
        $discussion->timeend   = $post->timeend;
    }
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_quora', 'post', $post->id,
            mod_quora_post_form::editor_options($context, $post->id), $post->message);
    $DB->set_field('quora_posts', 'message', $post->message, array('id'=>$post->id));

    $DB->update_record('quora_discussions', $discussion);

    quora_add_attachment($post, $quora, $cm, $mform, $message);

    if (quora_tp_can_track_quoras($quora) && quora_tp_is_tracked($quora)) {
        quora_tp_mark_post_read($post->userid, $post, $post->quora);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    quora_trigger_content_uploaded_event($post, $cm, 'quora_update_post');

    return true;
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused
 * @param int $userid
 * @return object
 */
function quora_add_discussion($discussion, $mform=null, $unused=null, $userid=null) {
    global $USER, $CFG, $DB;

    $timenow = time();

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $quora = $DB->get_record('quora', array('id'=>$discussion->quora));
    $cm    = get_coursemodule_from_instance('quora', $quora->id);

    $post = new stdClass();
    $post->discussion    = 0;
    $post->parent        = 0;
    $post->userid        = $userid;
    $post->created       = $timenow;
    $post->modified      = $timenow;
    $post->mailed        = FORUM_MAILED_PENDING;
    $post->subject       = $discussion->name;
    $post->message       = $discussion->message;
    $post->messageformat = $discussion->messageformat;
    $post->messagetrust  = $discussion->messagetrust;
    $post->attachments   = isset($discussion->attachments) ? $discussion->attachments : null;
    $post->quora         = $quora->id;     // speedup
    $post->course        = $quora->course; // speedup
    $post->mailnow       = $discussion->mailnow;

    $post->id = $DB->insert_record("quora_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm->id) && !empty($discussion->itemid)) {   // In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm->id);
        $text = file_save_draft_area_files($discussion->itemid, $context->id, 'mod_quora', 'post', $post->id,
                mod_quora_post_form::editor_options($context, null), $post->message);
        $DB->set_field('quora_posts', 'message', $text, array('id'=>$post->id));
    }

    // Now do the main entry for the discussion, linking to this first post

    $discussion->firstpost    = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid       = $userid;
    $discussion->assessed     = 0;

    $post->discussion = $DB->insert_record("quora_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB->set_field("quora_posts", "discussion", $post->discussion, array("id"=>$post->id));

    if (!empty($cm->id)) {
        quora_add_attachment($post, $quora, $cm, $mform, $unused);
    }

    if (quora_tp_can_track_quoras($quora) && quora_tp_is_tracked($quora)) {
        quora_tp_mark_post_read($post->userid, $post, $post->quora);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    if (!empty($cm->id)) {
        quora_trigger_content_uploaded_event($post, $cm, 'quora_add_discussion');
    }

    return $post->discussion;
}


/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @global object
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire quora
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $quora Forum
 * @return bool
 */
function quora_delete_discussion($discussion, $fulldelete, $course, $cm, $quora) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $result = true;

    if ($posts = $DB->get_records("quora_posts", array("discussion" => $discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->quora  = $discussion->quora;
            if (!quora_delete_post($post, 'ignore', $course, $cm, $quora, $fulldelete)) {
                $result = false;
            }
        }
    }

    quora_tp_delete_read_records(-1, -1, $discussion->id);

    // Discussion subscriptions must be removed before discussions because of key constraints.
    $DB->delete_records('quora_discussion_subs', array('discussion' => $discussion->id));
    if (!$DB->delete_records("quora_discussions", array("id" => $discussion->id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
           ($quora->completiondiscussions || $quora->completionreplies || $quora->completionposts)) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $discussion->userid);
        }
    }

    return $result;
}


/**
 * Deletes a single quora post.
 *
 * @global object
 * @param object $post Forum post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $quora Forum
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire quora anyway.
 * @return bool
 */
function quora_delete_post($post, $children, $course, $cm, $quora, $skipcompletion=false) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $context = context_module::instance($cm->id);

    if ($children !== 'ignore' && ($childposts = $DB->get_records('quora_posts', array('parent'=>$post->id)))) {
       if ($children) {
           foreach ($childposts as $childpost) {
               quora_delete_post($childpost, true, $course, $cm, $quora, $skipcompletion);
           }
       } else {
           return false;
       }
    }

    // Delete ratings.
    require_once($CFG->dirroot.'/rating/lib.php');
    $delopt = new stdClass;
    $delopt->contextid = $context->id;
    $delopt->component = 'mod_quora';
    $delopt->ratingarea = 'post';
    $delopt->itemid = $post->id;
    $rm = new rating_manager();
    $rm->delete_ratings($delopt);

    // Delete attachments.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_quora', 'attachment', $post->id);
    $fs->delete_area_files($context->id, 'mod_quora', 'post', $post->id);

    // Delete cached RSS feeds.
    if (!empty($CFG->enablerssfeeds)) {
        require_once($CFG->dirroot.'/mod/quora/rsslib.php');
        quora_rss_delete_file($quora);
    }

    if ($DB->delete_records("quora_posts", array("id" => $post->id))) {

        quora_tp_delete_read_records(-1, $post->id);

    // Just in case we are deleting the last post
        quora_discussion_update_last_post($post->discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing

        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
               ($quora->completiondiscussions || $quora->completionreplies || $quora->completionposts)) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $post->userid);
            }
        }

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post Forum post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
*/
function quora_trigger_content_uploaded_event($post, $cm, $name) {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_quora', 'attachment', $post->id, "timemodified", false);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'content' => $post->message,
            'pathnamehashes' => array_keys($files),
            'discussionid' => $post->discussion,
            'triggeredfrom' => $name,
        )
    );
    $event = \mod_quora\event\assessable_uploaded::create($params);
    $event->trigger();
    return true;
}

/**
 * @global object
 * @param object $post
 * @param bool $children
 * @return int
 */
function quora_count_replies($post, $children=true) {
    global $DB;
    $count = 0;

    if ($children) {
        if ($childposts = $DB->get_records('quora_posts', array('parent' => $post->id))) {
           foreach ($childposts as $childpost) {
               $count ++;                   // For this child
               $count += quora_count_replies($childpost, true);
           }
        }
    } else {
        $count += $DB->count_records('quora_posts', array('parent' => $post->id));
    }

    return $count;
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @param object $fromform The submitted form
 * @param stdClass $quora The quora record
 * @param stdClass $discussion The quora discussion record
 * @return string
 */
function quora_post_subscription($fromform, $quora, $discussion) {
    global $USER;

    if (\mod_quora\subscriptions::is_forcesubscribed($quora)) {
        return "";
    } else if (\mod_quora\subscriptions::subscription_disabled($quora)) {
        $subscribed = \mod_quora\subscriptions::is_subscribed($USER->id, $quora);
        if ($subscribed && !has_capability('moodle/course:manageactivities', context_course::instance($quora->course), $USER->id)) {
            // This user should not be subscribed to the quora.
            \mod_quora\subscriptions::unsubscribe_user($USER->id, $quora);
        }
        return "";
    }

    $info = new stdClass();
    $info->name  = fullname($USER);
    $info->discussion = format_string($discussion->name);
    $info->quora = format_string($quora->name);

    if (isset($fromform->discussionsubscribe) && $fromform->discussionsubscribe) {
        if ($result = \mod_quora\subscriptions::subscribe_user_to_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnowsubscribed', 'quora', $info));
        }
    } else {
        if ($result = \mod_quora\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnownotsubscribed', 'quora', $info));
        }
    }

    return '';
}

/**
 * Generate and return the subscribe or unsubscribe link for a quora.
 *
 * @param object $quora the quora. Fields used are $quora->id and $quora->forcesubscribe.
 * @param object $context the context object for this quora.
 * @param array $messages text used for the link in its various states
 *      (subscribed, unsubscribed, forcesubscribed or cantsubscribe).
 *      Any strings not passed in are taken from the $defaultmessages array
 *      at the top of the function.
 * @param bool $cantaccessagroup
 * @param bool $fakelink
 * @param bool $backtoindex
 * @param array $subscribed_quoras
 * @return string
 */
function quora_get_subscribe_link($quora, $context, $messages = array(), $cantaccessagroup = false, $fakelink=true, $backtoindex=false, $subscribed_quoras=null) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'quora'),
        'unsubscribed' => get_string('subscribe', 'quora'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'quora'),
        'cantsubscribe' => get_string('disallowsubscribe','quora')
    );
    $messages = $messages + $defaultmessages;

    if (\mod_quora\subscriptions::is_forcesubscribed($quora)) {
        return $messages['forcesubscribed'];
    } else if (\mod_quora\subscriptions::subscription_disabled($quora) &&
            !has_capability('mod/quora:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (!is_enrolled($context, $USER, '', true)) {
            return '';
        }

        $subscribed = \mod_quora\subscriptions::is_subscribed($USER->id, $quora);
        if ($subscribed) {
            $linktext = $messages['subscribed'];
            $linktitle = get_string('subscribestop', 'quora');
        } else {
            $linktext = $messages['unsubscribed'];
            $linktitle = get_string('subscribestart', 'quora');
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $PAGE->requires->js('/mod/quora/quora.js');
            $PAGE->requires->js_function_call('quora_produce_subscribe_link', array($quora->id, $backtoindexlink, $linktext, $linktitle));
            $link = "<noscript>";
        }
        $options['id'] = $quora->id;
        $options['sesskey'] = sesskey();
        $url = new moodle_url('/mod/quora/subscribe.php', $options);
        $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}

/**
 * Returns true if user created new discussion already
 *
 * @global object
 * @global object
 * @param int $quoraid
 * @param int $userid
 * @return bool
 */
function quora_user_has_posted_discussion($quoraid, $userid) {
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {quora_discussions} d, {quora_posts} p
             WHERE d.quora = ? AND p.discussion = d.id AND p.parent = 0 and p.userid = ?";

    return $DB->record_exists_sql($sql, array($quoraid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $quoraid
 * @param int $userid
 * @return array
 */
function quora_discussions_user_has_posted_in($quoraid, $userid) {
    global $CFG, $DB;

    $haspostedsql = "SELECT d.id AS id,
                            d.*
                       FROM {quora_posts} p,
                            {quora_discussions} d
                      WHERE p.discussion = d.id
                        AND d.quora = ?
                        AND p.userid = ?";

    return $DB->get_records_sql($haspostedsql, array($quoraid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $quoraid
 * @param int $did
 * @param int $userid
 * @return bool
 */
function quora_user_has_posted($quoraid, $did, $userid) {
    global $DB;

    if (empty($did)) {
        // posted in any quora discussion?
        $sql = "SELECT 'x'
                  FROM {quora_posts} p
                  JOIN {quora_discussions} d ON d.id = p.discussion
                 WHERE p.userid = :userid AND d.quora = :quoraid";
        return $DB->record_exists_sql($sql, array('quoraid'=>$quoraid,'userid'=>$userid));
    } else {
        return $DB->record_exists('quora_posts', array('discussion'=>$did,'userid'=>$userid));
    }
}

/**
 * Returns creation time of the first user's post in given discussion
 * @global object $DB
 * @param int $did Discussion id
 * @param int $userid User id
 * @return int|bool post creation time stamp or return false
 */
function quora_get_user_posted_time($did, $userid) {
    global $DB;

    $posttime = $DB->get_field('quora_posts', 'MIN(created)', array('userid'=>$userid, 'discussion'=>$did));
    if (empty($posttime)) {
        return false;
    }
    return $posttime;
}

/**
 * @global object
 * @param object $quora
 * @param object $currentgroup
 * @param int $unused
 * @param object $cm
 * @param object $context
 * @return bool
 */
function quora_user_can_post_discussion($quora, $currentgroup=null, $unused=-1, $cm=NULL, $context=NULL) {
// $quora is an object
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($quora->type == 'news') {
        $capname = 'mod/quora:addnews';
    } else if ($quora->type == 'qanda') {
        $capname = 'mod/quora:addquestion';
    } else {
        $capname = 'mod/quora:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($quora->type == 'single') {
        return false;
    }

    if ($quora->type == 'eachuser') {
        if (quora_user_has_posted_discussion($quora->id, $USER->id)) {
            return false;
        }
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        // no group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a quora
 * discussion. Use quora_user_can_post_discussion() to check whether the user
 * can start discussions.
 *
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $quora quora object
 * @param object $discussion
 * @param object $user
 * @param object $cm
 * @param object $course
 * @param object $context
 * @return bool
 */
function quora_user_can_post($quora, $discussion, $user=NULL, $cm=NULL, $course=NULL, $context=NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id' => $quora->course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // normal users with temporary guest access can not post, suspended users can not post either
    if (!is_viewing($context, $user->id) and !is_enrolled($context, $user->id, '', true)) {
        return false;
    }

    if ($quora->type == 'news') {
        $capname = 'mod/quora:replynews';
    } else {
        $capname = 'mod/quora:replypost';
    }

    if (!has_capability($capname, $context, $user->id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);

    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
}

/**
* Check to ensure a user can view a timed discussion.
*
* @param object $discussion
* @param object $user
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function quora_user_can_see_timed_discussion($discussion, $user, $context) {
    global $CFG;

    // Check that the user can view a discussion that is normally hidden due to access times.
    if (!empty($CFG->quora_enabletimedposts)) {
        $time = time();
        if (($discussion->timestart != 0 && $discussion->timestart > $time)
            || ($discussion->timeend != 0 && $discussion->timeend < $time)) {
            if (!has_capability('mod/quora:viewhiddentimedposts', $context, $user->id)) {
                return false;
            }
        }
    }

    return true;
}

/**
* Check to ensure a user can view a group discussion.
*
* @param object $discussion
* @param object $cm
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function quora_user_can_see_group_discussion($discussion, $cm, $context) {

    // If it's a grouped discussion, make sure the user is a member.
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $context);
        }
    }

    return true;
}

/**
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @param object $quora
 * @param object $discussion
 * @param object $context
 * @param object $user
 * @return bool
 */
function quora_user_can_see_discussion($quora, $discussion, $context, $user=NULL) {
    global $USER, $DB;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($quora)) {
        debugging('missing full quora', DEBUG_DEVELOPER);
        if (!$quora = $DB->get_record('quora',array('id'=>$quora))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('quora_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course)) {
        print_error('invalidcoursemodule');
    }

    if (!has_capability('mod/quora:viewdiscussion', $context)) {
        return false;
    }

    if (!quora_user_can_see_timed_discussion($discussion, $user, $context)) {
        return false;
    }

    if (!quora_user_can_see_group_discussion($discussion, $cm, $context)) {
        return false;
    }

    return true;
}

/**
 * @global object
 * @global object
 * @param object $quora
 * @param object $discussion
 * @param object $post
 * @param object $user
 * @param object $cm
 * @return bool
 */
function quora_user_can_see_post($quora, $discussion, $post, $user=NULL, $cm=NULL) {
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm->id);

    // retrieve objects (yuk)
    if (is_numeric($quora)) {
        debugging('missing full quora', DEBUG_DEVELOPER);
        if (!$quora = $DB->get_record('quora',array('id'=>$quora))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('quora_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('quora_posts',array('id'=>$post))) {
            return false;
        }
    }

    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    $canviewdiscussion = !empty($cm->cache->caps['mod/quora:viewdiscussion']) || has_capability('mod/quora:viewdiscussion', $modcontext, $user->id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!\core_availability\info_module::is_user_visible($cm, $user->id, false)) {
            return false;
        }
    }

    if (!quora_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!quora_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    if ($quora->type == 'qanda') {
        $firstpost = quora_get_firstpost_from_discussion($discussion->id);
        $userfirstpost = quora_get_user_posted_time($discussion->id, $user->id);

        return (($userfirstpost !== false && (time() - $userfirstpost >= $CFG->maxeditingtime)) ||
                $firstpost->id == $post->id || $post->userid == $user->id || $firstpost->userid == $user->id ||
                has_capability('mod/quora:viewqandawithoutposting', $modcontext, $user->id));
    }
    return true;
}


/**
 * Prints the discussion view screen for a quora.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $quora Forum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the quora (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 * @param boolean $subscriptionstatus Whether the user is currently subscribed to the discussion in some fashion.
 *
 */
function quora_print_latest_discussions($course, $quora, $maxdiscussions = -1, $displayformat = 'plain', $sort = '',
                                        $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = null) {
    global $CFG, $USER, $OUTPUT;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    if (empty($sort)) {
        $sort = "d.timemodified DESC";
    }

    $olddiscussionlink = false;

 // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page    = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page    = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }


// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array(); //cache

// If the user can post discussions, then this is a good place to put the
// button for it. We do not show the button if we are showing site news
// and the current user is a guest.

    $canstart = quora_user_can_post_discussion($quora, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $quora->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and !is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course->id);
        }
    }

    if ($canstart) {
        echo '<div class="singlebutton quoraaddnew">';
        echo "<form id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/mod/quora/post.php\">";
        echo '<div>';
        echo "<input type=\"hidden\" name=\"quora\" value=\"$quora->id\" />";
        switch ($quora->type) {
            case 'news':
            case 'blog':
                $buttonadd = get_string('addanewtopic', 'quora');
                break;
            case 'qanda':
                $buttonadd = get_string('addanewquestion', 'quora');
                break;
            default:
                $buttonadd = get_string('addanewdiscussion', 'quora');
                break;
        }
        echo '<input type="submit" value="'.$buttonadd.'" />';
        echo '</div>';
        echo '</form>';
        echo "</div>\n";

    } else if (isguestuser() or !isloggedin() or $quora->type == 'news' or
        $quora->type == 'qanda' and !has_capability('mod/quora:addquestion', $context) or
        $quora->type != 'qanda' and !has_capability('mod/quora:startdiscussion', $context)) {
        // no button and no info

    } else if ($groupmode and !has_capability('moodle/site:accessallgroups', $context)) {
        // inform users why they can not post new discussion
        if (!$currentgroup) {
            echo $OUTPUT->notification(get_string('cannotadddiscussionall', 'quora'));
        } else if (!groups_is_member($currentgroup)) {
            echo $OUTPUT->notification(get_string('cannotadddiscussion', 'quora'));
        }
    }

// Get all the recent discussions we're allowed to see

    $getuserlastmodified = ($displayformat == 'header');

    if (! $discussions = quora_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage) ) {
        echo '<div class="quoranodiscuss">';
        if ($quora->type == 'news') {
            echo '('.get_string('nonews', 'quora').')';
        } else if ($quora->type == 'qanda') {
            echo '('.get_string('noquestions','quora').')';
        } else {
            echo '('.get_string('nodiscussions', 'quora').')';
        }
        echo "</div>\n";
        return;
    }

// If we want paging
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = quora_get_discussions_count($cm);

        ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$quora->id");
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large quoras
            $replies = quora_count_discussion_replies($quora->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = quora_count_discussion_replies($quora->id);
        }

    } else {
        $replies = quora_count_discussion_replies($quora->id);

        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);

    $strdatestring = get_string('strftimerecentfull');

    // Check if the quora is tracked.
    if ($cantrack = quora_tp_can_track_quoras($quora)) {
        $quoratracked = quora_tp_is_tracked($quora);
    } else {
        $quoratracked = false;
    }

    if ($quoratracked) {
        $unreads = quora_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }

    if ($displayformat == 'header') {
        echo '<table cellspacing="0" class="forumheaderlist">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="header topic" scope="col">'.get_string('discussion', 'quora').'</th>';
        echo '<th class="header author" colspan="2" scope="col">'.get_string('startedby', 'quora').'</th>';
        if ($groupmode > 0) {
            echo '<th class="header group" scope="col">'.get_string('group').'</th>';
        }
        if (has_capability('mod/quora:viewdiscussion', $context)) {
            echo '<th class="header replies" scope="col">'.get_string('replies', 'quora').'</th>';
            // If the quora can be tracked, display the unread column.
            if ($cantrack) {
                echo '<th class="header replies" scope="col">'.get_string('unread', 'quora');
                if ($quoratracked) {
                    echo '<a title="'.get_string('markallread', 'quora').
                         '" href="'.$CFG->wwwroot.'/mod/quora/markposts.php?f='.
                         $quora->id.'&amp;mark=read&amp;returnpage=view.php">'.
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.get_string('markallread', 'quora').'" /></a>';
                }
                echo '</th>';
            }
        }
        echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'quora').'</th>';
        if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/quora:viewdiscussion', $context)) {
            if (\mod_quora\subscriptions::is_subscribable($quora)) {
                echo '<th class="header discussionsubscription" scope="col">';
                echo quora_get_discussion_subscription_icon_preloaders();
                echo '</th>';
            }
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    }

    foreach ($discussions as $discussion) {
        if ($quora->type == 'qanda' && !has_capability('mod/quora:viewqandawithoutposting', $context) &&
            !quora_user_has_posted($quora->id, $discussion->discussion, $USER->id)) {
            $canviewparticipants = false;
        }

        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$quoratracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;

        switch ($displayformat) {
            case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
                quora_print_discussion_header($discussion, $quora, $group, $strdatestring, $cantrack, $quoratracked,
                    $canviewparticipants, $context);
            break;
            default:
                $link = false;

                if ($discussion->replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm->id);
                    $link = quora_user_can_see_discussion($quora, $discussion, $modcontext, $USER);
                }

                $discussion->quora = $quora->id;

                quora_print_post($discussion, $discussion, $quora, $cm, $course, $ownpost, 0, $link, false,
                        '', null, true, $quoratracked);
            break;
        }
    }

    if ($displayformat == "header") {
        echo '</tbody>';
        echo '</table>';
    }

    if ($olddiscussionlink) {
        if ($quora->type == 'news') {
            $strolder = get_string('oldertopics', 'quora');
        } else {
            $strolder = get_string('olderdiscussions', 'quora');
        }
        echo '<div class="quoraolddiscuss">';
        echo '<a href="'.$CFG->wwwroot.'/mod/quora/view.php?f='.$quora->id.'&amp;showall=1">';
        echo $strolder.'</a> ...</div>';
    }

    if ($page != -1) { ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$quora->id");
    }
}


/**
 * Prints a quora discussion
 *
 * @uses CONTEXT_MODULE
 * @uses FORUM_MODE_FLATNEWEST
 * @uses FORUM_MODE_FLATOLDEST
 * @uses FORUM_MODE_THREADED
 * @uses FORUM_MODE_NESTED
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $quora
 * @param stdClass $discussion
 * @param stdClass $post
 * @param int $mode
 * @param mixed $canreply
 * @param bool $canrate
 */
function quora_print_discussion($course, $cm, $quora, $discussion, $post, $mode, $canreply=NULL, $canrate=false, $is_assessed) {
    global $USER, $CFG;

    require_once($CFG->dirroot.'/rating/lib.php');

    $ownpost = (isloggedin() && $USER->id == $post->userid);

    $modcontext = context_module::instance($cm->id);
    if ($canreply === NULL) {
        $reply = quora_user_can_post($quora, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for quora functions
    $cm->cache = new stdClass;
    $cm->cache->groups      = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    // preload all posts - TODO: improve...
    if ($mode == FORUM_MODE_FLATNEWEST) {
        $sort = "p.created DESC";
    } else {
        $sort = "p.created ASC";
    }

    $quoratracked = quora_tp_is_tracked($quora);
    $posts = quora_get_all_discussion_posts($discussion->id, $sort, $quoratracked);
    $post = $posts[$post->id];

    foreach ($posts as $pid=>$p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    //load ratings
    if ($quora->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $modcontext;
        $ratingoptions->component = 'mod_quora';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = $posts;
        $ratingoptions->aggregate = $quora->assessed;//the aggregation method
        $ratingoptions->scaleid = $quora->scale;
        $ratingoptions->userid = $USER->id;
        if ($quora->type == 'single' or !$discussion->id) {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/quora/view.php?id=$cm->id";
        } else {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/quora/discuss.php?d=$discussion->id";
        }
        $ratingoptions->assesstimestart = $quora->assesstimestart;
        $ratingoptions->assesstimefinish = $quora->assesstimefinish;

        $rm = new rating_manager();
        $posts = $rm->get_ratings($ratingoptions);
    }


    $post->quora = $quora->id;   // Add the quora id to the post object, later used by quora_print_post
    $post->quoratype = $quora->type;

    $post->subject = format_string($post->subject);

    $postread = !empty($post->postread);

    quora_print_post($post, $discussion, $quora, $cm, $course, $is_assessed, $ownpost, $reply, false,
                         '', '', $postread, true, $quoratracked);

    switch ($mode) {
        case FORUM_MODE_FLATOLDEST :
        case FORUM_MODE_FLATNEWEST :
        default:
            quora_print_posts_flat($course, $cm, $quora, $discussion, $post, $is_assessed, $mode, $reply, $quoratracked, $posts);
            break;

        case FORUM_MODE_THREADED :
            quora_print_posts_threaded($course, $cm, $quora, $discussion, $post, $is_assessed, 0, $reply, $quoratracked, $posts);
            break;

        case FORUM_MODE_NESTED :
            quora_print_posts_nested($course, $cm, $quora, $discussion, $post, $is_assessed, $reply, $quoratracked, $posts);
            break;
    }
}


/**
 * @global object
 * @global object
 * @uses FORUM_MODE_FLATNEWEST
 * @param object $course
 * @param object $cm
 * @param object $quora
 * @param object $discussion
 * @param object $post
 * @param object $mode
 * @param bool $reply
 * @param bool $quoratracked
 * @param array $posts
 * @return void
 */
function quora_print_posts_flat($course, &$cm, $quora, $discussion, $post, $is_assessed, $mode, $reply, $quoratracked, $posts) {
    global $USER, $CFG;

    $link  = false;

    if ($mode == FORUM_MODE_FLATNEWEST) {
        $sort = "ORDER BY created DESC";
    } else {
        $sort = "ORDER BY created ASC";
    }

    foreach ($posts as $post) {
        if (!$post->parent) {
            continue;
        }
        $post->subject = format_string($post->subject);
        $ownpost = ($USER->id == $post->userid);

        $postread = !empty($post->postread);

        quora_print_post($post, $discussion, $quora, $cm, $course, $is_assessed, $ownpost, $reply, $link,
                             '', '', $postread, true, $quoratracked);
    }
}

/**
 * @todo Document this function
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return void
 */
function quora_print_posts_threaded($course, &$cm, $quora, $discussion, $parent, $is_assessed, $depth, $reply, $quoratracked, $posts) {
    global $USER, $CFG;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        $modcontext       = context_module::instance($cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if ($depth > 0) {
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);

                quora_print_post($post, $discussion, $quora, $cm, $course, $is_assessed, $ownpost, $reply, $link,
                                     '', '', $postread, true, $quoratracked);
            } else {
                if (!quora_user_can_see_post($quora, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $by = new stdClass();
                $by->name = fullname($post, $canviewfullnames);
                $by->date = userdate($post->modified);

                if ($quoratracked) {
                    if (!empty($post->postread)) {
                        $style = '<span class="quorathread read">';
                    } else {
                        $style = '<span class="quorathread unread">';
                    }
                } else {
                    $style = '<span class="quorathread">';
                }
                echo $style."<a name=\"$post->id\"></a>".
                     "<a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a> ";
                print_string("bynameondate", "quora", $by);
                echo "</span>";
            }

            quora_print_posts_threaded($course, $cm, $quora, $discussion, $post, $is_assessed, $depth-1, $reply, $quoratracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * @todo Document this function
 * @global object
 * @global object
 * @return void
 */
function quora_print_posts_nested($course, &$cm, $quora, $discussion, $parent, $is_assessed, $reply, $quoratracked, $posts) {
    global $USER, $CFG, $DB;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;
        
        //NighCool make the replys of the firstpost sorted by the number of assessments
        if ($parent->id == $discussion->firstpost) {
            $assess = array();
            foreach ($posts as $post) {
                $assess[$post->id] = count($DB->get_records('quora_post_assessments', array('post' => $post->id)));
            }
            arsort($assess);
            $keys = array_keys($assess);
            $new_posts = array();
            foreach ($keys as $key) {
                $new_posts[$key] = &$posts[$key];
            }
            $posts = $new_posts;
        }

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);
            
            quora_print_post($post, $discussion, $quora, $cm, $course, $is_assessed, $ownpost, $reply, $link,
                                 '', '', $postread, true, $quoratracked);
            
            quora_print_posts_nested($course, $cm, $quora, $discussion, $post, $is_assessed, $reply, $quoratracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * Returns all quora posts since a given time in specified quora.
 *
 * @todo Document this functions args
 * @global object
 * @global object
 * @global object
 * @global object
 */
function quora_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $params = array($timestart, $cm->instance);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND d.groupid = ?";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS quoratype, d.quora, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {quora_posts} p
                                              JOIN {quora_discussions} d ON d.id = p.discussion
                                              JOIN {quora} f             ON f.id = d.quora
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.id = ?
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) { // order by initial posting date
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = context_module::instance($cm->id);
    $viewhiddentimed = has_capability('mod/quora:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->quora_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name,true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'quora';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $post->modified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->id         = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject    = format_string($post->subject);
        $tmpactivity->content->parent     = $post->parent;

        $tmpactivity->user = new stdClass();
        $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields = explode(',', user_picture::fields());
        $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
        $tmpactivity->user->id = $post->userid;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * @todo Document this function
 * @global object
 */
function quora_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="quora-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    echo "</td><td class=\"$class\">";

    if ($activity->content->parent) {
        $class = 'title';
    } else {
        // Bold the title of new discussions so they stand out.
        $class = 'title bold';
    }
    echo "<div class=\"{$class}\">";
    if ($detail) {
        $aname = s($activity->name);
        echo "<img src=\"" . $OUTPUT->pix_url('icon', $activity->type) . "\" ".
             "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/quora/discuss.php?d={$activity->content->discussion}"
         ."#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$fullname}</a> - ".userdate($activity->timestamp);
    echo '</div>';
      echo "</td></tr></table>";

    return;
}

/**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * used when pruning a post
 *
 * @global object
 * @param int $postid
 * @param int $discussionid
 * @return bool
 */
function quora_change_discussionid($postid, $discussionid) {
    global $DB;
    $DB->set_field('quora_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB->get_records('quora_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            quora_change_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $quoraid
 * @return string
 */
function quora_update_subscriptions_button($courseid, $quoraid) {
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form method=\"get\" action=\"$CFG->wwwroot/mod/quora/subscribers.php\">".
           "<input type=\"hidden\" name=\"id\" value=\"$quoraid\" />".
           "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />".
           "<input type=\"submit\" value=\"$string\" /></form>";
}

// Functions to do with read tracking.

/**
 * Mark posts as read.
 *
 * @global object
 * @global object
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 */
function quora_tp_mark_posts_read($user, $postids) {
    global $CFG, $DB;

    if (!quora_tp_can_track_quoras(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG->quora_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = quora_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $postidparams) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'postid');

    $insertparams = array(
        'userid1' => $user->id,
        'userid2' => $user->id,
        'userid3' => $user->id,
        'firstread' => $now,
        'lastread' => $now,
        'cutoffdate' => $cutoffdate,
    );
    $params = array_merge($postidparams, $insertparams);

    if ($CFG->quora_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".FORUM_TRACKING_FORCED."
                        OR (f.trackingtype = ".FORUM_TRACKING_OPTIONAL." AND tf.id IS NULL))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".FORUM_TRACKING_OPTIONAL."  OR f.trackingtype = ".FORUM_TRACKING_FORCED.")
                            AND tf.id IS NULL)";
    }

    // First insert any new entries.
    $sql = "INSERT INTO {quora_read} (userid, postid, discussionid, quoraid, firstread, lastread)

            SELECT :userid1, p.id, p.discussion, d.quora, :firstread, :lastread
                FROM {quora_posts} p
                    JOIN {quora_discussions} d       ON d.id = p.discussion
                    JOIN {quora} f                   ON f.id = d.quora
                    LEFT JOIN {quora_track_prefs} tf ON (tf.userid = :userid2 AND tf.quoraid = f.id)
                    LEFT JOIN {quora_read} fr        ON (
                            fr.userid = :userid3
                        AND fr.postid = p.id
                        AND fr.discussionid = d.id
                        AND fr.quoraid = f.id
                    )
                WHERE p.id $usql
                    AND p.modified >= :cutoffdate
                    $trackingsql
                    AND fr.id IS NULL";

    $status = $DB->execute($sql, $params) && $status;

    // Then update all records.
    $updateparams = array(
        'userid' => $user->id,
        'lastread' => $now,
    );
    $params = array_merge($postidparams, $updateparams);
    $status = $DB->set_field_select('quora_read', 'lastread', $now, '
                userid      =  :userid
            AND lastread    <> :lastread
            AND postid      ' . $usql,
            $params) && $status;

    return $status;
}

/**
 * Mark post as read.
 * @global object
 * @global object
 * @param int $userid
 * @param int $postid
 */
function quora_tp_add_read_record($userid, $postid) {
    global $CFG, $DB;

    $now = time();
    $cutoffdate = $now - ($CFG->quora_oldpostdays * 24 * 3600);

    if (!$DB->record_exists('quora_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "INSERT INTO {quora_read} (userid, postid, discussionid, quoraid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.quora, ?, ?
                  FROM {quora_posts} p
                       JOIN {quora_discussions} d ON d.id = p.discussion
                 WHERE p.id = ? AND p.modified >= ?";
        return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));

    } else {
        $sql = "UPDATE {quora_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function quora_tp_mark_post_read($userid, $post, $quoraid) {
    if (!quora_tp_is_post_old($post)) {
        return quora_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

/**
 * Marks a whole quora as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $quoraid
 * @param int|bool $groupid
 * @return bool
 */
function quora_tp_mark_quora_read($user, $quoraid, $groupid=false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->quora_oldpostdays*24*60*60);

    $groupsel = "";
    $params = array($user->id, $quoraid, $cutoffdate);

    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT p.id
              FROM {quora_posts} p
                   LEFT JOIN {quora_discussions} d ON d.id = p.discussion
                   LEFT JOIN {quora_read} r        ON (r.postid = p.id AND r.userid = ?)
             WHERE d.quora = ?
                   AND p.modified >= ? AND r.id is NULL
                   $groupsel";

    if ($posts = $DB->get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return quora_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $discussionid
 * @return bool
 */
function quora_tp_mark_discussion_read($user, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->quora_oldpostdays*24*60*60);

    $sql = "SELECT p.id
              FROM {quora_posts} p
                   LEFT JOIN {quora_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id is NULL";

    if ($posts = $DB->get_records_sql($sql, array($user->id, $discussionid, $cutoffdate))) {
        $postids = array_keys($posts);
        return quora_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * @global object
 * @param int $userid
 * @param object $post
 */
function quora_tp_is_post_read($userid, $post) {
    global $DB;
    return (quora_tp_is_post_old($post) ||
            $DB->record_exists('quora_read', array('userid' => $userid, 'postid' => $post->id)));
}

/**
 * @global object
 * @param object $post
 * @param int $time Defautls to time()
 */
function quora_tp_is_post_old($post, $time=null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post->modified < ($time - ($CFG->quora_oldpostdays * 24 * 3600)));
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid
 * @return array
 */
function quora_tp_get_course_unread_posts($userid, $courseid) {
    global $CFG, $DB;

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->quora_oldpostdays * 24 * 60 * 60);
    $params = array($userid, $userid, $courseid, $cutoffdate, $userid);

    if (!empty($CFG->quora_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    if ($CFG->quora_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".FORUM_TRACKING_FORCED."
                            OR (f.trackingtype = ".FORUM_TRACKING_OPTIONAL." AND tf.id IS NULL
                                AND (SELECT trackquoras FROM {user} WHERE id = ?) = 1))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".FORUM_TRACKING_OPTIONAL." OR f.trackingtype = ".FORUM_TRACKING_FORCED.")
                            AND tf.id IS NULL
                            AND (SELECT trackquoras FROM {user} WHERE id = ?) = 1)";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {quora_posts} p
                   JOIN {quora_discussions} d       ON d.id = p.discussion
                   JOIN {quora} f                   ON f.id = d.quora
                   JOIN {course} c                  ON c.id = f.course
                   LEFT JOIN {quora_read} r         ON (r.postid = p.id AND r.userid = ?)
                   LEFT JOIN {quora_track_prefs} tf ON (tf.userid = ? AND tf.quoraid = f.id)
             WHERE f.course = ?
                   AND p.modified >= ? AND r.id is NULL
                   $trackingsql
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB->get_records_sql($sql, $params)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and quora and [optionally] group.
 *
 * @global object
 * @global object
 * @global object
 * @param object $cm
 * @param object $course
 * @return int
 */
function quora_tp_count_quora_unread_posts($cm, $course) {
    global $CFG, $USER, $DB;

    static $readcache = array();

    $quoraid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = quora_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$quoraid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$quoraid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $readcache[$course->id][$quoraid];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list ($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);

    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->quora_oldpostdays*24*60*60);
    $params = array($USER->id, $quoraid, $cutoffdate);

    if (!empty($CFG->quora_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $params = array_merge($params, $groups_params);

    $sql = "SELECT COUNT(p.id)
              FROM {quora_posts} p
                   JOIN {quora_discussions} d ON p.discussion = d.id
                   LEFT JOIN {quora_read} r   ON (r.postid = p.id AND r.userid = ?)
             WHERE d.quora = ?
                   AND p.modified >= ? AND r.id is NULL
                   $timedsql
                   AND d.groupid $groups_sql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $quoraid
 * @return bool
 */
function quora_tp_delete_read_records($userid=-1, $postid=-1, $discussionid=-1, $quoraid=-1) {
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($quoraid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'quoraid = ?';
        $params[] = $quoraid;
    }
    if ($select == '') {
        return false;
    }
    else {
        return $DB->delete_records_select('quora_read', $select, $params);
    }
}
/**
 * Get a list of quoras not tracked by the user.
 *
 * @global object
 * @global object
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by quora id, or false.
 */
function quora_tp_get_untracked_quoras($userid, $courseid) {
    global $CFG, $DB;

    if ($CFG->quora_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".FORUM_TRACKING_OFF."
                            OR (f.trackingtype = ".FORUM_TRACKING_OPTIONAL." AND (ft.id IS NOT NULL
                                OR (SELECT trackquoras FROM {user} WHERE id = ?) = 0)))";
    } else {
        $trackingsql = "AND (f.trackingtype = ".FORUM_TRACKING_OFF."
                            OR ((f.trackingtype = ".FORUM_TRACKING_OPTIONAL." OR f.trackingtype = ".FORUM_TRACKING_FORCED.")
                                AND (ft.id IS NOT NULL
                                    OR (SELECT trackquoras FROM {user} WHERE id = ?) = 0)))";
    }

    $sql = "SELECT f.id
              FROM {quora} f
                   LEFT JOIN {quora_track_prefs} ft ON (ft.quoraid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   $trackingsql";

    if ($quoras = $DB->get_records_sql($sql, array($userid, $courseid, $userid))) {
        foreach ($quoras as $quora) {
            $quoras[$quora->id] = $quora;
        }
        return $quoras;

    } else {
        return array();
    }
}

/**
 * Determine if a user can track quoras and optionally a particular quora.
 * Checks the site settings, the user settings and the quora settings (if
 * requested).
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $quora The quora object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function quora_tp_can_track_quoras($quora=false, $user=false) {
    global $USER, $CFG, $DB;

    // if possible, avoid expensive
    // queries
    if (empty($CFG->quora_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if ($quora === false) {
        if ($CFG->quora_allowforcedreadtracking) {
            // Since we can force tracking, assume yes without a specific quora.
            return true;
        } else {
            return (bool)$user->trackquoras;
        }
    }

    // Work toward always passing an object...
    if (is_numeric($quora)) {
        debugging('Better use proper quora object.', DEBUG_DEVELOPER);
        $quora = $DB->get_record('quora', array('id' => $quora), '', 'id,trackingtype');
    }

    $quoraallows = ($quora->trackingtype == FORUM_TRACKING_OPTIONAL);
    $quoraforced = ($quora->trackingtype == FORUM_TRACKING_FORCED);

    if ($CFG->quora_allowforcedreadtracking) {
        // If we allow forcing, then forced quoras takes procidence over user setting.
        return ($quoraforced || ($quoraallows  && (!empty($user->trackquoras) && (bool)$user->trackquoras)));
    } else {
        // If we don't allow forcing, user setting trumps.
        return ($quoraforced || $quoraallows)  && !empty($user->trackquoras);
    }
}

/**
 * Tells whether a specific quora is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $quora If int, the id of the quora being checked; if object, the quora object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 */
function quora_tp_is_tracked($quora, $user=false) {
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($quora)) {
        debugging('Better use proper quora object.', DEBUG_DEVELOPER);
        $quora = $DB->get_record('quora', array('id' => $quora));
    }

    if (!quora_tp_can_track_quoras($quora, $user)) {
        return false;
    }

    $quoraallows = ($quora->trackingtype == FORUM_TRACKING_OPTIONAL);
    $quoraforced = ($quora->trackingtype == FORUM_TRACKING_FORCED);
    $userpref = $DB->get_record('quora_track_prefs', array('userid' => $user->id, 'quoraid' => $quora->id));

    if ($CFG->quora_allowforcedreadtracking) {
        return $quoraforced || ($quoraallows && $userpref === false);
    } else {
        return  ($quoraallows || $quoraforced) && $userpref === false;
    }
}

/**
 * @global object
 * @global object
 * @param int $quoraid
 * @param int $userid
 */
function quora_tp_start_tracking($quoraid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return $DB->delete_records('quora_track_prefs', array('userid' => $userid, 'quoraid' => $quoraid));
}

/**
 * @global object
 * @global object
 * @param int $quoraid
 * @param int $userid
 */
function quora_tp_stop_tracking($quoraid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!$DB->record_exists('quora_track_prefs', array('userid' => $userid, 'quoraid' => $quoraid))) {
        $track_prefs = new stdClass();
        $track_prefs->userid = $userid;
        $track_prefs->quoraid = $quoraid;
        $DB->insert_record('quora_track_prefs', $track_prefs);
    }

    return quora_tp_delete_read_records($userid, -1, -1, $quoraid);
}


/**
 * Clean old records from the quora_read table.
 * @global object
 * @global object
 * @return void
 */
function quora_tp_clean_read_records() {
    global $CFG, $DB;

    if (!isset($CFG->quora_oldpostdays)) {
        return;
    }
// Look for records older than the cutoffdate that are still in the quora_read table.
    $cutoffdate = time() - ($CFG->quora_oldpostdays*24*60*60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {quora_posts} fp
                   JOIN {quora_read} fr ON fr.postid=fp.id";
    if (!$first = $DB->get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {quora_read}
             WHERE postid IN (SELECT fp.id
                                FROM {quora_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)";
    $DB->execute($sql, array($first, $cutoffdate));
}

/**
 * Sets the last post for a given discussion
 *
 * @global object
 * @global object
 * @param into $discussionid
 * @return bool|int
 **/
function quora_discussion_update_last_post($discussionid) {
    global $CFG, $DB;

// Check the given discussion exists
    if (!$DB->record_exists('quora_discussions', array('id' => $discussionid))) {
        return false;
    }

// Use SQL to find the last post for this discussion
    $sql = "SELECT id, userid, modified
              FROM {quora_posts}
             WHERE discussion=?
             ORDER BY modified DESC";

// Lets go find the last post
    if (($lastposts = $DB->get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);
        $discussionobject = new stdClass();
        $discussionobject->id           = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        $DB->update_record('quora_discussions', $discussionobject);
        return $lastpost->id;
    }

// To get here either we couldn't find a post for the discussion (weird)
// or we couldn't update the discussion record (weird x2)
    return false;
}


/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function quora_get_view_actions() {
    return array('view discussion', 'search', 'quora', 'quoras', 'subscribers', 'view quora');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function quora_get_post_actions() {
    return array('add discussion','add post','delete discussion','delete post','move discussion','prune post','update post');
}

/**
 * Returns a warning object if a user has reached the number of posts equal to
 * the warning/blocking setting, or false if there is no warning to show.
 *
 * @param int|stdClass $quora the quora id or the quora object
 * @param stdClass $cm the course module
 * @return stdClass|bool returns an object with the warning information, else
 *         returns false if no warning is required.
 */
function quora_check_throttling($quora, $cm = null) {
    global $CFG, $DB, $USER;

    if (is_numeric($quora)) {
        $quora = $DB->get_record('quora', array('id' => $quora), '*', MUST_EXIST);
    }

    if (!is_object($quora)) {
        return false; // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course, false, MUST_EXIST);
    }

    if (empty($quora->blockafter)) {
        return false;
    }

    if (empty($quora->blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm->id);
    if (has_capability('mod/quora:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $quora->blockperiod;
    $numposts = $DB->count_records_sql('SELECT COUNT(p.id) FROM {quora_posts} p
                                        JOIN {quora_discussions} d
                                        ON p.discussion = d.id WHERE d.quora = ?
                                        AND p.userid = ? AND p.created > ?', array($quora->id, $USER->id, $timeafter));

    $a = new stdClass();
    $a->blockafter = $quora->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime'.$quora->blockperiod);

    if ($quora->blockafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = false;
        $warning->errorcode = 'quorablockingtoomanyposts';
        $warning->module = 'error';
        $warning->additional = $a;
        $warning->link = $CFG->wwwroot . '/mod/quora/view.php?f=' . $quora->id;

        return $warning;
    }

    if ($quora->warnafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = true;
        $warning->errorcode = 'quorablockingalmosttoomanyposts';
        $warning->module = 'quora';
        $warning->additional = $a;
        $warning->link = null;

        return $warning;
    }
}

/**
 * Throws an error if the user is no longer allowed to post due to having reached
 * or exceeded the number of posts specified in 'Post threshold for blocking'
 * setting.
 *
 * @since Moodle 2.5
 * @param stdClass $thresholdwarning the warning information returned
 *        from the function quora_check_throttling.
 */
function quora_check_blocking_threshold($thresholdwarning) {
    if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
        print_error($thresholdwarning->errorcode,
                    $thresholdwarning->module,
                    $thresholdwarning->link,
                    $thresholdwarning->additional);
    }
}


/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional
 */
function quora_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    if ($type) {
        $wheresql = "AND f.type=?";
        $params[] = $type;
    }

    $sql = "SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {quora} f, {course_modules} cm, {modules} m
             WHERE m.name='quora' AND m.id=cm.module AND cm.instance=f.id AND f.course=? $wheresql";

    if ($quoras = $DB->get_records_sql($sql, $params)) {
        foreach ($quoras as $quora) {
            quora_grade_item_update($quora, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified quora
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function quora_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'quora');
    $status = array();

    $params = array($data->courseid);

    $removeposts = false;
    $typesql     = "";
    if (!empty($data->reset_quora_all)) {
        $removeposts = true;
        $typesstr    = get_string('resetquorasall', 'quora');
        $types       = array();
    } else if (!empty($data->reset_quora_types)){
        $removeposts = true;
        $types       = array();
        $sqltypes    = array();
        $quora_types_all = quora_get_quora_types_all();
        foreach ($data->reset_quora_types as $type) {
            if (!array_key_exists($type, $quora_types_all)) {
                continue;
            }
            $types[] = $quora_types_all[$type];
            $sqltypes[] = $type;
        }
        if (!empty($sqltypes)) {
            list($typesql, $typeparams) = $DB->get_in_or_equal($sqltypes);
            $typesql = " AND f.type " . $typesql;
            $params = array_merge($params, $typeparams);
        }
        $typesstr = get_string('resetquoras', 'quora').': '.implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {quora_discussions} fd, {quora} f
                           WHERE f.course=? AND f.id=fd.quora";

    $allquorassql      = "SELECT f.id
                            FROM {quora} f
                           WHERE f.course=?";

    $allpostssql       = "SELECT fp.id
                            FROM {quora_posts} fp, {quora_discussions} fd, {quora} f
                           WHERE f.course=? AND f.id=fd.quora AND fd.id=fp.discussion";

    $quorassql = $quoras = $rm = null;

    if( $removeposts || !empty($data->reset_quora_ratings) ) {
        $quorassql      = "$allquorassql $typesql";
        $quoras = $quoras = $DB->get_records_sql($quorassql, $params);
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_quora';
        $ratingdeloptions->ratingarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql       = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($quoras) {
            foreach ($quoras as $quoraid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('quora', $quoraid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_quora', 'attachment');
                $fs->delete_area_files($context->id, 'mod_quora', 'post');

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // first delete all read flags
        $DB->delete_records_select('quora_read', "quoraid IN ($quorassql)", $params);

        // remove tracking prefs
        $DB->delete_records_select('quora_track_prefs', "quoraid IN ($quorassql)", $params);

        // remove posts from queue
        $DB->delete_records_select('quora_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion quoras
        $DB->delete_records_select('quora_posts', "discussion IN ($discussionssql) AND parent <> 0", $params); // first all children
        $DB->delete_records_select('quora_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params); // now the initial posts for non single simple

        // finally all discussions except single simple quoras
        $DB->delete_records_select('quora_discussions', "quora IN ($quorassql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                quora_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    quora_reset_gradebook($data->courseid, $type);
                }
            }
        }

        $status[] = array('component'=>$componentstr, 'item'=>$typesstr, 'error'=>false);
    }

    // remove all ratings in this course's quoras
    if (!empty($data->reset_quora_ratings)) {
        if ($quoras) {
            foreach ($quoras as $quoraid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('quora', $quoraid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            quora_reset_gradebook($data->courseid);
        }
    }

    // remove all digest settings unconditionally - even for users still enrolled in course.
    if (!empty($data->reset_quora_digests)) {
        $DB->delete_records_select('quora_digests', "quora IN ($allquorassql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetdigests', 'quora'), 'error' => false);
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_quora_subscriptions)) {
        $DB->delete_records_select('quora_subscriptions', "quora IN ($allquorassql)", $params);
        $DB->delete_records_select('quora_discussion_subs', "quora IN ($allquorassql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetsubscriptions', 'quora'), 'error' => false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_quora_track_prefs)) {
        $DB->delete_records_select('quora_track_prefs', "quoraid IN ($allquorassql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resettrackprefs','quora'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('quora', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function quora_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'forumheader', get_string('modulenameplural', 'quora'));

    $mform->addElement('checkbox', 'reset_quora_all', get_string('resetquorasall','quora'));

    $mform->addElement('select', 'reset_quora_types', get_string('resetquoras', 'quora'), quora_get_quora_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_quora_types');
    $mform->disabledIf('reset_quora_types', 'reset_quora_all', 'checked');

    $mform->addElement('checkbox', 'reset_quora_digests', get_string('resetdigests','quora'));
    $mform->setAdvanced('reset_quora_digests');

    $mform->addElement('checkbox', 'reset_quora_subscriptions', get_string('resetsubscriptions','quora'));
    $mform->setAdvanced('reset_quora_subscriptions');

    $mform->addElement('checkbox', 'reset_quora_track_prefs', get_string('resettrackprefs','quora'));
    $mform->setAdvanced('reset_quora_track_prefs');
    $mform->disabledIf('reset_quora_track_prefs', 'reset_quora_all', 'checked');

    $mform->addElement('checkbox', 'reset_quora_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_quora_ratings', 'reset_quora_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function quora_reset_course_form_defaults($course) {
    return array('reset_quora_all'=>1, 'reset_quora_digests' => 0, 'reset_quora_subscriptions'=>0, 'reset_quora_track_prefs'=>0, 'reset_quora_ratings'=>1);
}

/**
 * Returns array of quora layout modes
 *
 * @return array
 */
function quora_get_layout_modes() {
    return array (FORUM_MODE_FLATOLDEST => get_string('modeflatoldestfirst', 'quora'),
                  FORUM_MODE_FLATNEWEST => get_string('modeflatnewestfirst', 'quora'),
                  FORUM_MODE_THREADED   => get_string('modethreaded', 'quora'),
                  FORUM_MODE_NESTED     => get_string('modenested', 'quora'));
}

/**
 * Returns array of quora types chooseable on the quora editing form
 *
 * @return array
 */
function quora_get_quora_types() {
    return array ('general'  => get_string('generalquora', 'quora'),
                  'eachuser' => get_string('eachuserquora', 'quora'),
                  'single'   => get_string('singlequora', 'quora'),
                  'qanda'    => get_string('qandaquora', 'quora'),
                  'blog'     => get_string('blogquora', 'quora'));
}

/**
 * Returns array of all quora layout modes
 *
 * @return array
 */
function quora_get_quora_types_all() {
    return array ('news'     => get_string('namenews','quora'),
                  'social'   => get_string('namesocial','quora'),
                  'general'  => get_string('generalquora', 'quora'),
                  'eachuser' => get_string('eachuserquora', 'quora'),
                  'single'   => get_string('singlequora', 'quora'),
                  'qanda'    => get_string('qandaquora', 'quora'),
                  'blog'     => get_string('blogquora', 'quora'));
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function quora_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent', 'moodle/rating:view', 'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate');
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $quoranode The node to add module settings to
 */
function quora_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $quoranode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $quoraobject = $DB->get_record("quora", array("id" => $PAGE->cm->instance));
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    $params = $PAGE->url->params();
    if (!empty($params['d'])) {
        $discussionid = $params['d'];
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);

    $canmanage  = has_capability('mod/quora:managesubscriptions', $PAGE->cm->context);
    $subscriptionmode = \mod_quora\subscriptions::get_subscription_mode($quoraobject);
    $cansubscribe = $activeenrolled && !\mod_quora\subscriptions::is_forcesubscribed($quoraobject) &&
            (!\mod_quora\subscriptions::subscription_disabled($quoraobject) || $canmanage);

    if ($canmanage) {
        $mode = $quoranode->add(get_string('subscriptionmode', 'quora'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode->add(get_string('subscriptionoptional', 'quora'), new moodle_url('/mod/quora/subscribe.php', array('id'=>$quoraobject->id, 'mode'=>FORUM_CHOOSESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceforever = $mode->add(get_string("subscriptionforced", "quora"), new moodle_url('/mod/quora/subscribe.php', array('id'=>$quoraobject->id, 'mode'=>FORUM_FORCESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode->add(get_string("subscriptionauto", "quora"), new moodle_url('/mod/quora/subscribe.php', array('id'=>$quoraobject->id, 'mode'=>FORUM_INITIALSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $disallowchoice = $mode->add(get_string('subscriptiondisabled', 'quora'), new moodle_url('/mod/quora/subscribe.php', array('id'=>$quoraobject->id, 'mode'=>FORUM_DISALLOWSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case FORUM_CHOOSESUBSCRIBE : // 0
                $allowchoice->action = null;
                $allowchoice->add_class('activesetting');
                break;
            case FORUM_FORCESUBSCRIBE : // 1
                $forceforever->action = null;
                $forceforever->add_class('activesetting');
                break;
            case FORUM_INITIALSUBSCRIBE : // 2
                $forceinitially->action = null;
                $forceinitially->add_class('activesetting');
                break;
            case FORUM_DISALLOWSUBSCRIBE : // 3
                $disallowchoice->action = null;
                $disallowchoice->add_class('activesetting');
                break;
        }

    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case FORUM_CHOOSESUBSCRIBE : // 0
                $notenode = $quoranode->add(get_string('subscriptionoptional', 'quora'));
                break;
            case FORUM_FORCESUBSCRIBE : // 1
                $notenode = $quoranode->add(get_string('subscriptionforced', 'quora'));
                break;
            case FORUM_INITIALSUBSCRIBE : // 2
                $notenode = $quoranode->add(get_string('subscriptionauto', 'quora'));
                break;
            case FORUM_DISALLOWSUBSCRIBE : // 3
                $notenode = $quoranode->add(get_string('subscriptiondisabled', 'quora'));
                break;
        }
    }

    if ($cansubscribe) {
        if (\mod_quora\subscriptions::is_subscribed($USER->id, $quoraobject, null, $PAGE->cm)) {
            $linktext = get_string('unsubscribe', 'quora');
        } else {
            $linktext = get_string('subscribe', 'quora');
        }
        $url = new moodle_url('/mod/quora/subscribe.php', array('id'=>$quoraobject->id, 'sesskey'=>sesskey()));
        $quoranode->add($linktext, $url, navigation_node::TYPE_SETTING);

        if (isset($discussionid)) {
            if (\mod_quora\subscriptions::is_subscribed($USER->id, $quoraobject, $discussionid, $PAGE->cm)) {
                $linktext = get_string('unsubscribediscussion', 'quora');
            } else {
                $linktext = get_string('subscribediscussion', 'quora');
            }
            $url = new moodle_url('/mod/quora/subscribe.php', array(
                    'id' => $quoraobject->id,
                    'sesskey' => sesskey(),
                    'd' => $discussionid,
                    'returnurl' => $PAGE->url->out(),
                ));
            $quoranode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (has_capability('mod/quora:viewsubscribers', $PAGE->cm->context)){
        $url = new moodle_url('/mod/quora/subscribers.php', array('id'=>$quoraobject->id));
        $quoranode->add(get_string('showsubscribers', 'quora'), $url, navigation_node::TYPE_SETTING);
    }

    if ($enrolled && quora_tp_can_track_quoras($quoraobject)) { // keep tracking info for users with suspended enrolments
        if ($quoraobject->trackingtype == FORUM_TRACKING_OPTIONAL
                || ((!$CFG->quora_allowforcedreadtracking) && $quoraobject->trackingtype == FORUM_TRACKING_FORCED)) {
            if (quora_tp_is_tracked($quoraobject)) {
                $linktext = get_string('notrackquora', 'quora');
            } else {
                $linktext = get_string('trackquora', 'quora');
            }
            $url = new moodle_url('/mod/quora/settracking.php', array(
                    'id' => $quoraobject->id,
                    'sesskey' => sesskey(),
                ));
            $quoranode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE->course->id == SITEID) {
        $userid = guest_user()->id;
    } else {
        $userid = $USER->id;
    }

    $hascourseaccess = ($PAGE->course->id == SITEID) || can_access_course($PAGE->course, $userid);
    $enablerssfeeds = !empty($CFG->enablerssfeeds) && !empty($CFG->quora_enablerssfeeds);

    if ($enablerssfeeds && $quoraobject->rsstype && $quoraobject->rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once("$CFG->libdir/rsslib.php");
        }

        if ($quoraobject->rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions','quora');
        } else {
            $string = get_string('rsssubscriberssposts','quora');
        }

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $userid, "mod_quora", $quoraobject->id));
        $quoranode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function quora_cm_info_view(cm_info $cm) {
    global $CFG;

    if (quora_tp_can_track_quoras()) {
        if ($unread = quora_tp_count_quora_unread_posts($cm, $cm->get_course())) {
            $out = '<span class="unread"> <a href="' . $cm->url . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'quora');
            } else {
                $out .= get_string('unreadpostsnumber', 'quora', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function quora_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $quora_pagetype = array(
        'mod-quora-*'=>get_string('page-mod-quora-x', 'quora'),
        'mod-quora-view'=>get_string('page-mod-quora-view', 'quora'),
        'mod-quora-discuss'=>get_string('page-mod-quora-discuss', 'quora')
    );
    return $quora_pagetype;
}

/**
 * Gets all of the courses where the provided user has posted in a quora.
 *
 * @global moodle_database $DB The database connection
 * @param stdClass $user The user who's posts we are looking for
 * @param bool $discussionsonly If true only look for discussions started by the user
 * @param bool $includecontexts If set to trye contexts for the courses will be preloaded
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of courses
 */
function quora_get_courses_user_posted_in($user, $discussionsonly = false, $includecontexts = true, $limitfrom = null, $limitnum = null) {
    global $DB;

    // If we are only after discussions we need only look at the quora_discussions
    // table and join to the userid there. If we are looking for posts then we need
    // to join to the quora_posts table.
    if (!$discussionsonly) {
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {quora_discussions} fd
                         JOIN {quora_posts} fp ON fp.discussion = fd.id
                        WHERE fp.userid = :userid )";
    } else {
        $subquery= "(SELECT DISTINCT fd.course
                         FROM {quora_discussions} fd
                        WHERE fd.userid = :userid )";
    }

    $params = array('userid' => $user->id);

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a quora will be returned.
    $sql = "SELECT c.* $ctxselect
            FROM {course} c
            $ctxjoin
            WHERE c.id IN ($subquery)";
    $courses = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_helper::preload_from_record', $courses);
    }
    return $courses;
}

/**
 * Gets all of the quoras a user has posted in for one or more courses.
 *
 * @global moodle_database $DB
 * @param stdClass $user
 * @param array $courseids An array of courseids to search or if not provided
 *                       all courses the user has posted within
 * @param bool $discussionsonly If true then only quoras where the user has started
 *                       a discussion will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of quoras the user has posted within in the provided courses
 */
function quora_get_quoras_user_posted_in($user, array $courseids = null, $discussionsonly = false, $limitfrom = null, $limitnum = null) {
    global $DB;

    if (!is_null($courseids)) {
        list($coursewhere, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course '.$coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user->id;
    $params['quora'] = 'quora';

    if ($discussionsonly) {
        $join = 'JOIN {quora_discussions} ff ON ff.quora = f.id';
    } else {
        $join = 'JOIN {quora_discussions} fd ON fd.quora = f.id
                 JOIN {quora_posts} ff ON ff.discussion = fd.id';
    }

    $sql = "SELECT f.*, cm.id AS cmid
              FROM {quora} f
              JOIN {course_modules} cm ON cm.instance = f.id
              JOIN {modules} m ON m.id = cm.module
              JOIN (
                  SELECT f.id
                    FROM {quora} f
                    {$join}
                   WHERE ff.userid = :userid
                GROUP BY f.id
                   ) j ON j.id = f.id
             WHERE m.name = :quora
                 {$coursewhere}";

    $coursequoras = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    return $coursequoras;
}

/**
 * Returns posts made by the selected user in the requested courses.
 *
 * This method can be used to return all of the posts made by the requested user
 * within the given courses.
 * For each course the access of the current user and requested user is checked
 * and then for each post access to the post and quora is checked as well.
 *
 * This function is safe to use with usercapabilities.
 *
 * @global moodle_database $DB
 * @param stdClass $user The user whose posts we want to get
 * @param array $courses The courses to search
 * @param bool $musthaveaccess If set to true errors will be thrown if the user
 *                             cannot access one or more of the courses to search
 * @param bool $discussionsonly If set to true only discussion starting posts
 *                              will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return stdClass An object the following properties
 *               ->totalcount: the total number of posts made by the requested user
 *                             that the current user can see.
 *               ->courses: An array of courses the current user can see that the
 *                          requested user has posted in.
 *               ->quoras: An array of quoras relating to the posts returned in the
 *                         property below.
 *               ->posts: An array containing the posts to show for this request.
 */
function quora_get_posts_by_user($user, array $courses, $musthaveaccess = false, $discussionsonly = false, $limitfrom = 0, $limitnum = 50) {
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return->totalcount = 0;    // The total number of posts that the current user is able to view
    $return->courses = array(); // The courses the current user can access
    $return->quoras = array();  // The quoras that the current user can access that contain posts
    $return->posts = array();   // The posts to display

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'quora');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'quora');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course, $user) && !is_enrolled($coursecontext, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'quora');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B quora. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce
              && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin or $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot."/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the quora accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return->courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the quoras that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which quoras we can search by testing accessibility.
    $quoras = quora_get_quoras_user_posted_in($user, array_keys($return->courses), $discussionsonly);

    // Will be used to build the where conditions for the search
    $quorasearchwhere = array();
    // Will be used to store the where condition params for the search
    $quorasearchparams = array();
    // Will record quoras where the user can freely access everything
    $quorasearchfullaccess = array();
    // DB caching friendly
    $now = round(time(), -2);
    // For each course to search we want to find the quoras the user has posted in
    // and providing the current user can access the quora create a search condition
    // for the quora to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the quoras
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->instances['quora'])) {
            // hmmm, no quoras? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('quora') as $quoraid => $cm) {
            if (!$cm->uservisible or !isset($quoras[$quoraid])) {
                continue;
            }
            // Get the quora in question
            $quora = $quoras[$quoraid];

            // This is needed for functionality later on in the quora code. It is converted to an object
            // because the cm_info is readonly from 2.6. This is a dirty hack because some other parts of the
            // code were expecting an writeable object. See {@link quora_print_post()}.
            $quora->cm = new stdClass();
            foreach ($cm as $key => $value) {
                $quora->cm->$key = $value;
            }

            // Check that either the current user can view the quora, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/quora:viewdiscussion', $cm->context) && !($hascapsonuser && has_capability('mod/quora:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain quora specific where clauses
            $quorasearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps'.$quoraid.'_');
                    $quorasearchparams = array_merge($quorasearchparams, $groupid_params);
                    $quorasearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG->quora_enabletimedposts) && !has_capability('mod/quora:viewhiddentimedposts', $cm->context)) {
                    $quorasearchselect[] = "(d.userid = :userid{$quoraid} OR (d.timestart < :timestart{$quoraid} AND (d.timeend = 0 OR d.timeend > :timeend{$quoraid})))";
                    $quorasearchparams['userid'.$quoraid] = $user->id;
                    $quorasearchparams['timestart'.$quoraid] = $now;
                    $quorasearchparams['timeend'.$quoraid] = $now;
                }

                // qanda access
                if ($quora->type == 'qanda' && !has_capability('mod/quora:viewqandawithoutposting', $cm->context)) {
                    // We need to check whether the user has posted in the qanda quora.
                    $discussionspostedin = quora_discussions_user_has_posted_in($quora->id, $user->id);
                    if (!empty($discussionspostedin)) {
                        $quoraonlydiscussions = array();  // Holds discussion ids for the discussions the user is allowed to see in this quora.
                        foreach ($discussionspostedin as $d) {
                            $quoraonlydiscussions[] = $d->id;
                        }
                        list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($quoraonlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$quoraid.'_');
                        $quorasearchparams = array_merge($quorasearchparams, $discussionid_params);
                        $quorasearchselect[] = "(d.id $discussionid_sql OR p.parent = 0)";
                    } else {
                        $quorasearchselect[] = "p.parent = 0";
                    }

                }

                if (count($quorasearchselect) > 0) {
                    $quorasearchwhere[] = "(d.quora = :quora{$quoraid} AND ".implode(" AND ", $quorasearchselect).")";
                    $quorasearchparams['quora'.$quoraid] = $quoraid;
                } else {
                    $quorasearchfullaccess[] = $quoraid;
                }
            } else {
                // The current user/parent can see all of their own posts
                $quorasearchfullaccess[] = $quoraid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any quoras where
    // the user has full access then we just return the default.
    if (empty($quorasearchwhere) && empty($quorasearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access quoras.
    if (count($quorasearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($quorasearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $quorasearchparams = array_merge($quorasearchparams, $fullidparams);
        $quorasearchwhere[] = "(d.quora $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we quora_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.quora, d.name AS discussionname, '.$userfields.' ';
    $wheresql = implode(" OR ", $quorasearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND ('.$wheresql.')';
        }
    }

    $sql = "FROM {quora_posts} p
            JOIN {quora_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $quorasearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see
    $return->totalcount = $DB->count_records_sql($countsql.$sql, $quorasearchparams);
    // Set the collection of posts that has been requested
    $return->posts = $DB->get_records_sql($selectsql.$sql.$orderby, $quorasearchparams, $limitfrom, $limitnum);

    // We need to build an array of quoras for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these quoras posts. Given we have the quoras already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post->quora, $return->quoras)) {
            $return->quoras[$post->quora] = $quoras[$post->quora];
        }
    }

    return $return;
}

/**
 * Set the per-quora maildigest option for the specified user.
 *
 * @param stdClass $quora The quora to set the option for.
 * @param int $maildigest The maildigest option.
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @throws invalid_digest_setting thrown if an invalid maildigest option is provided.
 */
function quora_set_user_maildigest($quora, $maildigest, $user = null) {
    global $DB, $USER;

    if (is_number($quora)) {
        $quora = $DB->get_record('quora', array('id' => $quora));
    }

    if ($user === null) {
        $user = $USER;
    }

    $course  = $DB->get_record('course', array('id' => $quora->course), '*', MUST_EXIST);
    $cm      = get_coursemodule_from_instance('quora', $quora->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // User must be allowed to see this quora.
    require_capability('mod/quora:viewdiscussion', $context, $user->id);

    // Validate the maildigest setting.
    $digestoptions = quora_get_user_digest_options($user);

    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invaliddigestsetting', 'mod_quora');
    }

    // Attempt to retrieve any existing quora digest record.
    $subscription = $DB->get_record('quora_digests', array(
        'userid' => $user->id,
        'quora' => $quora->id,
    ));

    // Create or Update the existing maildigest setting.
    if ($subscription) {
        if ($maildigest == -1) {
            $DB->delete_records('quora_digests', array('quora' => $quora->id, 'userid' => $user->id));
        } else if ($maildigest !== $subscription->maildigest) {
            // Only update the maildigest setting if it's changed.

            $subscription->maildigest = $maildigest;
            $DB->update_record('quora_digests', $subscription);
        }
    } else {
        if ($maildigest != -1) {
            // Only insert the maildigest setting if it's non-default.

            $subscription = new stdClass();
            $subscription->quora = $quora->id;
            $subscription->userid = $user->id;
            $subscription->maildigest = $maildigest;
            $subscription->id = $DB->insert_record('quora_digests', $subscription);
        }
    }
}

/**
 * Determine the maildigest setting for the specified user against the
 * specified quora.
 *
 * @param Array $digests An array of quoras and user digest settings.
 * @param stdClass $user The user object containing the id and maildigest default.
 * @param int $quoraid The ID of the quora to check.
 * @return int The calculated maildigest setting for this user and quora.
 */
function quora_get_user_maildigest_bulk($digests, $user, $quoraid) {
    if (isset($digests[$quoraid]) && isset($digests[$quoraid][$user->id])) {
        $maildigest = $digests[$quoraid][$user->id];
        if ($maildigest === -1) {
            $maildigest = $user->maildigest;
        }
    } else {
        $maildigest = $user->maildigest;
    }
    return $maildigest;
}

/**
 * Retrieve the list of available user digest options.
 *
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @return array The mapping of values to digest options.
 */
function quora_get_user_digest_options($user = null) {
    global $USER;

    // Revert to the global user object.
    if ($user === null) {
        $user = $USER;
    }

    $digestoptions = array();
    $digestoptions['0']  = get_string('emaildigestoffshort', 'mod_quora');
    $digestoptions['1']  = get_string('emaildigestcompleteshort', 'mod_quora');
    $digestoptions['2']  = get_string('emaildigestsubjectsshort', 'mod_quora');

    // We need to add the default digest option at the end - it relies on
    // the contents of the existing values.
    $digestoptions['-1'] = get_string('emaildigestdefault', 'mod_quora',
            $digestoptions[$user->maildigest]);

    // Resort the options to be in a sensible order.
    ksort($digestoptions);

    return $digestoptions;
}

/**
 * Determine the current context if one was not already specified.
 *
 * If a context of type context_module is specified, it is immediately
 * returned and not checked.
 *
 * @param int $quoraid The ID of the quora
 * @param context_module $context The current context.
 * @return context_module The context determined
 */
function quora_get_context($quoraid, $context = null) {
    global $PAGE;

    if (!$context || !($context instanceof context_module)) {
        // Find out quora context. First try to take current page context to save on DB query.
        if ($PAGE->cm && $PAGE->cm->modname === 'quora' && $PAGE->cm->instance == $quoraid
                && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->context->instanceid == $PAGE->cm->id) {
            $context = $PAGE->context;
        } else {
            $cm = get_coursemodule_from_instance('quora', $quoraid);
            $context = \context_module::instance($cm->id);
        }
    }

    return $context;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $quora   quora object
 * @param  stdClass $course  course object
 * @param  stdClass $cm      course module object
 * @param  stdClass $context context object
 * @since Moodle 2.9
 */
function quora_view($quora, $course, $cm, $context) {

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Trigger course_module_viewed event.

    $params = array(
        'context' => $context,
        'objectid' => $quora->id
    );

    $event = \mod_quora\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('quora', $quora);
    $event->trigger();
}

/**
 * Trigger the discussion viewed event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $quora      quora object
 * @param  stdClass $discussion discussion object
 * @since Moodle 2.9
 */
function quora_discussion_view($modcontext, $quora, $discussion) {
    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
    );

    $event = \mod_quora\event\discussion_viewed::create($params);
    $event->add_record_snapshot('quora_discussions', $discussion);
    $event->add_record_snapshot('quora', $quora);
    $event->trigger();
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function mod_quora_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (isguestuser($user)) {
        // The guest user cannot post, so it is not possible to view any posts.
        // May as well just bail aggressively here.
        return false;
    }
    $postsurl = new moodle_url('/mod/quora/user.php', array('id' => $user->id));
    if (!empty($course)) {
        $postsurl->param('course', $course->id);
    }
    $string = get_string('quoraposts', 'mod_quora');
    $node = new core_user\output\myprofile\node('miscellaneous', 'quoraposts', $string, null, $postsurl);
    $tree->add_node($node);

    $discussionssurl = new moodle_url('/mod/quora/user.php', array('id' => $user->id, 'mode' => 'discussions'));
    if (!empty($course)) {
        $discussionssurl->param('course', $course->id);
    }
    $string = get_string('myprofileotherdis', 'mod_quora');
    $node = new core_user\output\myprofile\node('miscellaneous', 'quoradiscussions', $string, null,
        $discussionssurl);
    $tree->add_node($node);

    return true;
}

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
 * @package   mod_teamworkforum
 * @copyright 2014 Andrew Robert Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Deprecated a very long time ago.

/**
 * How many posts by other users are unrated by a given user in the given discussion?
 *
 * @param int $discussionid
 * @param int $userid
 * @return mixed
 * @deprecated since Moodle 1.1 - please do not use this function any more.
 */
function teamworkforum_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;
    debugging('teamworkforum_count_unrated_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    $sql = "SELECT COUNT(*) as num
              FROM {teamworkforum_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {teamworkforum_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_teamworkforum' AND
                       r.ratingarea = 'post'";
        $rated = $DB->get_record_sql($sql, $params);
        if ($rated) {
            if ($posts->num > $rated->num) {
                return $posts->num - $rated->num;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return $posts->num;
        }
    } else {
        return 0;
    }
}


// Since Moodle 1.5.

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return bool
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function teamworkforum_tp_count_discussion_read_records($userid, $discussionid) {
    debugging('teamworkforum_tp_count_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = isset($CFG->teamworkforum_oldpostdays) ? (time() - ($CFG->teamworkforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM {teamworkforum_discussions} d '.
           'LEFT JOIN {teamworkforum_read} r ON d.id = r.discussionid AND r.userid = ? '.
           'LEFT JOIN {teamworkforum_posts} p ON p.discussion = d.id '.
                'AND (p.modified < ? OR p.id = r.postid) '.
           'WHERE d.id = ? ';

    return ($DB->count_records_sql($sql, array($userid, $cutoffdate, $discussionid)));
}

/**
 * Get all discussions started by a particular user in a course (or group)
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $userid
 * @param int $groupid
 * @return array
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function teamworkforum_get_user_discussions($courseid, $userid, $groupid=0) {
    debugging('teamworkforum_get_user_discussions() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($courseid, $userid);
    if ($groupid) {
        $groupselect = " AND d.groupid = ? ";
        $params[] = $groupid;
    } else  {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.groupid, $allnames, u.email, u.picture, u.imagealt,
                                   f.type as teamworkforumtype, f.name as teamworkforumname, f.id as teamworkforumid
                              FROM {teamworkforum_discussions} d,
                                   {teamworkforum_posts} p,
                                   {user} u,
                                   {teamworkforum} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.teamworkforum = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}


// Since Moodle 1.6.

/**
 * Returns the count of posts for the provided teamworkforum and [optionally] group.
 * @global object
 * @global object
 * @param int $teamworkforumid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function teamworkforum_tp_count_teamworkforum_posts($teamworkforumid, $groupid=false) {
    debugging('teamworkforum_tp_count_teamworkforum_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($teamworkforumid);
    $sql = 'SELECT COUNT(*) '.
           'FROM {teamworkforum_posts} fp,{teamworkforum_discussions} fd '.
           'WHERE fd.teamworkforum = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and teamworkforum and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $teamworkforumid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function teamworkforum_tp_count_teamworkforum_read_records($userid, $teamworkforumid, $groupid=false) {
    debugging('teamworkforum_tp_count_teamworkforum_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->teamworkforum_oldpostdays*24*60*60);

    $groupsel = '';
    $params = array($userid, $teamworkforumid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {teamworkforum_posts} p
                    JOIN {teamworkforum_discussions} d ON d.id = p.discussion
                    LEFT JOIN {teamworkforum_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.teamworkforum = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}


// Since Moodle 1.7.

/**
 * Returns array of teamworkforum open modes.
 *
 * @return array
 * @deprecated since Moodle 1.7 - please do not use this function any more.
 */
function teamworkforum_get_open_modes() {
    debugging('teamworkforum_get_open_modes() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
    return array();
}


// Since Moodle 1.9.

/**
 * Gets posts with all info ready for teamworkforum_print_post
 * We pass teamworkforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $teamworkforumid
 * @return array
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function teamworkforum_get_child_posts($parent, $teamworkforumid) {
    debugging('teamworkforum_get_child_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $teamworkforumid AS teamworkforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {teamworkforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * Gets posts with all info ready for teamworkforum_print_post
 * We pass teamworkforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function teamworkforum_get_discussion_posts($discussion, $sort, $teamworkforumid) {
    debugging('teamworkforum_get_discussion_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $teamworkforumid AS teamworkforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {teamworkforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.discussion = ?
                               AND p.parent > 0 $sort", array($discussion));
}


// Since Moodle 2.0.

/**
 * Returns a list of ratings for a particular post - sorted.
 *
 * @param stdClass $context
 * @param int $postid
 * @param string $sort
 * @return array Array of ratings or false
 * @deprecated since Moodle 2.0 MDL-21657 - please do not use this function any more.
 */
function teamworkforum_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    debugging('teamworkforum_get_ratings() is deprecated.', DEBUG_DEVELOPER);
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_teamworkforum';
    $options->ratingarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm->get_all_ratings_for_item($options);
}

/**
 * Generate and return the track or no track link for a teamworkforum.
 *
 * @global object
 * @global object
 * @global object
 * @param object $teamworkforum the teamworkforum. Fields used are $teamworkforum->id and $teamworkforum->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 * @deprecated since Moodle 2.0 MDL-14632 - please do not use this function any more.
 */
function teamworkforum_get_tracking_link($teamworkforum, $messages=array(), $fakelink=true) {
    debugging('teamworkforum_get_tracking_link() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotrackteamworkforum, $strtrackteamworkforum;

    if (isset($messages['trackteamworkforum'])) {
         $strtrackteamworkforum = $messages['trackteamworkforum'];
    }
    if (isset($messages['notrackteamworkforum'])) {
         $strnotrackteamworkforum = $messages['notrackteamworkforum'];
    }
    if (empty($strtrackteamworkforum)) {
        $strtrackteamworkforum = get_string('trackteamworkforum', 'teamworkforum');
    }
    if (empty($strnotrackteamworkforum)) {
        $strnotrackteamworkforum = get_string('notrackteamworkforum', 'teamworkforum');
    }

    if (teamworkforum_tp_is_tracked($teamworkforum)) {
        $linktitle = $strnotrackteamworkforum;
        $linktext = $strnotrackteamworkforum;
    } else {
        $linktitle = $strtrackteamworkforum;
        $linktext = $strtrackteamworkforum;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/teamworkforum/teamworkforum.js');
        $PAGE->requires->js_function_call('teamworkforum_produce_tracking_link', Array($teamworkforum->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/teamworkforum/settracking.php', array(
            'id' => $teamworkforum->id,
            'sesskey' => sesskey(),
        ));
    $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));

    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return int
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function teamworkforum_tp_count_discussion_unread_posts($userid, $discussionid) {
    debugging('teamworkforum_tp_count_discussion_unread_posts() is deprecated.', DEBUG_DEVELOPER);
    global $CFG, $DB;

    $cutoffdate = isset($CFG->teamworkforum_oldpostdays) ? (time() - ($CFG->teamworkforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM {teamworkforum_posts} p '.
           'LEFT JOIN {teamworkforum_read} r ON r.postid = p.id AND r.userid = ? '.
           'WHERE p.discussion = ? '.
                'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Converts a teamworkforum to use the Roles System
 *
 * @deprecated since Moodle 2.0 MDL-23479 - please do not use this function any more.
 */
function teamworkforum_convert_to_roles() {
    debugging('teamworkforum_convert_to_roles() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
}

/**
 * Returns all records in the 'teamworkforum_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $teamworkforumid
 * @return array
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function teamworkforum_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $teamworkforumid=-1) {
    debugging('teamworkforum_tp_get_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = '';
    $params = array();

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
    if ($teamworkforumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'teamworkforumid = ?';
        $params[] = $teamworkforumid;
    }

    return $DB->get_records_select('teamworkforum_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function teamworkforum_tp_get_discussion_read_records($userid, $discussionid) {
    debugging('teamworkforum_tp_get_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('teamworkforum_read', $select, array($userid, $discussionid), '', $fields);
}

// Deprecated in 2.3.

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated since Moodle 2.3 MDL-33166 - please do not use this function any more.
 * @param stdClass $cp
 * @return void
 */
function teamworkforum_user_enrolled($cp) {
    debugging('teamworkforum_user_enrolled() is deprecated. Please use teamworkforum_user_role_assigned instead.', DEBUG_DEVELOPER);
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/teamworkforum:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {teamworkforum} f
         LEFT JOIN {teamworkforum_subscriptions} fs ON (fs.teamworkforum = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid'=>$cp->courseid, 'userid'=>$cp->userid, 'initial'=>FORUM_INITIALSUBSCRIBE);

    $teamworkforums = $DB->get_records_sql($sql, $params);
    foreach ($teamworkforums as $teamworkforum) {
        \mod_teamworkforum\subscriptions::subscribe_user($cp->userid, $teamworkforum);
    }
}


// Deprecated in 2.4.

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use teamworkforum_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $teamworkforum
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function teamworkforum_user_can_view_post($post, $course, $cm, $teamworkforum, $discussion, $user=null){
    debugging('teamworkforum_user_can_view_post() is deprecated. Please use teamworkforum_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return teamworkforum_user_can_see_post($teamworkforum, $discussion, $post, $user, $cm);
}


// Deprecated in 2.6.

/**
 * FORUM_TRACKING_ON - deprecated alias for FORUM_TRACKING_FORCED.
 * @deprecated since 2.6
 */
define('FORUM_TRACKING_ON', 2);

/**
 * @deprecated since Moodle 2.6
 * @see shorten_text()
 */
function teamworkforum_shorten_post($message) {
    throw new coding_exception('teamworkforum_shorten_post() can not be used any more. Please use shorten_text($message, $CFG->teamworkforum_shortpost) instead.');
}

// Deprecated in 2.8.

/**
 * @global object
 * @param int $userid
 * @param object $teamworkforum
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::is_subscribed() instead
 */
function teamworkforum_is_subscribed($userid, $teamworkforum) {
    global $DB;
    debugging("teamworkforum_is_subscribed() has been deprecated, please use \\mod_teamworkforum\\subscriptions::is_subscribed() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of teamworkforum.
    if (is_numeric($teamworkforum)) {
        $teamworkforum = $DB->get_record('teamworkforum', array('id' => $teamworkforum));
    }

    return mod_teamworkforum\subscriptions::is_subscribed($userid, $teamworkforum);
}

/**
 * Adds user to the subscriber list
 *
 * @param int $userid
 * @param int $teamworkforumid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::subscribe_user() instead
 */
function teamworkforum_subscribe($userid, $teamworkforumid, $context = null, $userrequest = false) {
    global $DB;
    debugging("teamworkforum_subscribe() has been deprecated, please use \\mod_teamworkforum\\subscriptions::subscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of teamworkforum.
    $teamworkforum = $DB->get_record('teamworkforum', array('id' => $teamworkforumid));
    \mod_teamworkforum\subscriptions::subscribe_user($userid, $teamworkforum, $context, $userrequest);
}

/**
 * Removes user from the subscriber list
 *
 * @param int $userid
 * @param int $teamworkforumid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::unsubscribe_user() instead
 */
function teamworkforum_unsubscribe($userid, $teamworkforumid, $context = null, $userrequest = false) {
    global $DB;
    debugging("teamworkforum_unsubscribe() has been deprecated, please use \\mod_teamworkforum\\subscriptions::unsubscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of teamworkforum.
    $teamworkforum = $DB->get_record('teamworkforum', array('id' => $teamworkforumid));
    \mod_teamworkforum\subscriptions::unsubscribe_user($userid, $teamworkforum, $context, $userrequest);
}

/**
 * Returns list of user objects that are subscribed to this teamworkforum.
 *
 * @param stdClass $course the course
 * @param stdClass $teamworkforum the teamworkforum
 * @param int $groupid group id, or 0 for all.
 * @param context_module $context the teamworkforum context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @param boolean $considerdiscussions Whether to take discussion subscriptions and unsubscriptions into consideration.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::fetch_subscribed_users() instead
  */
function teamworkforum_subscribed_users($course, $teamworkforum, $groupid = 0, $context = null, $fields = null) {
    debugging("teamworkforum_subscribed_users() has been deprecated, please use \\mod_teamworkforum\\subscriptions::fetch_subscribed_users() instead.",
            DEBUG_DEVELOPER);

    \mod_teamworkforum\subscriptions::fetch_subscribed_users($teamworkforum, $groupid, $context, $fields);
}

/**
 * Determine whether the teamworkforum is force subscribed.
 *
 * @param object $teamworkforum
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::is_forcesubscribed() instead
 */
function teamworkforum_is_forcesubscribed($teamworkforum) {
    debugging("teamworkforum_is_forcesubscribed() has been deprecated, please use \\mod_teamworkforum\\subscriptions::is_forcesubscribed() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($teamworkforum->forcesubscribe)) {
       $teamworkforum = $DB->get_field('teamworkforum', 'forcesubscribe', array('id' => $teamworkforum));
    }

    return \mod_teamworkforum\subscriptions::is_forcesubscribed($teamworkforum);
}

/**
 * Set the subscription mode for a teamworkforum.
 *
 * @param int $teamworkforumid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::set_subscription_mode() instead
 */
function teamworkforum_forcesubscribe($teamworkforumid, $value = 1) {
    debugging("teamworkforum_forcesubscribe() has been deprecated, please use \\mod_teamworkforum\\subscriptions::set_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    return \mod_teamworkforum\subscriptions::set_subscription_mode($teamworkforumid, $value);
}

/**
 * Get the current subscription mode for the teamworkforum.
 *
 * @param int|stdClass $teamworkforumid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::get_subscription_mode() instead
 */
function teamworkforum_get_forcesubscribed($teamworkforum) {
    debugging("teamworkforum_get_forcesubscribed() has been deprecated, please use \\mod_teamworkforum\\subscriptions::get_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($teamworkforum->forcesubscribe)) {
       $teamworkforum = $DB->get_field('teamworkforum', 'forcesubscribe', array('id' => $teamworkforum));
    }

    return \mod_teamworkforum\subscriptions::get_subscription_mode($teamworkforumid, $value);
}

/**
 * Get a list of teamworkforums in the specified course in which a user can change
 * their subscription preferences.
 *
 * @param stdClass $course The course from which to find subscribable teamworkforums.
 * @return array
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::is_subscribed in combination wtih
 * \mod_teamworkforum\subscriptions::fill_subscription_cache_for_course instead.
 */
function teamworkforum_get_subscribed_teamworkforums($course) {
    debugging("teamworkforum_get_subscribed_teamworkforums() has been deprecated, please see " .
              "\\mod_teamworkforum\\subscriptions::is_subscribed::() " .
              " and \\mod_teamworkforum\\subscriptions::fill_subscription_cache_for_course instead.",
              DEBUG_DEVELOPER);

    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {teamworkforum} f
                   LEFT JOIN {teamworkforum_subscriptions} fs ON (fs.teamworkforum = f.id AND fs.userid = ?)
             WHERE f.course = ?
                   AND f.forcesubscribe <> ".FORUM_DISALLOWSUBSCRIBE."
                   AND (f.forcesubscribe = ".FORUM_FORCESUBSCRIBE." OR fs.id IS NOT NULL)";
    if ($subscribed = $DB->get_records_sql($sql, array($USER->id, $course->id))) {
        foreach ($subscribed as $s) {
            $subscribed[$s->id] = $s->id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Returns an array of teamworkforums that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable teamworkforums
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::get_unsubscribable_teamworkforums() instead
 */
function teamworkforum_get_optional_subscribed_teamworkforums() {
    debugging("teamworkforum_get_optional_subscribed_teamworkforums() has been deprecated, please use \\mod_teamworkforum\\subscriptions::get_unsubscribable_teamworkforums() instead.",
            DEBUG_DEVELOPER);

    return \mod_teamworkforum\subscriptions::get_unsubscribable_teamworkforums();
}

/**
 * Get the list of potential subscribers to a teamworkforum.
 *
 * @param object $teamworkforumcontext the teamworkforum context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_teamworkforum\subscriptions::get_potential_subscribers() instead
 */
function teamworkforum_get_potential_subscribers($teamworkforumcontext, $groupid, $fields, $sort = '') {
    debugging("teamworkforum_get_potential_subscribers() has been deprecated, please use \\mod_teamworkforum\\subscriptions::get_potential_subscribers() instead.",
            DEBUG_DEVELOPER);

    \mod_teamworkforum\subscriptions::get_potential_subscribers($teamworkforumcontext, $groupid, $fields, $sort);
}

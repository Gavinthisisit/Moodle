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
function quora_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;
    debugging('quora_count_unrated_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    $sql = "SELECT COUNT(*) as num
              FROM {quora_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {quora_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_quora' AND
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
function quora_tp_count_discussion_read_records($userid, $discussionid) {
    debugging('quora_tp_count_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = isset($CFG->quora_oldpostdays) ? (time() - ($CFG->quora_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM {quora_discussions} d '.
           'LEFT JOIN {quora_read} r ON d.id = r.discussionid AND r.userid = ? '.
           'LEFT JOIN {quora_posts} p ON p.discussion = d.id '.
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
function quora_get_user_discussions($courseid, $userid, $groupid=0) {
    debugging('quora_get_user_discussions() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

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
                                   f.type as quoratype, f.name as quoraname, f.id as quoraid
                              FROM {quora_discussions} d,
                                   {quora_posts} p,
                                   {user} u,
                                   {quora} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.quora = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}


// Since Moodle 1.6.

/**
 * Returns the count of posts for the provided quora and [optionally] group.
 * @global object
 * @global object
 * @param int $quoraid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function quora_tp_count_quora_posts($quoraid, $groupid=false) {
    debugging('quora_tp_count_quora_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($quoraid);
    $sql = 'SELECT COUNT(*) '.
           'FROM {quora_posts} fp,{quora_discussions} fd '.
           'WHERE fd.quora = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and quora and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $quoraid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function quora_tp_count_quora_read_records($userid, $quoraid, $groupid=false) {
    debugging('quora_tp_count_quora_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->quora_oldpostdays*24*60*60);

    $groupsel = '';
    $params = array($userid, $quoraid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {quora_posts} p
                    JOIN {quora_discussions} d ON d.id = p.discussion
                    LEFT JOIN {quora_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.quora = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}


// Since Moodle 1.7.

/**
 * Returns array of quora open modes.
 *
 * @return array
 * @deprecated since Moodle 1.7 - please do not use this function any more.
 */
function quora_get_open_modes() {
    debugging('quora_get_open_modes() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
    return array();
}


// Since Moodle 1.9.

/**
 * Gets posts with all info ready for quora_print_post
 * We pass quoraid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $quoraid
 * @return array
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function quora_get_child_posts($parent, $quoraid) {
    debugging('quora_get_child_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $quoraid AS quora, $allnames, u.email, u.picture, u.imagealt
                              FROM {quora_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * Gets posts with all info ready for quora_print_post
 * We pass quoraid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function quora_get_discussion_posts($discussion, $sort, $quoraid) {
    debugging('quora_get_discussion_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $quoraid AS quora, $allnames, u.email, u.picture, u.imagealt
                              FROM {quora_posts} p
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
function quora_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    debugging('quora_get_ratings() is deprecated.', DEBUG_DEVELOPER);
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_quora';
    $options->ratingarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm->get_all_ratings_for_item($options);
}

/**
 * Generate and return the track or no track link for a quora.
 *
 * @global object
 * @global object
 * @global object
 * @param object $quora the quora. Fields used are $quora->id and $quora->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 * @deprecated since Moodle 2.0 MDL-14632 - please do not use this function any more.
 */
function quora_get_tracking_link($quora, $messages=array(), $fakelink=true) {
    debugging('quora_get_tracking_link() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotrackquora, $strtrackquora;

    if (isset($messages['trackquora'])) {
         $strtrackquora = $messages['trackquora'];
    }
    if (isset($messages['notrackquora'])) {
         $strnotrackquora = $messages['notrackquora'];
    }
    if (empty($strtrackquora)) {
        $strtrackquora = get_string('trackquora', 'quora');
    }
    if (empty($strnotrackquora)) {
        $strnotrackquora = get_string('notrackquora', 'quora');
    }

    if (quora_tp_is_tracked($quora)) {
        $linktitle = $strnotrackquora;
        $linktext = $strnotrackquora;
    } else {
        $linktitle = $strtrackquora;
        $linktext = $strtrackquora;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/quora/quora.js');
        $PAGE->requires->js_function_call('quora_produce_tracking_link', Array($quora->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/quora/settracking.php', array(
            'id' => $quora->id,
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
function quora_tp_count_discussion_unread_posts($userid, $discussionid) {
    debugging('quora_tp_count_discussion_unread_posts() is deprecated.', DEBUG_DEVELOPER);
    global $CFG, $DB;

    $cutoffdate = isset($CFG->quora_oldpostdays) ? (time() - ($CFG->quora_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM {quora_posts} p '.
           'LEFT JOIN {quora_read} r ON r.postid = p.id AND r.userid = ? '.
           'WHERE p.discussion = ? '.
                'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Converts a quora to use the Roles System
 *
 * @deprecated since Moodle 2.0 MDL-23479 - please do not use this function any more.
 */
function quora_convert_to_roles() {
    debugging('quora_convert_to_roles() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
}

/**
 * Returns all records in the 'quora_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $quoraid
 * @return array
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function quora_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $quoraid=-1) {
    debugging('quora_tp_get_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

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
    if ($quoraid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'quoraid = ?';
        $params[] = $quoraid;
    }

    return $DB->get_records_select('quora_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function quora_tp_get_discussion_read_records($userid, $discussionid) {
    debugging('quora_tp_get_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('quora_read', $select, array($userid, $discussionid), '', $fields);
}

// Deprecated in 2.3.

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated since Moodle 2.3 MDL-33166 - please do not use this function any more.
 * @param stdClass $cp
 * @return void
 */
function quora_user_enrolled($cp) {
    debugging('quora_user_enrolled() is deprecated. Please use quora_user_role_assigned instead.', DEBUG_DEVELOPER);
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/quora:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {quora} f
         LEFT JOIN {quora_subscriptions} fs ON (fs.quora = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid'=>$cp->courseid, 'userid'=>$cp->userid, 'initial'=>FORUM_INITIALSUBSCRIBE);

    $quoras = $DB->get_records_sql($sql, $params);
    foreach ($quoras as $quora) {
        \mod_quora\subscriptions::subscribe_user($cp->userid, $quora);
    }
}


// Deprecated in 2.4.

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use quora_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $quora
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function quora_user_can_view_post($post, $course, $cm, $quora, $discussion, $user=null){
    debugging('quora_user_can_view_post() is deprecated. Please use quora_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return quora_user_can_see_post($quora, $discussion, $post, $user, $cm);
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
function quora_shorten_post($message) {
    throw new coding_exception('quora_shorten_post() can not be used any more. Please use shorten_text($message, $CFG->quora_shortpost) instead.');
}

// Deprecated in 2.8.

/**
 * @global object
 * @param int $userid
 * @param object $quora
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::is_subscribed() instead
 */
function quora_is_subscribed($userid, $quora) {
    global $DB;
    debugging("quora_is_subscribed() has been deprecated, please use \\mod_quora\\subscriptions::is_subscribed() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of quora.
    if (is_numeric($quora)) {
        $quora = $DB->get_record('quora', array('id' => $quora));
    }

    return mod_quora\subscriptions::is_subscribed($userid, $quora);
}

/**
 * Adds user to the subscriber list
 *
 * @param int $userid
 * @param int $quoraid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::subscribe_user() instead
 */
function quora_subscribe($userid, $quoraid, $context = null, $userrequest = false) {
    global $DB;
    debugging("quora_subscribe() has been deprecated, please use \\mod_quora\\subscriptions::subscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of quora.
    $quora = $DB->get_record('quora', array('id' => $quoraid));
    \mod_quora\subscriptions::subscribe_user($userid, $quora, $context, $userrequest);
}

/**
 * Removes user from the subscriber list
 *
 * @param int $userid
 * @param int $quoraid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::unsubscribe_user() instead
 */
function quora_unsubscribe($userid, $quoraid, $context = null, $userrequest = false) {
    global $DB;
    debugging("quora_unsubscribe() has been deprecated, please use \\mod_quora\\subscriptions::unsubscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of quora.
    $quora = $DB->get_record('quora', array('id' => $quoraid));
    \mod_quora\subscriptions::unsubscribe_user($userid, $quora, $context, $userrequest);
}

/**
 * Returns list of user objects that are subscribed to this quora.
 *
 * @param stdClass $course the course
 * @param stdClass $quora the quora
 * @param int $groupid group id, or 0 for all.
 * @param context_module $context the quora context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @param boolean $considerdiscussions Whether to take discussion subscriptions and unsubscriptions into consideration.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::fetch_subscribed_users() instead
  */
function quora_subscribed_users($course, $quora, $groupid = 0, $context = null, $fields = null) {
    debugging("quora_subscribed_users() has been deprecated, please use \\mod_quora\\subscriptions::fetch_subscribed_users() instead.",
            DEBUG_DEVELOPER);

    \mod_quora\subscriptions::fetch_subscribed_users($quora, $groupid, $context, $fields);
}

/**
 * Determine whether the quora is force subscribed.
 *
 * @param object $quora
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::is_forcesubscribed() instead
 */
function quora_is_forcesubscribed($quora) {
    debugging("quora_is_forcesubscribed() has been deprecated, please use \\mod_quora\\subscriptions::is_forcesubscribed() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($quora->forcesubscribe)) {
       $quora = $DB->get_field('quora', 'forcesubscribe', array('id' => $quora));
    }

    return \mod_quora\subscriptions::is_forcesubscribed($quora);
}

/**
 * Set the subscription mode for a quora.
 *
 * @param int $quoraid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::set_subscription_mode() instead
 */
function quora_forcesubscribe($quoraid, $value = 1) {
    debugging("quora_forcesubscribe() has been deprecated, please use \\mod_quora\\subscriptions::set_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    return \mod_quora\subscriptions::set_subscription_mode($quoraid, $value);
}

/**
 * Get the current subscription mode for the quora.
 *
 * @param int|stdClass $quoraid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::get_subscription_mode() instead
 */
function quora_get_forcesubscribed($quora) {
    debugging("quora_get_forcesubscribed() has been deprecated, please use \\mod_quora\\subscriptions::get_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($quora->forcesubscribe)) {
       $quora = $DB->get_field('quora', 'forcesubscribe', array('id' => $quora));
    }

    return \mod_quora\subscriptions::get_subscription_mode($quoraid, $value);
}

/**
 * Get a list of quoras in the specified course in which a user can change
 * their subscription preferences.
 *
 * @param stdClass $course The course from which to find subscribable quoras.
 * @return array
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::is_subscribed in combination wtih
 * \mod_quora\subscriptions::fill_subscription_cache_for_course instead.
 */
function quora_get_subscribed_quoras($course) {
    debugging("quora_get_subscribed_quoras() has been deprecated, please see " .
              "\\mod_quora\\subscriptions::is_subscribed::() " .
              " and \\mod_quora\\subscriptions::fill_subscription_cache_for_course instead.",
              DEBUG_DEVELOPER);

    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {quora} f
                   LEFT JOIN {quora_subscriptions} fs ON (fs.quora = f.id AND fs.userid = ?)
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
 * Returns an array of quoras that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable quoras
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::get_unsubscribable_quoras() instead
 */
function quora_get_optional_subscribed_quoras() {
    debugging("quora_get_optional_subscribed_quoras() has been deprecated, please use \\mod_quora\\subscriptions::get_unsubscribable_quoras() instead.",
            DEBUG_DEVELOPER);

    return \mod_quora\subscriptions::get_unsubscribable_quoras();
}

/**
 * Get the list of potential subscribers to a quora.
 *
 * @param object $quoracontext the quora context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_quora\subscriptions::get_potential_subscribers() instead
 */
function quora_get_potential_subscribers($quoracontext, $groupid, $fields, $sort = '') {
    debugging("quora_get_potential_subscribers() has been deprecated, please use \\mod_quora\\subscriptions::get_potential_subscribers() instead.",
            DEBUG_DEVELOPER);

    \mod_quora\subscriptions::get_potential_subscribers($quoracontext, $groupid, $fields, $sort);
}

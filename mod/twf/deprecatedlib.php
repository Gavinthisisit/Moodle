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
 * @package   mod_twf
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
function twf_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;
    debugging('twf_count_unrated_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    $sql = "SELECT COUNT(*) as num
              FROM {twf_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {twf_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_twf' AND
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
function twf_tp_count_discussion_read_records($userid, $discussionid) {
    debugging('twf_tp_count_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = isset($CFG->twf_oldpostdays) ? (time() - ($CFG->twf_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM {twf_discussions} d '.
           'LEFT JOIN {twf_read} r ON d.id = r.discussionid AND r.userid = ? '.
           'LEFT JOIN {twf_posts} p ON p.discussion = d.id '.
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
function twf_get_user_discussions($courseid, $userid, $groupid=0) {
    debugging('twf_get_user_discussions() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

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
                                   f.type as twftype, f.name as twfname, f.id as twfid
                              FROM {twf_discussions} d,
                                   {twf_posts} p,
                                   {user} u,
                                   {twf} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.twf = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}


// Since Moodle 1.6.

/**
 * Returns the count of posts for the provided twf and [optionally] group.
 * @global object
 * @global object
 * @param int $twfid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function twf_tp_count_twf_posts($twfid, $groupid=false) {
    debugging('twf_tp_count_twf_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($twfid);
    $sql = 'SELECT COUNT(*) '.
           'FROM {twf_posts} fp,{twf_discussions} fd '.
           'WHERE fd.twf = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and twf and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $twfid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function twf_tp_count_twf_read_records($userid, $twfid, $groupid=false) {
    debugging('twf_tp_count_twf_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->twf_oldpostdays*24*60*60);

    $groupsel = '';
    $params = array($userid, $twfid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {twf_posts} p
                    JOIN {twf_discussions} d ON d.id = p.discussion
                    LEFT JOIN {twf_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.twf = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}


// Since Moodle 1.7.

/**
 * Returns array of twf open modes.
 *
 * @return array
 * @deprecated since Moodle 1.7 - please do not use this function any more.
 */
function twf_get_open_modes() {
    debugging('twf_get_open_modes() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
    return array();
}


// Since Moodle 1.9.

/**
 * Gets posts with all info ready for twf_print_post
 * We pass twfid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $twfid
 * @return array
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function twf_get_child_posts($parent, $twfid) {
    debugging('twf_get_child_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $twfid AS twf, $allnames, u.email, u.picture, u.imagealt
                              FROM {twf_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * Gets posts with all info ready for twf_print_post
 * We pass twfid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function twf_get_discussion_posts($discussion, $sort, $twfid) {
    debugging('twf_get_discussion_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $twfid AS twf, $allnames, u.email, u.picture, u.imagealt
                              FROM {twf_posts} p
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
function twf_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    debugging('twf_get_ratings() is deprecated.', DEBUG_DEVELOPER);
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_twf';
    $options->ratingarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm->get_all_ratings_for_item($options);
}

/**
 * Generate and return the track or no track link for a twf.
 *
 * @global object
 * @global object
 * @global object
 * @param object $twf the twf. Fields used are $twf->id and $twf->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 * @deprecated since Moodle 2.0 MDL-14632 - please do not use this function any more.
 */
function twf_get_tracking_link($twf, $messages=array(), $fakelink=true) {
    debugging('twf_get_tracking_link() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotracktwf, $strtracktwf;

    if (isset($messages['tracktwf'])) {
         $strtracktwf = $messages['tracktwf'];
    }
    if (isset($messages['notracktwf'])) {
         $strnotracktwf = $messages['notracktwf'];
    }
    if (empty($strtracktwf)) {
        $strtracktwf = get_string('tracktwf', 'twf');
    }
    if (empty($strnotracktwf)) {
        $strnotracktwf = get_string('notracktwf', 'twf');
    }

    if (twf_tp_is_tracked($twf)) {
        $linktitle = $strnotracktwf;
        $linktext = $strnotracktwf;
    } else {
        $linktitle = $strtracktwf;
        $linktext = $strtracktwf;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/twf/twf.js');
        $PAGE->requires->js_function_call('twf_produce_tracking_link', Array($twf->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/twf/settracking.php', array(
            'id' => $twf->id,
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
function twf_tp_count_discussion_unread_posts($userid, $discussionid) {
    debugging('twf_tp_count_discussion_unread_posts() is deprecated.', DEBUG_DEVELOPER);
    global $CFG, $DB;

    $cutoffdate = isset($CFG->twf_oldpostdays) ? (time() - ($CFG->twf_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM {twf_posts} p '.
           'LEFT JOIN {twf_read} r ON r.postid = p.id AND r.userid = ? '.
           'WHERE p.discussion = ? '.
                'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Converts a twf to use the Roles System
 *
 * @deprecated since Moodle 2.0 MDL-23479 - please do not use this function any more.
 */
function twf_convert_to_roles() {
    debugging('twf_convert_to_roles() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
}

/**
 * Returns all records in the 'twf_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $twfid
 * @return array
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function twf_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $twfid=-1) {
    debugging('twf_tp_get_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

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
    if ($twfid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'twfid = ?';
        $params[] = $twfid;
    }

    return $DB->get_records_select('twf_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function twf_tp_get_discussion_read_records($userid, $discussionid) {
    debugging('twf_tp_get_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('twf_read', $select, array($userid, $discussionid), '', $fields);
}

// Deprecated in 2.3.

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated since Moodle 2.3 MDL-33166 - please do not use this function any more.
 * @param stdClass $cp
 * @return void
 */
function twf_user_enrolled($cp) {
    debugging('twf_user_enrolled() is deprecated. Please use twf_user_role_assigned instead.', DEBUG_DEVELOPER);
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/twf:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {twf} f
         LEFT JOIN {twf_subscriptions} fs ON (fs.twf = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid'=>$cp->courseid, 'userid'=>$cp->userid, 'initial'=>FORUM_INITIALSUBSCRIBE);

    $twfs = $DB->get_records_sql($sql, $params);
    foreach ($twfs as $twf) {
        \mod_twf\subscriptions::subscribe_user($cp->userid, $twf);
    }
}


// Deprecated in 2.4.

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use twf_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $twf
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function twf_user_can_view_post($post, $course, $cm, $twf, $discussion, $user=null){
    debugging('twf_user_can_view_post() is deprecated. Please use twf_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return twf_user_can_see_post($twf, $discussion, $post, $user, $cm);
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
function twf_shorten_post($message) {
    throw new coding_exception('twf_shorten_post() can not be used any more. Please use shorten_text($message, $CFG->twf_shortpost) instead.');
}

// Deprecated in 2.8.

/**
 * @global object
 * @param int $userid
 * @param object $twf
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::is_subscribed() instead
 */
function twf_is_subscribed($userid, $twf) {
    global $DB;
    debugging("twf_is_subscribed() has been deprecated, please use \\mod_twf\\subscriptions::is_subscribed() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of twf.
    if (is_numeric($twf)) {
        $twf = $DB->get_record('twf', array('id' => $twf));
    }

    return mod_twf\subscriptions::is_subscribed($userid, $twf);
}

/**
 * Adds user to the subscriber list
 *
 * @param int $userid
 * @param int $twfid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::subscribe_user() instead
 */
function twf_subscribe($userid, $twfid, $context = null, $userrequest = false) {
    global $DB;
    debugging("twf_subscribe() has been deprecated, please use \\mod_twf\\subscriptions::subscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of twf.
    $twf = $DB->get_record('twf', array('id' => $twfid));
    \mod_twf\subscriptions::subscribe_user($userid, $twf, $context, $userrequest);
}

/**
 * Removes user from the subscriber list
 *
 * @param int $userid
 * @param int $twfid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::unsubscribe_user() instead
 */
function twf_unsubscribe($userid, $twfid, $context = null, $userrequest = false) {
    global $DB;
    debugging("twf_unsubscribe() has been deprecated, please use \\mod_twf\\subscriptions::unsubscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of twf.
    $twf = $DB->get_record('twf', array('id' => $twfid));
    \mod_twf\subscriptions::unsubscribe_user($userid, $twf, $context, $userrequest);
}

/**
 * Returns list of user objects that are subscribed to this twf.
 *
 * @param stdClass $course the course
 * @param stdClass $twf the twf
 * @param int $groupid group id, or 0 for all.
 * @param context_module $context the twf context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @param boolean $considerdiscussions Whether to take discussion subscriptions and unsubscriptions into consideration.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::fetch_subscribed_users() instead
  */
function twf_subscribed_users($course, $twf, $groupid = 0, $context = null, $fields = null) {
    debugging("twf_subscribed_users() has been deprecated, please use \\mod_twf\\subscriptions::fetch_subscribed_users() instead.",
            DEBUG_DEVELOPER);

    \mod_twf\subscriptions::fetch_subscribed_users($twf, $groupid, $context, $fields);
}

/**
 * Determine whether the twf is force subscribed.
 *
 * @param object $twf
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::is_forcesubscribed() instead
 */
function twf_is_forcesubscribed($twf) {
    debugging("twf_is_forcesubscribed() has been deprecated, please use \\mod_twf\\subscriptions::is_forcesubscribed() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($twf->forcesubscribe)) {
       $twf = $DB->get_field('twf', 'forcesubscribe', array('id' => $twf));
    }

    return \mod_twf\subscriptions::is_forcesubscribed($twf);
}

/**
 * Set the subscription mode for a twf.
 *
 * @param int $twfid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::set_subscription_mode() instead
 */
function twf_forcesubscribe($twfid, $value = 1) {
    debugging("twf_forcesubscribe() has been deprecated, please use \\mod_twf\\subscriptions::set_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    return \mod_twf\subscriptions::set_subscription_mode($twfid, $value);
}

/**
 * Get the current subscription mode for the twf.
 *
 * @param int|stdClass $twfid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::get_subscription_mode() instead
 */
function twf_get_forcesubscribed($twf) {
    debugging("twf_get_forcesubscribed() has been deprecated, please use \\mod_twf\\subscriptions::get_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($twf->forcesubscribe)) {
       $twf = $DB->get_field('twf', 'forcesubscribe', array('id' => $twf));
    }

    return \mod_twf\subscriptions::get_subscription_mode($twfid, $value);
}

/**
 * Get a list of twfs in the specified course in which a user can change
 * their subscription preferences.
 *
 * @param stdClass $course The course from which to find subscribable twfs.
 * @return array
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::is_subscribed in combination wtih
 * \mod_twf\subscriptions::fill_subscription_cache_for_course instead.
 */
function twf_get_subscribed_twfs($course) {
    debugging("twf_get_subscribed_twfs() has been deprecated, please see " .
              "\\mod_twf\\subscriptions::is_subscribed::() " .
              " and \\mod_twf\\subscriptions::fill_subscription_cache_for_course instead.",
              DEBUG_DEVELOPER);

    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {twf} f
                   LEFT JOIN {twf_subscriptions} fs ON (fs.twf = f.id AND fs.userid = ?)
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
 * Returns an array of twfs that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable twfs
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::get_unsubscribable_twfs() instead
 */
function twf_get_optional_subscribed_twfs() {
    debugging("twf_get_optional_subscribed_twfs() has been deprecated, please use \\mod_twf\\subscriptions::get_unsubscribable_twfs() instead.",
            DEBUG_DEVELOPER);

    return \mod_twf\subscriptions::get_unsubscribable_twfs();
}

/**
 * Get the list of potential subscribers to a twf.
 *
 * @param object $twfcontext the twf context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_twf\subscriptions::get_potential_subscribers() instead
 */
function twf_get_potential_subscribers($twfcontext, $groupid, $fields, $sort = '') {
    debugging("twf_get_potential_subscribers() has been deprecated, please use \\mod_twf\\subscriptions::get_potential_subscribers() instead.",
            DEBUG_DEVELOPER);

    \mod_twf\subscriptions::get_potential_subscribers($twfcontext, $groupid, $fields, $sort);
}

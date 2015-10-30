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
 * Forum subscription manager.
 *
 * @package    mod_teamworkforum
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teamworkforum;

defined('MOODLE_INTERNAL') || die();

/**
 * Forum subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const FORUM_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for teamworkforums.
     *
     * The first level key is the user ID
     * The second level is the teamworkforum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $teamworkforumcache = array();

    /**
     * The list of teamworkforums which have been wholly retrieved for the teamworkforum subscription cache.
     *
     * This allows for prior caching of an entire teamworkforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedteamworkforums = array();

    /**
     * The subscription cache for teamworkforum discussions.
     *
     * The first level key is the user ID
     * The second level is the teamworkforum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $teamworkforumdiscussioncache = array();

    /**
     * The list of teamworkforums which have been wholly retrieved for the teamworkforum discussion subscription cache.
     *
     * This allows for prior caching of an entire teamworkforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedteamworkforums = array();

    /**
     * Whether a user is subscribed to this teamworkforum, or a discussion within
     * the teamworkforum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the teamworkforum preference.
     *
     * If it is not specified then only the teamworkforum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $teamworkforum The record of the teamworkforum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $teamworkforum, $discussionid = null, $cm = null) {
        // If teamworkforum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($teamworkforum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($teamworkforum->course)->instances['teamworkforum'][$teamworkforum->id];
            }
            if (has_capability('mod/teamworkforum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_teamworkforum($userid, $teamworkforum);
        }

        $subscriptions = self::fetch_discussion_subscription($teamworkforum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::FORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_teamworkforum($userid, $teamworkforum);
    }

    /**
     * Whether a user is subscribed to this teamworkforum.
     *
     * @param int $userid The user ID
     * @param \stdClass $teamworkforum The record of the teamworkforum to test
     * @return boolean
     */
    protected static function is_subscribed_to_teamworkforum($userid, $teamworkforum) {
        return self::fetch_subscription_cache($teamworkforum->id, $userid);
    }

    /**
     * Helper to determine whether a teamworkforum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $teamworkforum The record of the teamworkforum to test
     * @return bool
     */
    public static function is_forcesubscribed($teamworkforum) {
        return ($teamworkforum->forcesubscribe == FORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a teamworkforum has it's subscription mode set to disabled.
     *
     * @param \stdClass $teamworkforum The record of the teamworkforum to test
     * @return bool
     */
    public static function subscription_disabled($teamworkforum) {
        return ($teamworkforum->forcesubscribe == FORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified teamworkforum can be subscribed to.
     *
     * @param \stdClass $teamworkforum The record of the teamworkforum to test
     * @return bool
     */
    public static function is_subscribable($teamworkforum) {
        return (!\mod_teamworkforum\subscriptions::is_forcesubscribed($teamworkforum) &&
                !\mod_teamworkforum\subscriptions::subscription_disabled($teamworkforum));
    }

    /**
     * Set the teamworkforum subscription mode.
     *
     * By default when called without options, this is set to FORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $teamworkforum The record of the teamworkforum to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($teamworkforumid, $status = 1) {
        global $DB;
        return $DB->set_field("teamworkforum", "forcesubscribe", $status, array("id" => $teamworkforumid));
    }

    /**
     * Returns the current subscription mode for the teamworkforum.
     *
     * @param \stdClass $teamworkforum The record of the teamworkforum to set
     * @return int The teamworkforum subscription mode
     */
    public static function get_subscription_mode($teamworkforum) {
        return $teamworkforum->forcesubscribe;
    }

    /**
     * Returns an array of teamworkforums that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable teamworkforums
     */
    public static function get_unsubscribable_teamworkforums() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all teamworkforums from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a teamworkforum in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {teamworkforum} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {teamworkforum_subscriptions} fs ON (fs.teamworkforum = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'teamworkforum',
            'userid' => $USER->id,
            'forcesubscribe' => FORUM_FORCESUBSCRIBE,
        ));
        $teamworkforums = $DB->get_recordset_sql($sql, $params);

        $unsubscribableteamworkforums = array();
        foreach($teamworkforums as $teamworkforum) {
            if (empty($teamworkforum->visible)) {
                // The teamworkforum is hidden - check if the user can view the teamworkforum.
                $context = \context_module::instance($teamworkforum->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden teamworkforum to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribableteamworkforums[] = $teamworkforum;
        }
        $teamworkforums->close();

        return $unsubscribableteamworkforums;
    }

    /**
     * Get the list of potential subscribers to a teamworkforum.
     *
     * @param context_module $context the teamworkforum context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/teamworkforum:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the teamworkforum subscription data for the specified userid and teamworkforum.
     *
     * @param int $teamworkforumid The teamworkforum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($teamworkforumid, $userid) {
        if (isset(self::$teamworkforumcache[$userid]) && isset(self::$teamworkforumcache[$userid][$teamworkforumid])) {
            return self::$teamworkforumcache[$userid][$teamworkforumid];
        }
        self::fill_subscription_cache($teamworkforumid, $userid);

        if (!isset(self::$teamworkforumcache[$userid]) || !isset(self::$teamworkforumcache[$userid][$teamworkforumid])) {
            return false;
        }

        return self::$teamworkforumcache[$userid][$teamworkforumid];
    }

    /**
     * Fill the teamworkforum subscription data for the specified userid and teamworkforum.
     *
     * If the userid is not specified, then all subscription data for that teamworkforum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $teamworkforumid The teamworkforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($teamworkforumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedteamworkforums[$teamworkforumid])) {
            // This teamworkforum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$teamworkforumcache[$userid])) {
                    self::$teamworkforumcache[$userid] = array();
                }

                if (!isset(self::$teamworkforumcache[$userid][$teamworkforumid])) {
                    if ($DB->record_exists('teamworkforum_subscriptions', array(
                        'userid' => $userid,
                        'teamworkforum' => $teamworkforumid,
                    ))) {
                        self::$teamworkforumcache[$userid][$teamworkforumid] = true;
                    } else {
                        self::$teamworkforumcache[$userid][$teamworkforumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('teamworkforum_subscriptions', array(
                    'teamworkforum' => $teamworkforumid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$teamworkforumcache[$data->userid])) {
                        self::$teamworkforumcache[$data->userid] = array();
                    }
                    self::$teamworkforumcache[$data->userid][$teamworkforumid] = true;
                }
                self::$fetchedteamworkforums[$teamworkforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the teamworkforum subscription data for all teamworkforums that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$teamworkforumcache[$userid])) {
            self::$teamworkforumcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS teamworkforumid,
                    s.id AS subscriptionid
                FROM {teamworkforum} f
                LEFT JOIN {teamworkforum_subscriptions} s ON (s.teamworkforum = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => FORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$teamworkforumcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this teamworkforum.
     *
     * @param stdClass $teamworkforum The teamworkforum record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the teamworkforum context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($teamworkforum, $groupid = 0, $context = null, $fields = null,
            $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields ="u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackteamworkforums,
                      u.mnethostid";
        }

        // Retrieve the teamworkforum context if it wasn't specified.
        $context = teamworkforum_get_context($teamworkforum->id, $context);

        if (self::is_forcesubscribed($teamworkforum)) {
            $results = \mod_teamworkforum\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['teamworkforumid'] = $teamworkforum->id;

            if ($includediscussionsubscriptions) {
                $params['steamworkforumid'] = $teamworkforum->id;
                $params['dsteamworkforumid'] = $teamworkforum->id;
                $params['unsubscribed'] = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {teamworkforum_subscriptions} s
                            WHERE
                                s.teamworkforum = :steamworkforumid
                                UNION
                            SELECT userid FROM {teamworkforum_discussion_subs} ds
                            WHERE
                                ds.teamworkforum = :dsteamworkforumid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {teamworkforum_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.teamworkforum = :teamworkforumid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a teamworkforum.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('teamworkforum', $teamworkforum->id, $teamworkforum->course);
        $modinfo = get_fast_modinfo($teamworkforum->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and teamworkforum.
     *
     * This is returned as an array of discussions for that teamworkforum which contain the preference in a stdClass.
     *
     * @param int $teamworkforumid The teamworkforum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the teamworkforum.
     */
    public static function fetch_discussion_subscription($teamworkforumid, $userid = null) {
        self::fill_discussion_subscription_cache($teamworkforumid, $userid);

        if (!isset(self::$teamworkforumdiscussioncache[$userid]) || !isset(self::$teamworkforumdiscussioncache[$userid][$teamworkforumid])) {
            return array();
        }

        return self::$teamworkforumdiscussioncache[$userid][$teamworkforumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and teamworkforum.
     *
     * If the userid is not specified, then all discussion subscription data for that teamworkforum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $teamworkforumid The teamworkforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($teamworkforumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedteamworkforums[$teamworkforumid])) {
            // This teamworkforum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$teamworkforumdiscussioncache[$userid])) {
                    self::$teamworkforumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$teamworkforumdiscussioncache[$userid][$teamworkforumid])) {
                    $subscriptions = $DB->get_recordset('teamworkforum_discussion_subs', array(
                        'userid' => $userid,
                        'teamworkforum' => $teamworkforumid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($teamworkforumid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('teamworkforum_discussion_subs', array(
                    'teamworkforum' => $teamworkforumid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($teamworkforumid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedteamworkforums[$teamworkforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $teamworkforumid The ID of the teamworkforum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($teamworkforumid, $userid, $discussion, $preference) {
        if (!isset(self::$teamworkforumdiscussioncache[$userid])) {
            self::$teamworkforumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$teamworkforumdiscussioncache[$userid][$teamworkforumid])) {
            self::$teamworkforumdiscussioncache[$userid][$teamworkforumid] = array();
        }

        self::$teamworkforumdiscussioncache[$userid][$teamworkforumid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking teamworkforum discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$teamworkforumdiscussioncache = array();
        self::$discussionfetchedteamworkforums = array();
    }

    /**
     * Reset the teamworkforum cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking teamworkforum subscription states.
     */
    public static function reset_teamworkforum_cache() {
        self::$teamworkforumcache = array();
        self::$fetchedteamworkforums = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $teamworkforum The teamworkforum record for this teamworkforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the teamworkforum_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $teamworkforum, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $teamworkforum)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->teamworkforum = $teamworkforum->id;

        $result = $DB->insert_record("teamworkforum_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('teamworkforum_discussion_subs', array('userid' => $userid, 'teamworkforum' => $teamworkforum->id));
            $DB->delete_records_select('teamworkforum_discussion_subs',
                    'userid = :userid AND teamworkforum = :teamworkforumid AND preference <> :preference', array(
                        'userid' => $userid,
                        'teamworkforumid' => $teamworkforum->id,
                        'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this teamworkforum.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$teamworkforumdiscussioncache[$userid]) && isset(self::$teamworkforumdiscussioncache[$userid][$teamworkforum->id])) {
                foreach (self::$teamworkforumdiscussioncache[$userid][$teamworkforum->id] as $discussionid => $preference) {
                    if ($preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$teamworkforumdiscussioncache[$userid][$teamworkforum->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this teamworkforum.
        self::$teamworkforumcache[$userid][$teamworkforum->id] = true;

        $context = teamworkforum_get_context($teamworkforum->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('teamworkforumid' => $teamworkforum->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('teamworkforum_discussion_subs', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $teamworkforum The teamworkforum record for this teamworkforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $teamworkforum, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'teamworkforum' => $teamworkforum->id,
        );
        $DB->delete_records('teamworkforum_digests', $sqlparams);

        if ($teamworkforumsubscription = $DB->get_record('teamworkforum_subscriptions', $sqlparams)) {
            $DB->delete_records('teamworkforum_subscriptions', array('id' => $teamworkforumsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('teamworkforum_discussion_subs', $sqlparams);
                $DB->delete_records('teamworkforum_discussion_subs',
                        array('userid' => $userid, 'teamworkforum' => $teamworkforum->id, 'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$teamworkforumdiscussioncache[$userid]) && isset(self::$teamworkforumdiscussioncache[$userid][$teamworkforum->id])) {
                    self::$teamworkforumdiscussioncache[$userid][$teamworkforum->id] = array();
                }
            }

            // Reset the cache for this teamworkforum.
            self::$teamworkforumcache[$userid][$teamworkforum->id] = false;

            $context = teamworkforum_get_context($teamworkforum->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $teamworkforumsubscription->id,
                'relateduserid' => $userid,
                'other' => array('teamworkforumid' => $teamworkforum->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('teamworkforum_subscriptions', $teamworkforumsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('teamworkforum_discussion_subs', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('teamworkforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a teamworkforum level subscription.
        if ($DB->record_exists('teamworkforum_subscriptions', array('userid' => $userid, 'teamworkforum' => $discussion->teamworkforum))) {
            if ($subscription && $subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the teamworkforum, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('teamworkforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$teamworkforumdiscussioncache[$userid][$discussion->teamworkforum][$discussion->id]);
            } else {
                // The user is already subscribed to the teamworkforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('teamworkforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->teamworkforum = $discussion->teamworkforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('teamworkforum_discussion_subs', $subscription);
                self::$teamworkforumdiscussioncache[$userid][$discussion->teamworkforum][$discussion->id] = $subscription->preference;
            }
        }

        $context = teamworkforum_get_context($discussion->teamworkforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'teamworkforumid' => $discussion->teamworkforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }
    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        $subscription = $DB->get_record('teamworkforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a teamworkforum level subscription.
        if (!$DB->record_exists('teamworkforum_subscriptions', array('userid' => $userid, 'teamworkforum' => $discussion->teamworkforum))) {
            if ($subscription && $subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the teamworkforum, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('teamworkforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$teamworkforumdiscussioncache[$userid][$discussion->teamworkforum][$discussion->id]);
            } else {
                // The user is not subscribed from the teamworkforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('teamworkforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->teamworkforum = $discussion->teamworkforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('teamworkforum_discussion_subs', $subscription);
            }
            self::$teamworkforumdiscussioncache[$userid][$discussion->teamworkforum][$discussion->id] = $subscription->preference;
        }

        $context = teamworkforum_get_context($discussion->teamworkforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'teamworkforumid' => $discussion->teamworkforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}

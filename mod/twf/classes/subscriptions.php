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
 * @package    mod_twf
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_twf;

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
     * The subscription cache for twfs.
     *
     * The first level key is the user ID
     * The second level is the twf ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $twfcache = array();

    /**
     * The list of twfs which have been wholly retrieved for the twf subscription cache.
     *
     * This allows for prior caching of an entire twf to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedtwfs = array();

    /**
     * The subscription cache for twf discussions.
     *
     * The first level key is the user ID
     * The second level is the twf ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $twfdiscussioncache = array();

    /**
     * The list of twfs which have been wholly retrieved for the twf discussion subscription cache.
     *
     * This allows for prior caching of an entire twf to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedtwfs = array();

    /**
     * Whether a user is subscribed to this twf, or a discussion within
     * the twf.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the twf preference.
     *
     * If it is not specified then only the twf preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $twf The record of the twf to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $twf, $discussionid = null, $cm = null) {
        // If twf is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($twf)) {
            if (!$cm) {
                $cm = get_fast_modinfo($twf->course)->instances['twf'][$twf->id];
            }
            if (has_capability('mod/twf:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_twf($userid, $twf);
        }

        $subscriptions = self::fetch_discussion_subscription($twf->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::FORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_twf($userid, $twf);
    }

    /**
     * Whether a user is subscribed to this twf.
     *
     * @param int $userid The user ID
     * @param \stdClass $twf The record of the twf to test
     * @return boolean
     */
    protected static function is_subscribed_to_twf($userid, $twf) {
        return self::fetch_subscription_cache($twf->id, $userid);
    }

    /**
     * Helper to determine whether a twf has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $twf The record of the twf to test
     * @return bool
     */
    public static function is_forcesubscribed($twf) {
        return ($twf->forcesubscribe == FORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a twf has it's subscription mode set to disabled.
     *
     * @param \stdClass $twf The record of the twf to test
     * @return bool
     */
    public static function subscription_disabled($twf) {
        return ($twf->forcesubscribe == FORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified twf can be subscribed to.
     *
     * @param \stdClass $twf The record of the twf to test
     * @return bool
     */
    public static function is_subscribable($twf) {
        return (!\mod_twf\subscriptions::is_forcesubscribed($twf) &&
                !\mod_twf\subscriptions::subscription_disabled($twf));
    }

    /**
     * Set the twf subscription mode.
     *
     * By default when called without options, this is set to FORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $twf The record of the twf to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($twfid, $status = 1) {
        global $DB;
        return $DB->set_field("twf", "forcesubscribe", $status, array("id" => $twfid));
    }

    /**
     * Returns the current subscription mode for the twf.
     *
     * @param \stdClass $twf The record of the twf to set
     * @return int The twf subscription mode
     */
    public static function get_subscription_mode($twf) {
        return $twf->forcesubscribe;
    }

    /**
     * Returns an array of twfs that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable twfs
     */
    public static function get_unsubscribable_twfs() {
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

        // Get all twfs from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a twf in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {twf} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {twf_subscriptions} fs ON (fs.twf = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'twf',
            'userid' => $USER->id,
            'forcesubscribe' => FORUM_FORCESUBSCRIBE,
        ));
        $twfs = $DB->get_recordset_sql($sql, $params);

        $unsubscribabletwfs = array();
        foreach($twfs as $twf) {
            if (empty($twf->visible)) {
                // The twf is hidden - check if the user can view the twf.
                $context = \context_module::instance($twf->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden twf to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribabletwfs[] = $twf;
        }
        $twfs->close();

        return $unsubscribabletwfs;
    }

    /**
     * Get the list of potential subscribers to a twf.
     *
     * @param context_module $context the twf context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/twf:allowforcesubscribe', $groupid, true);
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
     * Fetch the twf subscription data for the specified userid and twf.
     *
     * @param int $twfid The twf to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($twfid, $userid) {
        if (isset(self::$twfcache[$userid]) && isset(self::$twfcache[$userid][$twfid])) {
            return self::$twfcache[$userid][$twfid];
        }
        self::fill_subscription_cache($twfid, $userid);

        if (!isset(self::$twfcache[$userid]) || !isset(self::$twfcache[$userid][$twfid])) {
            return false;
        }

        return self::$twfcache[$userid][$twfid];
    }

    /**
     * Fill the twf subscription data for the specified userid and twf.
     *
     * If the userid is not specified, then all subscription data for that twf is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $twfid The twf to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($twfid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedtwfs[$twfid])) {
            // This twf has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$twfcache[$userid])) {
                    self::$twfcache[$userid] = array();
                }

                if (!isset(self::$twfcache[$userid][$twfid])) {
                    if ($DB->record_exists('twf_subscriptions', array(
                        'userid' => $userid,
                        'twf' => $twfid,
                    ))) {
                        self::$twfcache[$userid][$twfid] = true;
                    } else {
                        self::$twfcache[$userid][$twfid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('twf_subscriptions', array(
                    'twf' => $twfid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$twfcache[$data->userid])) {
                        self::$twfcache[$data->userid] = array();
                    }
                    self::$twfcache[$data->userid][$twfid] = true;
                }
                self::$fetchedtwfs[$twfid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the twf subscription data for all twfs that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$twfcache[$userid])) {
            self::$twfcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS twfid,
                    s.id AS subscriptionid
                FROM {twf} f
                LEFT JOIN {twf_subscriptions} s ON (s.twf = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => FORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$twfcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this twf.
     *
     * @param stdClass $twf The twf record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the twf context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($twf, $groupid = 0, $context = null, $fields = null,
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
                      u.tracktwfs,
                      u.mnethostid";
        }

        // Retrieve the twf context if it wasn't specified.
        $context = twf_get_context($twf->id, $context);

        if (self::is_forcesubscribed($twf)) {
            $results = \mod_twf\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['twfid'] = $twf->id;

            if ($includediscussionsubscriptions) {
                $params['stwfid'] = $twf->id;
                $params['dstwfid'] = $twf->id;
                $params['unsubscribed'] = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {twf_subscriptions} s
                            WHERE
                                s.twf = :stwfid
                                UNION
                            SELECT userid FROM {twf_discussion_subs} ds
                            WHERE
                                ds.twf = :dstwfid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {twf_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.twf = :twfid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a twf.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('twf', $twf->id, $twf->course);
        $modinfo = get_fast_modinfo($twf->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and twf.
     *
     * This is returned as an array of discussions for that twf which contain the preference in a stdClass.
     *
     * @param int $twfid The twf to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the twf.
     */
    public static function fetch_discussion_subscription($twfid, $userid = null) {
        self::fill_discussion_subscription_cache($twfid, $userid);

        if (!isset(self::$twfdiscussioncache[$userid]) || !isset(self::$twfdiscussioncache[$userid][$twfid])) {
            return array();
        }

        return self::$twfdiscussioncache[$userid][$twfid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and twf.
     *
     * If the userid is not specified, then all discussion subscription data for that twf is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $twfid The twf to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($twfid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedtwfs[$twfid])) {
            // This twf hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$twfdiscussioncache[$userid])) {
                    self::$twfdiscussioncache[$userid] = array();
                }

                if (!isset(self::$twfdiscussioncache[$userid][$twfid])) {
                    $subscriptions = $DB->get_recordset('twf_discussion_subs', array(
                        'userid' => $userid,
                        'twf' => $twfid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($twfid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('twf_discussion_subs', array(
                    'twf' => $twfid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($twfid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedtwfs[$twfid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $twfid The ID of the twf that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($twfid, $userid, $discussion, $preference) {
        if (!isset(self::$twfdiscussioncache[$userid])) {
            self::$twfdiscussioncache[$userid] = array();
        }

        if (!isset(self::$twfdiscussioncache[$userid][$twfid])) {
            self::$twfdiscussioncache[$userid][$twfid] = array();
        }

        self::$twfdiscussioncache[$userid][$twfid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking twf discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$twfdiscussioncache = array();
        self::$discussionfetchedtwfs = array();
    }

    /**
     * Reset the twf cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking twf subscription states.
     */
    public static function reset_twf_cache() {
        self::$twfcache = array();
        self::$fetchedtwfs = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $twf The twf record for this twf.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the twf_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $twf, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $twf)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->twf = $twf->id;

        $result = $DB->insert_record("twf_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('twf_discussion_subs', array('userid' => $userid, 'twf' => $twf->id));
            $DB->delete_records_select('twf_discussion_subs',
                    'userid = :userid AND twf = :twfid AND preference <> :preference', array(
                        'userid' => $userid,
                        'twfid' => $twf->id,
                        'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this twf.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$twfdiscussioncache[$userid]) && isset(self::$twfdiscussioncache[$userid][$twf->id])) {
                foreach (self::$twfdiscussioncache[$userid][$twf->id] as $discussionid => $preference) {
                    if ($preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$twfdiscussioncache[$userid][$twf->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this twf.
        self::$twfcache[$userid][$twf->id] = true;

        $context = twf_get_context($twf->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('twfid' => $twf->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('twf_discussion_subs', $subscription);
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
     * @param \stdClass $twf The twf record for this twf.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $twf, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'twf' => $twf->id,
        );
        $DB->delete_records('twf_digests', $sqlparams);

        if ($twfsubscription = $DB->get_record('twf_subscriptions', $sqlparams)) {
            $DB->delete_records('twf_subscriptions', array('id' => $twfsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('twf_discussion_subs', $sqlparams);
                $DB->delete_records('twf_discussion_subs',
                        array('userid' => $userid, 'twf' => $twf->id, 'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$twfdiscussioncache[$userid]) && isset(self::$twfdiscussioncache[$userid][$twf->id])) {
                    self::$twfdiscussioncache[$userid][$twf->id] = array();
                }
            }

            // Reset the cache for this twf.
            self::$twfcache[$userid][$twf->id] = false;

            $context = twf_get_context($twf->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $twfsubscription->id,
                'relateduserid' => $userid,
                'other' => array('twfid' => $twf->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('twf_subscriptions', $twfsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('twf_discussion_subs', $subscription);
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
        $subscription = $DB->get_record('twf_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a twf level subscription.
        if ($DB->record_exists('twf_subscriptions', array('userid' => $userid, 'twf' => $discussion->twf))) {
            if ($subscription && $subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the twf, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('twf_discussion_subs', array('id' => $subscription->id));
                unset(self::$twfdiscussioncache[$userid][$discussion->twf][$discussion->id]);
            } else {
                // The user is already subscribed to the twf. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('twf_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->twf = $discussion->twf;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('twf_discussion_subs', $subscription);
                self::$twfdiscussioncache[$userid][$discussion->twf][$discussion->id] = $subscription->preference;
            }
        }

        $context = twf_get_context($discussion->twf, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'twfid' => $discussion->twf,
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
        $subscription = $DB->get_record('twf_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a twf level subscription.
        if (!$DB->record_exists('twf_subscriptions', array('userid' => $userid, 'twf' => $discussion->twf))) {
            if ($subscription && $subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the twf, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('twf_discussion_subs', array('id' => $subscription->id));
                unset(self::$twfdiscussioncache[$userid][$discussion->twf][$discussion->id]);
            } else {
                // The user is not subscribed from the twf. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('twf_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->twf = $discussion->twf;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('twf_discussion_subs', $subscription);
            }
            self::$twfdiscussioncache[$userid][$discussion->twf][$discussion->id] = $subscription->preference;
        }

        $context = twf_get_context($discussion->twf, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'twfid' => $discussion->twf,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}

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
 * @package    mod_quora
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quora;

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
     * The subscription cache for quoras.
     *
     * The first level key is the user ID
     * The second level is the quora ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $quoracache = array();

    /**
     * The list of quoras which have been wholly retrieved for the quora subscription cache.
     *
     * This allows for prior caching of an entire quora to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedquoras = array();

    /**
     * The subscription cache for quora discussions.
     *
     * The first level key is the user ID
     * The second level is the quora ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $quoradiscussioncache = array();

    /**
     * The list of quoras which have been wholly retrieved for the quora discussion subscription cache.
     *
     * This allows for prior caching of an entire quora to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedquoras = array();

    /**
     * Whether a user is subscribed to this quora, or a discussion within
     * the quora.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the quora preference.
     *
     * If it is not specified then only the quora preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $quora The record of the quora to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $quora, $discussionid = null, $cm = null) {
        // If quora is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($quora)) {
            if (!$cm) {
                $cm = get_fast_modinfo($quora->course)->instances['quora'][$quora->id];
            }
            if (has_capability('mod/quora:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_quora($userid, $quora);
        }

        $subscriptions = self::fetch_discussion_subscription($quora->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::FORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_quora($userid, $quora);
    }

    /**
     * Whether a user is subscribed to this quora.
     *
     * @param int $userid The user ID
     * @param \stdClass $quora The record of the quora to test
     * @return boolean
     */
    protected static function is_subscribed_to_quora($userid, $quora) {
        return self::fetch_subscription_cache($quora->id, $userid);
    }

    /**
     * Helper to determine whether a quora has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $quora The record of the quora to test
     * @return bool
     */
    public static function is_forcesubscribed($quora) {
        return ($quora->forcesubscribe == FORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a quora has it's subscription mode set to disabled.
     *
     * @param \stdClass $quora The record of the quora to test
     * @return bool
     */
    public static function subscription_disabled($quora) {
        return ($quora->forcesubscribe == FORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified quora can be subscribed to.
     *
     * @param \stdClass $quora The record of the quora to test
     * @return bool
     */
    public static function is_subscribable($quora) {
        return (!\mod_quora\subscriptions::is_forcesubscribed($quora) &&
                !\mod_quora\subscriptions::subscription_disabled($quora));
    }

    /**
     * Set the quora subscription mode.
     *
     * By default when called without options, this is set to FORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $quora The record of the quora to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($quoraid, $status = 1) {
        global $DB;
        return $DB->set_field("quora", "forcesubscribe", $status, array("id" => $quoraid));
    }

    /**
     * Returns the current subscription mode for the quora.
     *
     * @param \stdClass $quora The record of the quora to set
     * @return int The quora subscription mode
     */
    public static function get_subscription_mode($quora) {
        return $quora->forcesubscribe;
    }

    /**
     * Returns an array of quoras that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable quoras
     */
    public static function get_unsubscribable_quoras() {
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

        // Get all quoras from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a quora in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {quora} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {quora_subscriptions} fs ON (fs.quora = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'quora',
            'userid' => $USER->id,
            'forcesubscribe' => FORUM_FORCESUBSCRIBE,
        ));
        $quoras = $DB->get_recordset_sql($sql, $params);

        $unsubscribablequoras = array();
        foreach($quoras as $quora) {
            if (empty($quora->visible)) {
                // The quora is hidden - check if the user can view the quora.
                $context = \context_module::instance($quora->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden quora to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribablequoras[] = $quora;
        }
        $quoras->close();

        return $unsubscribablequoras;
    }

    /**
     * Get the list of potential subscribers to a quora.
     *
     * @param context_module $context the quora context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/quora:allowforcesubscribe', $groupid, true);
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
     * Fetch the quora subscription data for the specified userid and quora.
     *
     * @param int $quoraid The quora to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($quoraid, $userid) {
        if (isset(self::$quoracache[$userid]) && isset(self::$quoracache[$userid][$quoraid])) {
            return self::$quoracache[$userid][$quoraid];
        }
        self::fill_subscription_cache($quoraid, $userid);

        if (!isset(self::$quoracache[$userid]) || !isset(self::$quoracache[$userid][$quoraid])) {
            return false;
        }

        return self::$quoracache[$userid][$quoraid];
    }

    /**
     * Fill the quora subscription data for the specified userid and quora.
     *
     * If the userid is not specified, then all subscription data for that quora is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $quoraid The quora to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($quoraid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedquoras[$quoraid])) {
            // This quora has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$quoracache[$userid])) {
                    self::$quoracache[$userid] = array();
                }

                if (!isset(self::$quoracache[$userid][$quoraid])) {
                    if ($DB->record_exists('quora_subscriptions', array(
                        'userid' => $userid,
                        'quora' => $quoraid,
                    ))) {
                        self::$quoracache[$userid][$quoraid] = true;
                    } else {
                        self::$quoracache[$userid][$quoraid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('quora_subscriptions', array(
                    'quora' => $quoraid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$quoracache[$data->userid])) {
                        self::$quoracache[$data->userid] = array();
                    }
                    self::$quoracache[$data->userid][$quoraid] = true;
                }
                self::$fetchedquoras[$quoraid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the quora subscription data for all quoras that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$quoracache[$userid])) {
            self::$quoracache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS quoraid,
                    s.id AS subscriptionid
                FROM {quora} f
                LEFT JOIN {quora_subscriptions} s ON (s.quora = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => FORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$quoracache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this quora.
     *
     * @param stdClass $quora The quora record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the quora context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($quora, $groupid = 0, $context = null, $fields = null,
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
                      u.trackquoras,
                      u.mnethostid";
        }

        // Retrieve the quora context if it wasn't specified.
        $context = quora_get_context($quora->id, $context);

        if (self::is_forcesubscribed($quora)) {
            $results = \mod_quora\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['quoraid'] = $quora->id;

            if ($includediscussionsubscriptions) {
                $params['squoraid'] = $quora->id;
                $params['dsquoraid'] = $quora->id;
                $params['unsubscribed'] = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {quora_subscriptions} s
                            WHERE
                                s.quora = :squoraid
                                UNION
                            SELECT userid FROM {quora_discussion_subs} ds
                            WHERE
                                ds.quora = :dsquoraid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {quora_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.quora = :quoraid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a quora.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('quora', $quora->id, $quora->course);
        $modinfo = get_fast_modinfo($quora->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and quora.
     *
     * This is returned as an array of discussions for that quora which contain the preference in a stdClass.
     *
     * @param int $quoraid The quora to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the quora.
     */
    public static function fetch_discussion_subscription($quoraid, $userid = null) {
        self::fill_discussion_subscription_cache($quoraid, $userid);

        if (!isset(self::$quoradiscussioncache[$userid]) || !isset(self::$quoradiscussioncache[$userid][$quoraid])) {
            return array();
        }

        return self::$quoradiscussioncache[$userid][$quoraid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and quora.
     *
     * If the userid is not specified, then all discussion subscription data for that quora is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $quoraid The quora to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($quoraid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedquoras[$quoraid])) {
            // This quora hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$quoradiscussioncache[$userid])) {
                    self::$quoradiscussioncache[$userid] = array();
                }

                if (!isset(self::$quoradiscussioncache[$userid][$quoraid])) {
                    $subscriptions = $DB->get_recordset('quora_discussion_subs', array(
                        'userid' => $userid,
                        'quora' => $quoraid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($quoraid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('quora_discussion_subs', array(
                    'quora' => $quoraid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($quoraid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedquoras[$quoraid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $quoraid The ID of the quora that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($quoraid, $userid, $discussion, $preference) {
        if (!isset(self::$quoradiscussioncache[$userid])) {
            self::$quoradiscussioncache[$userid] = array();
        }

        if (!isset(self::$quoradiscussioncache[$userid][$quoraid])) {
            self::$quoradiscussioncache[$userid][$quoraid] = array();
        }

        self::$quoradiscussioncache[$userid][$quoraid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking quora discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$quoradiscussioncache = array();
        self::$discussionfetchedquoras = array();
    }

    /**
     * Reset the quora cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking quora subscription states.
     */
    public static function reset_quora_cache() {
        self::$quoracache = array();
        self::$fetchedquoras = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $quora The quora record for this quora.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the quora_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $quora, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $quora)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->quora = $quora->id;

        $result = $DB->insert_record("quora_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('quora_discussion_subs', array('userid' => $userid, 'quora' => $quora->id));
            $DB->delete_records_select('quora_discussion_subs',
                    'userid = :userid AND quora = :quoraid AND preference <> :preference', array(
                        'userid' => $userid,
                        'quoraid' => $quora->id,
                        'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this quora.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$quoradiscussioncache[$userid]) && isset(self::$quoradiscussioncache[$userid][$quora->id])) {
                foreach (self::$quoradiscussioncache[$userid][$quora->id] as $discussionid => $preference) {
                    if ($preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$quoradiscussioncache[$userid][$quora->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this quora.
        self::$quoracache[$userid][$quora->id] = true;

        $context = quora_get_context($quora->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('quoraid' => $quora->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('quora_discussion_subs', $subscription);
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
     * @param \stdClass $quora The quora record for this quora.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $quora, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'quora' => $quora->id,
        );
        $DB->delete_records('quora_digests', $sqlparams);

        if ($quorasubscription = $DB->get_record('quora_subscriptions', $sqlparams)) {
            $DB->delete_records('quora_subscriptions', array('id' => $quorasubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('quora_discussion_subs', $sqlparams);
                $DB->delete_records('quora_discussion_subs',
                        array('userid' => $userid, 'quora' => $quora->id, 'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$quoradiscussioncache[$userid]) && isset(self::$quoradiscussioncache[$userid][$quora->id])) {
                    self::$quoradiscussioncache[$userid][$quora->id] = array();
                }
            }

            // Reset the cache for this quora.
            self::$quoracache[$userid][$quora->id] = false;

            $context = quora_get_context($quora->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $quorasubscription->id,
                'relateduserid' => $userid,
                'other' => array('quoraid' => $quora->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('quora_subscriptions', $quorasubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('quora_discussion_subs', $subscription);
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
        $subscription = $DB->get_record('quora_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a quora level subscription.
        if ($DB->record_exists('quora_subscriptions', array('userid' => $userid, 'quora' => $discussion->quora))) {
            if ($subscription && $subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the quora, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('quora_discussion_subs', array('id' => $subscription->id));
                unset(self::$quoradiscussioncache[$userid][$discussion->quora][$discussion->id]);
            } else {
                // The user is already subscribed to the quora. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('quora_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->quora = $discussion->quora;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);
                self::$quoradiscussioncache[$userid][$discussion->quora][$discussion->id] = $subscription->preference;
            }
        }

        $context = quora_get_context($discussion->quora, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'quoraid' => $discussion->quora,
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
        $subscription = $DB->get_record('quora_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a quora level subscription.
        if (!$DB->record_exists('quora_subscriptions', array('userid' => $userid, 'quora' => $discussion->quora))) {
            if ($subscription && $subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the quora, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('quora_discussion_subs', array('id' => $subscription->id));
                unset(self::$quoradiscussioncache[$userid][$discussion->quora][$discussion->id]);
            } else {
                // The user is not subscribed from the quora. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('quora_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->quora = $discussion->quora;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('quora_discussion_subs', $subscription);
            }
            self::$quoradiscussioncache[$userid][$discussion->quora][$discussion->id] = $subscription->preference;
        }

        $context = quora_get_context($discussion->quora, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'quoraid' => $discussion->quora,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}

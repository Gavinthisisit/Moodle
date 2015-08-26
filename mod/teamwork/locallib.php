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
 * Library of internal classes and functions for module teamwork
 *
 * All the teamwork specific functions, needed to implement the module
 * logic, should go to here. Instead of having bunch of function named
 * teamwork_something() taking the teamwork instance as the first
 * parameter, we use a class teamwork that provides all methods.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/lib.php');     // we extend this library here
require_once($CFG->libdir . '/gradelib.php');   // we use some rounding and comparing routines here
require_once($CFG->libdir . '/filelib.php');

/**
 * Full-featured teamwork API
 *
 * This wraps the teamwork database record with a set of methods that are called
 * from the module itself. The class should be initialized right after you get
 * $teamwork, $cm and $course records at the begining of the script.
 */
class teamwork {

    /** error status of the {@link self::add_allocation()} */
    const ALLOCATION_EXISTS             = -9999;

    /** the internal code of the teamwork phases as are stored in the database */
    const PHASE_SETUP                   = 10;
    const PHASE_SUBMISSION              = 20;
    const PHASE_ASSESSMENT              = 30;
    const PHASE_EVALUATION              = 40;
    const PHASE_CLOSED                  = 50;

    /** the internal code of the examples modes as are stored in the database */
    const EXAMPLES_VOLUNTARY            = 0;
    const EXAMPLES_BEFORE_SUBMISSION    = 1;
    const EXAMPLES_BEFORE_ASSESSMENT    = 2;

    /** @var cm_info course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var int teamwork instance identifier */
    public $id;

    /** @var string teamwork activity name */
    public $name;

    /** @var string introduction or description of the activity */
    public $intro;

    /** @var int format of the {@link $intro} */
    public $introformat;

    /** @var string instructions for the submission phase */
    public $instructauthors;

    /** @var int format of the {@link $instructauthors} */
    public $instructauthorsformat;

    /** @var string instructions for the assessment phase */
    public $instructreviewers;

    /** @var int format of the {@link $instructreviewers} */
    public $instructreviewersformat;

    /** @var int timestamp of when the module was modified */
    public $timemodified;

    /** @var int current phase of teamwork, for example {@link teamwork::PHASE_SETUP} */
    public $phase;

    /** @var bool optional feature: students practise evaluating on example submissions from teacher */
    public $useexamples;

    /** @var bool optional feature: students perform peer assessment of others' work (deprecated, consider always enabled) */
    public $usepeerassessment;

    /** @var bool optional feature: students perform self assessment of their own work */
    public $useselfassessment;

    /** @var float number (10, 5) unsigned, the maximum grade for submission */
    public $grade;

    /** @var float number (10, 5) unsigned, the maximum grade for assessment */
    public $gradinggrade;

    /** @var string type of the current grading strategy used in this teamwork, for example 'accumulative' */
    public $strategy;

    /** @var string the name of the evaluation plugin to use for grading grades calculation */
    public $evaluation;

    /** @var int number of digits that should be shown after the decimal point when displaying grades */
    public $gradedecimals;

    /** @var int number of allowed submission attachments and the files embedded into submission */
    public $nattachments;

    /** @var bool allow submitting the work after the deadline */
    public $latesubmissions;

    /** @var int maximum size of the one attached file in bytes */
    public $maxbytes;

    /** @var int mode of example submissions support, for example {@link teamwork::EXAMPLES_VOLUNTARY} */
    public $examplesmode;

    /** @var int if greater than 0 then the submission is not allowed before this timestamp */
    public $submissionstart;

    /** @var int if greater than 0 then the submission is not allowed after this timestamp */
    public $submissionend;

    /** @var int if greater than 0 then the peer assessment is not allowed before this timestamp */
    public $assessmentstart;

    /** @var int if greater than 0 then the peer assessment is not allowed after this timestamp */
    public $assessmentend;

    /** @var bool automatically switch to the assessment phase after the submissions deadline */
    public $phaseswitchassessment;

    /** @var string conclusion text to be displayed at the end of the activity */
    public $conclusion;

    /** @var int format of the conclusion text */
    public $conclusionformat;

    /** @var int the mode of the overall feedback */
    public $overallfeedbackmode;

    /** @var int maximum number of overall feedback attachments */
    public $overallfeedbackfiles;

    /** @var int maximum size of one file attached to the overall feedback */
    public $overallfeedbackmaxbytes;

    /**
     * @var teamwork_strategy grading strategy instance
     * Do not use directly, get the instance using {@link teamwork::grading_strategy_instance()}
     */
    protected $strategyinstance = null;

    /**
     * @var teamwork_evaluation grading evaluation instance
     * Do not use directly, get the instance using {@link teamwork::grading_evaluation_instance()}
     */
    protected $evaluationinstance = null;

    /**
     * Initializes the teamwork API instance using the data from DB
     *
     * Makes deep copy of all passed records properties.
     *
     * For unit testing only, $cm and $course may be set to null. This is so that
     * you can test without having any real database objects if you like. Not all
     * functions will work in this situation.
     *
     * @param stdClass $dbrecord Teamwork instance data from {teamwork} table
     * @param stdClass|cm_info $cm Course module record
     * @param stdClass $course Course record from {course} table
     * @param stdClass $context The context of the teamwork instance
     */
    public function __construct(stdclass $dbrecord, $cm, $course, stdclass $context=null) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('teamwork', $field)) {
                $this->{$field} = $value;
            }
        }
        if (is_null($cm) || is_null($course)) {
            throw new coding_exception('Must specify $cm and $course');
        }
        $this->course = $course;
        if ($cm instanceof cm_info) {
            $this->cm = $cm;
        } else {
            $modinfo = get_fast_modinfo($course);
            $this->cm = $modinfo->get_cm($cm->id);
        }
        if (is_null($context)) {
            $this->context = context_module::instance($this->cm->id);
        } else {
            $this->context = $context;
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Static methods                                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Return list of available allocation methods
     *
     * @return array Array ['string' => 'string'] of localized allocation method names
     */
    public static function installed_allocators() {
        $installed = core_component::get_plugin_list('teamworkallocation');
        $forms = array();
        foreach ($installed as $allocation => $allocationpath) {
            if (file_exists($allocationpath . '/lib.php')) {
                $forms[$allocation] = get_string('pluginname', 'teamworkallocation_' . $allocation);
            }
        }
        // usability - make sure that manual allocation appears the first
        if (isset($forms['manual'])) {
            $m = array('manual' => $forms['manual']);
            unset($forms['manual']);
            $forms = array_merge($m, $forms);
        }
        return $forms;
    }

    /**
     * Returns an array of options for the editors that are used for submitting and assessing instructions
     *
     * @param stdClass $context
     * @uses EDITOR_UNLIMITED_FILES hard-coded value for the 'maxfiles' option
     * @return array
     */
    public static function instruction_editors_options(stdclass $context) {
        return array('subdirs' => 1, 'maxbytes' => 0, 'maxfiles' => -1,
                     'changeformat' => 1, 'context' => $context, 'noclean' => 1, 'trusttext' => 0);
    }

    /**
     * Given the percent and the total, returns the number
     *
     * @param float $percent from 0 to 100
     * @param float $total   the 100% value
     * @return float
     */
    public static function percent_to_value($percent, $total) {
        if ($percent < 0 or $percent > 100) {
            throw new coding_exception('The percent can not be less than 0 or higher than 100');
        }

        return $total * $percent / 100;
    }

    /**
     * Returns an array of numeric values that can be used as maximum grades
     *
     * @return array Array of integers
     */
    public static function available_maxgrades_list() {
        $grades = array();
        for ($i=100; $i>=0; $i--) {
            $grades[$i] = $i;
        }
        return $grades;
    }

    /**
     * Returns the localized list of supported examples modes
     *
     * @return array
     */
    public static function available_example_modes_list() {
        $options = array();
        $options[self::EXAMPLES_VOLUNTARY]         = get_string('examplesvoluntary', 'teamwork');
        $options[self::EXAMPLES_BEFORE_SUBMISSION] = get_string('examplesbeforesubmission', 'teamwork');
        $options[self::EXAMPLES_BEFORE_ASSESSMENT] = get_string('examplesbeforeassessment', 'teamwork');
        return $options;
    }

    /**
     * Returns the list of available grading strategy methods
     *
     * @return array ['string' => 'string']
     */
    public static function available_strategies_list() {
        $installed = core_component::get_plugin_list('teamworkform');
        $forms = array();
        foreach ($installed as $strategy => $strategypath) {
            if (file_exists($strategypath . '/lib.php')) {
                $forms[$strategy] = get_string('pluginname', 'teamworkform_' . $strategy);
            }
        }
        return $forms;
    }

    /**
     * Returns the list of available grading evaluation methods
     *
     * @return array of (string)name => (string)localized title
     */
    public static function available_evaluators_list() {
        $evals = array();
        foreach (core_component::get_plugin_list_with_file('teamworkeval', 'lib.php', false) as $eval => $evalpath) {
            $evals[$eval] = get_string('pluginname', 'teamworkeval_' . $eval);
        }
        return $evals;
    }

    /**
     * Return an array of possible values of assessment dimension weight
     *
     * @return array of integers 0, 1, 2, ..., 16
     */
    public static function available_dimension_weights_list() {
        $weights = array();
        for ($i=16; $i>=0; $i--) {
            $weights[$i] = $i;
        }
        return $weights;
    }

    /**
     * Return an array of possible values of assessment weight
     *
     * Note there is no real reason why the maximum value here is 16. It used to be 10 in
     * teamwork 1.x and I just decided to use the same number as in the maximum weight of
     * a single assessment dimension.
     * The value looks reasonable, though. Teachers who would want to assign themselves
     * higher weight probably do not want peer assessment really...
     *
     * @return array of integers 0, 1, 2, ..., 16
     */
    public static function available_assessment_weights_list() {
        $weights = array();
        for ($i=16; $i>=0; $i--) {
            $weights[$i] = $i;
        }
        return $weights;
    }

    /**
     * Helper function returning the greatest common divisor
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function gcd($a, $b) {
        return ($b == 0) ? ($a):(self::gcd($b, $a % $b));
    }

    /**
     * Helper function returning the least common multiple
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function lcm($a, $b) {
        return ($a / self::gcd($a,$b)) * $b;
    }

    /**
     * Returns an object suitable for strings containing dates/times
     *
     * The returned object contains properties date, datefullshort, datetime, ... containing the given
     * timestamp formatted using strftimedate, strftimedatefullshort, strftimedatetime, ... from the
     * current lang's langconfig.php
     * This allows translators and administrators customize the date/time format.
     *
     * @param int $timestamp the timestamp in UTC
     * @return stdclass
     */
    public static function timestamp_formats($timestamp) {
        $formats = array('date', 'datefullshort', 'dateshort', 'datetime',
                'datetimeshort', 'daydate', 'daydatetime', 'dayshort', 'daytime',
                'monthyear', 'recent', 'recentfull', 'time');
        $a = new stdclass();
        foreach ($formats as $format) {
            $a->{$format} = userdate($timestamp, get_string('strftime'.$format, 'langconfig'));
        }
        $day = userdate($timestamp, '%Y%m%d', 99, false);
        $today = userdate(time(), '%Y%m%d', 99, false);
        $tomorrow = userdate(time() + DAYSECS, '%Y%m%d', 99, false);
        $yesterday = userdate(time() - DAYSECS, '%Y%m%d', 99, false);
        $distance = (int)round(abs(time() - $timestamp) / DAYSECS);
        if ($day == $today) {
            $a->distanceday = get_string('daystoday', 'teamwork');
        } elseif ($day == $yesterday) {
            $a->distanceday = get_string('daysyesterday', 'teamwork');
        } elseif ($day < $today) {
            $a->distanceday = get_string('daysago', 'teamwork', $distance);
        } elseif ($day == $tomorrow) {
            $a->distanceday = get_string('daystomorrow', 'teamwork');
        } elseif ($day > $today) {
            $a->distanceday = get_string('daysleft', 'teamwork', $distance);
        }
        return $a;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Teamwork API                                                               //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Fetches all enrolled users with the capability mod/teamwork:submit in the current teamwork
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_potential_authors($musthavesubmission=true, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/teamwork:submit', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of users that would be fetched by {@link self::get_potential_authors()}
     *
     * @param bool $musthavesubmission if true, count only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_potential_authors($musthavesubmission=true, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/teamwork:submit', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Fetches all enrolled users with the capability mod/teamwork:peerassess in the current teamwork
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_potential_reviewers($musthavesubmission=false, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/teamwork:peerassess', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of users that would be fetched by {@link self::get_potential_reviewers()}
     *
     * @param bool $musthavesubmission if true, count only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_potential_reviewers($musthavesubmission=false, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_users_with_capability_sql('mod/teamwork:peerassess', $musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Fetches all enrolled users that are authors or reviewers (or both) in the current teamwork
     *
     * The returned objects contain properties required by user_picture and are ordered by lastname, firstname.
     * Only users with the active enrolment are returned.
     *
     * @see self::get_potential_authors()
     * @see self::get_potential_reviewers()
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set)
     * @param int $limitnum return a subset containing this number of records (optional, required if $limitfrom is set)
     * @return array array[userid] => stdClass
     */
    public function get_participants($musthavesubmission=false, $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        list($sql, $params) = $this->get_participants_sql($musthavesubmission, $groupid);

        if (empty($sql)) {
            return array();
        }

        list($sort, $sortparams) = users_order_by_sql('tmp');
        $sql = "SELECT *
                  FROM ($sql) tmp
              ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns the total number of records that would be returned by {@link self::get_participants()}
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return int
     */
    public function count_participants($musthavesubmission=false, $groupid=0) {
        global $DB;

        list($sql, $params) = $this->get_participants_sql($musthavesubmission, $groupid);

        if (empty($sql)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                  FROM ($sql) tmp";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Checks if the given user is an actively enrolled participant in the teamwork
     *
     * @param int $userid, defaults to the current $USER
     * @return boolean
     */
    public function is_participant($userid=null) {
        global $USER, $DB;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        list($sql, $params) = $this->get_participants_sql();

        if (empty($sql)) {
            return false;
        }

        $sql = "SELECT COUNT(*)
                  FROM {user} uxx
                  JOIN ({$sql}) pxx ON uxx.id = pxx.id
                 WHERE uxx.id = :uxxid";
        $params['uxxid'] = $userid;

        if ($DB->count_records_sql($sql, $params)) {
            return true;
        }

        return false;
    }

    /**
     * Groups the given users by the group membership
     *
     * This takes the module grouping settings into account. If a grouping is
     * set, returns only groups withing the course module grouping. Always
     * returns group [0] with all the given users.
     *
     * @param array $users array[userid] => stdclass{->id ->lastname ->firstname}
     * @return array array[groupid][userid] => stdclass{->id ->lastname ->firstname}
     */
    public function get_grouped($users) {
        global $DB;
        global $CFG;

        $grouped = array();  // grouped users to be returned
        if (empty($users)) {
            return $grouped;
        }
        if ($this->cm->groupingid) {
            // Group teamwork set to specified grouping - only consider groups
            // within this grouping, and leave out users who aren't members of
            // this grouping.
            $groupingid = $this->cm->groupingid;
            // All users that are members of at least one group will be
            // added into a virtual group id 0
            $grouped[0] = array();
        } else {
            $groupingid = 0;
            // there is no need to be member of a group so $grouped[0] will contain
            // all users
            $grouped[0] = $users;
        }
        $gmemberships = groups_get_all_groups($this->cm->course, array_keys($users), $groupingid,
                            'gm.id,gm.groupid,gm.userid');
        foreach ($gmemberships as $gmembership) {
            if (!isset($grouped[$gmembership->groupid])) {
                $grouped[$gmembership->groupid] = array();
            }
            $grouped[$gmembership->groupid][$gmembership->userid] = $users[$gmembership->userid];
            $grouped[0][$gmembership->userid] = $users[$gmembership->userid];
        }
        return $grouped;
    }

    /**
     * Returns the list of all allocations (i.e. assigned assessments) in the teamwork
     *
     * Assessments of example submissions are ignored
     *
     * @return array
     */
    public function get_allocations() {
        global $DB;

        $sql = 'SELECT a.id, a.submissionid, a.reviewerid, s.authorid
                  FROM {teamwork_assessments} a
            INNER JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
                 WHERE s.example = 0 AND s.teamworkid = :teamworkid';
        $params = array('teamworkid' => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns the total number of records that would be returned by {@link self::get_submissions()}
     *
     * @param mixed $authorid int|array|'all' If set to [array of] integer, return submission[s] of the given user[s] only
     * @param int $groupid If non-zero, return only submissions by authors in the specified group
     * @return int number of records
     */
    public function count_submissions($authorid='all', $groupid=0) {
        global $DB;

        $params = array('teamworkid' => $this->id);
        $sql = "SELECT COUNT(s.id)
                  FROM {teamwork_submissions} s
                  JOIN {user} u ON (s.authorid = u.id)";
        if ($groupid) {
            $sql .= " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $sql .= " WHERE s.example = 0 AND s.teamworkid = :teamworkid";

        if ('all' === $authorid) {
            // no additional conditions
        } elseif (!empty($authorid)) {
            list($usql, $uparams) = $DB->get_in_or_equal($authorid, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            // $authorid is empty
            return 0;
        }

        return $DB->count_records_sql($sql, $params);
    }


    /**
     * Returns submissions from this teamwork
     *
     * Fetches data from {teamwork_submissions} and adds some useful information from other
     * tables. Does not return textual fields to prevent possible memory lack issues.
     *
     * @see self::count_submissions()
     * @param mixed $authorid int|array|'all' If set to [array of] integer, return submission[s] of the given user[s] only
     * @param int $groupid If non-zero, return only submissions by authors in the specified group
     * @param int $limitfrom Return a subset of records, starting at this point (optional)
     * @param int $limitnum Return a subset containing this many records in total (optional, required if $limitfrom is set)
     * @return array of records or an empty array
     */
    public function get_submissions($authorid='all', $groupid=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('t', null, 'gradeoverbyx', 'over');
        $params            = array('teamworkid' => $this->id);
        $sql = "SELECT s.id, s.teamworkid, s.example, s.authorid, s.timecreated, s.timemodified,
                       s.title, s.grade, s.gradeover, s.gradeoverby, s.published,
                       $authorfields, $gradeoverbyfields
                  FROM {teamwork_submissions} s
                  JOIN {user} u ON (s.authorid = u.id)";
        if ($groupid) {
            $sql .= " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = :groupid)";
            $params['groupid'] = $groupid;
        }
        $sql .= " LEFT JOIN {user} t ON (s.gradeoverby = t.id)
                 WHERE s.example = 0 AND s.teamworkid = :teamworkid";

        if ('all' === $authorid) {
            // no additional conditions
        } elseif (!empty($authorid)) {
            list($usql, $uparams) = $DB->get_in_or_equal($authorid, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            // $authorid is empty
            return array();
        }
        list($sort, $sortparams) = users_order_by_sql('u');
        $sql .= " ORDER BY $sort";

        return $DB->get_records_sql($sql, array_merge($params, $sortparams), $limitfrom, $limitnum);
    }

    /**
     * Returns a submission record with the author's data
     *
     * @param int $id submission id
     * @return stdclass
     */
    public function get_submission_by_id($id) {
        global $DB;

        // we intentionally check the teamworkid here, too, so the teamwork can't touch submissions
        // from other instances
        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('g', null, 'gradeoverbyx', 'gradeoverby');
        $sql = "SELECT s.*, $authorfields, $gradeoverbyfields
                  FROM {teamwork_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
             LEFT JOIN {user} g ON (s.gradeoverby = g.id)
                 WHERE s.example = 0 AND s.teamworkid = :teamworkid AND s.id = :id";
        $params = array('teamworkid' => $this->id, 'id' => $id);
        return $DB->get_record_sql($sql, $params, MUST_EXIST);
    }

    /**
     * Returns a submission submitted by the given author
     *
     * @param int $id author id
     * @return stdclass|false
     */
    public function get_submission_by_author($authorid) {
        global $DB;

        if (empty($authorid)) {
            return false;
        }
        $authorfields      = user_picture::fields('u', null, 'authoridx', 'author');
        $gradeoverbyfields = user_picture::fields('g', null, 'gradeoverbyx', 'gradeoverby');
        $sql = "SELECT s.*, $authorfields, $gradeoverbyfields
                  FROM {teamwork_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
             LEFT JOIN {user} g ON (s.gradeoverby = g.id)
                 WHERE s.example = 0 AND s.teamworkid = :teamworkid AND s.authorid = :authorid";
        $params = array('teamworkid' => $this->id, 'authorid' => $authorid);
        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Returns published submissions with their authors data
     *
     * @return array of stdclass
     */
    public function get_published_submissions($orderby='finalgrade DESC') {
        global $DB;

        $authorfields = user_picture::fields('u', null, 'authoridx', 'author');
        $sql = "SELECT s.id, s.authorid, s.timecreated, s.timemodified,
                       s.title, s.grade, s.gradeover, COALESCE(s.gradeover,s.grade) AS finalgrade,
                       $authorfields
                  FROM {teamwork_submissions} s
            INNER JOIN {user} u ON (s.authorid = u.id)
                 WHERE s.example = 0 AND s.teamworkid = :teamworkid AND s.published = 1
              ORDER BY $orderby";
        $params = array('teamworkid' => $this->id);
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns full record of the given example submission
     *
     * @param int $id example submission od
     * @return object
     */
    public function get_example_by_id($id) {
        global $DB;
        return $DB->get_record('teamwork_submissions',
                array('id' => $id, 'teamworkid' => $this->id, 'example' => 1), '*', MUST_EXIST);
    }

    /**
     * Returns the list of example submissions in this teamwork with reference assessments attached
     *
     * @return array of objects or an empty array
     * @see teamwork::prepare_example_summary()
     */
    public function get_examples_for_manager() {
        global $DB;

        $sql = 'SELECT s.id, s.title,
                       a.id AS assessmentid, a.grade, a.gradinggrade
                  FROM {teamwork_submissions} s
             LEFT JOIN {teamwork_assessments} a ON (a.submissionid = s.id AND a.weight = 1)
                 WHERE s.example = 1 AND s.teamworkid = :teamworkid
              ORDER BY s.title';
        return $DB->get_records_sql($sql, array('teamworkid' => $this->id));
    }

    /**
     * Returns the list of all example submissions in this teamwork with the information of assessments done by the given user
     *
     * @param int $reviewerid user id
     * @return array of objects, indexed by example submission id
     * @see teamwork::prepare_example_summary()
     */
    public function get_examples_for_reviewer($reviewerid) {
        global $DB;

        if (empty($reviewerid)) {
            return false;
        }
        $sql = 'SELECT s.id, s.title,
                       a.id AS assessmentid, a.grade, a.gradinggrade
                  FROM {teamwork_submissions} s
             LEFT JOIN {teamwork_assessments} a ON (a.submissionid = s.id AND a.reviewerid = :reviewerid AND a.weight = 0)
                 WHERE s.example = 1 AND s.teamworkid = :teamworkid
              ORDER BY s.title';
        return $DB->get_records_sql($sql, array('teamworkid' => $this->id, 'reviewerid' => $reviewerid));
    }

    /**
     * Prepares renderable submission component
     *
     * @param stdClass $record required by {@see teamwork_submission}
     * @param bool $showauthor show the author-related information
     * @return teamwork_submission
     */
    public function prepare_submission(stdClass $record, $showauthor = false) {

        $submission         = new teamwork_submission($this, $record, $showauthor);
        $submission->url    = $this->submission_url($record->id);

        return $submission;
    }

    /**
     * Prepares renderable submission summary component
     *
     * @param stdClass $record required by {@see teamwork_submission_summary}
     * @param bool $showauthor show the author-related information
     * @return teamwork_submission_summary
     */
    public function prepare_submission_summary(stdClass $record, $showauthor = false) {

        $summary        = new teamwork_submission_summary($this, $record, $showauthor);
        $summary->url   = $this->submission_url($record->id);

        return $summary;
    }

    /**
     * Prepares renderable example submission component
     *
     * @param stdClass $record required by {@see teamwork_example_submission}
     * @return teamwork_example_submission
     */
    public function prepare_example_submission(stdClass $record) {

        $example = new teamwork_example_submission($this, $record);

        return $example;
    }

    /**
     * Prepares renderable example submission summary component
     *
     * If the example is editable, the caller must set the 'editable' flag explicitly.
     *
     * @param stdClass $example as returned by {@link teamwork::get_examples_for_manager()} or {@link teamwork::get_examples_for_reviewer()}
     * @return teamwork_example_submission_summary to be rendered
     */
    public function prepare_example_summary(stdClass $example) {

        $summary = new teamwork_example_submission_summary($this, $example);

        if (is_null($example->grade)) {
            $summary->status = 'notgraded';
            $summary->assesslabel = get_string('assess', 'teamwork');
        } else {
            $summary->status = 'graded';
            $summary->assesslabel = get_string('reassess', 'teamwork');
        }

        $summary->gradeinfo           = new stdclass();
        $summary->gradeinfo->received = $this->real_grade($example->grade);
        $summary->gradeinfo->max      = $this->real_grade(100);

        $summary->url       = new moodle_url($this->exsubmission_url($example->id));
        $summary->editurl   = new moodle_url($this->exsubmission_url($example->id), array('edit' => 'on'));
        $summary->assessurl = new moodle_url($this->exsubmission_url($example->id), array('assess' => 'on', 'sesskey' => sesskey()));

        return $summary;
    }

    /**
     * Prepares renderable assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     * showweight - should the assessment weight be available for the renderer
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param teamwork_assessment_form|null $form as returned by {@link teamwork_strategy::get_assessment_form()}
     * @param array $options
     * @return teamwork_assessment
     */
    public function prepare_assessment(stdClass $record, $form, array $options = array()) {

        $assessment             = new teamwork_assessment($this, $record, $options);
        $assessment->url        = $this->assess_url($record->id);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof teamwork_assessment_form)) {
            debugging('Not a valid instance of teamwork_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof teamwork_assessment_form)) {
            $assessment->form = $form;
        }

        if (empty($options['showweight'])) {
            $assessment->weight = null;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        return $assessment;
    }

    /**
     * Prepares renderable example submission's assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param teamwork_assessment_form|null $form as returned by {@link teamwork_strategy::get_assessment_form()}
     * @param array $options
     * @return teamwork_example_assessment
     */
    public function prepare_example_assessment(stdClass $record, $form = null, array $options = array()) {

        $assessment             = new teamwork_example_assessment($this, $record, $options);
        $assessment->url        = $this->exassess_url($record->id);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof teamwork_assessment_form)) {
            debugging('Not a valid instance of teamwork_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof teamwork_assessment_form)) {
            $assessment->form = $form;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        $assessment->weight = null;

        return $assessment;
    }

    /**
     * Prepares renderable example submission's reference assessment component
     *
     * The $options array supports the following keys:
     * showauthor - should the author user info be available for the renderer
     * showreviewer - should the reviewer user info be available for the renderer
     * showform - show the assessment form if it is available
     *
     * @param stdClass $record as returned by eg {@link self::get_assessment_by_id()}
     * @param teamwork_assessment_form|null $form as returned by {@link teamwork_strategy::get_assessment_form()}
     * @param array $options
     * @return teamwork_example_reference_assessment
     */
    public function prepare_example_reference_assessment(stdClass $record, $form = null, array $options = array()) {

        $assessment             = new teamwork_example_reference_assessment($this, $record, $options);
        $assessment->maxgrade   = $this->real_grade(100);

        if (!empty($options['showform']) and !($form instanceof teamwork_assessment_form)) {
            debugging('Not a valid instance of teamwork_assessment_form supplied', DEBUG_DEVELOPER);
        }

        if (!empty($options['showform']) and ($form instanceof teamwork_assessment_form)) {
            $assessment->form = $form;
        }

        if (!is_null($record->grade)) {
            $assessment->realgrade = $this->real_grade($record->grade);
        }

        $assessment->weight = null;

        return $assessment;
    }

    /**
     * Removes the submission and all relevant data
     *
     * @param stdClass $submission record to delete
     * @return void
     */
    public function delete_submission(stdclass $submission) {
        global $DB;

        $assessments = $DB->get_records('teamwork_assessments', array('submissionid' => $submission->id), '', 'id');
        $this->delete_assessment(array_keys($assessments));

        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_teamwork', 'submission_content', $submission->id);
        $fs->delete_area_files($this->context->id, 'mod_teamwork', 'submission_attachment', $submission->id);

        $DB->delete_records('teamwork_submissions', array('id' => $submission->id));
    }

    /**
     * Returns the list of all assessments in the teamwork with some data added
     *
     * Fetches data from {teamwork_assessments} and adds some useful information from other
     * tables. The returned object does not contain textual fields (i.e. comments) to prevent memory
     * lack issues.
     *
     * @return array [assessmentid] => assessment stdclass
     */
    public function get_all_assessments() {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        list($sort, $params) = users_order_by_sql('reviewer');
        $sql = "SELECT a.id, a.submissionid, a.reviewerid, a.timecreated, a.timemodified,
                       a.grade, a.gradinggrade, a.gradinggradeover, a.gradinggradeoverby,
                       $reviewerfields, $authorfields, $overbyfields,
                       s.title
                  FROM {teamwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.teamworkid = :teamworkid AND s.example = 0
              ORDER BY $sort";
        $params['teamworkid'] = $this->id;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the complete information about the given assessment
     *
     * @param int $id Assessment ID
     * @return stdclass
     */
    public function get_assessment_by_id($id) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, s.title, $reviewerfields, $authorfields, $overbyfields
                  FROM {teamwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE a.id = :id AND s.teamworkid = :teamworkid";
        $params = array('id' => $id, 'teamworkid' => $this->id);

        return $DB->get_record_sql($sql, $params, MUST_EXIST);
    }

    /**
     * Get the complete information about the user's assessment of the given submission
     *
     * @param int $sid submission ID
     * @param int $uid user ID of the reviewer
     * @return false|stdclass false if not found, stdclass otherwise
     */
    public function get_assessment_of_submission_by_user($submissionid, $reviewerid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, s.title, $reviewerfields, $authorfields, $overbyfields
                  FROM {teamwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {teamwork_submissions} s ON (a.submissionid = s.id AND s.example = 0)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.id = :sid AND reviewer.id = :rid AND s.teamworkid = :teamworkid";
        $params = array('sid' => $submissionid, 'rid' => $reviewerid, 'teamworkid' => $this->id);

        return $DB->get_record_sql($sql, $params, IGNORE_MISSING);
    }

    /**
     * Get the complete information about all assessments of the given submission
     *
     * @param int $submissionid
     * @return array
     */
    public function get_assessments_of_submission($submissionid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        list($sort, $params) = users_order_by_sql('reviewer');
        $sql = "SELECT a.*, s.title, $reviewerfields, $overbyfields
                  FROM {teamwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.example = 0 AND s.id = :submissionid AND s.teamworkid = :teamworkid
              ORDER BY $sort";
        $params['submissionid'] = $submissionid;
        $params['teamworkid']   = $this->id;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the complete information about all assessments allocated to the given reviewer
     *
     * @param int $reviewerid
     * @return array
     */
    public function get_assessments_by_reviewer($reviewerid) {
        global $DB;

        $reviewerfields = user_picture::fields('reviewer', null, 'revieweridx', 'reviewer');
        $authorfields   = user_picture::fields('author', null, 'authorid', 'author');
        $overbyfields   = user_picture::fields('overby', null, 'gradinggradeoverbyx', 'overby');
        $sql = "SELECT a.*, $reviewerfields, $authorfields, $overbyfields,
                       s.id AS submissionid, s.title AS submissiontitle, s.timecreated AS submissioncreated,
                       s.timemodified AS submissionmodified
                  FROM {teamwork_assessments} a
            INNER JOIN {user} reviewer ON (a.reviewerid = reviewer.id)
            INNER JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
            INNER JOIN {user} author ON (s.authorid = author.id)
             LEFT JOIN {user} overby ON (a.gradinggradeoverby = overby.id)
                 WHERE s.example = 0 AND reviewer.id = :reviewerid AND s.teamworkid = :teamworkid";
        $params = array('reviewerid' => $reviewerid, 'teamworkid' => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get allocated assessments not graded yet by the given reviewer
     *
     * @see self::get_assessments_by_reviewer()
     * @param int $reviewerid the reviewer id
     * @param null|int|array $exclude optional assessment id (or list of them) to be excluded
     * @return array
     */
    public function get_pending_assessments_by_reviewer($reviewerid, $exclude = null) {

        $assessments = $this->get_assessments_by_reviewer($reviewerid);

        foreach ($assessments as $id => $assessment) {
            if (!is_null($assessment->grade)) {
                unset($assessments[$id]);
                continue;
            }
            if (!empty($exclude)) {
                if (is_array($exclude) and in_array($id, $exclude)) {
                    unset($assessments[$id]);
                    continue;
                } else if ($id == $exclude) {
                    unset($assessments[$id]);
                    continue;
                }
            }
        }

        return $assessments;
    }

    /**
     * Allocate a submission to a user for review
     *
     * @param stdClass $submission Submission object with at least id property
     * @param int $reviewerid User ID
     * @param int $weight of the new assessment, from 0 to 16
     * @param bool $bulk repeated inserts into DB expected
     * @return int ID of the new assessment or an error code {@link self::ALLOCATION_EXISTS} if the allocation already exists
     */
    public function add_allocation(stdclass $submission, $reviewerid, $weight=1, $bulk=false) {
        global $DB;

        if ($DB->record_exists('teamwork_assessments', array('submissionid' => $submission->id, 'reviewerid' => $reviewerid))) {
            return self::ALLOCATION_EXISTS;
        }

        $weight = (int)$weight;
        if ($weight < 0) {
            $weight = 0;
        }
        if ($weight > 16) {
            $weight = 16;
        }

        $now = time();
        $assessment = new stdclass();
        $assessment->submissionid           = $submission->id;
        $assessment->reviewerid             = $reviewerid;
        $assessment->timecreated            = $now;         // do not set timemodified here
        $assessment->weight                 = $weight;
        $assessment->feedbackauthorformat   = editors_get_preferred_format();
        $assessment->feedbackreviewerformat = editors_get_preferred_format();

        return $DB->insert_record('teamwork_assessments', $assessment, true, $bulk);
    }

    /**
     * Delete assessment record or records.
     *
     * Removes associated records from the teamwork_grades table, too.
     *
     * @param int|array $id assessment id or array of assessments ids
     * @todo Give grading strategy plugins a chance to clean up their data, too.
     * @return bool true
     */
    public function delete_assessment($id) {
        global $DB;

        if (empty($id)) {
            return true;
        }

        $fs = get_file_storage();

        if (is_array($id)) {
            $DB->delete_records_list('teamwork_grades', 'assessmentid', $id);
            foreach ($id as $itemid) {
                $fs->delete_area_files($this->context->id, 'mod_teamwork', 'overallfeedback_content', $itemid);
                $fs->delete_area_files($this->context->id, 'mod_teamwork', 'overallfeedback_attachment', $itemid);
            }
            $DB->delete_records_list('teamwork_assessments', 'id', $id);

        } else {
            $DB->delete_records('teamwork_grades', array('assessmentid' => $id));
            $fs->delete_area_files($this->context->id, 'mod_teamwork', 'overallfeedback_content', $id);
            $fs->delete_area_files($this->context->id, 'mod_teamwork', 'overallfeedback_attachment', $id);
            $DB->delete_records('teamwork_assessments', array('id' => $id));
        }

        return true;
    }

    /**
     * Returns instance of grading strategy class
     *
     * @return stdclass Instance of a grading strategy
     */
    public function grading_strategy_instance() {
        global $CFG;    // because we require other libs here

        if (is_null($this->strategyinstance)) {
            $strategylib = dirname(__FILE__) . '/form/' . $this->strategy . '/lib.php';
            if (is_readable($strategylib)) {
                require_once($strategylib);
            } else {
                throw new coding_exception('the grading forms subplugin must contain library ' . $strategylib);
            }
            $classname = 'teamwork_' . $this->strategy . '_strategy';
            $this->strategyinstance = new $classname($this);
            if (!in_array('teamwork_strategy', class_implements($this->strategyinstance))) {
                throw new coding_exception($classname . ' does not implement teamwork_strategy interface');
            }
        }
        return $this->strategyinstance;
    }

    /**
     * Sets the current evaluation method to the given plugin.
     *
     * @param string $method the name of the teamworkeval subplugin
     * @return bool true if successfully set
     * @throws coding_exception if attempting to set a non-installed evaluation method
     */
    public function set_grading_evaluation_method($method) {
        global $DB;

        $evaluationlib = dirname(__FILE__) . '/eval/' . $method . '/lib.php';

        if (is_readable($evaluationlib)) {
            $this->evaluationinstance = null;
            $this->evaluation = $method;
            $DB->set_field('teamwork', 'evaluation', $method, array('id' => $this->id));
            return true;
        }

        throw new coding_exception('Attempt to set a non-existing evaluation method.');
    }

    /**
     * Returns instance of grading evaluation class
     *
     * @return stdclass Instance of a grading evaluation
     */
    public function grading_evaluation_instance() {
        global $CFG;    // because we require other libs here

        if (is_null($this->evaluationinstance)) {
            if (empty($this->evaluation)) {
                $this->evaluation = 'best';
            }
            $evaluationlib = dirname(__FILE__) . '/eval/' . $this->evaluation . '/lib.php';
            if (is_readable($evaluationlib)) {
                require_once($evaluationlib);
            } else {
                // Fall back in case the subplugin is not available.
                $this->evaluation = 'best';
                $evaluationlib = dirname(__FILE__) . '/eval/' . $this->evaluation . '/lib.php';
                if (is_readable($evaluationlib)) {
                    require_once($evaluationlib);
                } else {
                    // Fall back in case the subplugin is not available any more.
                    throw new coding_exception('Missing default grading evaluation library ' . $evaluationlib);
                }
            }
            $classname = 'teamwork_' . $this->evaluation . '_evaluation';
            $this->evaluationinstance = new $classname($this);
            if (!in_array('teamwork_evaluation', class_parents($this->evaluationinstance))) {
                throw new coding_exception($classname . ' does not extend teamwork_evaluation class');
            }
        }
        return $this->evaluationinstance;
    }

    /**
     * Returns instance of submissions allocator
     *
     * @param string $method The name of the allocation method, must be PARAM_ALPHA
     * @return stdclass Instance of submissions allocator
     */
    public function allocator_instance($method) {
        global $CFG;    // because we require other libs here

        $allocationlib = dirname(__FILE__) . '/allocation/' . $method . '/lib.php';
        if (is_readable($allocationlib)) {
            require_once($allocationlib);
        } else {
            throw new coding_exception('Unable to find the allocation library ' . $allocationlib);
        }
        $classname = 'teamwork_' . $method . '_allocator';
        return new $classname($this);
    }

    /**
     * @return moodle_url of this teamwork's view page
     */
    public function view_url() {
        global $CFG;
        return new moodle_url('/mod/teamwork/view.php', array('id' => $this->cm->id));
    }

    /**
     * @return moodle_url of the page for editing this teamwork's grading form
     */
    public function editform_url() {
        global $CFG;
        return new moodle_url('/mod/teamwork/editform.php', array('cmid' => $this->cm->id));
    }

    /**
     * @return moodle_url of the page for previewing this teamwork's grading form
     */
    public function previewform_url() {
        global $CFG;
        return new moodle_url('/mod/teamwork/editformpreview.php', array('cmid' => $this->cm->id));
    }

    /**
     * @param int $assessmentid The ID of assessment record
     * @return moodle_url of the assessment page
     */
    public function assess_url($assessmentid) {
        global $CFG;
        $assessmentid = clean_param($assessmentid, PARAM_INT);
        return new moodle_url('/mod/teamwork/assessment.php', array('asid' => $assessmentid));
    }

    /**
     * @param int $assessmentid The ID of assessment record
     * @return moodle_url of the example assessment page
     */
    public function exassess_url($assessmentid) {
        global $CFG;
        $assessmentid = clean_param($assessmentid, PARAM_INT);
        return new moodle_url('/mod/teamwork/exassessment.php', array('asid' => $assessmentid));
    }

    /**
     * @return moodle_url of the page to view a submission, defaults to the own one
     */
    public function submission_url($id=null) {
        global $CFG;
        return new moodle_url('/mod/teamwork/submission.php', array('cmid' => $this->cm->id, 'id' => $id));
    }

    /**
     * @param int $id example submission id
     * @return moodle_url of the page to view an example submission
     */
    public function exsubmission_url($id) {
        global $CFG;
        return new moodle_url('/mod/teamwork/exsubmission.php', array('cmid' => $this->cm->id, 'id' => $id));
    }

    /**
     * @param int $sid submission id
     * @param array $aid of int assessment ids
     * @return moodle_url of the page to compare assessments of the given submission
     */
    public function compare_url($sid, array $aids) {
        global $CFG;

        $url = new moodle_url('/mod/teamwork/compare.php', array('cmid' => $this->cm->id, 'sid' => $sid));
        $i = 0;
        foreach ($aids as $aid) {
            $url->param("aid{$i}", $aid);
            $i++;
        }
        return $url;
    }

    /**
     * @param int $sid submission id
     * @param int $aid assessment id
     * @return moodle_url of the page to compare the reference assessments of the given example submission
     */
    public function excompare_url($sid, $aid) {
        global $CFG;
        return new moodle_url('/mod/teamwork/excompare.php', array('cmid' => $this->cm->id, 'sid' => $sid, 'aid' => $aid));
    }

    /**
     * @return moodle_url of the mod_edit form
     */
    public function updatemod_url() {
        global $CFG;
        return new moodle_url('/course/modedit.php', array('update' => $this->cm->id, 'return' => 1));
    }

    /**
     * @param string $method allocation method
     * @return moodle_url to the allocation page
     */
    public function allocation_url($method=null) {
        global $CFG;
        $params = array('cmid' => $this->cm->id);
        if (!empty($method)) {
            $params['method'] = $method;
        }
        return new moodle_url('/mod/teamwork/allocation.php', $params);
    }

    /**
     * @param int $phasecode The internal phase code
     * @return moodle_url of the script to change the current phase to $phasecode
     */
    public function switchphase_url($phasecode) {
        global $CFG;
        $phasecode = clean_param($phasecode, PARAM_INT);
        return new moodle_url('/mod/teamwork/switchphase.php', array('cmid' => $this->cm->id, 'phase' => $phasecode));
    }

    /**
     * @return moodle_url to the aggregation page
     */
    public function aggregate_url() {
        global $CFG;
        return new moodle_url('/mod/teamwork/aggregate.php', array('cmid' => $this->cm->id));
    }

    /**
     * @return moodle_url of this teamwork's toolbox page
     */
    public function toolbox_url($tool) {
        global $CFG;
        return new moodle_url('/mod/teamwork/toolbox.php', array('id' => $this->cm->id, 'tool' => $tool));
    }

    /**
     * Teamwork wrapper around {@see add_to_log()}
     * @deprecated since 2.7 Please use the provided event classes for logging actions.
     *
     * @param string $action to be logged
     * @param moodle_url $url absolute url as returned by {@see teamwork::submission_url()} and friends
     * @param mixed $info additional info, usually id in a table
     * @param bool $return true to return the arguments for add_to_log.
     * @return void|array array of arguments for add_to_log if $return is true
     */
    public function log($action, moodle_url $url = null, $info = null, $return = false) {
        debugging('The log method is now deprecated, please use event classes instead', DEBUG_DEVELOPER);

        if (is_null($url)) {
            $url = $this->view_url();
        }

        if (is_null($info)) {
            $info = $this->id;
        }

        $logurl = $this->log_convert_url($url);
        $args = array($this->course->id, 'teamwork', $action, $logurl, $info, $this->cm->id);
        if ($return) {
            return $args;
        }
        call_user_func_array('add_to_log', $args);
    }

    /**
     * Is the given user allowed to create their submission?
     *
     * @param int $userid
     * @return bool
     */
    public function creating_submission_allowed($userid) {

        $now = time();
        $ignoredeadlines = has_capability('mod/teamwork:ignoredeadlines', $this->context, $userid);

        if ($this->latesubmissions) {
            if ($this->phase != self::PHASE_SUBMISSION and $this->phase != self::PHASE_ASSESSMENT) {
                // late submissions are allowed in the submission and assessment phase only
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
                // late submissions are not allowed before the submission start
                return false;
            }
            return true;

        } else {
            if ($this->phase != self::PHASE_SUBMISSION) {
                // submissions are allowed during the submission phase only
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
                // if enabled, submitting is not allowed before the date/time defined in the mod_form
                return false;
            }
            if (!$ignoredeadlines and !empty($this->submissionend) and $now > $this->submissionend ) {
                // if enabled, submitting is not allowed after the date/time defined in the mod_form unless late submission is allowed
                return false;
            }
            return true;
        }
    }

    /**
     * Is the given user allowed to modify their existing submission?
     *
     * @param int $userid
     * @return bool
     */
    public function modifying_submission_allowed($userid) {

        $now = time();
        $ignoredeadlines = has_capability('mod/teamwork:ignoredeadlines', $this->context, $userid);

        if ($this->phase != self::PHASE_SUBMISSION) {
            // submissions can be edited during the submission phase only
            return false;
        }
        if (!$ignoredeadlines and !empty($this->submissionstart) and $this->submissionstart > $now) {
            // if enabled, re-submitting is not allowed before the date/time defined in the mod_form
            return false;
        }
        if (!$ignoredeadlines and !empty($this->submissionend) and $now > $this->submissionend) {
            // if enabled, re-submitting is not allowed after the date/time defined in the mod_form even if late submission is allowed
            return false;
        }
        return true;
    }

    /**
     * Is the given reviewer allowed to create/edit their assessments?
     *
     * @param int $userid
     * @return bool
     */
    public function assessing_allowed($userid) {

        if ($this->phase != self::PHASE_ASSESSMENT) {
            // assessing is allowed in the assessment phase only, unless the user is a teacher
            // providing additional assessment during the evaluation phase
            if ($this->phase != self::PHASE_EVALUATION or !has_capability('mod/teamwork:overridegrades', $this->context, $userid)) {
                return false;
            }
        }

        $now = time();
        $ignoredeadlines = has_capability('mod/teamwork:ignoredeadlines', $this->context, $userid);

        if (!$ignoredeadlines and !empty($this->assessmentstart) and $this->assessmentstart > $now) {
            // if enabled, assessing is not allowed before the date/time defined in the mod_form
            return false;
        }
        if (!$ignoredeadlines and !empty($this->assessmentend) and $now > $this->assessmentend) {
            // if enabled, assessing is not allowed after the date/time defined in the mod_form
            return false;
        }
        // here we go, assessing is allowed
        return true;
    }

    /**
     * Are reviewers allowed to create/edit their assessments of the example submissions?
     *
     * Returns null if example submissions are not enabled in this teamwork. Otherwise returns
     * true or false. Note this does not check other conditions like the number of already
     * assessed examples, examples mode etc.
     *
     * @return null|bool
     */
    public function assessing_examples_allowed() {
        if (empty($this->useexamples)) {
            return null;
        }
        if (self::EXAMPLES_VOLUNTARY == $this->examplesmode) {
            return true;
        }
        if (self::EXAMPLES_BEFORE_SUBMISSION == $this->examplesmode and self::PHASE_SUBMISSION == $this->phase) {
            return true;
        }
        if (self::EXAMPLES_BEFORE_ASSESSMENT == $this->examplesmode and self::PHASE_ASSESSMENT == $this->phase) {
            return true;
        }
        return false;
    }

    /**
     * Are the peer-reviews available to the authors?
     *
     * @return bool
     */
    public function assessments_available() {
        return $this->phase == self::PHASE_CLOSED;
    }

    /**
     * Switch to a new teamwork phase
     *
     * Modifies the underlying database record. You should terminate the script shortly after calling this.
     *
     * @param int $newphase new phase code
     * @return bool true if success, false otherwise
     */
    public function switch_phase($newphase) {
        global $DB;

        $known = $this->available_phases_list();
        if (!isset($known[$newphase])) {
            return false;
        }

        if (self::PHASE_CLOSED == $newphase) {
            // push the grades into the gradebook
            $teamwork = new stdclass();
            foreach ($this as $property => $value) {
                $teamwork->{$property} = $value;
            }
            $teamwork->course     = $this->course->id;
            $teamwork->cmidnumber = $this->cm->id;
            $teamwork->modname    = 'teamwork';
            teamwork_update_grades($teamwork);
        }

        $DB->set_field('teamwork', 'phase', $newphase, array('id' => $this->id));
        $this->phase = $newphase;
        $eventdata = array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array(
                'teamworkphase' => $this->phase
            )
        );
        $event = \mod_teamwork\event\phase_switched::create($eventdata);
        $event->trigger();
        return true;
    }

    /**
     * Saves a raw grade for submission as calculated from the assessment form fields
     *
     * @param array $assessmentid assessment record id, must exists
     * @param mixed $grade        raw percentual grade from 0.00000 to 100.00000
     * @return false|float        the saved grade
     */
    public function set_peer_grade($assessmentid, $grade) {
        global $DB;

        if (is_null($grade)) {
            return false;
        }
        $data = new stdclass();
        $data->id = $assessmentid;
        $data->grade = $grade;
        $data->timemodified = time();
        $DB->update_record('teamwork_assessments', $data);
        return $grade;
    }

    /**
     * Prepares data object with all teamwork grades to be rendered
     *
     * @param int $userid the user we are preparing the report for
     * @param int $groupid if non-zero, prepare the report for the given group only
     * @param int $page the current page (for the pagination)
     * @param int $perpage participants per page (for the pagination)
     * @param string $sortby lastname|firstname|submissiontitle|submissiongrade|gradinggrade
     * @param string $sorthow ASC|DESC
     * @return stdclass data for the renderer
     */
    public function prepare_grading_report_data($userid, $groupid, $page, $perpage, $sortby, $sorthow) {
        global $DB;

        $canviewall     = has_capability('mod/teamwork:viewallassessments', $this->context, $userid);
        $isparticipant  = $this->is_participant($userid);

        if (!$canviewall and !$isparticipant) {
            // who the hell is this?
            return array();
        }

        if (!in_array($sortby, array('lastname','firstname','submissiontitle','submissiongrade','gradinggrade'))) {
            $sortby = 'lastname';
        }

        if (!($sorthow === 'ASC' or $sorthow === 'DESC')) {
            $sorthow = 'ASC';
        }

        // get the list of user ids to be displayed
        if ($canviewall) {
            $participants = $this->get_participants(false, $groupid);
        } else {
            // this is an ordinary teamwork participant (aka student) - display the report just for him/her
            $participants = array($userid => (object)array('id' => $userid));
        }

        // we will need to know the number of all records later for the pagination purposes
        $numofparticipants = count($participants);

        if ($numofparticipants > 0) {
            // load all fields which can be used for sorting and paginate the records
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            $params['teamworkid1'] = $this->id;
            $params['teamworkid2'] = $this->id;
            $sqlsort = array();
            $sqlsortfields = array($sortby => $sorthow) + array('lastname' => 'ASC', 'firstname' => 'ASC', 'u.id' => 'ASC');
            foreach ($sqlsortfields as $sqlsortfieldname => $sqlsortfieldhow) {
                $sqlsort[] = $sqlsortfieldname . ' ' . $sqlsortfieldhow;
            }
            $sqlsort = implode(',', $sqlsort);
            $picturefields = user_picture::fields('u', array(), 'userid');
            $sql = "SELECT $picturefields, s.title AS submissiontitle, s.grade AS submissiongrade, ag.gradinggrade
                      FROM {user} u
                 LEFT JOIN {teamwork_submissions} s ON (s.authorid = u.id AND s.teamworkid = :teamworkid1 AND s.example = 0)
                 LEFT JOIN {teamwork_aggregations} ag ON (ag.userid = u.id AND ag.teamworkid = :teamworkid2)
                     WHERE u.id $participantids
                  ORDER BY $sqlsort";
            $participants = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        } else {
            $participants = array();
        }

        // this will hold the information needed to display user names and pictures
        $userinfo = array();

        // get the user details for all participants to display
        $additionalnames = get_all_user_name_fields();
        foreach ($participants as $participant) {
            if (!isset($userinfo[$participant->userid])) {
                $userinfo[$participant->userid]            = new stdclass();
                $userinfo[$participant->userid]->id        = $participant->userid;
                $userinfo[$participant->userid]->picture   = $participant->picture;
                $userinfo[$participant->userid]->imagealt  = $participant->imagealt;
                $userinfo[$participant->userid]->email     = $participant->email;
                foreach ($additionalnames as $addname) {
                    $userinfo[$participant->userid]->$addname = $participant->$addname;
                }
            }
        }

        // load the submissions details
        $submissions = $this->get_submissions(array_keys($participants));

        // get the user details for all moderators (teachers) that have overridden a submission grade
        foreach ($submissions as $submission) {
            if (!isset($userinfo[$submission->gradeoverby])) {
                $userinfo[$submission->gradeoverby]            = new stdclass();
                $userinfo[$submission->gradeoverby]->id        = $submission->gradeoverby;
                $userinfo[$submission->gradeoverby]->picture   = $submission->overpicture;
                $userinfo[$submission->gradeoverby]->imagealt  = $submission->overimagealt;
                $userinfo[$submission->gradeoverby]->email     = $submission->overemail;
                foreach ($additionalnames as $addname) {
                    $temp = 'over' . $addname;
                    $userinfo[$submission->gradeoverby]->$addname = $submission->$temp;
                }
            }
        }

        // get the user details for all reviewers of the displayed participants
        $reviewers = array();

        if ($submissions) {
            list($submissionids, $params) = $DB->get_in_or_equal(array_keys($submissions), SQL_PARAMS_NAMED);
            list($sort, $sortparams) = users_order_by_sql('r');
            $picturefields = user_picture::fields('r', array(), 'reviewerid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, a.grade, a.gradinggrade, a.gradinggradeover, a.weight,
                           $picturefields, s.id AS submissionid, s.authorid
                      FROM {teamwork_assessments} a
                      JOIN {user} r ON (a.reviewerid = r.id)
                      JOIN {teamwork_submissions} s ON (a.submissionid = s.id AND s.example = 0)
                     WHERE a.submissionid $submissionids
                  ORDER BY a.weight DESC, $sort";
            $reviewers = $DB->get_records_sql($sql, array_merge($params, $sortparams));
            foreach ($reviewers as $reviewer) {
                if (!isset($userinfo[$reviewer->reviewerid])) {
                    $userinfo[$reviewer->reviewerid]            = new stdclass();
                    $userinfo[$reviewer->reviewerid]->id        = $reviewer->reviewerid;
                    $userinfo[$reviewer->reviewerid]->picture   = $reviewer->picture;
                    $userinfo[$reviewer->reviewerid]->imagealt  = $reviewer->imagealt;
                    $userinfo[$reviewer->reviewerid]->email     = $reviewer->email;
                    foreach ($additionalnames as $addname) {
                        $userinfo[$reviewer->reviewerid]->$addname = $reviewer->$addname;
                    }
                }
            }
        }

        // get the user details for all reviewees of the displayed participants
        $reviewees = array();
        if ($participants) {
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            list($sort, $sortparams) = users_order_by_sql('e');
            $params['teamworkid'] = $this->id;
            $picturefields = user_picture::fields('e', array(), 'authorid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, a.grade, a.gradinggrade, a.gradinggradeover, a.reviewerid, a.weight,
                           s.id AS submissionid, $picturefields
                      FROM {user} u
                      JOIN {teamwork_assessments} a ON (a.reviewerid = u.id)
                      JOIN {teamwork_submissions} s ON (a.submissionid = s.id AND s.example = 0)
                      JOIN {user} e ON (s.authorid = e.id)
                     WHERE u.id $participantids AND s.teamworkid = :teamworkid
                  ORDER BY a.weight DESC, $sort";
            $reviewees = $DB->get_records_sql($sql, array_merge($params, $sortparams));
            foreach ($reviewees as $reviewee) {
                if (!isset($userinfo[$reviewee->authorid])) {
                    $userinfo[$reviewee->authorid]            = new stdclass();
                    $userinfo[$reviewee->authorid]->id        = $reviewee->authorid;
                    $userinfo[$reviewee->authorid]->picture   = $reviewee->picture;
                    $userinfo[$reviewee->authorid]->imagealt  = $reviewee->imagealt;
                    $userinfo[$reviewee->authorid]->email     = $reviewee->email;
                    foreach ($additionalnames as $addname) {
                        $userinfo[$reviewee->authorid]->$addname = $reviewee->$addname;
                    }
                }
            }
        }

        // finally populate the object to be rendered
        $grades = $participants;

        foreach ($participants as $participant) {
            // set up default (null) values
            $grades[$participant->userid]->submissionid = null;
            $grades[$participant->userid]->submissiontitle = null;
            $grades[$participant->userid]->submissiongrade = null;
            $grades[$participant->userid]->submissiongradeover = null;
            $grades[$participant->userid]->submissiongradeoverby = null;
            $grades[$participant->userid]->submissionpublished = null;
            $grades[$participant->userid]->reviewedby = array();
            $grades[$participant->userid]->reviewerof = array();
        }
        unset($participants);
        unset($participant);

        foreach ($submissions as $submission) {
            $grades[$submission->authorid]->submissionid = $submission->id;
            $grades[$submission->authorid]->submissiontitle = $submission->title;
            $grades[$submission->authorid]->submissiongrade = $this->real_grade($submission->grade);
            $grades[$submission->authorid]->submissiongradeover = $this->real_grade($submission->gradeover);
            $grades[$submission->authorid]->submissiongradeoverby = $submission->gradeoverby;
            $grades[$submission->authorid]->submissionpublished = $submission->published;
        }
        unset($submissions);
        unset($submission);

        foreach($reviewers as $reviewer) {
            $info = new stdclass();
            $info->userid = $reviewer->reviewerid;
            $info->assessmentid = $reviewer->assessmentid;
            $info->submissionid = $reviewer->submissionid;
            $info->grade = $this->real_grade($reviewer->grade);
            $info->gradinggrade = $this->real_grading_grade($reviewer->gradinggrade);
            $info->gradinggradeover = $this->real_grading_grade($reviewer->gradinggradeover);
            $info->weight = $reviewer->weight;
            $grades[$reviewer->authorid]->reviewedby[$reviewer->reviewerid] = $info;
        }
        unset($reviewers);
        unset($reviewer);

        foreach($reviewees as $reviewee) {
            $info = new stdclass();
            $info->userid = $reviewee->authorid;
            $info->assessmentid = $reviewee->assessmentid;
            $info->submissionid = $reviewee->submissionid;
            $info->grade = $this->real_grade($reviewee->grade);
            $info->gradinggrade = $this->real_grading_grade($reviewee->gradinggrade);
            $info->gradinggradeover = $this->real_grading_grade($reviewee->gradinggradeover);
            $info->weight = $reviewee->weight;
            $grades[$reviewee->reviewerid]->reviewerof[$reviewee->authorid] = $info;
        }
        unset($reviewees);
        unset($reviewee);

        foreach ($grades as $grade) {
            $grade->gradinggrade = $this->real_grading_grade($grade->gradinggrade);
        }

        $data = new stdclass();
        $data->grades = $grades;
        $data->userinfo = $userinfo;
        $data->totalcount = $numofparticipants;
        $data->maxgrade = $this->real_grade(100);
        $data->maxgradinggrade = $this->real_grading_grade(100);
        return $data;
    }

    /**
     * Calculates the real value of a grade
     *
     * @param float $value percentual value from 0 to 100
     * @param float $max   the maximal grade
     * @return string
     */
    public function real_grade_value($value, $max) {
        $localized = true;
        if (is_null($value) or $value === '') {
            return null;
        } elseif ($max == 0) {
            return 0;
        } else {
            return format_float($max * $value / 100, $this->gradedecimals, $localized);
        }
    }

    /**
     * Calculates the raw (percentual) value from a real grade
     *
     * This is used in cases when a user wants to give a grade such as 12 of 20 and we need to save
     * this value in a raw percentual form into DB
     * @param float $value given grade
     * @param float $max   the maximal grade
     * @return float       suitable to be stored as numeric(10,5)
     */
    public function raw_grade_value($value, $max) {
        if (is_null($value) or $value === '') {
            return null;
        }
        if ($max == 0 or $value < 0) {
            return 0;
        }
        $p = $value / $max * 100;
        if ($p > 100) {
            return $max;
        }
        return grade_floatval($p);
    }

    /**
     * Calculates the real value of grade for submission
     *
     * @param float $value percentual value from 0 to 100
     * @return string
     */
    public function real_grade($value) {
        return $this->real_grade_value($value, $this->grade);
    }

    /**
     * Calculates the real value of grade for assessment
     *
     * @param float $value percentual value from 0 to 100
     * @return string
     */
    public function real_grading_grade($value) {
        return $this->real_grade_value($value, $this->gradinggrade);
    }

    /**
     * Sets the given grades and received grading grades to null
     *
     * This does not clear the information about how the peers filled the assessment forms, but
     * clears the calculated grades in teamwork_assessments. Therefore reviewers have to re-assess
     * the allocated submissions.
     *
     * @return void
     */
    public function clear_assessments() {
        global $DB;

        $submissions = $this->get_submissions();
        if (empty($submissions)) {
            // no money, no love
            return;
        }
        $submissions = array_keys($submissions);
        list($sql, $params) = $DB->get_in_or_equal($submissions, SQL_PARAMS_NAMED);
        $sql = "submissionid $sql";
        $DB->set_field_select('teamwork_assessments', 'grade', null, $sql, $params);
        $DB->set_field_select('teamwork_assessments', 'gradinggrade', null, $sql, $params);
    }

    /**
     * Sets the grades for submission to null
     *
     * @param null|int|array $restrict If null, update all authors, otherwise update just grades for the given author(s)
     * @return void
     */
    public function clear_submission_grades($restrict=null) {
        global $DB;

        $sql = "teamworkid = :teamworkid AND example = 0";
        $params = array('teamworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $DB->set_field_select('teamwork_submissions', 'grade', null, $sql, $params);
    }

    /**
     * Calculates grades for submission for the given participant(s) and updates it in the database
     *
     * @param null|int|array $restrict If null, update all authors, otherwise update just grades for the given author(s)
     * @return void
     */
    public function aggregate_submission_grades($restrict=null) {
        global $DB;

        // fetch a recordset with all assessments to process
        $sql = 'SELECT s.id AS submissionid, s.grade AS submissiongrade,
                       a.weight, a.grade
                  FROM {teamwork_submissions} s
             LEFT JOIN {teamwork_assessments} a ON (a.submissionid = s.id)
                 WHERE s.example=0 AND s.teamworkid=:teamworkid'; // to be cont.
        $params = array('teamworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND s.authorid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY s.id'; // this is important for bulk processing

        $rs         = $DB->get_recordset_sql($sql, $params);
        $batch      = array();    // will contain a set of all assessments of a single submission
        $previous   = null;       // a previous record in the recordset

        foreach ($rs as $current) {
            if (is_null($previous)) {
                // we are processing the very first record in the recordset
                $previous   = $current;
            }
            if ($current->submissionid == $previous->submissionid) {
                // we are still processing the current submission
                $batch[] = $current;
            } else {
                // process all the assessments of a sigle submission
                $this->aggregate_submission_grades_process($batch);
                // and then start to process another submission
                $batch      = array($current);
                $previous   = $current;
            }
        }
        // do not forget to process the last batch!
        $this->aggregate_submission_grades_process($batch);
        $rs->close();
    }

    /**
     * Sets the aggregated grades for assessment to null
     *
     * @param null|int|array $restrict If null, update all reviewers, otherwise update just grades for the given reviewer(s)
     * @return void
     */
    public function clear_grading_grades($restrict=null) {
        global $DB;

        $sql = "teamworkid = :teamworkid";
        $params = array('teamworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND userid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $DB->set_field_select('teamwork_aggregations', 'gradinggrade', null, $sql, $params);
    }

    /**
     * Calculates grades for assessment for the given participant(s)
     *
     * Grade for assessment is calculated as a simple mean of all grading grades calculated by the grading evaluator.
     * The assessment weight is not taken into account here.
     *
     * @param null|int|array $restrict If null, update all reviewers, otherwise update just grades for the given reviewer(s)
     * @return void
     */
    public function aggregate_grading_grades($restrict=null) {
        global $DB;

        // fetch a recordset with all assessments to process
        $sql = 'SELECT a.reviewerid, a.gradinggrade, a.gradinggradeover,
                       ag.id AS aggregationid, ag.gradinggrade AS aggregatedgrade
                  FROM {teamwork_assessments} a
            INNER JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
             LEFT JOIN {teamwork_aggregations} ag ON (ag.userid = a.reviewerid AND ag.teamworkid = s.teamworkid)
                 WHERE s.example=0 AND s.teamworkid=:teamworkid'; // to be cont.
        $params = array('teamworkid' => $this->id);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND a.reviewerid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY a.reviewerid'; // this is important for bulk processing

        $rs         = $DB->get_recordset_sql($sql, $params);
        $batch      = array();    // will contain a set of all assessments of a single submission
        $previous   = null;       // a previous record in the recordset

        foreach ($rs as $current) {
            if (is_null($previous)) {
                // we are processing the very first record in the recordset
                $previous   = $current;
            }
            if ($current->reviewerid == $previous->reviewerid) {
                // we are still processing the current reviewer
                $batch[] = $current;
            } else {
                // process all the assessments of a sigle submission
                $this->aggregate_grading_grades_process($batch);
                // and then start to process another reviewer
                $batch      = array($current);
                $previous   = $current;
            }
        }
        // do not forget to process the last batch!
        $this->aggregate_grading_grades_process($batch);
        $rs->close();
    }

    /**
     * Returns the mform the teachers use to put a feedback for the reviewer
     *
     * @param moodle_url $actionurl
     * @param stdClass $assessment
     * @param array $options editable, editableweight, overridablegradinggrade
     * @return teamwork_feedbackreviewer_form
     */
    public function get_feedbackreviewer_form(moodle_url $actionurl, stdclass $assessment, $options=array()) {
        global $CFG;
        require_once(dirname(__FILE__) . '/feedbackreviewer_form.php');

        $current = new stdclass();
        $current->asid                      = $assessment->id;
        $current->weight                    = $assessment->weight;
        $current->gradinggrade              = $this->real_grading_grade($assessment->gradinggrade);
        $current->gradinggradeover          = $this->real_grading_grade($assessment->gradinggradeover);
        $current->feedbackreviewer          = $assessment->feedbackreviewer;
        $current->feedbackreviewerformat    = $assessment->feedbackreviewerformat;
        if (is_null($current->gradinggrade)) {
            $current->gradinggrade = get_string('nullgrade', 'teamwork');
        }
        if (!isset($options['editable'])) {
            $editable = true;   // by default
        } else {
            $editable = (bool)$options['editable'];
        }

        // prepare wysiwyg editor
        $current = file_prepare_standard_editor($current, 'feedbackreviewer', array());

        return new teamwork_feedbackreviewer_form($actionurl,
                array('teamwork' => $this, 'current' => $current, 'editoropts' => array(), 'options' => $options),
                'post', '', null, $editable);
    }

    /**
     * Returns the mform the teachers use to put a feedback for the author on their submission
     *
     * @param moodle_url $actionurl
     * @param stdClass $submission
     * @param array $options editable
     * @return teamwork_feedbackauthor_form
     */
    public function get_feedbackauthor_form(moodle_url $actionurl, stdclass $submission, $options=array()) {
        global $CFG;
        require_once(dirname(__FILE__) . '/feedbackauthor_form.php');

        $current = new stdclass();
        $current->submissionid          = $submission->id;
        $current->published             = $submission->published;
        $current->grade                 = $this->real_grade($submission->grade);
        $current->gradeover             = $this->real_grade($submission->gradeover);
        $current->feedbackauthor        = $submission->feedbackauthor;
        $current->feedbackauthorformat  = $submission->feedbackauthorformat;
        if (is_null($current->grade)) {
            $current->grade = get_string('nullgrade', 'teamwork');
        }
        if (!isset($options['editable'])) {
            $editable = true;   // by default
        } else {
            $editable = (bool)$options['editable'];
        }

        // prepare wysiwyg editor
        $current = file_prepare_standard_editor($current, 'feedbackauthor', array());

        return new teamwork_feedbackauthor_form($actionurl,
                array('teamwork' => $this, 'current' => $current, 'editoropts' => array(), 'options' => $options),
                'post', '', null, $editable);
    }

    /**
     * Returns the information about the user's grades as they are stored in the gradebook
     *
     * The submission grade is returned for users with the capability mod/teamwork:submit and the
     * assessment grade is returned for users with the capability mod/teamwork:peerassess. Unless the
     * user has the capability to view hidden grades, grades must be visible to be returned. Null
     * grades are not returned. If none grade is to be returned, this method returns false.
     *
     * @param int $userid the user's id
     * @return teamwork_final_grades|false
     */
    public function get_gradebook_grades($userid) {
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');

        if (empty($userid)) {
            throw new coding_exception('User id expected, empty value given.');
        }

        // Read data via the Gradebook API
        $gradebook = grade_get_grades($this->course->id, 'mod', 'teamwork', $this->id, $userid);

        $grades = new teamwork_final_grades();

        if (has_capability('mod/teamwork:submit', $this->context, $userid)) {
            if (!empty($gradebook->items[0]->grades)) {
                $submissiongrade = reset($gradebook->items[0]->grades);
                if (!is_null($submissiongrade->grade)) {
                    if (!$submissiongrade->hidden or has_capability('moodle/grade:viewhidden', $this->context, $userid)) {
                        $grades->submissiongrade = $submissiongrade;
                    }
                }
            }
        }

        if (has_capability('mod/teamwork:peerassess', $this->context, $userid)) {
            if (!empty($gradebook->items[1]->grades)) {
                $assessmentgrade = reset($gradebook->items[1]->grades);
                if (!is_null($assessmentgrade->grade)) {
                    if (!$assessmentgrade->hidden or has_capability('moodle/grade:viewhidden', $this->context, $userid)) {
                        $grades->assessmentgrade = $assessmentgrade;
                    }
                }
            }
        }

        if (!is_null($grades->submissiongrade) or !is_null($grades->assessmentgrade)) {
            return $grades;
        }

        return false;
    }

    /**
     * Return the editor options for the overall feedback for the author.
     *
     * @return array
     */
    public function overall_feedback_content_options() {
        return array(
            'subdirs' => 0,
            'maxbytes' => $this->overallfeedbackmaxbytes,
            'maxfiles' => $this->overallfeedbackfiles,
            'changeformat' => 1,
            'context' => $this->context,
        );
    }

    /**
     * Return the filemanager options for the overall feedback for the author.
     *
     * @return array
     */
    public function overall_feedback_attachment_options() {
        return array(
            'subdirs' => 1,
            'maxbytes' => $this->overallfeedbackmaxbytes,
            'maxfiles' => $this->overallfeedbackfiles,
            'return_types' => FILE_INTERNAL,
        );
    }

    /**
     * Performs the reset of this teamwork instance.
     *
     * @param stdClass $data The actual course reset settings.
     * @return array List of results, each being array[(string)component, (string)item, (string)error]
     */
    public function reset_userdata(stdClass $data) {

        $componentstr = get_string('pluginname', 'teamwork').': '.format_string($this->name);
        $status = array();

        if (!empty($data->reset_teamwork_assessments) or !empty($data->reset_teamwork_submissions)) {
            // Reset all data related to assessments, including assessments of
            // example submissions.
            $result = $this->reset_userdata_assessments($data);
            if ($result === true) {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetassessments', 'mod_teamwork'),
                    'error' => false,
                );
            } else {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetassessments', 'mod_teamwork'),
                    'error' => $result,
                );
            }
        }

        if (!empty($data->reset_teamwork_submissions)) {
            // Reset all remaining data related to submissions.
            $result = $this->reset_userdata_submissions($data);
            if ($result === true) {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetsubmissions', 'mod_teamwork'),
                    'error' => false,
                );
            } else {
                $status[] = array(
                    'component' => $componentstr,
                    'item' => get_string('resetsubmissions', 'mod_teamwork'),
                    'error' => $result,
                );
            }
        }

        if (!empty($data->reset_teamwork_phase)) {
            // Do not use the {@link teamwork::switch_phase()} here, we do not
            // want to trigger events.
            $this->reset_phase();
            $status[] = array(
                'component' => $componentstr,
                'item' => get_string('resetsubmissions', 'mod_teamwork'),
                'error' => false,
            );
        }

        return $status;
    }


    ////////////////////////////////////////////////////////////////////////////////
    // Internal methods (implementation details)                                  //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Given an array of all assessments of a single submission, calculates the final grade for this submission
     *
     * This calculates the weighted mean of the passed assessment grades. If, however, the submission grade
     * was overridden by a teacher, the gradeover value is returned and the rest of grades are ignored.
     *
     * @param array $assessments of stdclass(->submissionid ->submissiongrade ->gradeover ->weight ->grade)
     * @return void
     */
    protected function aggregate_submission_grades_process(array $assessments) {
        global $DB;

        $submissionid   = null; // the id of the submission being processed
        $current        = null; // the grade currently saved in database
        $finalgrade     = null; // the new grade to be calculated
        $sumgrades      = 0;
        $sumweights     = 0;

        foreach ($assessments as $assessment) {
            if (is_null($submissionid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $submissionid = $assessment->submissionid;
            }
            if (is_null($current)) {
                // the currently saved grade is the same in all records, fetch it during the first loop cycle
                $current = $assessment->submissiongrade;
            }
            if (is_null($assessment->grade)) {
                // this was not assessed yet
                continue;
            }
            if ($assessment->weight == 0) {
                // this does not influence the calculation
                continue;
            }
            $sumgrades  += $assessment->grade * $assessment->weight;
            $sumweights += $assessment->weight;
        }
        if ($sumweights > 0 and is_null($finalgrade)) {
            $finalgrade = grade_floatval($sumgrades / $sumweights);
        }
        // check if the new final grade differs from the one stored in the database
        if (grade_floats_different($finalgrade, $current)) {
            // we need to save new calculation into the database
            $record = new stdclass();
            $record->id = $submissionid;
            $record->grade = $finalgrade;
            $record->timegraded = time();
            $DB->update_record('teamwork_submissions', $record);
        }
    }

    /**
     * Given an array of all assessments done by a single reviewer, calculates the final grading grade
     *
     * This calculates the simple mean of the passed grading grades. If, however, the grading grade
     * was overridden by a teacher, the gradinggradeover value is returned and the rest of grades are ignored.
     *
     * @param array $assessments of stdclass(->reviewerid ->gradinggrade ->gradinggradeover ->aggregationid ->aggregatedgrade)
     * @param null|int $timegraded explicit timestamp of the aggregation, defaults to the current time
     * @return void
     */
    protected function aggregate_grading_grades_process(array $assessments, $timegraded = null) {
        global $DB;

        $reviewerid = null; // the id of the reviewer being processed
        $current    = null; // the gradinggrade currently saved in database
        $finalgrade = null; // the new grade to be calculated
        $agid       = null; // aggregation id
        $sumgrades  = 0;
        $count      = 0;

        if (is_null($timegraded)) {
            $timegraded = time();
        }

        foreach ($assessments as $assessment) {
            if (is_null($reviewerid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $reviewerid = $assessment->reviewerid;
            }
            if (is_null($agid)) {
                // the id is the same in all records, fetch it during the first loop cycle
                $agid = $assessment->aggregationid;
            }
            if (is_null($current)) {
                // the currently saved grade is the same in all records, fetch it during the first loop cycle
                $current = $assessment->aggregatedgrade;
            }
            if (!is_null($assessment->gradinggradeover)) {
                // the grading grade for this assessment is overridden by a teacher
                $sumgrades += $assessment->gradinggradeover;
                $count++;
            } else {
                if (!is_null($assessment->gradinggrade)) {
                    $sumgrades += $assessment->gradinggrade;
                    $count++;
                }
            }
        }
        if ($count > 0) {
            $finalgrade = grade_floatval($sumgrades / $count);
        }

        // Event information.
        $params = array(
            'context' => $this->context,
            'courseid' => $this->course->id,
            'relateduserid' => $reviewerid
        );

        // check if the new final grade differs from the one stored in the database
        if (grade_floats_different($finalgrade, $current)) {
            $params['other'] = array(
                'currentgrade' => $current,
                'finalgrade' => $finalgrade
            );

            // we need to save new calculation into the database
            if (is_null($agid)) {
                // no aggregation record yet
                $record = new stdclass();
                $record->teamworkid = $this->id;
                $record->userid = $reviewerid;
                $record->gradinggrade = $finalgrade;
                $record->timegraded = $timegraded;
                $record->id = $DB->insert_record('teamwork_aggregations', $record);
                $params['objectid'] = $record->id;
                $event = \mod_teamwork\event\assessment_evaluated::create($params);
                $event->trigger();
            } else {
                $record = new stdclass();
                $record->id = $agid;
                $record->gradinggrade = $finalgrade;
                $record->timegraded = $timegraded;
                $DB->update_record('teamwork_aggregations', $record);
                $params['objectid'] = $agid;
                $event = \mod_teamwork\event\assessment_reevaluated::create($params);
                $event->trigger();
            }
        }
    }

    /**
     * Returns SQL to fetch all enrolled users with the given capability in the current teamwork
     *
     * The returned array consists of string $sql and the $params array. Note that the $sql can be
     * empty if a grouping is selected and it has no groups.
     *
     * The list is automatically restricted according to any availability restrictions
     * that apply to user lists (e.g. group, grouping restrictions).
     *
     * @param string $capability the name of the capability
     * @param bool $musthavesubmission ff true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return array of (string)sql, (array)params
     */
    protected function get_users_with_capability_sql($capability, $musthavesubmission, $groupid) {
        global $CFG;
        /** @var int static counter used to generate unique parameter holders */
        static $inc = 0;
        $inc++;

        // If the caller requests all groups and we are using a selected grouping,
        // recursively call this function for each group in the grouping (this is
        // needed because get_enrolled_sql only supports a single group).
        if (empty($groupid) and $this->cm->groupingid) {
            $groupingid = $this->cm->groupingid;
            $groupinggroupids = array_keys(groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.id'));
            $sql = array();
            $params = array();
            foreach ($groupinggroupids as $groupinggroupid) {
                if ($groupinggroupid > 0) { // just in case in order not to fall into the endless loop
                    list($gsql, $gparams) = $this->get_users_with_capability_sql($capability, $musthavesubmission, $groupinggroupid);
                    $sql[] = $gsql;
                    $params = array_merge($params, $gparams);
                }
            }
            $sql = implode(PHP_EOL." UNION ".PHP_EOL, $sql);
            return array($sql, $params);
        }

        list($esql, $params) = get_enrolled_sql($this->context, $capability, $groupid, true);

        $userfields = user_picture::fields('u');

        $sql = "SELECT $userfields
                  FROM {user} u
                  JOIN ($esql) je ON (je.id = u.id AND u.deleted = 0) ";

        if ($musthavesubmission) {
            $sql .= " JOIN {teamwork_submissions} ws ON (ws.authorid = u.id AND ws.example = 0 AND ws.teamworkid = :teamworkid{$inc}) ";
            $params['teamworkid'.$inc] = $this->id;
        }

        // If the activity is restricted so that only certain users should appear
        // in user lists, integrate this into the same SQL.
        $info = new \core_availability\info_module($this->cm);
        list ($listsql, $listparams) = $info->get_user_list_sql(false);
        if ($listsql) {
            $sql .= " JOIN ($listsql) restricted ON restricted.id = u.id ";
            $params = array_merge($params, $listparams);
        }

        return array($sql, $params);
    }

    /**
     * Returns SQL statement that can be used to fetch all actively enrolled participants in the teamwork
     *
     * @param bool $musthavesubmission if true, return only users who have already submitted
     * @param int $groupid 0 means ignore groups, any other value limits the result by group id
     * @return array of (string)sql, (array)params
     */
    protected function get_participants_sql($musthavesubmission=false, $groupid=0) {

        list($sql1, $params1) = $this->get_users_with_capability_sql('mod/teamwork:submit', $musthavesubmission, $groupid);
        list($sql2, $params2) = $this->get_users_with_capability_sql('mod/teamwork:peerassess', $musthavesubmission, $groupid);

        if (empty($sql1) or empty($sql2)) {
            if (empty($sql1) and empty($sql2)) {
                return array('', array());
            } else if (empty($sql1)) {
                $sql = $sql2;
                $params = $params2;
            } else {
                $sql = $sql1;
                $params = $params1;
            }
        } else {
            $sql = $sql1.PHP_EOL." UNION ".PHP_EOL.$sql2;
            $params = array_merge($params1, $params2);
        }

        return array($sql, $params);
    }

    /**
     * @return array of available teamwork phases
     */
    protected function available_phases_list() {
        return array(
            self::PHASE_SETUP       => true,
            self::PHASE_SUBMISSION  => true,
            self::PHASE_ASSESSMENT  => true,
            self::PHASE_EVALUATION  => true,
            self::PHASE_CLOSED      => true,
        );
    }

    /**
     * Converts absolute URL to relative URL needed by {@see add_to_log()}
     *
     * @param moodle_url $url absolute URL
     * @return string
     */
    protected function log_convert_url(moodle_url $fullurl) {
        static $baseurl;

        if (!isset($baseurl)) {
            $baseurl = new moodle_url('/mod/teamwork/');
            $baseurl = $baseurl->out();
        }

        return substr($fullurl->out(), strlen($baseurl));
    }

    /**
     * Removes all user data related to assessments (including allocations).
     *
     * This includes assessments of example submissions as long as they are not
     * referential assessments.
     *
     * @param stdClass $data The actual course reset settings.
     * @return bool|string True on success, error message otherwise.
     */
    protected function reset_userdata_assessments(stdClass $data) {
        global $DB;

        $sql = "SELECT a.id
                  FROM {teamwork_assessments} a
                  JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
                 WHERE s.teamworkid = :teamworkid
                       AND (s.example = 0 OR (s.example = 1 AND a.weight = 0))";

        $assessments = $DB->get_records_sql($sql, array('teamworkid' => $this->id));
        $this->delete_assessment(array_keys($assessments));

        $DB->delete_records('teamwork_aggregations', array('teamworkid' => $this->id));

        return true;
    }

    /**
     * Removes all user data related to participants' submissions.
     *
     * @param stdClass $data The actual course reset settings.
     * @return bool|string True on success, error message otherwise.
     */
    protected function reset_userdata_submissions(stdClass $data) {
        global $DB;

        $submissions = $this->get_submissions();
        foreach ($submissions as $submission) {
            $this->delete_submission($submission);
        }

        return true;
    }

    /**
     * Hard set the teamwork phase to the setup one.
     */
    protected function reset_phase() {
        global $DB;

        $DB->set_field('teamwork', 'phase', self::PHASE_SETUP, array('id' => $this->id));
        $this->phase = self::PHASE_SETUP;
    }
}


////////////////////////////////////////////////////////////////////////////////
// Renderable components
////////////////////////////////////////////////////////////////////////////////

/**
 * Overview of templets list
 *
 * Templets list contains several templets. Each templet contains templet header, templet introduce, and a "join in"
 * button.
 */

class teamwork_templet_list implements renderable {
    /** @var teamwork */
    public $teamwork;
    /** @var array of (stdclass)templets*/
    public $container = array();

    /**
     * Prepare an tasks list for the given teamwork moudle.
     *
     * @param teamwork $teamwork instance
     */
    public function __construct(teamwork $teamwork) {
        //global $DB;
        // TODO
        $this->teamwork = $teamwork->id;

        //$this->container = $DB->get_record_list('teamwork_templet', 'teamwork', array($this->teamwork));
    }
}

/**
 * Represents the user planner tool
 *
 * Planner contains list of phases. Each phase contains list of tasks. Task is a simple object with
 * title, link and completed (true/false/null logic).
 */
class teamwork_user_plan implements renderable {

    /** @var int id of the user this plan is for */
    public $userid;
    /** @var teamwork */
    public $teamwork;
    /** @var array of (stdclass)tasks */
    public $phases = array();
    /** @var null|array of example submissions to be assessed by the planner owner */
    protected $examples = null;

    /**
     * Prepare an individual teamwork plan for the given user.
     *
     * @param teamwork $teamwork instance
     * @param int $userid whom the plan is prepared for
     */
    public function __construct(teamwork $teamwork, $userid) {
        global $DB;

        $this->teamwork = $teamwork;
        $this->userid   = $userid;

        //---------------------------------------------------------
        // * SETUP | submission | assessment | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phasesetup', 'teamwork');
        $phase->tasks = array();
        if (has_capability('moodle/course:manageactivities', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskintro', 'teamwork');
            $task->link = $teamwork->updatemod_url();
            $task->completed = !(trim($teamwork->intro) == '');
            $phase->tasks['intro'] = $task;
        }
        if (has_capability('moodle/course:manageactivities', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskinstructauthors', 'teamwork');
            $task->link = $teamwork->updatemod_url();
            $task->completed = !(trim($teamwork->instructauthors) == '');
            $phase->tasks['instructauthors'] = $task;
        }
        if (has_capability('mod/teamwork:editdimensions', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('editassessmentform', 'teamwork');
            $task->link = $teamwork->editform_url();
            if ($teamwork->grading_strategy_instance()->form_ready()) {
                $task->completed = true;
            } elseif ($teamwork->phase > teamwork::PHASE_SETUP) {
                $task->completed = false;
            }
            $phase->tasks['editform'] = $task;
        }
        if ($teamwork->useexamples and has_capability('mod/teamwork:manageexamples', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('prepareexamples', 'teamwork');
            if ($DB->count_records('teamwork_submissions', array('example' => 1, 'teamworkid' => $teamwork->id)) > 0) {
                $task->completed = true;
            } elseif ($teamwork->phase > teamwork::PHASE_SETUP) {
                $task->completed = false;
            }
            $phase->tasks['prepareexamples'] = $task;
        }
        if (empty($phase->tasks) and $teamwork->phase == teamwork::PHASE_SETUP) {
            // if we are in the setup phase and there is no task (typical for students), let us
            // display some explanation what is going on
            $task = new stdclass();
            $task->title = get_string('undersetup', 'teamwork');
            $task->completed = 'info';
            $phase->tasks['setupinfo'] = $task;
        }
        $this->phases[teamwork::PHASE_SETUP] = $phase;

        //---------------------------------------------------------
        // setup | * SUBMISSION | assessment | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phasesubmission', 'teamwork');
        $phase->tasks = array();
        if (has_capability('moodle/course:manageactivities', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskinstructreviewers', 'teamwork');
            $task->link = $teamwork->updatemod_url();
            if (trim($teamwork->instructreviewers)) {
                $task->completed = true;
            } elseif ($teamwork->phase >= teamwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['instructreviewers'] = $task;
        }
        if ($teamwork->useexamples and $teamwork->examplesmode == teamwork::EXAMPLES_BEFORE_SUBMISSION
                and has_capability('mod/teamwork:submit', $teamwork->context, $userid, false)
                    and !has_capability('mod/teamwork:manageexamples', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('exampleassesstask', 'teamwork');
            $examples = $this->get_examples();
            $a = new stdclass();
            $a->expected = count($examples);
            $a->assessed = 0;
            foreach ($examples as $exampleid => $example) {
                if (!is_null($example->grade)) {
                    $a->assessed++;
                }
            }
            $task->details = get_string('exampleassesstaskdetails', 'teamwork', $a);
            if ($a->assessed == $a->expected) {
                $task->completed = true;
            } elseif ($teamwork->phase >= teamwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['examples'] = $task;
        }
        if (has_capability('mod/teamwork:submit', $teamwork->context, $userid, false)) {
            $task = new stdclass();
            $task->title = get_string('tasksubmit', 'teamwork');
            $task->link = $teamwork->submission_url();
            if ($DB->record_exists('teamwork_submissions', array('teamworkid'=>$teamwork->id, 'example'=>0, 'authorid'=>$userid))) {
                $task->completed = true;
            } elseif ($teamwork->phase >= teamwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            } else {
                $task->completed = null;    // still has a chance to submit
            }
            $phase->tasks['submit'] = $task;
        }
        if (has_capability('mod/teamwork:allocate', $teamwork->context, $userid)) {
            if ($teamwork->phaseswitchassessment) {
                $task = new stdClass();
                $allocator = $DB->get_record('teamworkallocation_scheduled', array('teamworkid' => $teamwork->id));
                if (empty($allocator)) {
                    $task->completed = false;
                } else if ($allocator->enabled and is_null($allocator->resultstatus)) {
                    $task->completed = true;
                } else if ($teamwork->submissionend > time()) {
                    $task->completed = null;
                } else {
                    $task->completed = false;
                }
                $task->title = get_string('setup', 'teamworkallocation_scheduled');
                $task->link = $teamwork->allocation_url('scheduled');
                $phase->tasks['allocatescheduled'] = $task;
            }
            $task = new stdclass();
            $task->title = get_string('allocate', 'teamwork');
            $task->link = $teamwork->allocation_url();
            $numofauthors = $teamwork->count_potential_authors(false);
            $numofsubmissions = $DB->count_records('teamwork_submissions', array('teamworkid'=>$teamwork->id, 'example'=>0));
            $sql = 'SELECT COUNT(s.id) AS nonallocated
                      FROM {teamwork_submissions} s
                 LEFT JOIN {teamwork_assessments} a ON (a.submissionid=s.id)
                     WHERE s.teamworkid = :teamworkid AND s.example=0 AND a.submissionid IS NULL';
            $params['teamworkid'] = $teamwork->id;
            $numnonallocated = $DB->count_records_sql($sql, $params);
            if ($numofsubmissions == 0) {
                $task->completed = null;
            } elseif ($numnonallocated == 0) {
                $task->completed = true;
            } elseif ($teamwork->phase > teamwork::PHASE_SUBMISSION) {
                $task->completed = false;
            } else {
                $task->completed = null;    // still has a chance to allocate
            }
            $a = new stdclass();
            $a->expected    = $numofauthors;
            $a->submitted   = $numofsubmissions;
            $a->allocate    = $numnonallocated;
            $task->details  = get_string('allocatedetails', 'teamwork', $a);
            unset($a);
            $phase->tasks['allocate'] = $task;

            if ($numofsubmissions < $numofauthors and $teamwork->phase >= teamwork::PHASE_SUBMISSION) {
                $task = new stdclass();
                $task->title = get_string('someuserswosubmission', 'teamwork');
                $task->completed = 'info';
                $phase->tasks['allocateinfo'] = $task;
            }

        }
        if ($teamwork->submissionstart) {
            $task = new stdclass();
            $task->title = get_string('submissionstartdatetime', 'teamwork', teamwork::timestamp_formats($teamwork->submissionstart));
            $task->completed = 'info';
            $phase->tasks['submissionstartdatetime'] = $task;
        }
        if ($teamwork->submissionend) {
            $task = new stdclass();
            $task->title = get_string('submissionenddatetime', 'teamwork', teamwork::timestamp_formats($teamwork->submissionend));
            $task->completed = 'info';
            $phase->tasks['submissionenddatetime'] = $task;
        }
        if (($teamwork->submissionstart < time()) and $teamwork->latesubmissions) {
            $task = new stdclass();
            $task->title = get_string('latesubmissionsallowed', 'teamwork');
            $task->completed = 'info';
            $phase->tasks['latesubmissionsallowed'] = $task;
        }
        if (isset($phase->tasks['submissionstartdatetime']) or isset($phase->tasks['submissionenddatetime'])) {
            if (has_capability('mod/teamwork:ignoredeadlines', $teamwork->context, $userid)) {
                $task = new stdclass();
                $task->title = get_string('deadlinesignored', 'teamwork');
                $task->completed = 'info';
                $phase->tasks['deadlinesignored'] = $task;
            }
        }
        $this->phases[teamwork::PHASE_SUBMISSION] = $phase;

        //---------------------------------------------------------
        // setup | submission | * ASSESSMENT | evaluation | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseassessment', 'teamwork');
        $phase->tasks = array();
        $phase->isreviewer = has_capability('mod/teamwork:peerassess', $teamwork->context, $userid);
        if ($teamwork->phase == teamwork::PHASE_SUBMISSION and $teamwork->phaseswitchassessment
                and has_capability('mod/teamwork:switchphase', $teamwork->context, $userid)) {
            $task = new stdClass();
            $task->title = get_string('switchphase30auto', 'mod_teamwork', teamwork::timestamp_formats($teamwork->submissionend));
            $task->completed = 'info';
            $phase->tasks['autoswitchinfo'] = $task;
        }
        if ($teamwork->useexamples and $teamwork->examplesmode == teamwork::EXAMPLES_BEFORE_ASSESSMENT
                and $phase->isreviewer and !has_capability('mod/teamwork:manageexamples', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('exampleassesstask', 'teamwork');
            $examples = $teamwork->get_examples_for_reviewer($userid);
            $a = new stdclass();
            $a->expected = count($examples);
            $a->assessed = 0;
            foreach ($examples as $exampleid => $example) {
                if (!is_null($example->grade)) {
                    $a->assessed++;
                }
            }
            $task->details = get_string('exampleassesstaskdetails', 'teamwork', $a);
            if ($a->assessed == $a->expected) {
                $task->completed = true;
            } elseif ($teamwork->phase > teamwork::PHASE_ASSESSMENT) {
                $task->completed = false;
            }
            $phase->tasks['examples'] = $task;
        }
        if (empty($phase->tasks['examples']) or !empty($phase->tasks['examples']->completed)) {
            $phase->assessments = $teamwork->get_assessments_by_reviewer($userid);
            $numofpeers     = 0;    // number of allocated peer-assessments
            $numofpeerstodo = 0;    // number of peer-assessments to do
            $numofself      = 0;    // number of allocated self-assessments - should be 0 or 1
            $numofselftodo  = 0;    // number of self-assessments to do - should be 0 or 1
            foreach ($phase->assessments as $a) {
                if ($a->authorid == $userid) {
                    $numofself++;
                    if (is_null($a->grade)) {
                        $numofselftodo++;
                    }
                } else {
                    $numofpeers++;
                    if (is_null($a->grade)) {
                        $numofpeerstodo++;
                    }
                }
            }
            unset($a);
            if ($numofpeers) {
                $task = new stdclass();
                if ($numofpeerstodo == 0) {
                    $task->completed = true;
                } elseif ($teamwork->phase > teamwork::PHASE_ASSESSMENT) {
                    $task->completed = false;
                }
                $a = new stdclass();
                $a->total = $numofpeers;
                $a->todo  = $numofpeerstodo;
                $task->title = get_string('taskassesspeers', 'teamwork');
                $task->details = get_string('taskassesspeersdetails', 'teamwork', $a);
                unset($a);
                $phase->tasks['assesspeers'] = $task;
            }
            if ($teamwork->useselfassessment and $numofself) {
                $task = new stdclass();
                if ($numofselftodo == 0) {
                    $task->completed = true;
                } elseif ($teamwork->phase > teamwork::PHASE_ASSESSMENT) {
                    $task->completed = false;
                }
                $task->title = get_string('taskassessself', 'teamwork');
                $phase->tasks['assessself'] = $task;
            }
        }
        if ($teamwork->assessmentstart) {
            $task = new stdclass();
            $task->title = get_string('assessmentstartdatetime', 'teamwork', teamwork::timestamp_formats($teamwork->assessmentstart));
            $task->completed = 'info';
            $phase->tasks['assessmentstartdatetime'] = $task;
        }
        if ($teamwork->assessmentend) {
            $task = new stdclass();
            $task->title = get_string('assessmentenddatetime', 'teamwork', teamwork::timestamp_formats($teamwork->assessmentend));
            $task->completed = 'info';
            $phase->tasks['assessmentenddatetime'] = $task;
        }
        if (isset($phase->tasks['assessmentstartdatetime']) or isset($phase->tasks['assessmentenddatetime'])) {
            if (has_capability('mod/teamwork:ignoredeadlines', $teamwork->context, $userid)) {
                $task = new stdclass();
                $task->title = get_string('deadlinesignored', 'teamwork');
                $task->completed = 'info';
                $phase->tasks['deadlinesignored'] = $task;
            }
        }
        $this->phases[teamwork::PHASE_ASSESSMENT] = $phase;

        //---------------------------------------------------------
        // setup | submission | assessment | * EVALUATION | closed
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseevaluation', 'teamwork');
        $phase->tasks = array();
        if (has_capability('mod/teamwork:overridegrades', $teamwork->context)) {
            $expected = $teamwork->count_potential_authors(false);
            $calculated = $DB->count_records_select('teamwork_submissions',
                    'teamworkid = ? AND (grade IS NOT NULL OR gradeover IS NOT NULL)', array($teamwork->id));
            $task = new stdclass();
            $task->title = get_string('calculatesubmissiongrades', 'teamwork');
            $a = new stdclass();
            $a->expected    = $expected;
            $a->calculated  = $calculated;
            $task->details  = get_string('calculatesubmissiongradesdetails', 'teamwork', $a);
            if ($calculated >= $expected) {
                $task->completed = true;
            } elseif ($teamwork->phase > teamwork::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['calculatesubmissiongrade'] = $task;

            $expected = $teamwork->count_potential_reviewers(false);
            $calculated = $DB->count_records_select('teamwork_aggregations',
                    'teamworkid = ? AND gradinggrade IS NOT NULL', array($teamwork->id));
            $task = new stdclass();
            $task->title = get_string('calculategradinggrades', 'teamwork');
            $a = new stdclass();
            $a->expected    = $expected;
            $a->calculated  = $calculated;
            $task->details  = get_string('calculategradinggradesdetails', 'teamwork', $a);
            if ($calculated >= $expected) {
                $task->completed = true;
            } elseif ($teamwork->phase > teamwork::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['calculategradinggrade'] = $task;

        } elseif ($teamwork->phase == teamwork::PHASE_EVALUATION) {
            $task = new stdclass();
            $task->title = get_string('evaluategradeswait', 'teamwork');
            $task->completed = 'info';
            $phase->tasks['evaluateinfo'] = $task;
        }

        if (has_capability('moodle/course:manageactivities', $teamwork->context, $userid)) {
            $task = new stdclass();
            $task->title = get_string('taskconclusion', 'teamwork');
            $task->link = $teamwork->updatemod_url();
            if (trim($teamwork->conclusion)) {
                $task->completed = true;
            } elseif ($teamwork->phase >= teamwork::PHASE_EVALUATION) {
                $task->completed = false;
            }
            $phase->tasks['conclusion'] = $task;
        }

        $this->phases[teamwork::PHASE_EVALUATION] = $phase;

        //---------------------------------------------------------
        // setup | submission | assessment | evaluation | * CLOSED
        //---------------------------------------------------------
        $phase = new stdclass();
        $phase->title = get_string('phaseclosed', 'teamwork');
        $phase->tasks = array();
        $this->phases[teamwork::PHASE_CLOSED] = $phase;

        // Polish data, set default values if not done explicitly
        foreach ($this->phases as $phasecode => $phase) {
            $phase->title       = isset($phase->title)      ? $phase->title     : '';
            $phase->tasks       = isset($phase->tasks)      ? $phase->tasks     : array();
            if ($phasecode == $teamwork->phase) {
                $phase->active = true;
            } else {
                $phase->active = false;
            }
            if (!isset($phase->actions)) {
                $phase->actions = array();
            }

            foreach ($phase->tasks as $taskcode => $task) {
                $task->title        = isset($task->title)       ? $task->title      : '';
                $task->link         = isset($task->link)        ? $task->link       : null;
                $task->details      = isset($task->details)     ? $task->details    : '';
                $task->completed    = isset($task->completed)   ? $task->completed  : null;
            }
        }

        // Add phase switching actions
        if (has_capability('mod/teamwork:switchphase', $teamwork->context, $userid)) {
            foreach ($this->phases as $phasecode => $phase) {
                if (! $phase->active) {
                    $action = new stdclass();
                    $action->type = 'switchphase';
                    $action->url  = $teamwork->switchphase_url($phasecode);
                    $phase->actions[] = $action;
                }
            }
        }
    }

    /**
     * Returns example submissions to be assessed by the owner of the planner
     *
     * This is here to cache the DB query because the same list is needed later in view.php
     *
     * @see teamwork::get_examples_for_reviewer() for the format of returned value
     * @return array
     */
    public function get_examples() {
        if (is_null($this->examples)) {
            $this->examples = $this->teamwork->get_examples_for_reviewer($this->userid);
        }
        return $this->examples;
    }
}

/**
 * Common base class for submissions and example submissions rendering
 *
 * Subclasses of this class convert raw submission record from
 * teamwork_submissions table (as returned by {@see teamwork::get_submission_by_id()}
 * for example) into renderable objects.
 */
abstract class teamwork_submission_base {

    /** @var bool is the submission anonymous (i.e. contains author information) */
    protected $anonymous;

    /* @var array of columns from teamwork_submissions that are assigned as properties */
    protected $fields = array();

    /** @var teamwork */
    protected $teamwork;

    /**
     * Copies the properties of the given database record into properties of $this instance
     *
     * @param teamwork $teamwork
     * @param stdClass $submission full record
     * @param bool $showauthor show the author-related information
     * @param array $options additional properties
     */
    public function __construct(teamwork $teamwork, stdClass $submission, $showauthor = false) {

        $this->teamwork = $teamwork;

        foreach ($this->fields as $field) {
            if (!property_exists($submission, $field)) {
                throw new coding_exception('Submission record must provide public property ' . $field);
            }
            if (!property_exists($this, $field)) {
                throw new coding_exception('Renderable component must accept public property ' . $field);
            }
            $this->{$field} = $submission->{$field};
        }

        if ($showauthor) {
            $this->anonymous = false;
        } else {
            $this->anonymize();
        }
    }

    /**
     * Unsets all author-related properties so that the renderer does not have access to them
     *
     * Usually this is called by the contructor but can be called explicitely, too.
     */
    public function anonymize() {
        $authorfields = explode(',', user_picture::fields());
        foreach ($authorfields as $field) {
            $prefixedusernamefield = 'author' . $field;
            unset($this->{$prefixedusernamefield});
        }
        $this->anonymous = true;
    }

    /**
     * Does the submission object contain author-related information?
     *
     * @return null|boolean
     */
    public function is_anonymous() {
        return $this->anonymous;
    }
}

/**
 * Renderable object containing a basic set of information needed to display the submission summary
 *
 * @see teamwork_renderer::render_teamwork_submission_summary
 */
class teamwork_submission_summary extends teamwork_submission_base implements renderable {

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string graded|notgraded */
    public $status;
    /** @var int */
    public $timecreated;
    /** @var int */
    public $timemodified;
    /** @var int */
    public $authorid;
    /** @var string */
    public $authorfirstname;
    /** @var string */
    public $authorlastname;
    /** @var string */
    public $authorfirstnamephonetic;
    /** @var string */
    public $authorlastnamephonetic;
    /** @var string */
    public $authormiddlename;
    /** @var string */
    public $authoralternatename;
    /** @var int */
    public $authorpicture;
    /** @var string */
    public $authorimagealt;
    /** @var string */
    public $authoremail;
    /** @var moodle_url to display submission */
    public $url;

    /**
     * @var array of columns from teamwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array(
        'id', 'title', 'timecreated', 'timemodified',
        'authorid', 'authorfirstname', 'authorlastname', 'authorfirstnamephonetic', 'authorlastnamephonetic',
        'authormiddlename', 'authoralternatename', 'authorpicture',
        'authorimagealt', 'authoremail');
}

/**
 * Renderable object containing all the information needed to display the submission
 *
 * @see teamwork_renderer::render_teamwork_submission()
 */
class teamwork_submission extends teamwork_submission_summary implements renderable {

    /** @var string */
    public $content;
    /** @var int */
    public $contentformat;
    /** @var bool */
    public $contenttrust;
    /** @var array */
    public $attachment;

    /**
     * @var array of columns from teamwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array(
        'id', 'title', 'timecreated', 'timemodified', 'content', 'contentformat', 'contenttrust',
        'attachment', 'authorid', 'authorfirstname', 'authorlastname', 'authorfirstnamephonetic', 'authorlastnamephonetic',
        'authormiddlename', 'authoralternatename', 'authorpicture', 'authorimagealt', 'authoremail');
}

/**
 * Renderable object containing a basic set of information needed to display the example submission summary
 *
 * @see teamwork::prepare_example_summary()
 * @see teamwork_renderer::render_teamwork_example_submission_summary()
 */
class teamwork_example_submission_summary extends teamwork_submission_base implements renderable {

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string graded|notgraded */
    public $status;
    /** @var stdClass */
    public $gradeinfo;
    /** @var moodle_url */
    public $url;
    /** @var moodle_url */
    public $editurl;
    /** @var string */
    public $assesslabel;
    /** @var moodle_url */
    public $assessurl;
    /** @var bool must be set explicitly by the caller */
    public $editable = false;

    /**
     * @var array of columns from teamwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array('id', 'title');

    /**
     * Example submissions are always anonymous
     *
     * @return true
     */
    public function is_anonymous() {
        return true;
    }
}

/**
 * Renderable object containing all the information needed to display the example submission
 *
 * @see teamwork_renderer::render_teamwork_example_submission()
 */
class teamwork_example_submission extends teamwork_example_submission_summary implements renderable {

    /** @var string */
    public $content;
    /** @var int */
    public $contentformat;
    /** @var bool */
    public $contenttrust;
    /** @var array */
    public $attachment;

    /**
     * @var array of columns from teamwork_submissions that are assigned as properties
     * of instances of this class
     */
    protected $fields = array('id', 'title', 'content', 'contentformat', 'contenttrust', 'attachment');
}


/**
 * Common base class for assessments rendering
 *
 * Subclasses of this class convert raw assessment record from
 * teamwork_assessments table (as returned by {@see teamwork::get_assessment_by_id()}
 * for example) into renderable objects.
 */
abstract class teamwork_assessment_base {

    /** @var string the optional title of the assessment */
    public $title = '';

    /** @var teamwork_assessment_form $form as returned by {@link teamwork_strategy::get_assessment_form()} */
    public $form;

    /** @var moodle_url */
    public $url;

    /** @var float|null the real received grade */
    public $realgrade = null;

    /** @var float the real maximum grade */
    public $maxgrade;

    /** @var stdClass|null reviewer user info */
    public $reviewer = null;

    /** @var stdClass|null assessed submission's author user info */
    public $author = null;

    /** @var array of actions */
    public $actions = array();

    /* @var array of columns that are assigned as properties */
    protected $fields = array();

    /** @var teamwork */
    protected $teamwork;

    /**
     * Copies the properties of the given database record into properties of $this instance
     *
     * The $options keys are: showreviewer, showauthor
     * @param teamwork $teamwork
     * @param stdClass $assessment full record
     * @param array $options additional properties
     */
    public function __construct(teamwork $teamwork, stdClass $record, array $options = array()) {

        $this->teamwork = $teamwork;
        $this->validate_raw_record($record);

        foreach ($this->fields as $field) {
            if (!property_exists($record, $field)) {
                throw new coding_exception('Assessment record must provide public property ' . $field);
            }
            if (!property_exists($this, $field)) {
                throw new coding_exception('Renderable component must accept public property ' . $field);
            }
            $this->{$field} = $record->{$field};
        }

        if (!empty($options['showreviewer'])) {
            $this->reviewer = user_picture::unalias($record, null, 'revieweridx', 'reviewer');
        }

        if (!empty($options['showauthor'])) {
            $this->author = user_picture::unalias($record, null, 'authorid', 'author');
        }
    }

    /**
     * Adds a new action
     *
     * @param moodle_url $url action URL
     * @param string $label action label
     * @param string $method get|post
     */
    public function add_action(moodle_url $url, $label, $method = 'get') {

        $action = new stdClass();
        $action->url = $url;
        $action->label = $label;
        $action->method = $method;

        $this->actions[] = $action;
    }

    /**
     * Makes sure that we can cook the renderable component from the passed raw database record
     *
     * @param stdClass $assessment full assessment record
     * @throws coding_exception if the caller passed unexpected data
     */
    protected function validate_raw_record(stdClass $record) {
        // nothing to do here
    }
}


/**
 * Represents a rendarable full assessment
 */
class teamwork_assessment extends teamwork_assessment_base implements renderable {

    /** @var int */
    public $id;

    /** @var int */
    public $submissionid;

    /** @var int */
    public $weight;

    /** @var int */
    public $timecreated;

    /** @var int */
    public $timemodified;

    /** @var float */
    public $grade;

    /** @var float */
    public $gradinggrade;

    /** @var float */
    public $gradinggradeover;

    /** @var string */
    public $feedbackauthor;

    /** @var int */
    public $feedbackauthorformat;

    /** @var int */
    public $feedbackauthorattachment;

    /** @var array */
    protected $fields = array('id', 'submissionid', 'weight', 'timecreated',
        'timemodified', 'grade', 'gradinggrade', 'gradinggradeover', 'feedbackauthor',
        'feedbackauthorformat', 'feedbackauthorattachment');

    /**
     * Format the overall feedback text content
     *
     * False is returned if the overall feedback feature is disabled. Null is returned
     * if the overall feedback content has not been found. Otherwise, string with
     * formatted feedback text is returned.
     *
     * @return string|bool|null
     */
    public function get_overall_feedback_content() {

        if ($this->teamwork->overallfeedbackmode == 0) {
            return false;
        }

        if (trim($this->feedbackauthor) === '') {
            return null;
        }

        $content = file_rewrite_pluginfile_urls($this->feedbackauthor, 'pluginfile.php', $this->teamwork->context->id,
            'mod_teamwork', 'overallfeedback_content', $this->id);
        $content = format_text($content, $this->feedbackauthorformat,
            array('overflowdiv' => true, 'context' => $this->teamwork->context));

        return $content;
    }

    /**
     * Prepares the list of overall feedback attachments
     *
     * Returns false if overall feedback attachments are not allowed. Otherwise returns
     * list of attachments (may be empty).
     *
     * @return bool|array of stdClass
     */
    public function get_overall_feedback_attachments() {

        if ($this->teamwork->overallfeedbackmode == 0) {
            return false;
        }

        if ($this->teamwork->overallfeedbackfiles == 0) {
            return false;
        }

        if (empty($this->feedbackauthorattachment)) {
            return array();
        }

        $attachments = array();
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->teamwork->context->id, 'mod_teamwork', 'overallfeedback_attachment', $this->id);
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filepath = $file->get_filepath();
            $filename = $file->get_filename();
            $fileurl = moodle_url::make_pluginfile_url($this->teamwork->context->id, 'mod_teamwork',
                'overallfeedback_attachment', $this->id, $filepath, $filename, true);
            $previewurl = new moodle_url(moodle_url::make_pluginfile_url($this->teamwork->context->id, 'mod_teamwork',
                'overallfeedback_attachment', $this->id, $filepath, $filename, false), array('preview' => 'bigthumb'));
            $attachments[] = (object)array(
                'filepath' => $filepath,
                'filename' => $filename,
                'fileurl' => $fileurl,
                'previewurl' => $previewurl,
                'mimetype' => $file->get_mimetype(),

            );
        }

        return $attachments;
    }
}


/**
 * Represents a renderable training assessment of an example submission
 */
class teamwork_example_assessment extends teamwork_assessment implements renderable {

    /**
     * @see parent::validate_raw_record()
     */
    protected function validate_raw_record(stdClass $record) {
        if ($record->weight != 0) {
            throw new coding_exception('Invalid weight of example submission assessment');
        }
        parent::validate_raw_record($record);
    }
}


/**
 * Represents a renderable reference assessment of an example submission
 */
class teamwork_example_reference_assessment extends teamwork_assessment implements renderable {

    /**
     * @see parent::validate_raw_record()
     */
    protected function validate_raw_record(stdClass $record) {
        if ($record->weight != 1) {
            throw new coding_exception('Invalid weight of the reference example submission assessment');
        }
        parent::validate_raw_record($record);
    }
}


/**
 * Renderable message to be displayed to the user
 *
 * Message can contain an optional action link with a label that is supposed to be rendered
 * as a button or a link.
 *
 * @see teamwork::renderer::render_teamwork_message()
 */
class teamwork_message implements renderable {

    const TYPE_INFO     = 10;
    const TYPE_OK       = 20;
    const TYPE_ERROR    = 30;

    /** @var string */
    protected $text = '';
    /** @var int */
    protected $type = self::TYPE_INFO;
    /** @var moodle_url */
    protected $actionurl = null;
    /** @var string */
    protected $actionlabel = '';

    /**
     * @param string $text short text to be displayed
     * @param string $type optional message type info|ok|error
     */
    public function __construct($text = null, $type = self::TYPE_INFO) {
        $this->set_text($text);
        $this->set_type($type);
    }

    /**
     * Sets the message text
     *
     * @param string $text short text to be displayed
     */
    public function set_text($text) {
        $this->text = $text;
    }

    /**
     * Sets the message type
     *
     * @param int $type
     */
    public function set_type($type = self::TYPE_INFO) {
        if (in_array($type, array(self::TYPE_OK, self::TYPE_ERROR, self::TYPE_INFO))) {
            $this->type = $type;
        } else {
            throw new coding_exception('Unknown message type.');
        }
    }

    /**
     * Sets the optional message action
     *
     * @param moodle_url $url to follow on action
     * @param string $label action label
     */
    public function set_action(moodle_url $url, $label) {
        $this->actionurl    = $url;
        $this->actionlabel  = $label;
    }

    /**
     * Returns message text with HTML tags quoted
     *
     * @return string
     */
    public function get_message() {
        return s($this->text);
    }

    /**
     * Returns message type
     *
     * @return int
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Returns action URL
     *
     * @return moodle_url|null
     */
    public function get_action_url() {
        return $this->actionurl;
    }

    /**
     * Returns action label
     *
     * @return string
     */
    public function get_action_label() {
        return $this->actionlabel;
    }
}


/**
 * Renderable component containing all the data needed to display the grading report
 */
class teamwork_grading_report implements renderable {

    /** @var stdClass returned by {@see teamwork::prepare_grading_report_data()} */
    protected $data;
    /** @var stdClass rendering options */
    protected $options;

    /**
     * Grades in $data must be already rounded to the set number of decimals or must be null
     * (in which later case, the [mod_teamwork,nullgrade] string shall be displayed)
     *
     * @param stdClass $data prepared by {@link teamwork::prepare_grading_report_data()}
     * @param stdClass $options display options (showauthornames, showreviewernames, sortby, sorthow, showsubmissiongrade, showgradinggrade)
     */
    public function __construct(stdClass $data, stdClass $options) {
        $this->data     = $data;
        $this->options  = $options;
    }

    /**
     * @return stdClass grading report data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * @return stdClass rendering options
     */
    public function get_options() {
        return $this->options;
    }
}


/**
 * Base class for renderable feedback for author and feedback for reviewer
 */
abstract class teamwork_feedback {

    /** @var stdClass the user info */
    protected $provider = null;

    /** @var string the feedback text */
    protected $content = null;

    /** @var int format of the feedback text */
    protected $format = null;

    /**
     * @return stdClass the user info
     */
    public function get_provider() {

        if (is_null($this->provider)) {
            throw new coding_exception('Feedback provider not set');
        }

        return $this->provider;
    }

    /**
     * @return string the feedback text
     */
    public function get_content() {

        if (is_null($this->content)) {
            throw new coding_exception('Feedback content not set');
        }

        return $this->content;
    }

    /**
     * @return int format of the feedback text
     */
    public function get_format() {

        if (is_null($this->format)) {
            throw new coding_exception('Feedback text format not set');
        }

        return $this->format;
    }
}


/**
 * Renderable feedback for the author of submission
 */
class teamwork_feedback_author extends teamwork_feedback implements renderable {

    /**
     * Extracts feedback from the given submission record
     *
     * @param stdClass $submission record as returned by {@see self::get_submission_by_id()}
     */
    public function __construct(stdClass $submission) {

        $this->provider = user_picture::unalias($submission, null, 'gradeoverbyx', 'gradeoverby');
        $this->content  = $submission->feedbackauthor;
        $this->format   = $submission->feedbackauthorformat;
    }
}


/**
 * Renderable feedback for the reviewer
 */
class teamwork_feedback_reviewer extends teamwork_feedback implements renderable {

    /**
     * Extracts feedback from the given assessment record
     *
     * @param stdClass $assessment record as returned by eg {@see self::get_assessment_by_id()}
     */
    public function __construct(stdClass $assessment) {

        $this->provider = user_picture::unalias($assessment, null, 'gradinggradeoverbyx', 'overby');
        $this->content  = $assessment->feedbackreviewer;
        $this->format   = $assessment->feedbackreviewerformat;
    }
}


/**
 * Holds the final grades for the activity as are stored in the gradebook
 */
class teamwork_final_grades implements renderable {

    /** @var object the info from the gradebook about the grade for submission */
    public $submissiongrade = null;

    /** @var object the infor from the gradebook about the grade for assessment */
    public $assessmentgrade = null;
}

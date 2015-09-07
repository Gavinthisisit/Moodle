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
 * Allows user to allocate the submissions manually
 *
 * @package    teamworkallocation
 * @subpackage manual
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/lib.php');                  // interface definition
require_once(dirname(dirname(dirname(__FILE__))) . '/locallib.php');    // teamwork internal API

/**
 * Allows users to allocate submissions for review manually
 */
class teamwork_manual_allocator implements teamwork_allocator {

    /** constants that are used to pass status messages between init() and ui() */
    const MSG_ADDED         = 1;
    const MSG_NOSUBMISSION  = 2;
    const MSG_EXISTS        = 3;
    const MSG_CONFIRM_DEL   = 4;
    const MSG_DELETED       = 5;
    const MSG_DELETE_ERROR  = 6;

    /** @var teamwork instance */
    protected $teamwork;

    /**
     * @param teamwork $teamwork Teamwork API object
     */
    public function __construct(teamwork $teamwork) {
        $this->teamwork = $teamwork;
    }

    /**
     * Allocate submissions as requested by user
     *
     * @return teamwork_allocation_result
     */
    public function init() {
        global $PAGE;

        $mode = optional_param('mode', 'display', PARAM_ALPHA);
        $perpage = optional_param('perpage', null, PARAM_INT);

        if ($perpage and $perpage > 0 and $perpage <= 1000) {
            require_sesskey();
            set_user_preference('teamworkallocation_manual_perpage', $perpage);
            redirect($PAGE->url);
        }

        $result = new teamwork_allocation_result($this);

        switch ($mode) {
        case 'new':
            if (!confirm_sesskey()) {
                throw new moodle_exception('confirmsesskeybad');
            }
            $reviewerid = required_param('by', PARAM_INT);
            $authorid   = required_param('of', PARAM_INT);
            $m          = array();  // message object to be passed to the next page
            $submission = $this->teamwork->get_submission_by_author($authorid);
            if (!$submission) {
                // nothing submitted by the given user
                $m[] = self::MSG_NOSUBMISSION;
                $m[] = $authorid;

            } else {
                // ok, we have the submission
                $res = $this->teamwork->add_allocation($submission, $reviewerid);
                if ($res == teamwork::ALLOCATION_EXISTS) {
                    $m[] = self::MSG_EXISTS;
                    $m[] = $submission->authorid;
                    $m[] = $reviewerid;
                } else {
                    $m[] = self::MSG_ADDED;
                    $m[] = $submission->authorid;
                    $m[] = $reviewerid;
                }
            }
            $m = implode('-', $m);  // serialize message object to be passed via URL
            redirect($PAGE->url->out(false, array('m' => $m)));
            break;
        case 'del':
            if (!confirm_sesskey()) {
                throw new moodle_exception('confirmsesskeybad');
            }
            $assessmentid   = required_param('what', PARAM_INT);
            $confirmed      = optional_param('confirm', 0, PARAM_INT);
            $assessment     = $this->teamwork->get_assessment_by_id($assessmentid);
            if ($assessment) {
                if (!$confirmed) {
                    $m[] = self::MSG_CONFIRM_DEL;
                    $m[] = $assessment->id;
                    $m[] = $assessment->authorid;
                    $m[] = $assessment->reviewerid;
                    if (is_null($assessment->grade)) {
                        $m[] = 0;
                    } else {
                        $m[] = 1;
                    }
                } else {
                    if($this->teamwork->delete_assessment($assessment->id)) {
                        $m[] = self::MSG_DELETED;
                        $m[] = $assessment->authorid;
                        $m[] = $assessment->reviewerid;
                    } else {
                        $m[] = self::MSG_DELETE_ERROR;
                        $m[] = $assessment->authorid;
                        $m[] = $assessment->reviewerid;
                    }
                }
                $m = implode('-', $m);  // serialize message object to be passed via URL
                redirect($PAGE->url->out(false, array('m' => $m)));
            }
            break;
        }

        $result->set_status(teamwork_allocation_result::STATUS_VOID);
        return $result;
    }

    /**
     * Prints user interface - current allocation and a form to edit it
     */
    public function ui() {
        global $PAGE, $DB;

        $output     = $PAGE->get_renderer('teamworkallocation_manual');

        $page       = optional_param('page', 0, PARAM_INT);
        $perpage    = get_user_preferences('teamworkallocation_manual_perpage', 10);
        $groupid    = groups_get_activity_group($this->teamwork->cm, true);

        $hlauthorid     = -1;           // highlight this author
        $hlreviewerid   = -1;           // highlight this reviewer

        $message        = new teamwork_message();

        $m  = optional_param('m', '', PARAM_ALPHANUMEXT);   // message code
        if ($m) {
            $m = explode('-', $m);
            switch ($m[0]) {
            case self::MSG_ADDED:
                $hlauthorid     = $m[1];
                $hlreviewerid   = $m[2];
                $message        = new teamwork_message(get_string('allocationadded', 'teamworkallocation_manual'),
                    teamwork_message::TYPE_OK);
                break;
            case self::MSG_EXISTS:
                $hlauthorid     = $m[1];
                $hlreviewerid   = $m[2];
                $message        = new teamwork_message(get_string('allocationexists', 'teamworkallocation_manual'),
                    teamwork_message::TYPE_INFO);
                break;
            case self::MSG_NOSUBMISSION:
                $hlauthorid     = $m[1];
                $message        = new teamwork_message(get_string('nosubmissionfound', 'teamwork'),
                    teamwork_message::TYPE_ERROR);
                break;
            case self::MSG_CONFIRM_DEL:
                $hlauthorid     = $m[2];
                $hlreviewerid   = $m[3];
                if ($m[4] == 0) {
                    $message    = new teamwork_message(get_string('areyousuretodeallocate', 'teamworkallocation_manual'),
                        teamwork_message::TYPE_INFO);
                } else {
                    $message    = new teamwork_message(get_string('areyousuretodeallocategraded', 'teamworkallocation_manual'),
                        teamwork_message::TYPE_ERROR);
                }
                $url = new moodle_url($PAGE->url, array('mode' => 'del', 'what' => $m[1], 'confirm' => 1, 'sesskey' => sesskey()));
                $label = get_string('iamsure', 'teamwork');
                $message->set_action($url, $label);
                break;
            case self::MSG_DELETED:
                $hlauthorid     = $m[1];
                $hlreviewerid   = $m[2];
                $message        = new teamwork_message(get_string('assessmentdeleted', 'teamwork'),
                    teamwork_message::TYPE_OK);
                break;
            case self::MSG_DELETE_ERROR:
                $hlauthorid     = $m[1];
                $hlreviewerid   = $m[2];
                $message        = new teamwork_message(get_string('assessmentnotdeleted', 'teamwork'),
                    teamwork_message::TYPE_ERROR);
                break;
            }
        }

        // fetch the list of ids of all teamwork participants
        $numofparticipants = $this->teamwork->count_participants(false, $groupid);
        $participants = $this->teamwork->get_participants(false, $groupid, $perpage * $page, $perpage);

        if ($hlauthorid > 0 and $hlreviewerid > 0) {
            // display just those two users
            $participants = array_intersect_key($participants, array($hlauthorid => null, $hlreviewerid => null));
            $button = $output->single_button($PAGE->url, get_string('showallparticipants', 'teamworkallocation_manual'), 'get');
        } else {
            $button = '';
        }

        // this will hold the information needed to display user names and pictures
        $userinfo = $participants;

        // load the participants' submissions
        $submissions = $this->teamwork->get_submissions(array_keys($participants));
        $allnames = get_all_user_name_fields();
        foreach ($submissions as $submission) {
            if (!isset($userinfo[$submission->authorid])) {
                $userinfo[$submission->authorid]            = new stdclass();
                $userinfo[$submission->authorid]->id        = $submission->authorid;
                $userinfo[$submission->authorid]->picture   = $submission->authorpicture;
                $userinfo[$submission->authorid]->imagealt  = $submission->authorimagealt;
                $userinfo[$submission->authorid]->email     = $submission->authoremail;
                foreach ($allnames as $addname) {
                    $temp = 'author' . $addname;
                    $userinfo[$submission->authorid]->$addname = $submission->$temp;
                }
            }
        }

        // get current reviewers
        $reviewers = array();
        if ($submissions) {
            list($submissionids, $params) = $DB->get_in_or_equal(array_keys($submissions), SQL_PARAMS_NAMED);
            $picturefields = user_picture::fields('r', array(), 'reviewerid');
            $sql = "SELECT a.id AS assessmentid, a.submissionid, $picturefields,
                           s.id AS submissionid, s.authorid
                      FROM {teamwork_assessments} a
                      JOIN {user} r ON (a.reviewerid = r.id)
                      JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
                     WHERE a.submissionid $submissionids";
            $reviewers = $DB->get_records_sql($sql, $params);
            foreach ($reviewers as $reviewer) {
                if (!isset($userinfo[$reviewer->reviewerid])) {
                    $userinfo[$reviewer->reviewerid]            = new stdclass();
                    $userinfo[$reviewer->reviewerid]->id        = $reviewer->reviewerid;
                    $userinfo[$reviewer->reviewerid]->picture   = $reviewer->picture;
                    $userinfo[$reviewer->reviewerid]->imagealt  = $reviewer->imagealt;
                    $userinfo[$reviewer->reviewerid]->email     = $reviewer->email;
                    foreach ($allnames as $addname) {
                        $userinfo[$reviewer->reviewerid]->$addname = $reviewer->$addname;
                    }
                }
            }
        }

        // get current reviewees
        $reviewees = array();
        if ($participants) {
            list($participantids, $params) = $DB->get_in_or_equal(array_keys($participants), SQL_PARAMS_NAMED);
            $namefields = get_all_user_name_fields(true, 'e');
            $params['teamworkid'] = $this->teamwork->id;
            $sql = "SELECT a.id AS assessmentid, a.submissionid,
                           u.id AS reviewerid,
                           s.id AS submissionid,
                           e.id AS revieweeid, e.lastname, e.firstname, $namefields, e.picture, e.imagealt, e.email
                      FROM {user} u
                      JOIN {teamwork_assessments} a ON (a.reviewerid = u.id)
                      JOIN {teamwork_submissions} s ON (a.submissionid = s.id)
                      JOIN {user} e ON (s.authorid = e.id)
                     WHERE u.id $participantids AND s.teamworkid = :teamworkid AND s.example = 0";
            $reviewees = $DB->get_records_sql($sql, $params);
            foreach ($reviewees as $reviewee) {
                if (!isset($userinfo[$reviewee->revieweeid])) {
                    $userinfo[$reviewee->revieweeid]            = new stdclass();
                    $userinfo[$reviewee->revieweeid]->id        = $reviewee->revieweeid;
                    $userinfo[$reviewee->revieweeid]->firstname = $reviewee->firstname;
                    $userinfo[$reviewee->revieweeid]->lastname  = $reviewee->lastname;
                    $userinfo[$reviewee->revieweeid]->picture   = $reviewee->picture;
                    $userinfo[$reviewee->revieweeid]->imagealt  = $reviewee->imagealt;
                    $userinfo[$reviewee->revieweeid]->email     = $reviewee->email;
                    foreach ($allnames as $addname) {
                        $userinfo[$reviewee->revieweeid]->$addname = $reviewee->$addname;
                    }
                }
            }
        }

        // the information about the allocations
        $allocations = array();

        foreach ($participants as $participant) {
            $allocations[$participant->id] = new stdClass();
            $allocations[$participant->id]->userid = $participant->id;
            $allocations[$participant->id]->submissionid = null;
            $allocations[$participant->id]->reviewedby = array();
            $allocations[$participant->id]->reviewerof = array();
        }
        unset($participants);

        foreach ($submissions as $submission) {
            $allocations[$submission->authorid]->submissionid = $submission->id;
            $allocations[$submission->authorid]->submissiontitle = $submission->title;
            $allocations[$submission->authorid]->submissiongrade = $submission->grade;
        }
        unset($submissions);
        foreach($reviewers as $reviewer) {
            $allocations[$reviewer->authorid]->reviewedby[$reviewer->reviewerid] = $reviewer->assessmentid;
        }
        unset($reviewers);
        foreach($reviewees as $reviewee) {
            $allocations[$reviewee->reviewerid]->reviewerof[$reviewee->revieweeid] = $reviewee->assessmentid;
        }
        unset($reviewees);

        // prepare data to be rendered
        $data                   = new teamworkallocation_manual_allocations();
        $data->teamwork         = $this->teamwork;
        $data->allocations      = $allocations;
        $data->userinfo         = $userinfo;
        $data->authors          = $this->teamwork->get_potential_authors();
        $data->reviewers        = $this->teamwork->get_potential_reviewers();
        $data->hlauthorid       = $hlauthorid;
        $data->hlreviewerid     = $hlreviewerid;
        $data->selfassessment   = $this->teamwork->useselfassessment;

        // prepare the group selector
        $groupselector = $output->container(groups_print_activity_menu($this->teamwork->cm, $PAGE->url, true), 'groupwidget');

        // prepare paging bar
        $pagingbar              = new paging_bar($numofparticipants, $page, $perpage, $PAGE->url, 'page');
        $pagingbarout           = $output->render($pagingbar);
        $perpageselector        = $output->perpage_selector($perpage);

        return $groupselector . $pagingbarout . $output->render($message) . $output->render($data) . $button . $pagingbarout . $perpageselector;
    }

    /**
     * Delete all data related to a given teamwork module instance
     *
     * This plugin does not store any data.
     *
     * @see teamwork_delete_instance()
     * @param int $teamworkid id of the teamwork module instance being deleted
     * @return void
     */
    public static function delete_instance($teamworkid) {
        return;
    }
}

/**
 * Contains all information needed to render current allocations and the allocator UI
 *
 * @see teamwork_manual_allocator::ui()
 */
class teamworkallocation_manual_allocations implements renderable {

    /** @var teamwork module instance */
    public $teamwork;

    /** @var array of stdClass, indexed by userid, properties userid, submissionid, (array)reviewedby, (array)reviewerof */
    public $allocations;

    /** @var array of stdClass contains the data needed to display the user name and picture */
    public $userinfo;

    /* var array of stdClass potential authors */
    public $authors;

    /* var array of stdClass potential reviewers */
    public $reviewers;

    /* var int the id of the user to highlight as the author */
    public $hlauthorid;

    /* var int the id of the user to highlight as the reviewer */
    public $hlreviewerid;

    /* var bool should the selfassessment be allowed */
    public $selfassessment;
}

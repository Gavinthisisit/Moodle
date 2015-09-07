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
 * This file defines a class with comments grading strategy logic
 *
 * @package    teamworkform_comments
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/lib.php');  // interface definition
require_once($CFG->libdir . '/gradelib.php');           // to handle float vs decimal issues

/**
 * Server teamwork files
 *
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function teamworkform_comments_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea !== 'description') {
        return false;
    }

    $itemid = (int)array_shift($args); // the id of the assessment form dimension
    if (!$teamwork = $DB->get_record('teamwork', array('id' => $cm->instance))) {
        send_file_not_found();
    }

    if (!$dimension = $DB->get_record('teamworkform_comments', array('id' => $itemid ,'teamworkid' => $teamwork->id))) {
        send_file_not_found();
    }

    // TODO now make sure the user is allowed to see the file
    // (media embedded into the dimension description)
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/teamworkform_comments/$filearea/$itemid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Accumulative grading strategy logic.
 */
class teamwork_comments_strategy implements teamwork_strategy {

    /** @const default number of dimensions to show */
    const MINDIMS = 3;

    /** @const number of dimensions to add */
    const ADDDIMS = 2;

    /** @var teamwork the parent teamwork instance */
    protected $teamwork;

    /** @var array definition of the assessment form fields */
    protected $dimensions = null;

    /** @var array options for dimension description fields */
    protected $descriptionopts;

    /**
     * Constructor
     *
     * @param teamwork $teamwork The teamwork instance record
     * @return void
     */
    public function __construct(teamwork $teamwork) {
        $this->teamwork         = $teamwork;
        $this->dimensions       = $this->load_fields();
        $this->descriptionopts  = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => -1);
    }

    /**
     * Factory method returning an instance of an assessment form editor class
     *
     * @param $actionurl URL of form handler, defaults to auto detect the current url
     */
    public function get_edit_strategy_form($actionurl=null) {
        global $CFG;    // needed because the included files use it
        global $PAGE;

        require_once(dirname(__FILE__) . '/edit_form.php');

        $fields             = $this->prepare_form_fields($this->dimensions);
        $nodimensions       = count($this->dimensions);
        $norepeatsdefault   = max($nodimensions + self::ADDDIMS, self::MINDIMS);
        $norepeats          = optional_param('norepeats', $norepeatsdefault, PARAM_INT);    // number of dimensions
        $noadddims          = optional_param('noadddims', '', PARAM_ALPHA);                 // shall we add more?
        if ($noadddims) {
            $norepeats += self::ADDDIMS;
        }

        // Append editor context to editor options, giving preference to existing context.
        $this->descriptionopts = array_merge(array('context' => $PAGE->context), $this->descriptionopts);

        // prepare the embedded files
        for ($i = 0; $i < $nodimensions; $i++) {
            // prepare all editor elements
            $fields = file_prepare_standard_editor($fields, 'description__idx_'.$i, $this->descriptionopts,
                $PAGE->context, 'teamworkform_comments', 'description', $fields->{'dimensionid__idx_'.$i});
        }

        $customdata = array();
        $customdata['teamwork'] = $this->teamwork;
        $customdata['strategy'] = $this;
        $customdata['norepeats'] = $norepeats;
        $customdata['descriptionopts'] = $this->descriptionopts;
        $customdata['current']  = $fields;
        $attributes = array('class' => 'editstrategyform');

        return new teamwork_edit_comments_strategy_form($actionurl, $customdata, 'post', '', $attributes);
    }

    /**
     * Save the assessment dimensions into database
     *
     * Saves data into the main strategy form table. If the record->id is null or zero,
     * new record is created. If the record->id is not empty, the existing record is updated. Records with
     * empty 'description' field are removed from database.
     * The passed data object are the raw data returned by the get_data().
     *
     * @uses $DB
     * @param stdClass $data Raw data returned by the dimension editor form
     * @return void
     */
    public function save_edit_strategy_form(stdclass $data) {
        global $DB, $PAGE;

        $teamworkid = $data->teamworkid;
        $norepeats  = $data->norepeats;

        $data       = $this->prepare_database_fields($data);
        $records    = $data->comments;  // records to be saved into {teamworkform_comments}
        $todelete   = array();              // dimension ids to be deleted

        for ($i=0; $i < $norepeats; $i++) {
            $record = $records[$i];
            if (0 == strlen(trim($record->description_editor['text']))) {
                if (!empty($record->id)) {
                    // existing record with empty description - to be deleted
                    $todelete[] = $record->id;
                }
                continue;
            }
            if (empty($record->id)) {
                // new field
                $record->id         = $DB->insert_record('teamworkform_comments', $record);
            } else {
                // exiting field
                $DB->update_record('teamworkform_comments', $record);
            }
            // re-save with correct path to embedded media files
            $record = file_postupdate_standard_editor($record, 'description', $this->descriptionopts,
                                                      $PAGE->context, 'teamworkform_comments', 'description', $record->id);
            $DB->update_record('teamworkform_comments', $record);
        }
        $this->delete_dimensions($todelete);
    }

    /**
     * Factory method returning an instance of an assessment form
     *
     * @param moodle_url $actionurl URL of form handler, defaults to auto detect the current url
     * @param string $mode          Mode to open the form in: preview/assessment
     * @param stdClass $assessment  The current assessment
     * @param bool $editable
     * @param array $options
     */
    public function get_assessment_form(moodle_url $actionurl=null, $mode='preview', stdclass $assessment=null, $editable=true, $options=array()) {
        global $CFG;    // needed because the included files use it
        global $PAGE;
        global $DB;
        require_once(dirname(__FILE__) . '/assessment_form.php');

        $fields         = $this->prepare_form_fields($this->dimensions);
        $nodimensions   = count($this->dimensions);

        // rewrite URLs to the embedded files
        for ($i = 0; $i < $nodimensions; $i++) {
            $fields->{'description__idx_'.$i} = file_rewrite_pluginfile_urls($fields->{'description__idx_'.$i},
                'pluginfile.php', $PAGE->context->id, 'teamworkform_comments', 'description', $fields->{'dimensionid__idx_'.$i});
        }

        if ('assessment' === $mode and !empty($assessment)) {
            // load the previously saved assessment data
            $grades = $this->get_current_assessment_data($assessment);
            $current = new stdclass();
            for ($i = 0; $i < $nodimensions; $i++) {
                $dimid = $fields->{'dimensionid__idx_'.$i};
                if (isset($grades[$dimid])) {
                    $current->{'gradeid__idx_'.$i}      = $grades[$dimid]->id;
                    $current->{'peercomment__idx_'.$i}  = $grades[$dimid]->peercomment;
                }
            }
        }

        // set up the required custom data common for all strategies
        $customdata['strategy'] = $this;
        $customdata['teamwork'] = $this->teamwork;
        $customdata['mode']     = $mode;
        $customdata['options']  = $options;

        // set up strategy-specific custom data
        $customdata['nodims']   = $nodimensions;
        $customdata['fields']   = $fields;
        $customdata['current']  = isset($current) ? $current : null;
        $attributes = array('class' => 'assessmentform comments');

        return new teamwork_comments_assessment_form($actionurl, $customdata, 'post', '', $attributes, $editable);
    }

    /**
     * Saves the filled assessment
     *
     * This method processes data submitted using the form returned by {@link get_assessment_form()}
     *
     * @param stdClass $assessment Assessment being filled
     * @param stdClass $data       Raw data as returned by the assessment form
     * @return float|null          Constant raw grade 100.00000 for submission as suggested by the peer
     */
    public function save_assessment(stdclass $assessment, stdclass $data) {
        global $DB;

        if (!isset($data->nodims)) {
            throw new coding_exception('You did not send me the number of assessment dimensions to process');
        }
        for ($i = 0; $i < $data->nodims; $i++) {
            $grade = new stdclass();
            $grade->id = $data->{'gradeid__idx_' . $i};
            $grade->assessmentid = $assessment->id;
            $grade->strategy = 'comments';
            $grade->dimensionid = $data->{'dimensionid__idx_' . $i};
            $grade->grade = 100.00000;
            $grade->peercomment = $data->{'peercomment__idx_' . $i};
            $grade->peercommentformat = FORMAT_MOODLE;
            if (empty($grade->id)) {
                // new grade
                $grade->id = $DB->insert_record('teamwork_grades', $grade);
            } else {
                // updated grade
                $DB->update_record('teamwork_grades', $grade);
            }
        }
        $this->teamwork->set_peer_grade($assessment->id, 100.00000);
        return 100.0000;
    }

    /**
     * Has the assessment form been defined and is ready to be used by the reviewers?
     *
     * @return boolean
     */
    public function form_ready() {
        if (count($this->dimensions) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @see parent::get_assessments_recordset()
     */
    public function get_assessments_recordset($restrict=null) {
        global $DB;

        $sql = 'SELECT s.id AS submissionid,
                       a.id AS assessmentid, a.weight AS assessmentweight, a.reviewerid, a.gradinggrade,
                       g.dimensionid, 100.00000 AS grade
                  FROM {teamwork_submissions} s
                  JOIN {teamwork_assessments} a ON (a.submissionid = s.id)
                  JOIN {teamwork_grades} g ON (g.assessmentid = a.id AND g.strategy = :strategy)
                 WHERE s.example=0 AND s.teamworkid=:teamworkid'; // to be cont.
        $params = array('teamworkid' => $this->teamwork->id, 'strategy' => $this->teamwork->strategy);

        if (is_null($restrict)) {
            // update all users - no more conditions
        } elseif (!empty($restrict)) {
            list($usql, $uparams) = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED);
            $sql .= " AND a.reviewerid $usql";
            $params = array_merge($params, $uparams);
        } else {
            throw new coding_exception('Empty value is not a valid parameter here');
        }

        $sql .= ' ORDER BY s.id'; // this is important for bulk processing

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * @see parent::get_dimensions_info()
     */
    public function get_dimensions_info() {
        global $DB;

        $params = array('teamworkid' => $this->teamwork->id);
        $dimrecords = $DB->get_records('teamworkform_comments', array('teamworkid' => $this->teamwork->id), 'sort', 'id');
        $diminfo = array();
        foreach ($dimrecords as $dimid => $dimrecord) {
            $diminfo[$dimid] = new stdclass();
            $diminfo[$dimid]->id = $dimid;
            $diminfo[$dimid]->weight = 1;
            $diminfo[$dimid]->min = 100;
            $diminfo[$dimid]->max = 100;
        }
        return $diminfo;
    }

    /**
     * Is a given scale used by the instance of teamwork?
     *
     * This grading strategy does not use scales.
     *
     * @param int $scaleid id of the scale to check
     * @param int|null $teamworkid id of teamwork instance to check, checks all in case of null
     * @return bool
     */
    public static function scale_used($scaleid, $teamworkid=null) {
        return false;
    }

    /**
     * Delete all data related to a given teamwork module instance
     *
     * @see teamwork_delete_instance()
     * @param int $teamworkid id of the teamwork module instance being deleted
     * @return void
     */
    public static function delete_instance($teamworkid) {
        global $DB;

        $DB->delete_records('teamworkform_comments', array('teamworkid' => $teamworkid));
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Internal methods                                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Loads the fields of the assessment form currently used in this teamwork
     *
     * @return array definition of assessment dimensions
     */
    protected function load_fields() {
        global $DB;
        return $DB->get_records('teamworkform_comments', array('teamworkid' => $this->teamwork->id), 'sort');
    }

    /**
     * Maps the dimension data from DB to the form fields
     *
     * @param array $raw Array of raw dimension records as returned by {@link load_fields()}
     * @return array Array of fields data to be used by the mform set_data
     */
    protected function prepare_form_fields(array $raw) {

        $formdata = new stdclass();
        $key = 0;
        foreach ($raw as $dimension) {
            $formdata->{'dimensionid__idx_' . $key}             = $dimension->id;
            $formdata->{'description__idx_' . $key}             = $dimension->description;
            $formdata->{'description__idx_' . $key.'format'}    = $dimension->descriptionformat;
            $key++;
        }
        return $formdata;
    }

    /**
     * Deletes dimensions and removes embedded media from its descriptions
     *
     * todo we may check that there are no assessments done using these dimensions and probably remove them
     *
     * @param array $masterids
     * @return void
     */
    protected function delete_dimensions(array $ids) {
        global $DB, $PAGE;

        $fs = get_file_storage();
        foreach ($ids as $id) {
            if (!empty($id)) {   // to prevent accidental removal of all files in the area
                $fs->delete_area_files($PAGE->context->id, 'teamworkform_comments', 'description', $id);
            }
        }
        $DB->delete_records_list('teamworkform_comments', 'id', $ids);
    }

    /**
     * Prepares data returned by {@link teamwork_edit_comments_strategy_form} so they can be saved into database
     *
     * It automatically adds some columns into every record. The sorting is
     * done by the order of the returned array and starts with 1.
     * Called internally from {@link save_edit_strategy_form()} only. Could be private but
     * keeping protected for unit testing purposes.
     *
     * @param stdClass $raw Raw data returned by mform
     * @return array Array of objects to be inserted/updated in DB
     */
    protected function prepare_database_fields(stdclass $raw) {
        global $PAGE;

        $cook               = new stdclass(); // to be returned
        $cook->comments = array();        // records to be stored in {teamworkform_comments}

        for ($i = 0; $i < $raw->norepeats; $i++) {
            $cook->comments[$i]                     = new stdclass();
            $cook->comments[$i]->id                 = $raw->{'dimensionid__idx_'.$i};
            $cook->comments[$i]->teamworkid         = $this->teamwork->id;
            $cook->comments[$i]->sort               = $i + 1;
            $cook->comments[$i]->description_editor = $raw->{'description__idx_'.$i.'_editor'};
        }
        return $cook;
    }

    /**
     * Returns the list of current grades filled by the reviewer indexed by dimensionid
     *
     * @param stdClass $assessment Assessment record
     * @return array [int dimensionid] => stdclass teamwork_grades record
     */
    protected function get_current_assessment_data(stdclass $assessment) {
        global $DB;

        if (empty($this->dimensions)) {
            return array();
        }
        list($dimsql, $dimparams) = $DB->get_in_or_equal(array_keys($this->dimensions), SQL_PARAMS_NAMED);
        // beware! the caller may rely on the returned array is indexed by dimensionid
        $sql = "SELECT dimensionid, wg.*
                  FROM {teamwork_grades} wg
                 WHERE assessmentid = :assessmentid AND strategy= :strategy AND dimensionid $dimsql";
        $params = array('assessmentid' => $assessment->id, 'strategy' => 'comments');
        $params = array_merge($params, $dimparams);

        return $DB->get_records_sql($sql, $params);
    }
}

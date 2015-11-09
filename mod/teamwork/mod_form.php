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
 * The main teamwork configuration form
 *
 * The UI mockup has been proposed in MDL-18688
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/dev/lib/formslib.php
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Module settings form for Teamwork instances
 */
class mod_teamwork_mod_form extends moodleform_mod {

    /** @var object the course this instance is part of */
    protected $course = null;

    /**
     * Constructor
     */
    public function __construct($current, $section, $cm, $course) {
        $this->course = $course;
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Defines the teamwork instance configuration form
     *
     * @return void
     */
    public function definition() {
        global $CFG;

        $teamworkconfig = get_config('teamwork');
        $mform = $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Teamwork name
        $label = get_string('teamworkname', 'teamwork');
        $mform->addElement('text', 'name', $label, array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction
        $this->add_intro_editor(false, get_string('introduction', 'teamwork'));

        // Grading settings -----------------------------------------------------------
        $mform->addElement('header', 'gradingsettings', get_string('gradingsettings', 'teamwork'));

        $label = get_string('strategy', 'teamwork');
        $mform->addElement('select', 'strategy', $label, teamwork::available_strategies_list());
        $mform->setDefault('strategy', $teamworkconfig->strategy);
        $mform->addHelpButton('strategy', 'strategy', 'teamwork');


        $options = array();
        for ($i=5; $i>=0; $i--) {
            $options[$i] = $i;
        }
        $label = get_string('gradedecimals', 'teamwork');
        $mform->addElement('select', 'gradedecimals', $label, $options);
        $mform->setDefault('gradedecimals', $teamworkconfig->gradedecimals);

        // Submission settings --------------------------------------------------------
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'teamwork'));


        $options = get_max_upload_sizes($CFG->maxbytes, $this->course->maxbytes, 0, $teamworkconfig->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'teamwork'), $options);
        $mform->setDefault('maxbytes', $teamworkconfig->maxbytes);



        // Participation settings --------------------------------------------------------
        $mform->addElement('header', 'participationsettings', get_string('participationsettings', 'teamwork'));
				$options = array();
        for ($i=1; $i<=10; $i++) {
            $options[$i] = $i;
        }
				$mform->addElement('select', 'participationnumlimit', get_string('participationnumlimit', 'teamwork'), $options);
        $mform->addHelpButton('participationnumlimit', 'participationnumlimit', 'teamwork');


        // Date settings---------------------------------------------------------------
        $mform->addElement('header', 'datecontrol', get_string('datesettings', 'teamwork'));

        $label = get_string('applystart', 'teamwork');
        $mform->addElement('date_time_selector', 'applystart', $label, array('optional' => false));

        $label = get_string('applyend', 'teamwork');
        $mform->addElement('date_time_selector', 'applyend', $label, array('optional' => false));


        // Common module settings, Restrict availability, Activity completion etc. ----
        $this->_features = array('groups' => false, 'groupings' => false,
                'outcomes'=>true, 'gradecat'=>false, 'idnumber'=>true);

        $this->standard_coursemodule_elements();

        // Standard buttons, common to all modules ------------------------------------
        $this->add_action_buttons();
    }

    /**
     * Prepares the form before data are set
     *
     * Additional wysiwyg editor are prepared here, the introeditor is prepared automatically by core.
     * Grade items are set here because the core modedit supports single grade item only.
     *
     * @param array $data to be set
     * @return void
     */
    public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            // editing an existing teamwork - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            $data['instructauthorseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'instructauthors', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['instructauthors']);
            $data['instructauthorseditor']['format'] = $data['instructauthorsformat'];
            $data['instructauthorseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            $data['instructreviewerseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'instructreviewers', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['instructreviewers']);
            $data['instructreviewerseditor']['format'] = $data['instructreviewersformat'];
            $data['instructreviewerseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            $data['conclusioneditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'conclusion', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['conclusion']);
            $data['conclusioneditor']['format'] = $data['conclusionformat'];
            $data['conclusioneditor']['itemid'] = $draftitemid;
        } else {
            // adding a new teamwork instance
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'instructauthors', 0);    // no context yet, itemid not used
            $data['instructauthorseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'instructreviewers', 0);    // no context yet, itemid not used
            $data['instructreviewerseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'conclusion', 0);    // no context yet, itemid not used
            $data['conclusioneditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);
        }
    }

    /**
     * Set the grade item categories when editing an instance
     */
    public function definition_after_data() {

        parent::definition_after_data();
    }

    /**
     * Validates the form input
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array eventual errors indexed by the field name
     */
    public function validation($data, $files) {
        $errors = array();

        // check the phases borders are valid
        if ($data['submissionstart'] > 0 and $data['submissionend'] > 0 and $data['submissionstart'] >= $data['submissionend']) {
            $errors['submissionend'] = get_string('submissionendbeforestart', 'mod_teamwork');
        }
        if ($data['assessmentstart'] > 0 and $data['assessmentend'] > 0 and $data['assessmentstart'] >= $data['assessmentend']) {
            $errors['assessmentend'] = get_string('assessmentendbeforestart', 'mod_teamwork');
        }

        // check the phases do not overlap
        if (max($data['submissionstart'], $data['submissionend']) > 0 and max($data['assessmentstart'], $data['assessmentend']) > 0) {
            $phasesubmissionend = max($data['submissionstart'], $data['submissionend']);
            $phaseassessmentstart = min($data['assessmentstart'], $data['assessmentend']);
            if ($phaseassessmentstart == 0) {
                $phaseassessmentstart = max($data['assessmentstart'], $data['assessmentend']);
            }
            if ($phasesubmissionend > 0 and $phaseassessmentstart > 0 and $phaseassessmentstart < $phasesubmissionend) {
                foreach (array('submissionend', 'submissionstart', 'assessmentstart', 'assessmentend') as $f) {
                    if ($data[$f] > 0) {
                        $errors[$f] = get_string('phasesoverlap', 'mod_teamwork');
                        break;
                    }
                }
            }
        }

        return $errors;
    }
}

/**
 * Settings form for templet
 */
class teamwork_templet_form extends moodleform {

	protected $teamworkid = 0;
	protected $templetid = 0;
	protected $phasenum = 0;
    /**
     * Constructor
     */
    public function __construct($teamworkid,$templetid=0,$phasenum=0) {
    	$this->teamworkid = $teamworkid;
    	$this->templetid = $templetid;
    	$this->phasenum = $phasenum==0 ? optional_param('phasenum', 0, PARAM_INT):$phasenum;
    	$phaseadd = optional_param('phaseadd', '', PARAM_TEXT);
    	if($phaseadd){
    		$this->phasenum++;
    	}
        parent::__construct();
    }

    /**
     * Defines the teamwork instance configuration form
     *
     * @return void
     */
    public function definition() {
        global $CFG;

        $teamworkconfig = get_config('teamwork');
        $mform = $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Templet title
        $label = get_string('templettitle', 'teamwork');
        $mform->addElement('text', 'title', $label, array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('title', PARAM_TEXT);
        } else {
            $mform->setType('title', PARAM_CLEANHTML);
        }
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        // Templet summary
        $label = get_string('templetsummary', 'teamwork');
        $mform->addElement('editor', 'summary', $label, array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true, 'context' => null, 'subdirs' => true));
        $mform->setType('summary', PARAM_RAW); // no XSS prevention here, users must be trusted


        // Participation settings --------------------------------------------------------
        $mform->addElement('header', 'participationsettings', get_string('participationsettings', 'teamwork'));
		$options = array();
        for ($i=1; $i<=20; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'teamminmembers', get_string('teamminmembers', 'teamwork'), $options);
		$mform->addElement('select', 'teammaxmembers', get_string('teammaxmembers', 'teamwork'), $options);

		for ($i=1; $i<=10; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'teamlimit', get_string('teamlimit', 'teamwork'), $options);
		$mform->addHelpButton('teamlimit', 'teamlimit', 'teamwork');

		// Submission settings --------------------------------------------------------
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'teamwork'));
		$label = get_string('displaysubmissions', 'teamwork');
        $mform->addElement('checkbox', 'displaysubmissions', $label);

        // Assessment settings---------------------------------------------------------------
        $mform->addElement('header', 'assessmentsettings', get_string('assessmentsettings', 'teamwork'));
		$label = get_string('assessmentanonymous', 'teamwork');
        $mform->addElement('checkbox', 'assessmentanonymous', $label);
        $label = get_string('assessfirst', 'teamwork');
        $mform->addElement('checkbox', 'assessfirst', $label);
        $options = array();
        for ($i=1; $i<=100; $i++) {
            $options[$i] = $i;
        }
        $label = get_string('scoremin', 'teamwork');
        $mform->addElement('select', 'scoremin', get_string('scoremin', 'teamwork'), $options);
        $label = get_string('scoremax', 'teamwork');
        $mform->addElement('select', 'scoremax', get_string('scoremax', 'teamwork'), $options);

		// Phase settings---------------------------------------------------------------
        $mform->addElement('header', 'phasesettings', get_string('phasesettings', 'teamwork'));

        $mform->registerNoSubmitButton('phaseadd');
        $mform->addElement('submit', 'phaseadd', get_string('phaseadd', 'teamwork'));

        for($i=1;$i<=$this->phasenum;$i++){
			$mform->addElement('header', 'phase_'.$i, get_string('phasenumber', 'teamwork', $i));
			$mform->addElement('text', 'phasename_'.$i, get_string('phasename', 'teamwork'));
			if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('phasename_'.$i, PARAM_TEXT);
        	} else {
            	$mform->setType('phasename_'.$i, PARAM_CLEANHTML);
        	}
        	$mform->addRule('phasename_'.$i, null, 'required', null, 'client');
        	$mform->addRule('phasename_'.$i, get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
			$mform->addElement('editor', 'phasedescription_'.$i, get_string('phasedescription', 'teamwork'), array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true, 'context' => null, 'subdirs' => true));
        	$mform->setType('phasedescription_'.$i, PARAM_RAW); // no XSS prevention here, users must be trusted
			$mform->addElement('date_time_selector', 'phasestart_'.$i, get_string('phasestart', 'teamwork'),array('optional' => false));
			$mform->addElement('date_time_selector', 'phaseend_'.$i, get_string('phaseend', 'teamwork'),array('optional' => false));
		}

		//Hidden------------------------------------------------------------------------
        $mform->addElement('hidden', 'id', $this->teamworkid);   
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'templetid', $this->templetid);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'phasenum', $this->phasenum);
        $mform->setType('phasenum', PARAM_INT);
		$mform->setConstants(array('phasenum' => $this->phasenum));
        // Standard buttons, common to all modules ------------------------------------
        $this->add_action_buttons();

    }

    /**
     * Prepares the form before data are set
     *
     * Additional wysiwyg editor are prepared here, the introeditor is prepared automatically by core.
     * Grade items are set here because the core modedit supports single grade item only.
     *
     * @param array $data to be set
     * @return void
     */
    public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            // editing an existing teamwork - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            $data['instructauthorseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'instructauthors', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['instructauthors']);
            $data['instructauthorseditor']['format'] = $data['instructauthorsformat'];
            $data['instructauthorseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            $data['instructreviewerseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'instructreviewers', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['instructreviewers']);
            $data['instructreviewerseditor']['format'] = $data['instructreviewersformat'];
            $data['instructreviewerseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            $data['conclusioneditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'conclusion', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['conclusion']);
            $data['conclusioneditor']['format'] = $data['conclusionformat'];
            $data['conclusioneditor']['itemid'] = $draftitemid;
        } else {
            // adding a new teamwork instance
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'instructauthors', 0);    // no context yet, itemid not used
            $data['instructauthorseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'instructreviewers', 0);    // no context yet, itemid not used
            $data['instructreviewerseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'conclusion', 0);    // no context yet, itemid not used
            $data['conclusioneditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);
        }
    }

    /**
     * Set the grade item categories when editing an instance
     */
    public function definition_after_data() {

        parent::definition_after_data();
    }

    /**
     * Validates the form input
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array eventual errors indexed by the field name
     */
    public function validation($data, $files) {
        $errors = array();

        return $errors;
    }
}

/**
 * Settings form for instance
 */
class teamwork_instance_form extends moodleform {

	protected $teamworkid = 0;
	protected $instanceid = 0;
	protected $templet = 0;
	protected $currentphase = 0;
	protected $phasenum = 0;
    /**
     * Constructor
     */
    public function __construct($teamworkid,$instanceid=0,$phasenum=0) {
    	global $DB;
    	
    	$instancerecord = $DB->get_record('teamwork_instance',array('id' => $instanceid));
    	
    	$this->teamworkid = $teamworkid;
    	$this->instanceid = $instanceid;
    	$this->templet = $instancerecord->templet;
    	$this->currentphase = $instancerecord->currentphase;
    	$this->phasenum = $phasenum==0 ? optional_param('phasenum', 0, PARAM_INT):$phasenum;
    	$phaseadd = optional_param('phaseadd', '', PARAM_TEXT);
    	if($phaseadd){
    		$this->phasenum++;
    	}
        parent::__construct();
    }

    /**
     * Defines the teamwork instance configuration form
     *
     * @return void
     */
    public function definition() {
        global $DB,$CFG;

        $teamworkconfig = get_config('teamwork');
        $mform = $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Templet title
        $label = get_string('templettitle', 'teamwork');
        $mform->addElement('text', 'title', $label, array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('title', PARAM_TEXT);
        } else {
            $mform->setType('title', PARAM_CLEANHTML);
        }
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        // Templet summary
        $label = get_string('templetsummary', 'teamwork');
        $mform->addElement('editor', 'summary', $label, array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true, 'context' => null, 'subdirs' => true));
        $mform->setType('summary', PARAM_RAW); // no XSS prevention here, users must be trusted


        // Participation settings --------------------------------------------------------
        $mform->addElement('header', 'participationsettings', get_string('participationsettings', 'teamwork'));
		$options = array();
        for ($i=1; $i<=20; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'teamminmembers', get_string('teamminmembers', 'teamwork'), $options);
		$mform->addElement('select', 'teammaxmembers', get_string('teammaxmembers', 'teamwork'), $options);

		for ($i=1; $i<=10; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'teamlimit', get_string('teamlimit', 'teamwork'), $options);
		$mform->addHelpButton('teamlimit', 'teamlimit', 'teamwork');

		// Submission settings --------------------------------------------------------
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'teamwork'));
		$label = get_string('displaysubmissions', 'teamwork');
        $mform->addElement('checkbox', 'displaysubmissions', $label);

        // Assessment settings---------------------------------------------------------------
        $mform->addElement('header', 'assessmentsettings', get_string('assessmentsettings', 'teamwork'));
		$label = get_string('assessmentanonymous', 'teamwork');
        $mform->addElement('checkbox', 'assessmentanonymous', $label);
        $label = get_string('assessfirst', 'teamwork');
        $mform->addElement('checkbox', 'assessfirst', $label);
        $options = array();
        for ($i=1; $i<=100; $i++) {
            $options[$i] = $i;
        }
        $label = get_string('scoremin', 'teamwork');
        $mform->addElement('select', 'scoremin', get_string('scoremin', 'teamwork'), $options);
        $label = get_string('scoremax', 'teamwork');
        $mform->addElement('select', 'scoremax', get_string('scoremax', 'teamwork'), $options);

		// Phase settings---------------------------------------------------------------
        $mform->addElement('header', 'phasesettings', get_string('phasesettings', 'teamwork'));

        $mform->registerNoSubmitButton('phaseadd');
        $mform->addElement('submit', 'phaseadd', get_string('phaseadd', 'teamwork'));

        for($i=1;$i<=$this->phasenum;$i++){
			$mform->addElement('header', 'phase_'.$i, get_string('phasenumber', 'teamwork', $i));
			$mform->addElement('text', 'phasename_'.$i, get_string('phasename', 'teamwork'));
			if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('phasename_'.$i, PARAM_TEXT);
        	} else {
            	$mform->setType('phasename_'.$i, PARAM_CLEANHTML);
        	}
        	$mform->addRule('phasename_'.$i, null, 'required', null, 'client');
        	$mform->addRule('phasename_'.$i, get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
			$mform->addElement('editor', 'phasedescription_'.$i, get_string('phasedescription', 'teamwork'), array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true, 'context' => null, 'subdirs' => true));
        	$mform->setType('phasedescription_'.$i, PARAM_RAW); // no XSS prevention here, users must be trusted
        	$label = get_string('needassess', 'teamwork');
        	$mform->addElement('checkbox', 'needassess', $label);
			$mform->addElement('date_time_selector', 'phasestart_'.$i, get_string('phasestart', 'teamwork'),array('optional' => false));
			$mform->addElement('date_time_selector', 'phaseend_'.$i, get_string('phaseend', 'teamwork'),array('optional' => false));
		}
		
		//Other instances with the same templet
		$mform->addElement('header', 'syncsettings', get_string('syncsettings', 'teamwork'));
		
		$records = $DB->get_records('teamwork_instance',array('templet' => $this->templet));
		$options = array();
		foreach($records as $record) {
				if($record->id != $this->instanceid) {
					$team = $DB->get_record('teamwork_team',array('id' => $record->team));
					$options[$record->id] = $team->name;
				}
		}
		if(count($options)>0){
			$mform->addElement('select', 'selectothers', get_string('selectothers', 'teamwork'), $options,array('multiple'=>'multiple', 'size'=>3));
			$mform->addHelpButton('selectothers', 'selectothers', 'teamwork');
		}
		
		
		//Hidden------------------------------------------------------------------------
        $mform->addElement('hidden', 'teamwork', $this->teamworkid);   
        $mform->setType('teamwork', PARAM_INT);
        $mform->addElement('hidden', 'instance', $this->instanceid);
        $mform->setType('instance', PARAM_INT);
        $mform->addElement('hidden', 'templet', $this->templet);
        $mform->setType('templet', PARAM_INT);
        $mform->addElement('hidden', 'currentphase', $this->currentphase);
        $mform->setType('currentphase', PARAM_INT);
        $mform->addElement('hidden', 'phasenum', $this->phasenum);
        $mform->setType('phasenum', PARAM_INT);
		$mform->setConstants(array('phasenum' => $this->phasenum));
        // Standard buttons, common to all modules ------------------------------------
        $this->add_action_buttons();

    }

    /**
     * Prepares the form before data are set
     *
     * Additional wysiwyg editor are prepared here, the introeditor is prepared automatically by core.
     * Grade items are set here because the core modedit supports single grade item only.
     *
     * @param array $data to be set
     * @return void
     */
    public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            // editing an existing teamwork - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            $data['instructauthorseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'instructauthors', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['instructauthors']);
            $data['instructauthorseditor']['format'] = $data['instructauthorsformat'];
            $data['instructauthorseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            $data['instructreviewerseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'instructreviewers', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['instructreviewers']);
            $data['instructreviewerseditor']['format'] = $data['instructreviewersformat'];
            $data['instructreviewerseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            $data['conclusioneditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_teamwork', 'conclusion', 0,
                                teamwork::instruction_editors_options($this->context),
                                $data['conclusion']);
            $data['conclusioneditor']['format'] = $data['conclusionformat'];
            $data['conclusioneditor']['itemid'] = $draftitemid;
        } else {
            // adding a new teamwork instance
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'instructauthors', 0);    // no context yet, itemid not used
            $data['instructauthorseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'instructreviewers', 0);    // no context yet, itemid not used
            $data['instructreviewerseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            file_prepare_draft_area($draftitemid, null, 'mod_teamwork', 'conclusion', 0);    // no context yet, itemid not used
            $data['conclusioneditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);
        }
    }

    /**
     * Set the grade item categories when editing an instance
     */
    public function definition_after_data() {

        parent::definition_after_data();
    }

    /**
     * Validates the form input
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array eventual errors indexed by the field name
     */
    public function validation($data, $files) {
        $errors = array();

        return $errors;
    }
}

/**
 * teamwork info setting form
 */
class teamwork_teaminfo_form extends moodleform {

    protected $course = 0;
    protected $teamwork = 0;
    protected $templet = 0;
    
    /**
     * Constructor
     */
    public function __construct($course, $teamwork, $templet) {
        $this->course = $course;
        $this->teamwork = $teamwork;
        $this->templet = $templet;
        parent::__construct();
    }

    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // team title
        $label = get_string('teamtitle', 'teamwork');
        $mform->addElement('text', 'title', $label, array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('title', PARAM_TEXT);
        } else {
            $mform->setType('title', PARAM_CLEANHTML);
        }
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        //Hidden------------------------------------------------------------------------
        $mform->addElement('hidden', 'courseid', $this->course);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'teamworkid', $this->teamwork);
        $mform->setType('teamworkid', PARAM_INT);
        $mform->addElement('hidden', 'templetid', $this->templet);
        $mform->setType('templetid', PARAM_INT);
        //TODO
        $mform->addElement('hidden', 'time', time());
        $mform->setType('time', PARAM_INT);
        // Standard buttons, common to all modules ------------------------------------
        $this->add_action_buttons();
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}

/**
 * teamwork join team form
 */
class teamwork_jointeam_form extends moodleform {

    protected $course = 0;
    protected $teamwork = 0;
    
    /**
     * Constructor
     */
    public function __construct($course, $teamwork) {
        $this->course = $course;
        $this->teamwork = $teamwork;
        parent::__construct();
    }

    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // team title
        $label = get_string('invitedkey', 'teamwork');
        $mform->addElement('text', 'invitedkey', $label, array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('invitedkey', PARAM_TEXT);
        } else {
            $mform->setType('invitedkey', PARAM_CLEANHTML);
        }
        $mform->addRule('invitedkey', null, 'required', null, 'client');
        $mform->addRule('invitedkey', get_string('maximumchars', '', 10), 'maxlength', 10, 'client');

        //Hidden------------------------------------------------------------------------
        $mform->addElement('hidden', 'courseid', $this->course);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'teamworkid', $this->teamwork);
        $mform->setType('teamworkid', PARAM_INT);

        //TODO
        $mform->addElement('hidden', 'time', time());
        $mform->setType('time', PARAM_INT);
        // Standard buttons, common to all modules ------------------------------------
        $this->add_action_buttons();
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}

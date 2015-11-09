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
 * Teamwork module renderering methods are defined here
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Teamwork module renderer class
 *
 * @copyright 2009 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_teamwork_renderer extends plugin_renderer_base {

    ////////////////////////////////////////////////////////////////////////////
    // External API - methods to render teamwork renderable components
    ////////////////////////////////////////////////////////////////////////////
	
	
	    /**
     * Renders the user plannner tool
     *
     * @param teamwork_user_plan $plan prepared for the user
     * @return string html code to be displayed
     */
    protected function render_teamwork_team_info(teamwork_team_info $plan) {
        $table = new html_table();
        $table->attributes['class'] = 'userplan';
        $table->head = array();
        $table->colclasses = array();
        $row = new html_table_row();
        $row->attributes['class'] = 'phasetasks';
        foreach ($plan->phases as $phasecode => $phase) {
            $title = html_writer::tag('span', $phase->title);
            $actions = '';
            foreach ($phase->actions as $action) {
                switch ($action->type) {
                case 'switchphase':
                    $icon = 'i/marker';
                    if ($phasecode == teamwork::PHASE_ASSESSMENT
                            and $plan->teamwork->phase == teamwork::PHASE_SUBMISSION
                            and $plan->teamwork->phaseswitchassessment) {
                        $icon = 'i/scheduled';
                    }
                    $actions .= $this->output->action_icon($action->url, new pix_icon($icon, get_string('switchphase', 'teamwork')));
                    break;
                }
            }
            if (!empty($actions)) {
                $actions = $this->output->container($actions, 'actions');
            }
            $table->head[] = $this->output->container($title . $actions);
            $classes = 'phase' . $phasecode;
            if ($phase->active) {
                $classes .= ' active';
            } else {
                $classes .= ' nonactive';
            }
            $table->colclasses[] = $classes;
            $cell = new html_table_cell();
            $cell->text = $this->helper_team_info_tasks($phase->tasks);
            $row->cells[] = $cell;
        }
        $table->data = array($row);

        return html_writer::table($table);
    }
	
    /**
     * Renders teamwork message
     *
     * @param teamwork_message $message to display
     * @return string html code
     */
    protected function render_teamwork_message(teamwork_message $message) {

        $text   = $message->get_message();
        $url    = $message->get_action_url();
        $label  = $message->get_action_label();

        if (empty($text) and empty($label)) {
            return '';
        }

        switch ($message->get_type()) {
        case teamwork_message::TYPE_OK:
            $sty = 'ok';
            break;
        case teamwork_message::TYPE_ERROR:
            $sty = 'error';
            break;
        default:
            $sty = 'info';
        }

        $o = html_writer::tag('span', $message->get_message());

        if (!is_null($url) and !is_null($label)) {
            $o .= $this->output->single_button($url, $label, 'get');
        }

        return $this->output->container($o, array('message', $sty));
    }


    /**
     * Renders full teamwork submission
     *
     * @param teamwork_submission $submission
     * @return string HTML
     */
    protected function render_teamwork_submission(teamwork_submission $submission) {
        global $CFG;

        $o  = '';    // output HTML code
        $anonymous = $submission->is_anonymous();
        $classes = 'submission-full';
        if ($anonymous) {
            $classes .= ' anonymous';
        }
        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');

        $title = format_string($submission->title);

        if ($this->page->url != $submission->url) {
            $title = html_writer::link($submission->url, $title);
        }

        $o .= $this->output->heading($title, 3, 'title');

        if (!$anonymous) {
            $author = new stdclass();
            $additionalfields = explode(',', user_picture::fields());
            $author = username_load_fields_from_object($author, $submission, 'author', $additionalfields);
            $userpic            = $this->output->user_picture($author, array('courseid' => $this->page->course->id, 'size' => 64));
            $userurl            = new moodle_url('/user/view.php',
                                            array('id' => $author->id, 'course' => $this->page->course->id));
            $a                  = new stdclass();
            $a->name            = fullname($author);
            $a->url             = $userurl->out();
            $byfullname         = get_string('byfullname', 'teamwork', $a);
            $oo  = $this->output->container($userpic, 'picture');
            $oo .= $this->output->container($byfullname, 'fullname');

            $o .= $this->output->container($oo, 'author');
        }

        $created = get_string('userdatecreated', 'teamwork', userdate($submission->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($submission->timemodified > $submission->timecreated) {
            $modified = get_string('userdatemodified', 'teamwork', userdate($submission->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $this->output->container_end(); // end of header

        $content = file_rewrite_pluginfile_urls($submission->content, 'pluginfile.php', $this->page->context->id,
                                                        'mod_teamwork', 'submission_content', $submission->id);
        $content = format_text($content, $submission->contentformat, array('overflowdiv'=>true));
        if (!empty($content)) {
            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $content .= plagiarism_get_links(array('userid' => $submission->authorid,
                    'content' => $submission->content,
                    'cmid' => $this->page->cm->id,
                    'course' => $this->page->course));
            }
        }
        $o .= $this->output->container($content, 'content');

        $o .= $this->helper_submission_attachments($submission->id, 'html');

        $o .= $this->output->container_end(); // end of submission-full

        return $o;
    }

    /**
     * Renders short summary of the submission
     *
     * @param teamwork_submission_summary $summary
     * @return string text to be echo'ed
     */
    protected function render_teamwork_submission_summary(teamwork_submission_summary $summary) {

        $o  = '';    // output HTML code
        $anonymous = $summary->is_anonymous();
        $classes = 'submission-summary';

        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $gradestatus = '';

        if ($summary->status == 'notgraded') {
            $classes    .= ' notgraded';
            $gradestatus = $this->output->container(get_string('nogradeyet', 'teamwork'), 'grade-status');

        } else if ($summary->status == 'graded') {
            $classes    .= ' graded';
            $gradestatus = $this->output->container(get_string('alreadygraded', 'teamwork'), 'grade-status');
        }

        $o .= $this->output->container_start($classes);  // main wrapper
        $o .= html_writer::link($summary->url, format_string($summary->title), array('class' => 'title'));

        if (!$anonymous) {
            $author             = new stdClass();
            $additionalfields = explode(',', user_picture::fields());
            $author = username_load_fields_from_object($author, $summary, 'author', $additionalfields);
            $userpic            = $this->output->user_picture($author, array('courseid' => $this->page->course->id, 'size' => 35));
            $userurl            = new moodle_url('/user/view.php',
                                            array('id' => $author->id, 'course' => $this->page->course->id));
            $a                  = new stdClass();
            $a->name            = fullname($author);
            $a->url             = $userurl->out();
            $byfullname         = get_string('byfullname', 'teamwork', $a);

            $oo  = $this->output->container($userpic, 'picture');
            $oo .= $this->output->container($byfullname, 'fullname');
            $o  .= $this->output->container($oo, 'author');
        }

        $created = get_string('userdatecreated', 'teamwork', userdate($summary->timecreated));
        $o .= $this->output->container($created, 'userdate created');

        if ($summary->timemodified > $summary->timecreated) {
            $modified = get_string('userdatemodified', 'teamwork', userdate($summary->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $gradestatus;
        $o .= $this->output->container_end(); // end of the main wrapper
        return $o;
    }


    /**
     * Renders short summary of the submission
     *
     * @param teamwork_discussion_summary $summary
     * @return string text to be echo'ed
     */
    protected function render_teamwork_discussion_summary(teamwork_discussion_summary $summary) {

        global $USER, $DB, $PAGE;

        $o  = '';    // output HTML code
        $anonymous = $summary->is_anonymous();
        $classes = 'discussion-summary';

        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $gradestatus = '';

        if ($summary->status == 'notgraded') {
            $classes    .= ' notgraded';
            $gradestatus = $this->output->container(get_string('nogradeyet', 'teamwork'), 'grade-status');

        } else if ($summary->status == 'graded') {
            $classes    .= ' graded';
            $gradestatus = $this->output->container(get_string('alreadygraded', 'teamwork'), 'grade-status');
        }

        $o .= $this->output->container_start($classes);  // main wrapper
        $grade = get_string('fen', 'teamwork', $summary->score);
        $o .= html_writer::link($summary->url, format_string($grade), array('class' => 'grade'));

        if (!$anonymous) {
            $author             = new stdClass();

            // change in course_server.
            $any_student = $DB->get_record('user', array('username' => 'any_student'));

            $additionalfields = explode(',', user_picture::fields());
            if ($USER->id == $summary->authorid || has_capability('mod/teamwork:editsettings', $PAGE->context)) {
                $author = username_load_fields_from_object($author, $summary, 'author', $additionalfields);
            }
            else {
                $author = username_load_fields_from_object($author, $any_student, null, $additionalfields);
            }
            $userpic            = $this->output->user_picture($author, array('courseid' => $this->page->course->id, 'size' => 35));
            $userurl            = new moodle_url('/user/view.php',
                                            array('id' => $author->id, 'course' => $this->page->course->id));
            $a                  = new stdClass();
            $a->name            = fullname($author);
            $a->url             = $userurl->out();
            $byfullname         = get_string('postbyfullname', 'teamwork', $a);

            $oo  = $this->output->container($userpic, 'picture');
            $oo .= $this->output->container($byfullname, 'fullname');
            $o  .= $this->output->container($oo, 'author');
        }
        $o .= html_writer::link($summary->url, format_string($summary->message), array('class' => 'description'));

        if ($summary->timemodified > $summary->timecreated) {
            $modified = get_string('userdatemodified', 'teamwork', userdate($summary->timemodified));
            $o .= $this->output->container($modified, 'userdate modified');
        }

        $o .= $gradestatus;
        $o .= $this->output->container_end(); // end of the main wrapper
        return $o;
    }
    
    /**
     * Renders full teamwork example submission
     *
     * @param teamwork_example_submission $example
     * @return string HTML
     */
    protected function render_teamwork_example_submission(teamwork_example_submission $example) {

        $o  = '';    // output HTML code
        $classes = 'submission-full example';
        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');
        $o .= $this->output->container(format_string($example->title), array('class' => 'title'));
        $o .= $this->output->container_end(); // end of header

        $content = file_rewrite_pluginfile_urls($example->content, 'pluginfile.php', $this->page->context->id,
                                                        'mod_teamwork', 'submission_content', $example->id);
        $content = format_text($content, $example->contentformat, array('overflowdiv'=>true));
        $o .= $this->output->container($content, 'content');

        $o .= $this->helper_submission_attachments($example->id, 'html');

        $o .= $this->output->container_end(); // end of submission-full

        return $o;
    }

    /**
     * Renders short summary of the example submission
     *
     * @param teamwork_example_submission_summary $summary
     * @return string text to be echo'ed
     */
    protected function render_teamwork_example_submission_summary(teamwork_example_submission_summary $summary) {

        $o  = '';    // output HTML code

        // wrapping box
        $o .= $this->output->box_start('generalbox example-summary ' . $summary->status);

        // title
        $o .= $this->output->container_start('example-title');
        $o .= html_writer::link($summary->url, format_string($summary->title), array('class' => 'title'));

        if ($summary->editable) {
            $o .= $this->output->action_icon($summary->editurl, new pix_icon('i/edit', get_string('edit')));
        }
        $o .= $this->output->container_end();

        // additional info
        if ($summary->status == 'notgraded') {
            $o .= $this->output->container(get_string('nogradeyet', 'teamwork'), 'example-info nograde');
        } else {
            $o .= $this->output->container(get_string('gradeinfo', 'teamwork' , $summary->gradeinfo), 'example-info grade');
        }

        // button to assess
        $button = new single_button($summary->assessurl, $summary->assesslabel, 'get');
        $o .= $this->output->container($this->output->render($button), 'example-actions');

        // end of wrapping box
        $o .= $this->output->box_end();

        return $o;
    }
	
	/**
     * Renders my projects
     *
     * @author lkq
     * @param teamwork_templet_list $list prepared for the user
     * @return string html code to be displayed
     */
    protected function render_teamwork_myproject(teamwork_myproject $myproject) {
    	global $DB,$USER;
        $output .= html_writer::start_tag('div', array('class' => 'content'));
        $takepartin = $DB->get_records('teamwork_teammembers',array('teamwork' => $myproject->teamwork,'userid' => $USER->id));
        if(empty($takepartin)){
        	$output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
            $output .= get_string('noprojects', 'teamwork');
            $output .= html_writer::end_tag('div'); // .summary
        }else{
        	foreach($takepartin as $p){
        		$teamid = $p->team;
        		$p = $DB->get_record('teamwork_team',array('id' => $p->team));
        		$p = $DB->get_record('teamwork_templet',array('id' => $p->templet));
        		$output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
                $output .= html_writer::start_tag('div', array('class' => 'info'));
                $output .= html_writer::start_tag('h3', array('class' => 'coursename'));
                $output .= $p->title;
                $output .= html_writer::end_tag('h3'); // .name
                $output .= html_writer::tag('div', '', array('class' => 'moreinfo'));
                $output .= html_writer::start_tag('div', array('class' => 'enrolmenticons'));
                
                $output .= html_writer::end_tag('div');
                $output .= html_writer::end_tag('div'); // .info
                $output .= html_writer::start_tag('div', array('class' => 'content'));
                $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
                $output .= $p->summary;
                $output .= html_writer::end_tag('div'); // .summary
                
                $leader = $DB->get_record('teamwork_teammembers',array('team'=>$teamid,'leader'=>1));
                //var_dump($teamid);die;
                $output .= html_writer::tag('leader', get_string('teamleader', 'teamwork'));
                $leaderinfo = $DB->get_record('user',array('id'=>$leader->userid));
                $output .= html_writer::start_tag('div', array('class' => 'leader')); // .leader
	            $output .= $leaderinfo->lastname.$leaderinfo->firstname;
	            $output .= html_writer::end_tag('div'); // .leader
	            
	            $members = $DB->get_records('teamwork_teammembers',array('team'=>$teamid,'leader'=>0));
	            if(!empty($members)){
	            	$output .= html_writer::tag('member', get_string('teammember', 'teamwork'));
	            }
	            foreach($members as $member){
	            	$memberinfo = $DB->get_record('user',array('id'=>$member->userid));
	                $output .= html_writer::start_tag('div', array('class' => 'member')); // .member
		            $output .= $memberinfo->lastname.$memberinfo->firstname;
		            $output .= html_writer::end_tag('div'); // .member
	            }
                
                $output .= html_writer::end_tag('div'); // .content
                $output .= html_writer::end_tag('div'); // .coursebox
        	}
        }
        $output .= html_writer::end_tag('div'); // .content
        return $output;
	}
	/**
     * Renders the team list
     *
     * @author lkq
     * @param teamwork_team_list $list prepared for the user
     * @return string html code to be displayed
     */
	protected function render_teamwork_team_list(teamwork_team_list $list) {
        global $DB,$USER,$PAGE;
        $teamwork = $DB->get_record('teamwork',array('id' => $list->teamwork));
        $output = '';      
        $output .= html_writer::start_tag('div', array('class' => 'content'));
        //$output .= html_writer::tag('h2', get_string('templetlist', 'teamwork'));

        $output .= html_writer::start_tag('div', array('class' => 'instance'));
        if (! empty($list->container)) {
            foreach ($list->container as $id => $instance) {
                $team_instance = $DB->get_record('teamwork_team',array('id'=>$instance->team));

                $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
                $output .= html_writer::start_tag('div', array('class' => 'info'));
                $output .= html_writer::start_tag('h3', array('class' => 'coursename'));
                $output .= html_writer::start_tag('a', array('href' => "project.php?w=$list->teamwork&instance=$instance->id"));
                $output .= $instance->title.'@'.$team_instance->name;
                $output .= html_writer::end_tag('a');
                $output .= html_writer::end_tag('h3'); // .name
                $output .= html_writer::tag('div', '', array('class' => 'moreinfo'));
                if(has_capability('mod/teamwork:editsettings', $PAGE->context)){
	                $output .= html_writer::start_tag('div', array('class' => 'enrolmenticons'));
	                $std_btn = new stdClass();
	                $std_btn->url = new moodle_url('instance_edit.php',array('teamwork' => $list->teamwork, 'instance' => $instance->id));
	                $std_btn->str = get_string('edittemplet', 'teamwork');
	                $std_btn->method = 'post';
	                $output .= $this->single_button($std_btn->url, $std_btn->str, $std_btn->method);
	                $output .= html_writer::end_tag('div');
            	}

                
                $output .= html_writer::end_tag('div'); // .info
                $output .= html_writer::start_tag('div', array('class' => 'content'));
                $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
                $output .= $instance->summary;
                $output .= html_writer::end_tag('div'); // .summary

                $output .= html_writer::end_tag('div'); // .content
                $output .= html_writer::end_tag('div'); // .coursebox
            }
        }
        else {
            $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
            $output .= html_writer::start_tag('div', array('class' => 'content'));
            $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
            $output .= get_string('noprojects', 'teamwork');
            $output .= html_writer::end_tag('div'); // .summary
            $output .= html_writer::end_tag('div'); // .content
            $output .= html_writer::end_tag('div'); // .coursebox
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }
    
    /**
     * Renders the templet list
     *
     * @author skyxuan
     * @param teamwork_templet_list $list prepared for the user
     * @return string html code to be displayed
     */
    protected function render_teamwork_templet_list(teamwork_templet_list $list) {
        global $DB,$USER;
        $teamwork = $DB->get_record('teamwork',array('id' => $list->teamwork));
        $output = '';      
        $output .= html_writer::start_tag('div', array('class' => 'content'));
        //$output .= html_writer::tag('h2', get_string('templetlist', 'teamwork'));
        $output .= html_writer::tag('font', get_string('applystart', 'teamwork').': '.date("Y.m.d H:i:s",$teamwork->applystart).'<br>'.get_string('applyend', 'teamwork').': '.date("Y.m.d H:i:s",$teamwork->applyend),array('color' => '#FF0000'));
        $output .= html_writer::start_tag('div', array('class' => 'templet'));
        if (! empty($list->container)) {
            foreach ($list->container as $id => $templet) {
                $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
                $output .= html_writer::start_tag('div', array('class' => 'info'));
                $output .= html_writer::start_tag('h3', array('class' => 'coursename'));
                $output .= $templet->title;
                $output .= html_writer::end_tag('h3'); // .name
                $output .= html_writer::tag('div', '', array('class' => 'moreinfo'));
                
                if($teamwork->applyover == 0){
                	$output .= html_writer::start_tag('div', array('class' => 'enrolmenticons'));
                	$std_btn = new stdClass();
	                $std_btn->url = new moodle_url('team_edit.php',array('templetid' => $templet->id, 'teamworkid' => $list->teamwork));
	                $std_btn->str = get_string('createteam', 'teamwork');
	                $std_btn->method = 'post';
	                $std_btn->actions = array();
	                if (count($DB->get_records('teamwork_team', array('templet' => $templet->id))) >= $templet->teamlimit) {
	                    $std_btn->actions['disabled'] = true;
	                }
	                $output .= $this->single_button($std_btn->url, $std_btn->str, $std_btn->method, $std_btn->actions);
	                $output .= html_writer::end_tag('div');
                }
                
                $output .= html_writer::end_tag('div'); // .info
                $output .= html_writer::start_tag('div', array('class' => 'content'));
                $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
                $output .= $templet->summary;
                $output .= html_writer::end_tag('div'); // .summary
                
                $output .= html_writer::start_tag('div', array('class' => 'teammember')); // .teammember
	            $output .= get_string('teammembers', 'teamwork',$templet);
	            $output .= html_writer::end_tag('div'); // .teammember
	                
	            $records = $DB->get_records('teamwork_team',array('teamwork'=>$templet->teamwork,'templet'=>$templet->id));
	            $temp = new stdClass();
	            $temp->teamnum = count($records);
	            $temp->teamlimit = $templet->teamlimit;
	            $output .= html_writer::start_tag('div', array('class' => 'teamlimit')); // .teamlimit
	            $output .= get_string('teamlimits', 'teamwork',$temp);
	            $output .= html_writer::end_tag('div'); // .teamlimit
                
                $output .= html_writer::end_tag('div'); // .content
                $output .= html_writer::end_tag('div'); // .coursebox
            }
        }
        else {
            $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
            $output .= html_writer::start_tag('div', array('class' => 'content'));
            $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
            $output .= get_string('noprojects', 'teamwork');
            $output .= html_writer::end_tag('div'); // .summary
            $output .= html_writer::end_tag('div'); // .content
            $output .= html_writer::end_tag('div'); // .coursebox
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Renders the templet control buttons
     *
     * @author skyxuan
     * @param teamwork_templet_buttons $conf prepared for the user
     * @return string html code to be displayed
     */
    protected function render_teamwork_templet_buttons(teamwork_templet_buttons $conf) {
        $output = '';
        $output .= html_writer::start_tag('div', array('class' => 'buttons'));
        if ($conf->create_templet) {
            $add_btn = get_string('addproject', 'teamwork');
            $output .= $this->single_button("templet_edit.php?id=$conf->teamwork", $add_btn, 'post');
        }
        else if ($conf->can_join){
            $add_btn = get_string('joininteam', 'teamwork');
            $output .= $this->single_button("jointeam.php?teamworkid=$conf->teamwork", $add_btn, 'post');
        }
        if ($conf->edit_team_info) {
            $edit_btn = get_string('editteaminfo', 'teamwork');
            $output .= $this->single_button("team_manage.php?w=$conf->teamwork", $edit_btn, 'post');
        }
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Renders the templet list with disabled buttons
     *
     * @author skyxuan
     * @param teamwork_templet_list $list prepared for the user
     * @return string html code to be displayed
     */
    protected function render_teamwork_templet_list_member(teamwork_templet_list_member $list) {
    	global $DB,$USER;
    	$teamwork = $DB->get_record('teamwork',array('id' => $list->teamwork));
        $output = '';   
        $output .= html_writer::start_tag('div', array('class' => 'content'));
        //$output .= html_writer::tag('h2', get_string('templetlist', 'teamwork'));
        $output .= html_writer::tag('font', get_string('applystart', 'teamwork').': '.date("Y.m.d H:i:s",$teamwork->applystart).'<br>'.get_string('applyend', 'teamwork').': '.date("Y.m.d H:i:s",$teamwork->applyend),array('color' => '#FF0000'));
        $output .= html_writer::start_tag('div', array('class' => 'templet'));
        //var_dump($list->url);
        if (! empty($list->container)) {
            foreach ($list->container as $id => $templet) {
                $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
                $output .= html_writer::start_tag('div', array('class' => 'info'));
                $output .= html_writer::start_tag('h3', array('class' => 'coursename'));
                $output .= $templet->title;
                $output .= html_writer::end_tag('h3'); // .name
                $output .= html_writer::tag('div', '', array('class' => 'moreinfo'));
                if($teamwork->applyover == 0){
	                $output .= html_writer::start_tag('div', array('class' => 'enrolmenticons'));
	                $std_btn = new stdClass();
	                $std_btn->url = new moodle_url('team_edit.php',array('templetid' => $templet->id, 'teamworkid' => $list->teamwork));
	                $std_btn->str = get_string('createteam', 'teamwork');
	                $std_btn->method = 'post';
	                $output .= $this->single_button($std_btn->url, $std_btn->str, $std_btn->method, array('disabled' => true));
	                $output .= html_writer::end_tag('div');
            	}
                $output .= html_writer::end_tag('div'); // .info
                $output .= html_writer::start_tag('div', array('class' => 'content'));
                $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
                $output .= $templet->summary;
                $output .= html_writer::end_tag('div'); // .summary
                
                $output .= html_writer::start_tag('div', array('class' => 'teammember')); // .teammember
		        $output .= get_string('teammembers', 'teamwork',$templet);
		        $output .= html_writer::end_tag('div'); // .teammember
		            
		        $records = $DB->get_records('teamwork_team',array('teamwork'=>$templet->teamwork,'templet'=>$templet->id));
		        $temp = new stdClass();
		        $temp->teamnum = count($records);
		        $temp->teamlimit = $templet->teamlimit;
		        $output .= html_writer::start_tag('div', array('class' => 'teamlimit')); // .teamlimit
		        $output .= get_string('teamlimits', 'teamwork',$temp);
		        $output .= html_writer::end_tag('div'); // .teamlimit
                
                $output .= html_writer::end_tag('div'); // .content
                $output .= html_writer::end_tag('div'); // .coursebox
            }
        }
        else {
            $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
            $output .= html_writer::start_tag('div', array('class' => 'content'));
            $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
            $output .= get_string('noprojects', 'teamwork');
            $output .= html_writer::end_tag('div'); // .summary            
            $output .= html_writer::end_tag('div'); // .content
            $output .= html_writer::end_tag('div'); // .coursebox
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Renders the templet list with edit buttons
     *
     * @author skyxuan
     * @param teamwork_templet_list_manager $list prepared for the user
     * @return string html code to be displayed
     */
    protected function render_teamwork_templet_list_manager(teamwork_templet_list_manager $list) {
    	global $DB;
    	$teamwork = $DB->get_record('teamwork',array('id' => $list->teamwork));
        $output = '';
        $output .= html_writer::start_tag('div', array('class' => 'content'));
        //$output .= html_writer::tag('h2', get_string('templetlist', 'teamwork'));
        $output .= html_writer::tag('font', get_string('applystart', 'teamwork').': '.date("Y.m.d H:i:s",$teamwork->applystart).'<br>'.get_string('applyend', 'teamwork').': '.date("Y.m.d H:i:s",$teamwork->applyend),array('color' => '#FF0000'));
        $output .= html_writer::start_tag('div', array('class' => 'templet'));
        //var_dump($list->url);
        if (! empty($list->container)) {
            foreach ($list->container as $id => $templet) {
                $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
                $output .= html_writer::start_tag('div', array('class' => 'info'));
                $output .= html_writer::start_tag('h3', array('class' => 'coursename'));
                $output .= html_writer::start_tag('a', array('href' => "team_manage.php?w=$list->teamwork&view=$templet->id"));
                $output .= $templet->title;
                $output .= html_writer::end_tag('a'); // .name
                $output .= html_writer::end_tag('h3'); // .name
                $output .= html_writer::tag('div', '', array('class' => 'moreinfo'));
                if($teamwork->applyover == 0){
	                $output .= html_writer::start_tag('div', array('class' => 'enrolmenticons'));
	                $std_btn = new stdClass();
	                $std_btn->url = new moodle_url('templet_edit.php',array('id' => $list->teamwork, 'update' => $templet->id));
	                $std_btn->str = get_string('edittemplet', 'teamwork');
	                $std_btn->method = 'post';
	                $output .= $this->single_button($std_btn->url, $std_btn->str, $std_btn->method);
	                $output .= html_writer::end_tag('div');
            	}
                
                $output .= html_writer::end_tag('div'); // .info
                $output .= html_writer::start_tag('div', array('class' => 'content'));
                $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
                $output .= $templet->summary;
                $output .= html_writer::end_tag('div'); // .summary
                
                $output .= html_writer::start_tag('div', array('class' => 'teammember')); // .teammember
                $output .= get_string('teammembers', 'teamwork',$templet);
                $output .= html_writer::end_tag('div'); // .teammember
                
                $records = $DB->get_records('teamwork_team',array('teamwork'=>$templet->teamwork,'templet'=>$templet->id));
                $temp = new stdClass();
                $temp->teamnum = count($records);
                $temp->teamlimit = $templet->teamlimit;
                $output .= html_writer::start_tag('div', array('class' => 'teamlimit')); // .teamlimit
                $output .= get_string('teamlimits', 'teamwork',$temp);
                $output .= html_writer::end_tag('div'); // .teamlimit
                
                $output .= html_writer::end_tag('div'); // .content
                $output .= html_writer::end_tag('div'); // .coursebox
            }
        }
        else {
            $output .= html_writer::start_tag('div', array('class' => 'coursebox clearfix'));
            $output .= html_writer::start_tag('div', array('class' => 'content'));
            $output .= html_writer::start_tag('div', array('class' => 'summary')); // .summary
            $output .= get_string('noprojects', 'teamwork');
            $output .= html_writer::end_tag('div'); // .summary
            $output .= html_writer::end_tag('div'); // .content
            $output .= html_writer::end_tag('div'); // .coursebox
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }
    
    protected function render_teamwork_team_invitedkey(teamwork_team_invitedkey $teaminvitedkey) {
    	global $DB;
    	$team = $DB->get_record('teamwork_team',array('id' => $teaminvitedkey->teamid));
		$output = '';
        $output .= html_writer::start_tag('div', array('class' => 'alert alert-success'));
		$output .= $team->invitedkey;
		$output .= html_writer::end_tag('div');
		return $output;
	}
	
	protected function render_teamwork_team_manage(teamwork_team_manage $teammanage) {
		global $DB;
    	$teammembers = $DB->get_records('teamwork_teammembers',array('teamwork' => $teammanage->teamwork,'team' =>$teammanage->teamid));
		$table = new html_table();
		$table->head = array(get_string('name','teamwork'),get_string('jointime','teamwork'),get_string('activitysituation','teamwork'),get_string('removemember','teamwork'));	
		$rowarray = array();
		
		foreach($teammembers as $member){
			$row = new html_table_row();
			$cell1 = new html_table_cell();
			$cell2 = new html_table_cell();
			$cell3 = new html_table_cell();
			$icon = 't/delete';
			$member_record = $DB->get_record('user',array('id'=>$member->userid));
			$a = array();
			$count  = $DB->count_records('logstore_standard_log',array('userid' => $member->userid));
			$cell_name = html_writer::link($task->link, $member_record->lastname.$member_record->firstname);
        	$cell1->text = $cell_name;
        	$cell2->text = date("Y.m.d H:i:s",$member->time);
			$cell3->text = $count;
        	$row->cells[] = $cell1;
        	$row->cells[] = $cell2;
			$row->cells[] = $cell3;
        	$row->cells[] = $this->output->action_icon("team_manage.php?w=$teammanage->teamwork&teamid=$teammanage->teamid&remove=$member->userid", new pix_icon($icon, get_string('removemember', 'teamwork')));
        
        	$rowarray[] = $row;
		}
		
		$table->data = $rowarray;
		$output .= html_writer::table($table);
		return $output;
	}
    /**
     * Renders the user plannner tool
     *
     * @param teamwork_user_plan $plan prepared for the user
     * @return string html code to be displayed
     */
    protected function render_teamwork_user_plan(teamwork_user_plan $plan,$showphase=0) {
        $table = new html_table();
        $table->attributes['class'] = 'userplan';
        $table->head = array();
        $table->colclasses = array();
        $row = array();
        foreach($plan->instance as $id => $instance) {
        	$row[$id] = new html_table_row();
        	$row[$id]->attributes['class'] = 'phasetasks';
        	foreach ($instance->phases as $phasecode => $phase) {
        		$title = html_writer::tag('span', $phase->name);
        		$icon = 'i/marker';
        		$actions = '';
            $actions .= $this->output->action_icon("project.php?w=$instance->teamwork&instance=$instance->id&phase=$phase->orderid", new pix_icon($icon, get_string('switchphase', 'teamwork')));
            $actions = $this->output->container($actions, 'actions');
        		$table->head[] = $this->output->container($title.$actions);
        		$classes = 'phase' . $phasecode;
        		$showphase = empty($showphase) ? $instance->currentphase : $showphase;
        		if($showphase == $phasecode) {
        			$classes .= ' active';
        		}else {
        			$classes .= ' nonactive';
        		}
                
            	$table->colclasses[] = $classes;
        		$cell = new html_table_cell();
        		$cell->text = get_string('phasedescription','teamwork').':'.$phase->description;
            	$row[$id]->cells[] = $cell;
        	}
		}
		/*        foreach ($plan->phases as $phasecode => $phase) {
            $title = html_writer::tag('span', $phase->name);
            $actions = '';
            foreach ($phase->actions as $action) {
                switch ($action->type) {
                case 'switchphase':
                    $icon = 'i/marker';
                    if ($phasecode == teamwork::PHASE_ASSESSMENT
                            and $plan->teamwork->phase == teamwork::PHASE_SUBMISSION
                            and $plan->teamwork->phaseswitchassessment) {
                        $icon = 'i/scheduled';
                    }
                    $actions .= $this->output->action_icon($action->url, new pix_icon($icon, get_string('switchphase', 'teamwork')));
                    break;
                }
            }
            if (!empty($actions)) {
                $actions = $this->output->container($actions, 'actions');
            }
            $table->head[] = $this->output->container($title . $actions);
            $classes = 'phase' . $phasecode;
            if ($phase->active) {
                $classes .= ' active';
            } else {
                $classes .= ' nonactive';
            }
            $table->colclasses[] = $classes;
            $cell = new html_table_cell();
            $cell->text = $this->helper_user_plan_tasks($phase->tasks);
            $row->cells[] = $cell;
        }*/
        $table->data = $row;

        return html_writer::table($table);
    }

    /**
     * Renders the result of the submissions allocation process
     *
     * @param teamwork_allocation_result $result as returned by the allocator's init() method
     * @return string HTML to be echoed
     */
    protected function render_teamwork_allocation_result(teamwork_allocation_result $result) {
        global $CFG;

        $status = $result->get_status();

        if (is_null($status) or $status == teamwork_allocation_result::STATUS_VOID) {
            debugging('Attempt to render teamwork_allocation_result with empty status', DEBUG_DEVELOPER);
            return '';
        }

        switch ($status) {
        case teamwork_allocation_result::STATUS_FAILED:
            if ($message = $result->get_message()) {
                $message = new teamwork_message($message, teamwork_message::TYPE_ERROR);

            } else {
                $message = new teamwork_message(get_string('allocationerror', 'teamwork'), teamwork_message::TYPE_ERROR);
            }
            break;

        case teamwork_allocation_result::STATUS_CONFIGURED:
            if ($message = $result->get_message()) {
                $message = new teamwork_message($message, teamwork_message::TYPE_INFO);
            } else {
                $message = new teamwork_message(get_string('allocationconfigured', 'teamwork'), teamwork_message::TYPE_INFO);
            }
            break;

        case teamwork_allocation_result::STATUS_EXECUTED:
            if ($message = $result->get_message()) {
                $message = new teamwork_message($message, teamwork_message::TYPE_OK);
            } else {
                $message = new teamwork_message(get_string('allocationdone', 'teamwork'), teamwork_message::TYPE_OK);
            }
            break;

        default:
            throw new coding_exception('Unknown allocation result status', $status);
        }

        // start with the message
        $o = $this->render($message);

        // display the details about the process if available
        $logs = $result->get_logs();
        if (is_array($logs) and !empty($logs)) {
            $o .= html_writer::start_tag('ul', array('class' => 'allocation-init-results'));
            foreach ($logs as $log) {
                if ($log->type == 'debug' and !$CFG->debugdeveloper) {
                    // display allocation debugging messages for developers only
                    continue;
                }
                $class = $log->type;
                if ($log->indent) {
                    $class .= ' indent';
                }
                $o .= html_writer::tag('li', $log->message, array('class' => $class)).PHP_EOL;
            }
            $o .= html_writer::end_tag('ul');
        }

        return $o;
    }

    /**
     * Renders the teamwork grading report
     *
     * @param teamwork_grading_report $gradingreport
     * @return string html code
     */
    protected function render_teamwork_grading_report(teamwork_grading_report $gradingreport) {

        $data       = $gradingreport->get_data();
        $options    = $gradingreport->get_options();
        $grades     = $data->grades;
        $userinfo   = $data->userinfo;

        if (empty($grades)) {
            return '';
        }

        $table = new html_table();
        $table->attributes['class'] = 'grading-report';

        $sortbyfirstname = $this->helper_sortable_heading(get_string('firstname'), 'firstname', $options->sortby, $options->sorthow);
        $sortbylastname = $this->helper_sortable_heading(get_string('lastname'), 'lastname', $options->sortby, $options->sorthow);
        if (self::fullname_format() == 'lf') {
            $sortbyname = $sortbylastname . ' / ' . $sortbyfirstname;
        } else {
            $sortbyname = $sortbyfirstname . ' / ' . $sortbylastname;
        }

        $table->head = array();
        $table->head[] = $sortbyname;
        $table->head[] = $this->helper_sortable_heading(get_string('submission', 'teamwork'), 'submissiontitle',
                $options->sortby, $options->sorthow);
        $table->head[] = $this->helper_sortable_heading(get_string('receivedgrades', 'teamwork'));
        if ($options->showsubmissiongrade) {
            $table->head[] = $this->helper_sortable_heading(get_string('submissiongradeof', 'teamwork', $data->maxgrade),
                    'submissiongrade', $options->sortby, $options->sorthow);
        }
        $table->head[] = $this->helper_sortable_heading(get_string('givengrades', 'teamwork'));
        if ($options->showgradinggrade) {
            $table->head[] = $this->helper_sortable_heading(get_string('gradinggradeof', 'teamwork', $data->maxgradinggrade),
                    'gradinggrade', $options->sortby, $options->sorthow);
        }

        $table->rowclasses  = array();
        $table->colclasses  = array();
        $table->data        = array();

        foreach ($grades as $participant) {
            $numofreceived  = count($participant->reviewedby);
            $numofgiven     = count($participant->reviewerof);
            $published      = $participant->submissionpublished;

            // compute the number of <tr> table rows needed to display this participant
            if ($numofreceived > 0 and $numofgiven > 0) {
                $numoftrs       = teamwork::lcm($numofreceived, $numofgiven);
                $spanreceived   = $numoftrs / $numofreceived;
                $spangiven      = $numoftrs / $numofgiven;
            } elseif ($numofreceived == 0 and $numofgiven > 0) {
                $numoftrs       = $numofgiven;
                $spanreceived   = $numoftrs;
                $spangiven      = $numoftrs / $numofgiven;
            } elseif ($numofreceived > 0 and $numofgiven == 0) {
                $numoftrs       = $numofreceived;
                $spanreceived   = $numoftrs / $numofreceived;
                $spangiven      = $numoftrs;
            } else {
                $numoftrs       = 1;
                $spanreceived   = 1;
                $spangiven      = 1;
            }

            for ($tr = 0; $tr < $numoftrs; $tr++) {
                $row = new html_table_row();
                if ($published) {
                    $row->attributes['class'] = 'published';
                }
                // column #1 - participant - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_participant($participant, $userinfo);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'participant';
                    $row->cells[] = $cell;
                }
                // column #2 - submission - spans over all rows
                if ($tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_submission($participant);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submission';
                    $row->cells[] = $cell;
                }
                // column #3 - received grades
                if ($tr % $spanreceived == 0) {
                    $idx = intval($tr / $spanreceived);
                    $assessment = self::array_nth($participant->reviewedby, $idx);
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_assessment($assessment, $options->showreviewernames, $userinfo,
                            get_string('gradereceivedfrom', 'teamwork'));
                    $cell->rowspan = $spanreceived;
                    $cell->attributes['class'] = 'receivedgrade';
                    if (is_null($assessment) or is_null($assessment->grade)) {
                        $cell->attributes['class'] .= ' null';
                    } else {
                        $cell->attributes['class'] .= ' notnull';
                    }
                    $row->cells[] = $cell;
                }
                // column #4 - total grade for submission
                if ($options->showsubmissiongrade and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->submissiongrade, $participant->submissiongradeover);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'submissiongrade';
                    $row->cells[] = $cell;
                }
                // column #5 - given grades
                if ($tr % $spangiven == 0) {
                    $idx = intval($tr / $spangiven);
                    $assessment = self::array_nth($participant->reviewerof, $idx);
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_assessment($assessment, $options->showauthornames, $userinfo,
                            get_string('gradegivento', 'teamwork'));
                    $cell->rowspan = $spangiven;
                    $cell->attributes['class'] = 'givengrade';
                    if (is_null($assessment) or is_null($assessment->grade)) {
                        $cell->attributes['class'] .= ' null';
                    } else {
                        $cell->attributes['class'] .= ' notnull';
                    }
                    $row->cells[] = $cell;
                }
                // column #6 - total grade for assessment
                if ($options->showgradinggrade and $tr == 0) {
                    $cell = new html_table_cell();
                    $cell->text = $this->helper_grading_report_grade($participant->gradinggrade);
                    $cell->rowspan = $numoftrs;
                    $cell->attributes['class'] = 'gradinggrade';
                    $row->cells[] = $cell;
                }

                $table->data[] = $row;
            }
        }

        return html_writer::table($table);
    }

    /**
     * Renders the feedback for the author of the submission
     *
     * @param teamwork_feedback_author $feedback
     * @return string HTML
     */
    protected function render_teamwork_feedback_author(teamwork_feedback_author $feedback) {
        return $this->helper_render_feedback($feedback);
    }

    /**
     * Renders the feedback for the reviewer of the submission
     *
     * @param teamwork_feedback_reviewer $feedback
     * @return string HTML
     */
    protected function render_teamwork_feedback_reviewer(teamwork_feedback_reviewer $feedback) {
        return $this->helper_render_feedback($feedback);
    }

    /**
     * Helper method to rendering feedback
     *
     * @param teamwork_feedback_author|teamwork_feedback_reviewer $feedback
     * @return string HTML
     */
    private function helper_render_feedback($feedback) {

        $o  = '';    // output HTML code
        $o .= $this->output->container_start('feedback feedbackforauthor');
        $o .= $this->output->container_start('header');
        $o .= $this->output->heading(get_string('feedbackby', 'teamwork', s(fullname($feedback->get_provider()))), 3, 'title');

        $userpic = $this->output->user_picture($feedback->get_provider(), array('courseid' => $this->page->course->id, 'size' => 32));
        $o .= $this->output->container($userpic, 'picture');
        $o .= $this->output->container_end(); // end of header

        $content = format_text($feedback->get_content(), $feedback->get_format(), array('overflowdiv' => true));
        $o .= $this->output->container($content, 'content');

        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Renders the full assessment
     *
     * @param teamwork_assessment $assessment
     * @return string HTML
     */
    protected function render_teamwork_assessment(teamwork_assessment $assessment) {

        $o = ''; // output HTML code
        $anonymous = is_null($assessment->reviewer);
        $classes = 'assessment-full';
        if ($anonymous) {
            $classes .= ' anonymous';
        }

        $o .= $this->output->container_start($classes);
        $o .= $this->output->container_start('header');

        if (!empty($assessment->title)) {
            $title = s($assessment->title);
        } else {
            $title = get_string('assessment', 'teamwork');
        }
        if (($assessment->url instanceof moodle_url) and ($this->page->url != $assessment->url)) {
            $o .= $this->output->container(html_writer::link($assessment->url, $title), 'title');
        } else {
            $o .= $this->output->container($title, 'title');
        }

        if (!$anonymous) {
            $reviewer   = $assessment->reviewer;
            $userpic    = $this->output->user_picture($reviewer, array('courseid' => $this->page->course->id, 'size' => 32));

            $userurl    = new moodle_url('/user/view.php',
                                       array('id' => $reviewer->id, 'course' => $this->page->course->id));
            $a          = new stdClass();
            $a->name    = fullname($reviewer);
            $a->url     = $userurl->out();
            $byfullname = get_string('assessmentby', 'teamwork', $a);
            $oo         = $this->output->container($userpic, 'picture');
            $oo        .= $this->output->container($byfullname, 'fullname');

            $o .= $this->output->container($oo, 'reviewer');
        }

        if (is_null($assessment->realgrade)) {
            $o .= $this->output->container(
                get_string('notassessed', 'teamwork'),
                'grade nograde'
            );
        } else {
            $a              = new stdClass();
            $a->max         = $assessment->maxgrade;
            $a->received    = $assessment->realgrade;
            $o .= $this->output->container(
                get_string('gradeinfo', 'teamwork', $a),
                'grade'
            );

            if (!is_null($assessment->weight) and $assessment->weight != 1) {
                $o .= $this->output->container(
                    get_string('weightinfo', 'teamwork', $assessment->weight),
                    'weight'
                );
            }
        }

        $o .= $this->output->container_start('actions');
        foreach ($assessment->actions as $action) {
            $o .= $this->output->single_button($action->url, $action->label, $action->method);
        }
        $o .= $this->output->container_end(); // actions

        $o .= $this->output->container_end(); // header

        if (!is_null($assessment->form)) {
            $o .= print_collapsible_region_start('assessment-form-wrapper', uniqid('teamwork-assessment'),
                    get_string('assessmentform', 'teamwork'), '', false, true);
            $o .= $this->output->container(self::moodleform($assessment->form), 'assessment-form');
            $o .= print_collapsible_region_end(true);

            if (!$assessment->form->is_editable()) {
                $o .= $this->overall_feedback($assessment);
            }
        }

        $o .= $this->output->container_end(); // main wrapper

        return $o;
    }

    /**
     * Renders the assessment of an example submission
     *
     * @param teamwork_example_assessment $assessment
     * @return string HTML
     */
    protected function render_teamwork_example_assessment(teamwork_example_assessment $assessment) {
        return $this->render_teamwork_assessment($assessment);
    }

    /**
     * Renders the reference assessment of an example submission
     *
     * @param teamwork_example_reference_assessment $assessment
     * @return string HTML
     */
    protected function render_teamwork_example_reference_assessment(teamwork_example_reference_assessment $assessment) {
        return $this->render_teamwork_assessment($assessment);
    }

    /**
     * Renders the overall feedback for the author of the submission
     *
     * @param teamwork_assessment $assessment
     * @return string HTML
     */
    protected function overall_feedback(teamwork_assessment $assessment) {

        $content = $assessment->get_overall_feedback_content();

        if ($content === false) {
            return '';
        }

        $o = '';

        if (!is_null($content)) {
            $o .= $this->output->container($content, 'content');
        }

        $attachments = $assessment->get_overall_feedback_attachments();

        if (!empty($attachments)) {
            $o .= $this->output->container_start('attachments');
            $images = '';
            $files = '';
            foreach ($attachments as $attachment) {
                $icon = $this->output->pix_icon(file_file_icon($attachment), get_mimetype_description($attachment),
                    'moodle', array('class' => 'icon'));
                $link = html_writer::link($attachment->fileurl, $icon.' '.substr($attachment->filepath.$attachment->filename, 1));
                if (file_mimetype_in_typegroup($attachment->mimetype, 'web_image')) {
                    $preview = html_writer::empty_tag('img', array('src' => $attachment->previewurl, 'alt' => '', 'class' => 'preview'));
                    $preview = html_writer::tag('a', $preview, array('href' => $attachment->fileurl));
                    $images .= $this->output->container($preview);
                } else {
                    $files .= html_writer::tag('li', $link, array('class' => $attachment->mimetype));
                }
            }
            if ($images) {
                $images = $this->output->container($images, 'images');
            }

            if ($files) {
                $files = html_writer::tag('ul', $files, array('class' => 'files'));
            }

            $o .= $images.$files;
            $o .= $this->output->container_end();
        }

        if ($o === '') {
            return '';
        }

        $o = $this->output->box($o, 'overallfeedback');
        $o = print_collapsible_region($o, 'overall-feedback-wrapper', uniqid('teamwork-overall-feedback'),
            get_string('overallfeedback', 'teamwork'), '', false, true);

        return $o;
    }

    /**
     * Renders a perpage selector for teamwork listings
     *
     * The scripts using this have to define the $PAGE->url prior to calling this
     * and deal with eventually submitted value themselves.
     *
     * @param int $current current value of the perpage parameter
     * @return string HTML
     */
    public function perpage_selector($current=10) {

        $options = array();
        foreach (array(10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 200, 300, 400, 500, 1000) as $option) {
            if ($option != $current) {
                $options[$option] = $option;
            }
        }
        $select = new single_select($this->page->url, 'perpage', $options, '', array('' => get_string('showingperpagechange', 'mod_teamwork')));
        $select->label = get_string('showingperpage', 'mod_teamwork', $current);
        $select->method = 'post';

        return $this->output->container($this->output->render($select), 'perpagewidget');
    }

    /**
     * Renders the user's final grades
     *
     * @param teamwork_final_grades $grades with the info about grades in the gradebook
     * @return string HTML
     */
    protected function render_teamwork_final_grades(teamwork_final_grades $grades) {

        $out = html_writer::start_tag('div', array('class' => 'finalgrades'));

        if (!empty($grades->submissiongrade)) {
            $cssclass = 'grade submissiongrade';
            if ($grades->submissiongrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('submissiongrade', 'mod_teamwork'), array('class' => 'gradetype')) .
                html_writer::tag('div', $grades->submissiongrade->str_long_grade, array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        if (!empty($grades->assessmentgrade)) {
            $cssclass = 'grade assessmentgrade';
            if ($grades->assessmentgrade->hidden) {
                $cssclass .= ' hiddengrade';
            }
            $out .= html_writer::tag(
                'div',
                html_writer::tag('div', get_string('gradinggrade', 'mod_teamwork'), array('class' => 'gradetype')) .
                html_writer::tag('div', $grades->assessmentgrade->str_long_grade, array('class' => 'gradevalue')),
                array('class' => $cssclass)
            );
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Internal rendering helper methods
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Renders a list of files attached to the submission
     *
     * If format==html, then format a html string. If format==text, then format a text-only string.
     * Otherwise, returns html for non-images and html to display the image inline.
     *
     * @param int $submissionid submission identifier
     * @param string format the format of the returned string - html|text
     * @return string formatted text to be echoed
     */
    protected function helper_submission_attachments($submissionid, $format = 'html') {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');

        $fs     = get_file_storage();
        $ctx    = $this->page->context;
        $files  = $fs->get_area_files($ctx->id, 'mod_teamwork', 'submission_attachment', $submissionid);

        $outputimgs     = '';   // images to be displayed inline
        $outputfiles    = '';   // list of attachment files

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filepath   = $file->get_filepath();
            $filename   = $file->get_filename();
            $fileurl    = moodle_url::make_pluginfile_url($ctx->id, 'mod_teamwork', 'submission_attachment',
                            $submissionid, $filepath, $filename, true);
            $embedurl   = moodle_url::make_pluginfile_url($ctx->id, 'mod_teamwork', 'submission_attachment',
                            $submissionid, $filepath, $filename, false);
            $embedurl   = new moodle_url($embedurl, array('preview' => 'bigthumb'));
            $type       = $file->get_mimetype();
            $image      = $this->output->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));

            $linkhtml   = html_writer::link($fileurl, $image) . substr($filepath, 1) . html_writer::link($fileurl, $filename);
            $linktxt    = "$filename [$fileurl]";

            if ($format == 'html') {
                if (file_mimetype_in_typegroup($type, 'web_image')) {
                    $preview     = html_writer::empty_tag('img', array('src' => $embedurl, 'alt' => '', 'class' => 'preview'));
                    $preview     = html_writer::tag('a', $preview, array('href' => $fileurl));
                    $outputimgs .= $this->output->container($preview);

                } else {
                    $outputfiles .= html_writer::tag('li', $linkhtml, array('class' => $type));
                }

            } else if ($format == 'text') {
                $outputfiles .= $linktxt . PHP_EOL;
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $outputfiles .= plagiarism_get_links(array('userid' => $file->get_userid(),
                    'file' => $file,
                    'cmid' => $this->page->cm->id,
                    'course' => $this->page->course->id));
            }
        }

        if ($format == 'html') {
            if ($outputimgs) {
                $outputimgs = $this->output->container($outputimgs, 'images');
            }

            if ($outputfiles) {
                $outputfiles = html_writer::tag('ul', $outputfiles, array('class' => 'files'));
            }

            return $this->output->container($outputimgs . $outputfiles, 'attachments');

        } else {
            return $outputfiles;
        }
    }

    /**
     * Renders the tasks for the single phase in the user plan
     *
     * @param stdClass $tasks
     * @return string html code
     */
    protected function helper_team_info_tasks(array $tasks) {
        $out = '';
        foreach ($tasks as $taskcode => $task) {
            $classes = '';
            $icon = null;
            if ($task->completed === true) {
                $classes .= ' completed';
            } elseif ($task->completed === false) {
                $classes .= ' fail';
            } elseif ($task->completed === 'info') {
                $classes .= ' info';
            }
            if (is_null($task->link)) {
                $title = $task->title;
            } else {
                $title = html_writer::link($task->link, $task->title);
            }
			$s = $task->details;
            $title = $this->output->container($title.$s, 'title');
            
            $out .= html_writer::tag('li', $title, array('class' => $classes));
        }
        if ($out) {
            $out = html_writer::tag('ul', $out, array('class' => 'tasks'));
        }
        return $out;
    }


    /**
     * Renders the tasks for the single phase in the user plan
     *
     * @param stdClass $tasks
     * @return string html code
     */
    protected function helper_user_plan_tasks(array $tasks) {
        $out = '';
        foreach ($tasks as $taskcode => $task) {
            $classes = '';
            $icon = null;
            if ($task->completed === true) {
                $classes .= ' completed';
            } elseif ($task->completed === false) {
                $classes .= ' fail';
            } elseif ($task->completed === 'info') {
                $classes .= ' info';
            }
            if (is_null($task->link)) {
                $title = $task->title;
            } else {
                $title = html_writer::link($task->link, $task->title);
            }
            $title = $this->output->container($title, 'title');
            $details = $this->output->container($task->details, 'details');
            $out .= html_writer::tag('li', $title . $details, array('class' => $classes));
        }
        if ($out) {
            $out = html_writer::tag('ul', $out, array('class' => 'tasks'));
        }
        return $out;
    }

    /**
     * Renders a text with icons to sort by the given column
     *
     * This is intended for table headings.
     *
     * @param string $text    The heading text
     * @param string $sortid  The column id used for sorting
     * @param string $sortby  Currently sorted by (column id)
     * @param string $sorthow Currently sorted how (ASC|DESC)
     *
     * @return string
     */
    protected function helper_sortable_heading($text, $sortid=null, $sortby=null, $sorthow=null) {
        global $PAGE;

        $out = html_writer::tag('span', $text, array('class'=>'text'));

        if (!is_null($sortid)) {
            if ($sortby !== $sortid or $sorthow !== 'ASC') {
                $url = new moodle_url($PAGE->url);
                $url->params(array('sortby' => $sortid, 'sorthow' => 'ASC'));
                $out .= $this->output->action_icon($url, new pix_icon('t/sort_asc', get_string('sortasc', 'teamwork')),
                    null, array('class' => 'iconsort sort asc'));
            }
            if ($sortby !== $sortid or $sorthow !== 'DESC') {
                $url = new moodle_url($PAGE->url);
                $url->params(array('sortby' => $sortid, 'sorthow' => 'DESC'));
                $out .= $this->output->action_icon($url, new pix_icon('t/sort_desc', get_string('sortdesc', 'teamwork')),
                    null, array('class' => 'iconsort sort desc'));
            }
        }
        return $out;
}

    /**
     * @param stdClass $participant
     * @param array $userinfo
     * @return string
     */
    protected function helper_grading_report_participant(stdclass $participant, array $userinfo) {
        $userid = $participant->userid;
        $out  = $this->output->user_picture($userinfo[$userid], array('courseid' => $this->page->course->id, 'size' => 35));
        $out .= html_writer::tag('span', fullname($userinfo[$userid]));

        return $out;
    }

    /**
     * @param stdClass $participant
     * @return string
     */
    protected function helper_grading_report_submission(stdclass $participant) {
        global $CFG;

        if (is_null($participant->submissionid)) {
            $out = $this->output->container(get_string('nosubmissionfound', 'teamwork'), 'info');
        } else {
            $url = new moodle_url('/mod/teamwork/submission.php',
                                  array('cmid' => $this->page->context->instanceid, 'id' => $participant->submissionid));
            $out = html_writer::link($url, format_string($participant->submissiontitle), array('class'=>'title'));
        }

        return $out;
    }

    /**
     * @todo Highlight the nulls
     * @param stdClass|null $assessment
     * @param bool $shownames
     * @param string $separator between the grade and the reviewer/author
     * @return string
     */
    protected function helper_grading_report_assessment($assessment, $shownames, array $userinfo, $separator) {
        global $CFG;

        if (is_null($assessment)) {
            return get_string('nullgrade', 'teamwork');
        }
        $a = new stdclass();
        $a->grade = is_null($assessment->grade) ? get_string('nullgrade', 'teamwork') : $assessment->grade;
        $a->gradinggrade = is_null($assessment->gradinggrade) ? get_string('nullgrade', 'teamwork') : $assessment->gradinggrade;
        $a->weight = $assessment->weight;
        // grrr the following logic should really be handled by a future language pack feature
        if (is_null($assessment->gradinggradeover)) {
            if ($a->weight == 1) {
                $grade = get_string('formatpeergrade', 'teamwork', $a);
            } else {
                $grade = get_string('formatpeergradeweighted', 'teamwork', $a);
            }
        } else {
            $a->gradinggradeover = $assessment->gradinggradeover;
            if ($a->weight == 1) {
                $grade = get_string('formatpeergradeover', 'teamwork', $a);
            } else {
                $grade = get_string('formatpeergradeoverweighted', 'teamwork', $a);
            }
        }
        $url = new moodle_url('/mod/teamwork/assessment.php',
                              array('asid' => $assessment->assessmentid));
        $grade = html_writer::link($url, $grade, array('class'=>'grade'));

        if ($shownames) {
            $userid = $assessment->userid;
            $name   = $this->output->user_picture($userinfo[$userid], array('courseid' => $this->page->course->id, 'size' => 16));
            $name  .= html_writer::tag('span', fullname($userinfo[$userid]), array('class' => 'fullname'));
            $name   = $separator . html_writer::tag('span', $name, array('class' => 'user'));
        } else {
            $name   = '';
        }

        return $this->output->container($grade . $name, 'assessmentdetails');
    }

    /**
     * Formats the aggreagated grades
     */
    protected function helper_grading_report_grade($grade, $over=null) {
        $a = new stdclass();
        $a->grade = is_null($grade) ? get_string('nullgrade', 'teamwork') : $grade;
        if (is_null($over)) {
            $text = get_string('formataggregatedgrade', 'teamwork', $a);
        } else {
            $a->over = is_null($over) ? get_string('nullgrade', 'teamwork') : $over;
            $text = get_string('formataggregatedgradeover', 'teamwork', $a);
        }
        return $text;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Static helpers
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param moodleform $mform
     * @return string HTML
     */
    protected static function moodleform(moodleform $mform) {

        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Helper function returning the n-th item of the array
     *
     * @param array $a
     * @param int   $n from 0 to m, where m is th number of items in the array
     * @return mixed the $n-th element of $a
     */
    protected static function array_nth(array $a, $n) {
        $keys = array_keys($a);
        if ($n < 0 or $n > count($keys) - 1) {
            return null;
        }
        $key = $keys[$n];
        return $a[$key];
    }

    /**
     * Tries to guess the fullname format set at the site
     *
     * @return string fl|lf
     */
    protected static function fullname_format() {
        $fake = new stdclass(); // fake user
        $fake->lastname = 'LLLL';
        $fake->firstname = 'FFFF';
        $fullname = get_string('fullnamedisplay', '', $fake);
        if (strpos($fullname, 'LLLL') < strpos($fullname, 'FFFF')) {
            return 'lf';
        } else {
            return 'fl';
        }
    }
}

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
 * @package   mod_randchoice
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @global int $CHOICE_COLUMN_HEIGHT */
global $CHOICE_COLUMN_HEIGHT;
$CHOICE_COLUMN_HEIGHT = 300;

/** @global int $CHOICE_COLUMN_WIDTH */
global $CHOICE_COLUMN_WIDTH;
$CHOICE_COLUMN_WIDTH = 300;

define('CHOICE_PUBLISH_ANONYMOUS', '0');
define('CHOICE_PUBLISH_NAMES',     '1');

define('CHOICE_SHOWRESULTS_NOT',          '0');
define('CHOICE_SHOWRESULTS_AFTER_ANSWER', '1');
define('CHOICE_SHOWRESULTS_AFTER_CLOSE',  '2');
define('CHOICE_SHOWRESULTS_ALWAYS',       '3');

define('CHOICE_DISPLAY_HORIZONTAL',  '0');
define('CHOICE_DISPLAY_VERTICAL',    '1');

/** @global array $CHOICE_PUBLISH */
global $CHOICE_PUBLISH;
$CHOICE_PUBLISH = array (CHOICE_PUBLISH_ANONYMOUS  => get_string('publishanonymous', 'randchoice'),
                         CHOICE_PUBLISH_NAMES      => get_string('publishnames', 'randchoice'));

/** @global array $CHOICE_SHOWRESULTS */
global $CHOICE_SHOWRESULTS;
$CHOICE_SHOWRESULTS = array (CHOICE_SHOWRESULTS_NOT          => get_string('publishnot', 'randchoice'),
                         CHOICE_SHOWRESULTS_AFTER_ANSWER => get_string('publishafteranswer', 'randchoice'),
                         CHOICE_SHOWRESULTS_AFTER_CLOSE  => get_string('publishafterclose', 'randchoice'),
                         CHOICE_SHOWRESULTS_ALWAYS       => get_string('publishalways', 'randchoice'));

/** @global array $CHOICE_DISPLAY */
global $CHOICE_DISPLAY;
$CHOICE_DISPLAY = array (CHOICE_DISPLAY_HORIZONTAL   => get_string('displayhorizontal', 'randchoice'),
                         CHOICE_DISPLAY_VERTICAL     => get_string('displayvertical','randchoice'));

/// Standard functions /////////////////////////////////////////////////////////

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $randchoice
 * @return object|null
 */
function randchoice_user_outline($course, $user, $mod, $randchoice) {
    global $DB;
    if ($answer = $DB->get_record('randchoice_answers', array('randchoiceid' => $randchoice->id, 'userid' => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(randchoice_get_option_text($randchoice, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $randchoice
 * @return string|void
 */
function randchoice_user_complete($course, $user, $mod, $randchoice) {
    global $DB;
    if ($answer = $DB->get_record('randchoice_answers', array("randchoiceid" => $randchoice->id, "userid" => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(randchoice_get_option_text($randchoice, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        echo get_string("answered", "randchoice").": $result->info. ".get_string("updated", '', userdate($result->time));
    } else {
        print_string("notanswered", "randchoice");
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $randchoice
 * @return int
 */
function randchoice_add_instance($randchoice) {
    global $DB;

    $randchoice->timemodified = time();

    if (empty($randchoice->timerestrict)) {
        $randchoice->timeopen = 0;
        $randchoice->timeclose = 0;
    }

    //insert answers
    $randchoice->id = $DB->insert_record("randchoice", $randchoice);
    foreach ($randchoice->option as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $option = new stdClass();
            $option->text = $value;
            $option->randchoiceid = $randchoice->id;
            if (isset($randchoice->limit[$key])) {
                $option->maxanswers = $randchoice->limit[$key];
            }
            $option->timemodified = time();
            $DB->insert_record("randchoice_options", $option);
        }
    }

    return $randchoice->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $randchoice
 * @return bool
 */
function randchoice_update_instance($randchoice) {
    global $DB;

    $randchoice->id = $randchoice->instance;
    $randchoice->timemodified = time();


    if (empty($randchoice->timerestrict)) {
        $randchoice->timeopen = 0;
        $randchoice->timeclose = 0;
    }

    //update, delete or insert answers
    foreach ($randchoice->option as $key => $value) {
        $value = trim($value);
        $option = new stdClass();
        $option->text = $value;
        $option->randchoiceid = $randchoice->id;
        if (isset($randchoice->limit[$key])) {
            $option->maxanswers = $randchoice->limit[$key];
        }
        $option->timemodified = time();
        if (isset($randchoice->optionid[$key]) && !empty($randchoice->optionid[$key])){//existing randchoice record
            $option->id=$randchoice->optionid[$key];
            if (isset($value) && $value <> '') {
                $DB->update_record("randchoice_options", $option);
            } else { //empty old option - needs to be deleted.
                $DB->delete_records("randchoice_options", array("id"=>$option->id));
            }
        } else {
            if (isset($value) && $value <> '') {
                $DB->insert_record("randchoice_options", $option);
            }
        }
    }

    return $DB->update_record('randchoice', $randchoice);

}

/**
 * @global object
 * @param object $randchoice
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function randchoice_prepare_options($randchoice, $user, $coursemodule, $allresponses) {
    global $DB;

    $cdisplay = array('options'=>array());

    $cdisplay['limitanswers'] = true;
    $context = context_module::instance($coursemodule->id);

    foreach ($randchoice->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->attributes = new stdClass;
            $option->attributes->value = $optionid;
            $option->text = format_string($text);
            $option->maxanswers = $randchoice->maxanswers[$optionid];
            $option->displaylayout = $randchoice->display;

            if (isset($allresponses[$optionid])) {
                $option->countanswers = count($allresponses[$optionid]);
            } else {
                $option->countanswers = 0;
            }
            if ($DB->record_exists('randchoice_answers', array('randchoiceid' => $randchoice->id, 'userid' => $user->id, 'optionid' => $optionid))) {
                $option->attributes->checked = true;
            }
            if ( $randchoice->limitanswers && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $cdisplay['options'][] = $option;
        }
    }

    $cdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/randchoice:choose'); //only enrolled users are allowed to make a randchoice

    if ($randchoice->allowupdate && $DB->record_exists('randchoice_answers', array('randchoiceid'=> $randchoice->id, 'userid'=> $user->id))) {
        $cdisplay['allowupdate'] = true;
    }

    if ($randchoice->showpreview && $randchoice->timeopen > time()) {
        $cdisplay['previewonly'] = true;
    }

    return $cdisplay;
}

/**
 * Process user submitted answers for a randchoice,
 * and either updating them or saving new answers.
 *
 * @param int $formanswer users submitted answers.
 * @param object $randchoice the selected randchoice.
 * @param int $userid user identifier.
 * @param object $course current course.
 * @param object $cm course context.
 * @return void
 */
function randchoice_user_submit_response($formanswer, $randchoice, $userid, $course, $cm) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $continueurl = new moodle_url('/mod/randchoice/view.php', array('id' => $cm->id));

    if (empty($formanswer)) {
        print_error('atleastoneoption', 'randchoice', $continueurl);
    }

    if (is_array($formanswer)) {
        if (!$randchoice->allowmultiple) {
            print_error('multiplenotallowederror', 'randchoice', $continueurl);
        }
        $formanswers = $formanswer;
    } else {
        $formanswers = array($formanswer);
    }

    // Start lock to prevent synchronous access to the same data
    // before it's updated, if using limits.
    if ($randchoice->limitanswers) {
        $timeout = 10;
        $locktype = 'mod_randchoice_randchoice_user_submit_response';
        // Limiting access to this randchoice.
        $resouce = 'randchoiceid:' . $randchoice->id;
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

        // Opening the lock.
        $randchoicelock = $lockfactory->get_lock($resouce, $timeout);
        if (!$randchoicelock) {
            print_error('cannotsubmit', 'randchoice', $continueurl);
        }
    }

    $current = $DB->get_records('randchoice_answers', array('randchoiceid' => $randchoice->id, 'userid' => $userid));
    $context = context_module::instance($cm->id);

    $randchoicesexceeded = false;
    $countanswers = array();
    foreach ($formanswers as $val) {
        $countanswers[$val] = 0;
    }
    if($randchoice->limitanswers) {
        // Find out whether groups are being used and enabled
        if (groups_get_activity_groupmode($cm) > 0) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            $currentgroup = 0;
        }

        list ($insql, $params) = $DB->get_in_or_equal($formanswers, SQL_PARAMS_NAMED);

        if($currentgroup) {
            // If groups are being used, retrieve responses only for users in
            // current group
            global $CFG;

            $params['groupid'] = $currentgroup;
            $sql = "SELECT ca.*
                      FROM {randchoice_answers} ca
                INNER JOIN {groups_members} gm ON ca.userid=gm.userid
                     WHERE optionid $insql
                       AND gm.groupid= :groupid";
        } else {
            // Groups are not used, retrieve all answers for this option ID
            $sql = "SELECT ca.*
                      FROM {randchoice_answers} ca
                     WHERE optionid $insql";
        }

        $answers = $DB->get_records_sql($sql, $params);
        if ($answers) {
            foreach ($answers as $a) { //only return enrolled users.
                if (is_enrolled($context, $a->userid, 'mod/randchoice:choose')) {
                    $countanswers[$a->optionid]++;
                }
            }
        }
        foreach ($countanswers as $opt => $count) {
            if ($count >= $randchoice->maxanswers[$opt]) {
                $randchoicesexceeded = true;
                break;
            }
        }
    }

    // Check the user hasn't exceeded the maximum selections for the randchoice(s) they have selected.
    if (!($randchoice->limitanswers && $randchoicesexceeded)) {
        $answersnapshots = array();
        if ($current) {
            // Update an existing answer.
            $existingrandchoices = array();
            foreach ($current as $c) {
                if (in_array($c->optionid, $formanswers)) {
                    $existingrandchoices[] = $c->optionid;
                    $DB->set_field('randchoice_answers', 'timemodified', time(), array('id' => $c->id));
                    $answersnapshots[] = $c;
                } else {
                    $DB->delete_records('randchoice_answers', array('id' => $c->id));
                }
            }

            // Add new ones.
            foreach ($formanswers as $f) {
                if (!in_array($f, $existingrandchoices)) {
                    $newanswer = new stdClass();
                    $newanswer->optionid = $f;
                    $newanswer->randchoiceid = $randchoice->id;
                    $newanswer->userid = $userid;
                    $newanswer->timemodified = time();
                    $newanswer->id = $DB->insert_record("randchoice_answers", $newanswer);
                    $answersnapshots[] = $newanswer;
                }
            }

            // Initialised as true, meaning we updated the answer.
            $answerupdated = true;
        } else {
            // Add new answer.
            foreach ($formanswers as $answer) {
                $newanswer = new stdClass();
                $newanswer->randchoiceid = $randchoice->id;
                $newanswer->userid = $userid;
                $newanswer->optionid = $answer;
                $newanswer->timemodified = time();
                $newanswer->id = $DB->insert_record("randchoice_answers", $newanswer);
                $answersnapshots[] = $newanswer;
            }

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $randchoice->completionsubmit) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            // Initalised as false, meaning we submitted a new answer.
            $answerupdated = false;
        }
    } else {
        // Check to see if current randchoice already selected - if not display error.
        $currentids = array_keys($current);

        if (array_diff($currentids, $formanswers) || array_diff($formanswers, $currentids) ) {
            // Release lock before error.
            $randchoicelock->release();
            print_error('randchoicefull', 'randchoice', $continueurl);
        }
    }

    // Release lock.
    if (isset($randchoicelock)) {
        $randchoicelock->release();
    }

    // Now record completed event.
    if (isset($answerupdated)) {
        $eventdata = array();
        $eventdata['context'] = $context;
        $eventdata['objectid'] = $randchoice->id;
        $eventdata['userid'] = $userid;
        $eventdata['courseid'] = $course->id;
        $eventdata['other'] = array();
        $eventdata['other']['randchoiceid'] = $randchoice->id;

        if ($answerupdated) {
            $eventdata['other']['optionid'] = $formanswer;
            $event = \mod_randchoice\event\answer_updated::create($eventdata);
        } else {
            $eventdata['other']['optionid'] = $formanswers;
            $event = \mod_randchoice\event\answer_submitted::create($eventdata);
        }
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('randchoice', $randchoice);
        foreach ($answersnapshots as $record) {
            $event->add_record_snapshot('randchoice_answers', $record);
        }
        $event->trigger();
    }
}

/**
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function randchoice_show_reportlink($user, $cm) {
    $userschosen = array();
    foreach($user as $optionid => $userlist) {
        if ($optionid) {
            $userschosen = array_merge($userschosen, array_keys($userlist));
        }
    }
    $responsecount = count(array_unique($userschosen));

    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">".get_string("viewallresponses", "randchoice", $responsecount)."</a>";
    echo '</div>';
}

/**
 * @global object
 * @param object $randchoice
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses
 
 *  * @param bool $allresponses
 * @return object
 */
function prepare_randchoice_show_results($randchoice, $course, $cm, $allresponses) {
    global $OUTPUT;

    $display = clone($randchoice);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;

    //overwrite options value;
    $display->options = array();
    $totaluser = 0;
    foreach ($randchoice->option as $optionid => $optiontext) {
        $display->options[$optionid] = new stdClass;
        $display->options[$optionid]->text = $optiontext;
        $display->options[$optionid]->maxanswer = $randchoice->maxanswers[$optionid];

        if (array_key_exists($optionid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$optionid];
            $totaluser += count($allresponses[$optionid]);
        }
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = $totaluser;
    $context = context_module::instance($cm->id);
    $display->viewresponsecapability = has_capability('mod/randchoice:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/randchoice:deleteresponses',$context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"), 3, null);
        return false;
    }

    return $display;
}

/**
 * @global object
 * @param array $attemptids
 * @param object $randchoice Choice main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function randchoice_delete_responses($attemptids, $randchoice, $cm, $course) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if(!is_array($attemptids) || empty($attemptids)) {
        return false;
    }

    foreach($attemptids as $num => $attemptid) {
        if(empty($attemptid)) {
            unset($attemptids[$num]);
        }
    }

    $completion = new completion_info($course);
    foreach($attemptids as $attemptid) {
        if ($todelete = $DB->get_record('randchoice_answers', array('randchoiceid' => $randchoice->id, 'id' => $attemptid))) {
            $DB->delete_records('randchoice_answers', array('randchoiceid' => $randchoice->id, 'id' => $attemptid));
            // Update completion state
            if ($completion->is_enabled($cm) && $randchoice->completionsubmit) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $attemptid);
            }
        }
    }
    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function randchoice_delete_instance($id) {
    global $DB;

    if (! $randchoice = $DB->get_record("randchoice", array("id"=>"$id"))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("randchoice_answers", array("randchoiceid"=>"$randchoice->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("randchoice_options", array("randchoiceid"=>"$randchoice->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("randchoice", array("id"=>"$randchoice->id"))) {
        $result = false;
    }

    return $result;
}

/**
 * Returns text string which is the answer that matches the id
 *
 * @global object
 * @param object $randchoice
 * @param int $id
 * @return string
 */
function randchoice_get_option_text($randchoice, $id) {
    global $DB;

    if ($result = $DB->get_record("randchoice_options", array("id" => $id))) {
        return $result->text;
    } else {
        return get_string("notanswered", "randchoice");
    }
}

/**
 * Gets a full randchoice record
 *
 * @global object
 * @param int $randchoiceid
 * @return object|bool The randchoice or false
 */
function randchoice_get_randchoice($randchoiceid) {
    global $DB;

    if ($randchoice = $DB->get_record("randchoice", array("id" => $randchoiceid))) {
        if ($options = $DB->get_records("randchoice_options", array("randchoiceid" => $randchoiceid), "id")) {
            foreach ($options as $option) {
                $randchoice->option[$option->id] = $option->text;
                $randchoice->maxanswers[$option->id] = $option->maxanswers;
            }
            return $randchoice;
        }
    }
    return false;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function randchoice_get_view_actions() {
    return array('view','view all','report');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function randchoice_get_post_actions() {
    return array('choose','choose again');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the randchoice.
 *
 * @param object $mform form passed by reference
 */
function randchoice_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'randchoiceheader', get_string('modulenameplural', 'randchoice'));
    $mform->addElement('advcheckbox', 'reset_randchoice', get_string('removeresponses','randchoice'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function randchoice_reset_course_form_defaults($course) {
    return array('reset_randchoice'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * randchoice responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function randchoice_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'randchoice');
    $status = array();

    if (!empty($data->reset_randchoice)) {
        $randchoicessql = "SELECT ch.id
                       FROM {randchoice} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('randchoice_answers', "randchoiceid IN ($randchoicessql)", array($data->courseid));
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removeresponses', 'randchoice'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('randchoice', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $randchoice
 * @param object $cm
 * @param int $groupmode
 * @param bool $onlyactive Whether to get response data for active users only.
 * @return array
 */
function randchoice_get_response_data($randchoice, $cm, $groupmode, $onlyactive) {
    global $CFG, $USER, $DB;

    $context = context_module::instance($cm->id);

/// Get the current group
    if ($groupmode > 0) {
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }

/// Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject
    $allresponses = array();

/// First get all the users who have access here
/// To start with we assume they are all "unanswered" then move them later
    $allresponses[0] = get_enrolled_users($context, 'mod/randchoice:choose', $currentgroup,
            user_picture::fields('u', array('idnumber')), null, 0, 0, $onlyactive);

/// Get all the recorded responses for this randchoice
    $rawresponses = $DB->get_records('randchoice_answers', array('randchoiceid' => $randchoice->id));

/// Use the responses to move users into the correct column

    if ($rawresponses) {
        $answeredusers = array();
        foreach ($rawresponses as $response) {
            if (isset($allresponses[0][$response->userid])) {   // This person is enrolled and in correct group
                $allresponses[0][$response->userid]->timemodified = $response->timemodified;
                $allresponses[$response->optionid][$response->userid] = clone($allresponses[0][$response->userid]);
                $allresponses[$response->optionid][$response->userid]->answerid = $response->id;
                $answeredusers[] = $response->userid;
            }
        }
        foreach ($answeredusers as $answereduser) {
            unset($allresponses[0][$answereduser]);
        }
    }
    return $allresponses;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function randchoice_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function randchoice_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $randchoicenode The node to add module settings to
 */
function randchoice_extend_settings_navigation(settings_navigation $settings, navigation_node $randchoicenode) {
    global $PAGE;

    if (has_capability('mod/randchoice:readresponses', $PAGE->cm->context)) {

        $groupmode = groups_get_activity_groupmode($PAGE->cm);
        if ($groupmode) {
            groups_get_activity_group($PAGE->cm, true);
        }

        $randchoice = randchoice_get_randchoice($PAGE->cm->instance);

        // Check if we want to include responses from inactive users.
        $onlyactive = $randchoice->includeinactive ? false : true;

        // Big function, approx 6 SQL calls per user.
        $allresponses = randchoice_get_response_data($randchoice, $PAGE->cm, $groupmode, $onlyactive);

        $responsecount =0;
        foreach($allresponses as $optionid => $userlist) {
            if ($optionid) {
                $responsecount += count($userlist);
            }
        }
        $randchoicenode->add(get_string("viewallresponses", "randchoice", $responsecount), new moodle_url('/mod/randchoice/report.php', array('id'=>$PAGE->cm->id)));
    }
}

/**
 * Obtains the automatic completion state for this randchoice based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function randchoice_get_completion_state($course, $cm, $userid, $type) {
    global $CFG,$DB;

    // Get randchoice details
    $randchoice = $DB->get_record('randchoice', array('id'=>$cm->instance), '*',
            MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if($randchoice->completionsubmit) {
        return $DB->record_exists('randchoice_answers', array(
                'randchoiceid'=>$randchoice->id, 'userid'=>$userid));
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function randchoice_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-randchoice-*'=>get_string('page-mod-randchoice-x', 'randchoice'));
    return $module_pagetype;
}

/**
 * Prints randchoice summaries on MyMoodle Page
 *
 * Prints randchoice name, due date and attempt information on
 * randchoice activities that have a deadline that has not already passed
 * and it is available for completing.
 * @uses CONTEXT_MODULE
 * @param array $courses An array of course objects to get randchoice instances from.
 * @param array $htmlarray Store overview output array( course ID => 'randchoice' => HTML output )
 */
function randchoice_print_overview($courses, &$htmlarray) {
    global $USER, $DB, $OUTPUT;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return;
    }
    if (!$randchoices = get_all_instances_in_courses('randchoice', $courses)) {
        return;
    }

    $now = time();
    foreach ($randchoices as $randchoice) {
        if ($randchoice->timeclose != 0                                      // If this randchoice is scheduled.
            and $randchoice->timeclose >= $now                               // And the deadline has not passed.
            and ($randchoice->timeopen == 0 or $randchoice->timeopen <= $now)) { // And the randchoice is available.

            // Visibility.
            $class = (!$randchoice->visible) ? 'dimmed' : '';

            // Link to activity.
            $url = new moodle_url('/mod/randchoice/view.php', array('id' => $randchoice->coursemodule));
            $url = html_writer::link($url, format_string($randchoice->name), array('class' => $class));
            $str = $OUTPUT->box(get_string('randchoiceactivityname', 'randchoice', $url), 'name');

             // Deadline.
            $str .= $OUTPUT->box(get_string('randchoicecloseson', 'randchoice', userdate($randchoice->timeclose)), 'info');

            // Display relevant info based on permissions.
            if (has_capability('mod/randchoice:readresponses', context_module::instance($randchoice->coursemodule))) {
                $attempts = $DB->count_records('randchoice_answers', array('randchoiceid' => $randchoice->id));
                $str .= $OUTPUT->box(get_string('viewallresponses', 'randchoice', $attempts), 'info');

            } else if (has_capability('mod/randchoice:choose', context_module::instance($randchoice->coursemodule))) {
                // See if the user has submitted anything.
                $answers = $DB->count_records('randchoice_answers', array('randchoiceid' => $randchoice->id, 'userid' => $USER->id));
                if ($answers > 0) {
                    // User has already selected an answer, nothing to show.
                    $str = '';
                } else {
                    // User has not made a selection yet.
                    $str .= $OUTPUT->box(get_string('notanswered', 'randchoice'), 'info');
                }
            } else {
                // Does not have permission to do anything on this randchoice activity.
                $str = '';
            }

            // Make sure we have something to display.
            if (!empty($str)) {
                // Generate the containing div.
                $str = $OUTPUT->box($str, 'randchoice overview');

                if (empty($htmlarray[$randchoice->course]['randchoice'])) {
                    $htmlarray[$randchoice->course]['randchoice'] = $str;
                } else {
                    $htmlarray[$randchoice->course]['randchoice'] .= $str;
                }
            }
        }
    }
    return;
}

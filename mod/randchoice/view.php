<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');

$id         = required_param('id', PARAM_INT);                 // Course Module ID
$action     = optional_param('action', '', PARAM_ALPHA);
$attemptids = optional_param_array('attemptid', array(), PARAM_INT); // array of attempt ids for delete action
$notify     = optional_param('notify', '', PARAM_ALPHA);

$url = new moodle_url('/mod/randchoice/view.php', array('id'=>$id));
if ($action !== '') {
    $url->param('action', $action);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('randchoice', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

require_course_login($course, false, $cm);

if (!$randchoice = randchoice_get_randchoice($cm->instance)) {
    print_error('invalidcoursemodule');
}

$strrandchoice = get_string('modulename', 'randchoice');
$strrandchoices = get_string('modulenameplural', 'randchoice');

$context = context_module::instance($cm->id);

if ($action == 'delrandchoice' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/randchoice:choose') and $randchoice->allowupdate) {
    $answercount = $DB->count_records('randchoice_answers', array('randchoiceid' => $randchoice->id, 'userid' => $USER->id));
    if ($answercount > 0) {
        $DB->delete_records('randchoice_answers', array('randchoiceid' => $randchoice->id, 'userid' => $USER->id));

        // Update completion state
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) && $randchoice->completionsubmit) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE);
        }
        redirect("view.php?id=$cm->id");
    }
}

$PAGE->set_title($randchoice->name);
$PAGE->set_heading($course->fullname);

// Mark viewed by user (if required)
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

/// Submit any new data if there is any
if (data_submitted() && is_enrolled($context, NULL, 'mod/randchoice:choose') && confirm_sesskey()) {
    $timenow = time();
    if (has_capability('mod/randchoice:deleteresponses', $context) && $action == 'delete') {
        //some responses need to be deleted
        randchoice_delete_responses($attemptids, $randchoice, $cm, $course); //delete responses.
        redirect("view.php?id=$cm->id");
    }

    // Redirection after all POSTs breaks block editing, we need to be more specific!
    if ($randchoice->allowmultiple) {
        $answer = optional_param_array('answer', array(), PARAM_INT);
    } else {
        $answer = optional_param('answer', '', PARAM_INT);
    }

    if ($answer) {
        $answer = rand(1, $answer);
        randchoice_user_submit_response($answer, $randchoice, $USER->id, $course, $cm);
        redirect(new moodle_url('/mod/randchoice/view.php',
            array('id' => $cm->id, 'notify' => 'randchoicesaved', 'sesskey' => sesskey())));
    } else if (empty($answer) and $action === 'makerandchoice') {
        // We cannot use the 'makerandchoice' alone because there might be some legacy renderers without it,
        // outdated renderers will not get the 'mustchoose' message - bad luck.
        redirect(new moodle_url('/mod/randchoice/view.php',
            array('id' => $cm->id, 'notify' => 'mustchooseone', 'sesskey' => sesskey())));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($randchoice->name), 2, null);

if ($notify and confirm_sesskey()) {
    if ($notify === 'randchoicesaved') {
        echo $OUTPUT->notification(get_string('randchoicesaved', 'randchoice'), 'notifysuccess');
    } else if ($notify === 'mustchooseone') {
        echo $OUTPUT->notification(get_string('mustchooseone', 'randchoice'), 'notifyproblem');
    }
}

/// Display the randchoice and possibly results
$eventdata = array();
$eventdata['objectid'] = $randchoice->id;
$eventdata['context'] = $context;

$event = \mod_randchoice\event\course_module_viewed::create($eventdata);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->trigger();

/// Check to see if groups are being used in this randchoice
$groupmode = groups_get_activity_groupmode($cm);

if ($groupmode) {
    groups_get_activity_group($cm, true);
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/randchoice/view.php?id='.$id);
}

// Check if we want to include responses from inactive users.
$onlyactive = $randchoice->includeinactive ? false : true;

$allresponses = randchoice_get_response_data($randchoice, $cm, $groupmode, $onlyactive);   // Big function, approx 6 SQL calls per user.


if (has_capability('mod/randchoice:readresponses', $context)) {
    randchoice_show_reportlink($allresponses, $cm);
}

echo '<div class="clearer"></div>';

if ($randchoice->intro) {
    echo $OUTPUT->box(format_module_intro('randchoice', $randchoice, $cm->id), 'generalbox', 'intro');
}

$timenow = time();
//var_dump($timenow); die;
$current = $DB->get_records('randchoice_answers', array('randchoiceid' => $randchoice->id, 'userid' => $USER->id));
//if user has already made a selection, and they are not allowed to update it or if randchoice is not open, show their selected answer.
if (isloggedin() && (!empty($current)) &&
    (empty($randchoice->allowupdate) || ($timenow > $randchoice->timeclose)) ) {
    $randchoicetexts = array();
    foreach ($current as $c) {
        $randchoicetexts[] = format_string(randchoice_get_option_text($randchoice, $c->optionid));
    }
    echo $OUTPUT->box(get_string("yourselection", "randchoice", userdate($randchoice->timeopen)).": ".implode('; ', $randchoicetexts), 'generalbox', 'yourselection');
}

/// Print the form
$randchoiceopen = true;
if ($randchoice->timeclose !=0) {
    if ($randchoice->timeopen > $timenow ) {
        if ($randchoice->showpreview) {
            echo $OUTPUT->box(get_string('previewonly', 'randchoice', userdate($randchoice->timeopen)), 'generalbox alert');
        } else {
            echo $OUTPUT->box(get_string("notopenyet", "randchoice", userdate($randchoice->timeopen)), "generalbox notopenyet");
            echo $OUTPUT->footer();
            exit;
        }
    } else if ($timenow > $randchoice->timeclose) {
        echo $OUTPUT->box(get_string("expired", "randchoice", userdate($randchoice->timeclose)), "generalbox expired");
        $randchoiceopen = false;
    }
}

if ( (!$current or $randchoice->allowupdate) and $randchoiceopen and is_enrolled($context, NULL, 'mod/randchoice:choose')) {
// They haven't made their randchoice yet or updates allowed and randchoice is open

    $options = randchoice_prepare_options($randchoice, $USER, $cm, $allresponses);
    $renderer = $PAGE->get_renderer('mod_randchoice');
    echo $renderer->display_options($options, $cm->id, $randchoice->display, $randchoice->allowmultiple);
    $randchoiceformshown = true;
} else {
    $randchoiceformshown = false;
}

if (!$randchoiceformshown) {
    $sitecontext = context_system::instance();

    if (isguestuser()) {
        // Guest account
        echo $OUTPUT->confirm(get_string('noguestchoose', 'randchoice').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
    } else if (!is_enrolled($context)) {
        // Only people enrolled can make a randchoice
        $SESSION->wantsurl = qualified_me();
        $SESSION->enrolcancel = get_local_referer(false);

        $coursecontext = context_course::instance($course->id);
        $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

        echo $OUTPUT->box_start('generalbox', 'notice');
        echo '<p align="center">'. get_string('notenrolledchoose', 'randchoice') .'</p>';
        echo $OUTPUT->container_start('continuebutton');
        echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', $courseshortname));
        echo $OUTPUT->container_end();
        echo $OUTPUT->box_end();

    }
}

// print the results at the bottom of the screen
if ( $randchoice->showresults == CHOICE_SHOWRESULTS_ALWAYS or
    ($randchoice->showresults == CHOICE_SHOWRESULTS_AFTER_ANSWER and $current) or
    ($randchoice->showresults == CHOICE_SHOWRESULTS_AFTER_CLOSE and !$randchoiceopen)) {

    if (!empty($randchoice->showunanswered)) {
        $randchoice->option[0] = get_string('notanswered', 'randchoice');
        $randchoice->maxanswers[0] = 0;
    }
    $results = prepare_randchoice_show_results($randchoice, $course, $cm, $allresponses);
    $renderer = $PAGE->get_renderer('mod_randchoice');
    echo $renderer->display_result($results);

} else if (!$randchoiceformshown) {
    echo $OUTPUT->box(get_string('noresultsviewable', 'randchoice'));
}

echo $OUTPUT->footer();

<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id',PARAM_INT);   // course

    $PAGE->set_url('/mod/randchoice/index.php', array('id'=>$id));

    if (!$course = $DB->get_record('course', array('id'=>$id))) {
        print_error('invalidcourseid');
    }

    require_course_login($course);
    $PAGE->set_pagelayout('incourse');

    $eventdata = array('context' => context_course::instance($id));
    $event = \mod_randchoice\event\course_module_instance_list_viewed::create($eventdata);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    $strrandchoice = get_string("modulename", "randchoice");
    $strrandchoices = get_string("modulenameplural", "randchoice");
    $PAGE->set_title($strrandchoices);
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strrandchoices);
    echo $OUTPUT->header();

    if (! $randchoices = get_all_instances_in_course("randchoice", $course)) {
        notice(get_string('thereareno', 'moodle', $strrandchoices), "../../course/view.php?id=$course->id");
    }

    $usesections = course_format_uses_sections($course->format);

    $sql = "SELECT cha.*
              FROM {randchoice} ch, {randchoice_answers} cha
             WHERE cha.randchoiceid = ch.id AND
                   ch.course = ? AND cha.userid = ?";

    $answers = array () ;
    if (isloggedin() and !isguestuser() and $allanswers = $DB->get_records_sql($sql, array($course->id, $USER->id))) {
        foreach ($allanswers as $aa) {
            $answers[$aa->randchoiceid] = $aa;
        }
        unset($allanswers);
    }


    $timenow = time();

    $table = new html_table();

    if ($usesections) {
        $strsectionname = get_string('sectionname', 'format_'.$course->format);
        $table->head  = array ($strsectionname, get_string("question"), get_string("answer"));
        $table->align = array ("center", "left", "left");
    } else {
        $table->head  = array (get_string("question"), get_string("answer"));
        $table->align = array ("left", "left");
    }

    $currentsection = "";

    foreach ($randchoices as $randchoice) {
        if (!empty($answers[$randchoice->id])) {
            $answer = $answers[$randchoice->id];
        } else {
            $answer = "";
        }
        if (!empty($answer->optionid)) {
            $aa = format_string(randchoice_get_option_text($randchoice, $answer->optionid));
        } else {
            $aa = "";
        }
        if ($usesections) {
            $printsection = "";
            if ($randchoice->section !== $currentsection) {
                if ($randchoice->section) {
                    $printsection = get_section_name($course, $randchoice->section);
                }
                if ($currentsection !== "") {
                    $table->data[] = 'hr';
                }
                $currentsection = $randchoice->section;
            }
        }

        //Calculate the href
        if (!$randchoice->visible) {
            //Show dimmed if the mod is hidden
            $tt_href = "<a class=\"dimmed\" href=\"view.php?id=$randchoice->coursemodule\">".format_string($randchoice->name,true)."</a>";
        } else {
            //Show normal if the mod is visible
            $tt_href = "<a href=\"view.php?id=$randchoice->coursemodule\">".format_string($randchoice->name,true)."</a>";
        }
        if ($usesections) {
            $table->data[] = array ($printsection, $tt_href, $aa);
        } else {
            $table->data[] = array ($tt_href, $aa);
        }
    }
    echo "<br />";
    echo html_writer::table($table);

    echo $OUTPUT->footer();



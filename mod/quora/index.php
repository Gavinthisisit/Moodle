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
 * @package   mod_quora
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quora/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all quoras

$url = new moodle_url('/mod/quora/index.php', array('id'=>$id));
if ($subscribe !== null) {
    require_sesskey();
    $url->param('subscribe', $subscribe);
}
$PAGE->set_url($url);

if ($id) {
    if (! $course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);


unset($SESSION->fromdiscussion);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_quora\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strquoras       = get_string('quoras', 'quora');
$strquora        = get_string('quora', 'quora');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'quora');
$strsubscribed   = get_string('subscribed', 'quora');
$strunreadposts  = get_string('unreadposts', 'quora');
$strtracking     = get_string('tracking', 'quora');
$strmarkallread  = get_string('markallread', 'quora');
$strtrackquora   = get_string('trackquora', 'quora');
$strnotrackquora = get_string('notrackquora', 'quora');
$strsubscribe    = get_string('subscribe', 'quora');
$strunsubscribe  = get_string('unsubscribe', 'quora');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = quora_search_form($course);

// Retrieve the list of quora digest options for later.
$digestoptions = quora_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/quora/maildigest.php',
    array(
        'backtoindex' => 1,
    )),
    'maildigest',
    $digestoptions,
    null,
    '');
$digestoptions_selector->method = 'post';

// Start of the table for General Forums

$generaltable = new html_table();
$generaltable->head  = array ($strquora, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = quora_tp_can_track_quoras()) {
    $untracked = quora_tp_get_untracked_quoras($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_quora\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_quora');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->quora_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->quora_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the quoras.  Most quoras are course modules but
// some special ones are not.  These get placed in the general quoras
// category with the quoras in section 0.

$quoras = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {quora} f
 LEFT JOIN {quora_digests} d ON d.quora = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalquoras  = array();
$learningquoras = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('quora') as $quoraid=>$cm) {
    if (!$cm->uservisible or !isset($quoras[$quoraid])) {
        continue;
    }

    $quora = $quoras[$quoraid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/quora:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($quora->type == 'news' or $quora->type == 'social') {
        $generalquoras[$quora->id] = $quora;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalquoras[$quora->id] = $quora;

    } else {
        $learningquoras[$quora->id] = $quora;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // there should not be any links leading to this place, just redirect
        redirect(new moodle_url('/mod/quora/index.php', array('id' => $id)), get_string('subscribeenrolledonly', 'quora'));
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('quora') as $quoraid=>$cm) {
        $quora = $quoras[$quoraid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/quora:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/quora:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_quora\subscriptions::is_forcesubscribed($quora)) {
            $subscribed = \mod_quora\subscriptions::is_subscribed($USER->id, $quora, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_quora\subscriptions::is_subscribable($quora)) && $subscribe && !$subscribed && $cansub) {
                \mod_quora\subscriptions::subscribe_user($USER->id, $quora, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_quora\subscriptions::unsubscribe_user($USER->id, $quora, $modcontext, true);
            }
        }
    }
    $returnto = quora_go_back_to("index.php?id=$course->id");
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect($returnto, get_string('nowallsubscribed', 'quora', $shortname), 1);
    } else {
        redirect($returnto, get_string('nowallunsubscribed', 'quora', $shortname), 1);
    }
}

/// First, let's process the general quoras and build up a display

if ($generalquoras) {
    foreach ($generalquoras as $quora) {
        $cm      = $modinfo->instances['quora'][$quora->id];
        $context = context_module::instance($cm->id);

        $count = quora_count_discussions($quora, $cm, $course);

        if ($usetracking) {
            if ($quora->trackingtype == FORUM_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$quora->id])) {
                        $unreadlink  = '-';
                } else if ($unread = quora_tp_count_quora_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$quora->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $quora->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($quora->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->quora_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($quora->trackingtype === FORUM_TRACKING_OFF || ($USER->trackquoras == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/quora/settracking.php', array(
                            'id' => $quora->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$quora->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackquora));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackquora));
                    }
                }
            }
        }

        $quora->intro = shorten_text(format_module_intro('quora', $quora, $cm->id), $CFG->quora_shortpost);
        $quoraname = format_string($quora->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $quoralink = "<a href=\"view.php?f=$quora->id\" $style>".format_string($quora->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$quora->id\" $style>".$count."</a>";

        $row = array ($quoralink, $quora->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = quora_get_subscribe_link($quora, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $quora->id);
            if ($quora->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $quora->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this quora has RSS activated, calculate it
        if ($show_rss) {
            if ($quora->rsstype and $quora->rssarticles) {
                //Calculate the tooltip text
                if ($quora->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'quora');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'quora');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_quora', $quora->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forums
$learningtable = new html_table();
$learningtable->head  = array ($strquora, $strdescription, $strdiscussions);
$learningtable->align = array ('left', 'left', 'center');

if ($usetracking) {
    $learningtable->head[] = $strunreadposts;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $strtracking;
    $learningtable->align[] = 'center';
}

if ($can_subscribe) {
    $learningtable->head[] = $strsubscribed;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_quora');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->quora_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->quora_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning quoras

if ($course->id != SITEID) {    // Only real courses have learning quoras
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningquoras) {
        $currentsection = '';
            foreach ($learningquoras as $quora) {
            $cm      = $modinfo->instances['quora'][$quora->id];
            $context = context_module::instance($cm->id);

            $count = quora_count_discussions($quora, $cm, $course);

            if ($usetracking) {
                if ($quora->trackingtype == FORUM_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$quora->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = quora_tp_count_quora_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$quora->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $quora->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($quora->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->quora_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($quora->trackingtype === FORUM_TRACKING_OFF || ($USER->trackquoras == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/quora/settracking.php', array('id'=>$quora->id));
                        if (!isset($untracked[$quora->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackquora));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackquora));
                        }
                    }
                }
            }

            $quora->intro = shorten_text(format_module_intro('quora', $quora, $cm->id), $CFG->quora_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $quoraname = format_string($quora->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $quoralink = "<a href=\"view.php?f=$quora->id\" $style>".format_string($quora->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$quora->id\" $style>".$count."</a>";

            $row = array ($printsection, $quoralink, $quora->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = quora_get_subscribe_link($quora, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $quora->id);
                if ($quora->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $quora->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this quora has RSS activated, calculate it
            if ($show_rss) {
                if ($quora->rsstype and $quora->rssarticles) {
                    //Calculate the tolltip text
                    if ($quora->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'quora');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'quora');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_quora', $quora->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strquoras);
$PAGE->set_title("$course->shortname: $strquoras");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/quora/index.php', array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'quora')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/quora/index.php', array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'quora')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalquoras) {
    echo $OUTPUT->heading(get_string('generalquoras', 'quora'), 2);
    echo html_writer::table($generaltable);
}

if ($learningquoras) {
    echo $OUTPUT->heading(get_string('learningquoras', 'quora'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();


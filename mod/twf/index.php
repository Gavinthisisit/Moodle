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
 * @package   mod_twf
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/twf/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all twfs

$url = new moodle_url('/mod/twf/index.php', array('id'=>$id));
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
$event = \mod_twf\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strtwfs       = get_string('twfs', 'twf');
$strtwf        = get_string('twf', 'twf');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'twf');
$strsubscribed   = get_string('subscribed', 'twf');
$strunreadposts  = get_string('unreadposts', 'twf');
$strtracking     = get_string('tracking', 'twf');
$strmarkallread  = get_string('markallread', 'twf');
$strtracktwf   = get_string('tracktwf', 'twf');
$strnotracktwf = get_string('notracktwf', 'twf');
$strsubscribe    = get_string('subscribe', 'twf');
$strunsubscribe  = get_string('unsubscribe', 'twf');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = twf_search_form($course);

// Retrieve the list of twf digest options for later.
$digestoptions = twf_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/twf/maildigest.php',
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
$generaltable->head  = array ($strtwf, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = twf_tp_can_track_twfs()) {
    $untracked = twf_tp_get_untracked_twfs($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_twf\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_twf');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->twf_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->twf_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the twfs.  Most twfs are course modules but
// some special ones are not.  These get placed in the general twfs
// category with the twfs in section 0.

$twfs = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {twf} f
 LEFT JOIN {twf_digests} d ON d.twf = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generaltwfs  = array();
$learningtwfs = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('twf') as $twfid=>$cm) {
    if (!$cm->uservisible or !isset($twfs[$twfid])) {
        continue;
    }

    $twf = $twfs[$twfid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/twf:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($twf->type == 'news' or $twf->type == 'social') {
        $generaltwfs[$twf->id] = $twf;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generaltwfs[$twf->id] = $twf;

    } else {
        $learningtwfs[$twf->id] = $twf;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // there should not be any links leading to this place, just redirect
        redirect(new moodle_url('/mod/twf/index.php', array('id' => $id)), get_string('subscribeenrolledonly', 'twf'));
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('twf') as $twfid=>$cm) {
        $twf = $twfs[$twfid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/twf:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/twf:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_twf\subscriptions::is_forcesubscribed($twf)) {
            $subscribed = \mod_twf\subscriptions::is_subscribed($USER->id, $twf, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_twf\subscriptions::is_subscribable($twf)) && $subscribe && !$subscribed && $cansub) {
                \mod_twf\subscriptions::subscribe_user($USER->id, $twf, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_twf\subscriptions::unsubscribe_user($USER->id, $twf, $modcontext, true);
            }
        }
    }
    $returnto = twf_go_back_to("index.php?id=$course->id");
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect($returnto, get_string('nowallsubscribed', 'twf', $shortname), 1);
    } else {
        redirect($returnto, get_string('nowallunsubscribed', 'twf', $shortname), 1);
    }
}

/// First, let's process the general twfs and build up a display

if ($generaltwfs) {
    foreach ($generaltwfs as $twf) {
        $cm      = $modinfo->instances['twf'][$twf->id];
        $context = context_module::instance($cm->id);

        $count = twf_count_discussions($twf, $cm, $course);

        if ($usetracking) {
            if ($twf->trackingtype == FORUM_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$twf->id])) {
                        $unreadlink  = '-';
                } else if ($unread = twf_tp_count_twf_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$twf->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $twf->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($twf->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->twf_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($twf->trackingtype === FORUM_TRACKING_OFF || ($USER->tracktwfs == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/twf/settracking.php', array(
                            'id' => $twf->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$twf->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotracktwf));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtracktwf));
                    }
                }
            }
        }

        $twf->intro = shorten_text(format_module_intro('twf', $twf, $cm->id), $CFG->twf_shortpost);
        $twfname = format_string($twf->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $twflink = "<a href=\"view.php?f=$twf->id\" $style>".format_string($twf->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$twf->id\" $style>".$count."</a>";

        $row = array ($twflink, $twf->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = twf_get_subscribe_link($twf, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $twf->id);
            if ($twf->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $twf->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this twf has RSS activated, calculate it
        if ($show_rss) {
            if ($twf->rsstype and $twf->rssarticles) {
                //Calculate the tooltip text
                if ($twf->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'twf');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'twf');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_twf', $twf->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forums
$learningtable = new html_table();
$learningtable->head  = array ($strtwf, $strdescription, $strdiscussions);
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

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_twf');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->twf_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->twf_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning twfs

if ($course->id != SITEID) {    // Only real courses have learning twfs
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningtwfs) {
        $currentsection = '';
            foreach ($learningtwfs as $twf) {
            $cm      = $modinfo->instances['twf'][$twf->id];
            $context = context_module::instance($cm->id);

            $count = twf_count_discussions($twf, $cm, $course);

            if ($usetracking) {
                if ($twf->trackingtype == FORUM_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$twf->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = twf_tp_count_twf_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$twf->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $twf->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($twf->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->twf_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($twf->trackingtype === FORUM_TRACKING_OFF || ($USER->tracktwfs == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/twf/settracking.php', array('id'=>$twf->id));
                        if (!isset($untracked[$twf->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotracktwf));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtracktwf));
                        }
                    }
                }
            }

            $twf->intro = shorten_text(format_module_intro('twf', $twf, $cm->id), $CFG->twf_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $twfname = format_string($twf->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $twflink = "<a href=\"view.php?f=$twf->id\" $style>".format_string($twf->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$twf->id\" $style>".$count."</a>";

            $row = array ($printsection, $twflink, $twf->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = twf_get_subscribe_link($twf, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $twf->id);
                if ($twf->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $twf->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this twf has RSS activated, calculate it
            if ($show_rss) {
                if ($twf->rsstype and $twf->rssarticles) {
                    //Calculate the tolltip text
                    if ($twf->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'twf');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'twf');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_twf', $twf->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strtwfs);
$PAGE->set_title("$course->shortname: $strtwfs");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/twf/index.php', array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'twf')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/twf/index.php', array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'twf')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generaltwfs) {
    echo $OUTPUT->heading(get_string('generaltwfs', 'twf'), 2);
    echo html_writer::table($generaltable);
}

if ($learningtwfs) {
    echo $OUTPUT->heading(get_string('learningtwfs', 'twf'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();


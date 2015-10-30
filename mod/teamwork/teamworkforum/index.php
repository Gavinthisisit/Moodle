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
 * @package   mod_teamworkforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/teamworkforum/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all teamworkforums

$url = new moodle_url('/mod/teamworkforum/index.php', array('id'=>$id));
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
$event = \mod_teamworkforum\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strteamworkforums       = get_string('teamworkforums', 'teamworkforum');
$strteamworkforum        = get_string('teamworkforum', 'teamworkforum');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'teamworkforum');
$strsubscribed   = get_string('subscribed', 'teamworkforum');
$strunreadposts  = get_string('unreadposts', 'teamworkforum');
$strtracking     = get_string('tracking', 'teamworkforum');
$strmarkallread  = get_string('markallread', 'teamworkforum');
$strtrackteamworkforum   = get_string('trackteamworkforum', 'teamworkforum');
$strnotrackteamworkforum = get_string('notrackteamworkforum', 'teamworkforum');
$strsubscribe    = get_string('subscribe', 'teamworkforum');
$strunsubscribe  = get_string('unsubscribe', 'teamworkforum');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = teamworkforum_search_form($course);

// Retrieve the list of teamworkforum digest options for later.
$digestoptions = teamworkforum_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/teamworkforum/maildigest.php',
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
$generaltable->head  = array ($strteamworkforum, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = teamworkforum_tp_can_track_teamworkforums()) {
    $untracked = teamworkforum_tp_get_untracked_teamworkforums($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_teamworkforum\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_teamworkforum');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->teamworkforum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->teamworkforum_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the teamworkforums.  Most teamworkforums are course modules but
// some special ones are not.  These get placed in the general teamworkforums
// category with the teamworkforums in section 0.

$teamworkforums = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {teamworkforum} f
 LEFT JOIN {teamworkforum_digests} d ON d.teamworkforum = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalteamworkforums  = array();
$learningteamworkforums = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('teamworkforum') as $teamworkforumid=>$cm) {
    if (!$cm->uservisible or !isset($teamworkforums[$teamworkforumid])) {
        continue;
    }

    $teamworkforum = $teamworkforums[$teamworkforumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/teamworkforum:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($teamworkforum->type == 'news' or $teamworkforum->type == 'social') {
        $generalteamworkforums[$teamworkforum->id] = $teamworkforum;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalteamworkforums[$teamworkforum->id] = $teamworkforum;

    } else {
        $learningteamworkforums[$teamworkforum->id] = $teamworkforum;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // there should not be any links leading to this place, just redirect
        redirect(new moodle_url('/mod/teamworkforum/index.php', array('id' => $id)), get_string('subscribeenrolledonly', 'teamworkforum'));
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('teamworkforum') as $teamworkforumid=>$cm) {
        $teamworkforum = $teamworkforums[$teamworkforumid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/teamworkforum:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/teamworkforum:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_teamworkforum\subscriptions::is_forcesubscribed($teamworkforum)) {
            $subscribed = \mod_teamworkforum\subscriptions::is_subscribed($USER->id, $teamworkforum, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_teamworkforum\subscriptions::is_subscribable($teamworkforum)) && $subscribe && !$subscribed && $cansub) {
                \mod_teamworkforum\subscriptions::subscribe_user($USER->id, $teamworkforum, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_teamworkforum\subscriptions::unsubscribe_user($USER->id, $teamworkforum, $modcontext, true);
            }
        }
    }
    $returnto = teamworkforum_go_back_to("index.php?id=$course->id");
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect($returnto, get_string('nowallsubscribed', 'teamworkforum', $shortname), 1);
    } else {
        redirect($returnto, get_string('nowallunsubscribed', 'teamworkforum', $shortname), 1);
    }
}

/// First, let's process the general teamworkforums and build up a display

if ($generalteamworkforums) {
    foreach ($generalteamworkforums as $teamworkforum) {
        $cm      = $modinfo->instances['teamworkforum'][$teamworkforum->id];
        $context = context_module::instance($cm->id);

        $count = teamworkforum_count_discussions($teamworkforum, $cm, $course);

        if ($usetracking) {
            if ($teamworkforum->trackingtype == FORUM_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$teamworkforum->id])) {
                        $unreadlink  = '-';
                } else if ($unread = teamworkforum_tp_count_teamworkforum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$teamworkforum->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $teamworkforum->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($teamworkforum->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->teamworkforum_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($teamworkforum->trackingtype === FORUM_TRACKING_OFF || ($USER->trackteamworkforums == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/teamworkforum/settracking.php', array(
                            'id' => $teamworkforum->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$teamworkforum->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackteamworkforum));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackteamworkforum));
                    }
                }
            }
        }

        $teamworkforum->intro = shorten_text(format_module_intro('teamworkforum', $teamworkforum, $cm->id), $CFG->teamworkforum_shortpost);
        $teamworkforumname = format_string($teamworkforum->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $teamworkforumlink = "<a href=\"view.php?f=$teamworkforum->id\" $style>".format_string($teamworkforum->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$teamworkforum->id\" $style>".$count."</a>";

        $row = array ($teamworkforumlink, $teamworkforum->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = teamworkforum_get_subscribe_link($teamworkforum, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $teamworkforum->id);
            if ($teamworkforum->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $teamworkforum->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this teamworkforum has RSS activated, calculate it
        if ($show_rss) {
            if ($teamworkforum->rsstype and $teamworkforum->rssarticles) {
                //Calculate the tooltip text
                if ($teamworkforum->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'teamworkforum');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'teamworkforum');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_teamworkforum', $teamworkforum->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forums
$learningtable = new html_table();
$learningtable->head  = array ($strteamworkforum, $strdescription, $strdiscussions);
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

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_teamworkforum');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->teamworkforum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->teamworkforum_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning teamworkforums

if ($course->id != SITEID) {    // Only real courses have learning teamworkforums
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningteamworkforums) {
        $currentsection = '';
            foreach ($learningteamworkforums as $teamworkforum) {
            $cm      = $modinfo->instances['teamworkforum'][$teamworkforum->id];
            $context = context_module::instance($cm->id);

            $count = teamworkforum_count_discussions($teamworkforum, $cm, $course);

            if ($usetracking) {
                if ($teamworkforum->trackingtype == FORUM_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$teamworkforum->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = teamworkforum_tp_count_teamworkforum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$teamworkforum->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $teamworkforum->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($teamworkforum->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->teamworkforum_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($teamworkforum->trackingtype === FORUM_TRACKING_OFF || ($USER->trackteamworkforums == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/teamworkforum/settracking.php', array('id'=>$teamworkforum->id));
                        if (!isset($untracked[$teamworkforum->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackteamworkforum));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackteamworkforum));
                        }
                    }
                }
            }

            $teamworkforum->intro = shorten_text(format_module_intro('teamworkforum', $teamworkforum, $cm->id), $CFG->teamworkforum_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $teamworkforumname = format_string($teamworkforum->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $teamworkforumlink = "<a href=\"view.php?f=$teamworkforum->id\" $style>".format_string($teamworkforum->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$teamworkforum->id\" $style>".$count."</a>";

            $row = array ($printsection, $teamworkforumlink, $teamworkforum->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = teamworkforum_get_subscribe_link($teamworkforum, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $teamworkforum->id);
                if ($teamworkforum->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $teamworkforum->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this teamworkforum has RSS activated, calculate it
            if ($show_rss) {
                if ($teamworkforum->rsstype and $teamworkforum->rssarticles) {
                    //Calculate the tolltip text
                    if ($teamworkforum->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'teamworkforum');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'teamworkforum');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_teamworkforum', $teamworkforum->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strteamworkforums);
$PAGE->set_title("$course->shortname: $strteamworkforums");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/teamworkforum/index.php', array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'teamworkforum')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/teamworkforum/index.php', array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'teamworkforum')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalteamworkforums) {
    echo $OUTPUT->heading(get_string('generalteamworkforums', 'teamworkforum'), 2);
    echo html_writer::table($generaltable);
}

if ($learningteamworkforums) {
    echo $OUTPUT->heading(get_string('learningteamworkforums', 'teamworkforum'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();


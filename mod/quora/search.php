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

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);                  // course id
$search = trim(optional_param('search', '', PARAM_NOTAGS));  // search string
$page = optional_param('page', 0, PARAM_INT);   // which page to show
$perpage = optional_param('perpage', 10, PARAM_INT);   // how many per page
$showform = optional_param('showform', 0, PARAM_INT);   // Just show the form

$user    = trim(optional_param('user', '', PARAM_NOTAGS));    // Names to search for
$userid  = trim(optional_param('userid', 0, PARAM_INT));      // UserID to search for
$quoraid = trim(optional_param('quoraid', 0, PARAM_INT));      // ForumID to search for
$subject = trim(optional_param('subject', '', PARAM_NOTAGS)); // Subject
$phrase  = trim(optional_param('phrase', '', PARAM_NOTAGS));  // Phrase
$words   = trim(optional_param('words', '', PARAM_NOTAGS));   // Words
$fullwords = trim(optional_param('fullwords', '', PARAM_NOTAGS)); // Whole words
$notwords = trim(optional_param('notwords', '', PARAM_NOTAGS));   // Words we don't want

$timefromrestrict = optional_param('timefromrestrict', 0, PARAM_INT); // Use starting date
$fromday = optional_param('fromday', 0, PARAM_INT);      // Starting date
$frommonth = optional_param('frommonth', 0, PARAM_INT);      // Starting date
$fromyear = optional_param('fromyear', 0, PARAM_INT);      // Starting date
$fromhour = optional_param('fromhour', 0, PARAM_INT);      // Starting date
$fromminute = optional_param('fromminute', 0, PARAM_INT);      // Starting date
if ($timefromrestrict) {
    $datefrom = make_timestamp($fromyear, $frommonth, $fromday, $fromhour, $fromminute);
} else {
    $datefrom = optional_param('datefrom', 0, PARAM_INT);      // Starting date
}

$timetorestrict = optional_param('timetorestrict', 0, PARAM_INT); // Use ending date
$today = optional_param('today', 0, PARAM_INT);      // Ending date
$tomonth = optional_param('tomonth', 0, PARAM_INT);      // Ending date
$toyear = optional_param('toyear', 0, PARAM_INT);      // Ending date
$tohour = optional_param('tohour', 0, PARAM_INT);      // Ending date
$tominute = optional_param('tominute', 0, PARAM_INT);      // Ending date
if ($timetorestrict) {
    $dateto = make_timestamp($toyear, $tomonth, $today, $tohour, $tominute);
} else {
    $dateto = optional_param('dateto', 0, PARAM_INT);      // Ending date
}

$PAGE->set_pagelayout('standard');
$PAGE->set_url($FULLME); //TODO: this is very sloppy --skodak

if (empty($search)) {   // Check the other parameters instead
    if (!empty($words)) {
        $search .= ' '.$words;
    }
    if (!empty($userid)) {
        $search .= ' userid:'.$userid;
    }
    if (!empty($quoraid)) {
        $search .= ' quoraid:'.$quoraid;
    }
    if (!empty($user)) {
        $search .= ' '.quora_clean_search_terms($user, 'user:');
    }
    if (!empty($subject)) {
        $search .= ' '.quora_clean_search_terms($subject, 'subject:');
    }
    if (!empty($fullwords)) {
        $search .= ' '.quora_clean_search_terms($fullwords, '+');
    }
    if (!empty($notwords)) {
        $search .= ' '.quora_clean_search_terms($notwords, '-');
    }
    if (!empty($phrase)) {
        $search .= ' "'.$phrase.'"';
    }
    if (!empty($datefrom)) {
        $search .= ' datefrom:'.$datefrom;
    }
    if (!empty($dateto)) {
        $search .= ' dateto:'.$dateto;
    }
    $individualparams = true;
} else {
    $individualparams = false;
}

if ($search) {
    $search = quora_clean_search_terms($search);
}

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourseid');
}

require_course_login($course);

$params = array(
    'context' => $PAGE->context,
    'other' => array('searchterm' => $search)
);

$event = \mod_quora\event\course_searched::create($params);
$event->trigger();

$strquoras = get_string("modulenameplural", "quora");
$strsearch = get_string("search", "quora");
$strsearchresults = get_string("searchresults", "quora");
$strpage = get_string("page");

if (!$search || $showform) {

    $PAGE->navbar->add($strquoras, new moodle_url('/mod/quora/index.php', array('id'=>$course->id)));
    $PAGE->navbar->add(get_string('advancedsearch', 'quora'));

    $PAGE->set_title($strsearch);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    quora_print_big_search_form($course);
    echo $OUTPUT->footer();
    exit;
}

/// We need to do a search now and print results

$searchterms = str_replace('quoraid:', 'instance:', $search);
$searchterms = explode(' ', $searchterms);

$searchform = quora_search_form($course, $search);

$PAGE->navbar->add($strsearch, new moodle_url('/mod/quora/search.php', array('id'=>$course->id)));
$PAGE->navbar->add($strsearchresults);
if (!$posts = quora_search_posts($searchterms, $course->id, $page*$perpage, $perpage, $totalcount)) {
    $PAGE->set_title($strsearchresults);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strquoras, 2);
    echo $OUTPUT->heading($strsearchresults, 3);
    echo $OUTPUT->heading(get_string("noposts", "quora"), 4);

    if (!$individualparams) {
        $words = $search;
    }

    quora_print_big_search_form($course);

    echo $OUTPUT->footer();
    exit;
}

//including this here to prevent it being included if there are no search results
require_once($CFG->dirroot.'/rating/lib.php');

//set up the ratings information that will be the same for all posts
$ratingoptions = new stdClass();
$ratingoptions->component = 'mod_quora';
$ratingoptions->ratingarea = 'post';
$ratingoptions->userid = $USER->id;
$ratingoptions->returnurl = $PAGE->url->out(false);
$rm = new rating_manager();

$PAGE->set_title($strsearchresults);
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();
echo '<div class="reportlink">';
echo '<a href="search.php?id='.$course->id.
                         '&amp;user='.urlencode($user).
                         '&amp;userid='.$userid.
                         '&amp;quoraid='.$quoraid.
                         '&amp;subject='.urlencode($subject).
                         '&amp;phrase='.urlencode($phrase).
                         '&amp;words='.urlencode($words).
                         '&amp;fullwords='.urlencode($fullwords).
                         '&amp;notwords='.urlencode($notwords).
                         '&amp;dateto='.$dateto.
                         '&amp;datefrom='.$datefrom.
                         '&amp;showform=1'.
                         '">'.get_string('advancedsearch','quora').'...</a>';
echo '</div>';

echo $OUTPUT->heading($strquoras, 2);
echo $OUTPUT->heading("$strsearchresults: $totalcount", 3);

$url = new moodle_url('search.php', array('search' => $search, 'id' => $course->id, 'perpage' => $perpage));
echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $url);

//added to implement highlighting of search terms found only in HTML markup
//fiedorow - 9/2/2005
$strippedsearch = str_replace('user:','',$search);
$strippedsearch = str_replace('subject:','',$strippedsearch);
$strippedsearch = str_replace('&quot;','',$strippedsearch);
$searchterms = explode(' ', $strippedsearch);    // Search for words independently
foreach ($searchterms as $key => $searchterm) {
    if (preg_match('/^\-/',$searchterm)) {
        unset($searchterms[$key]);
    } else {
        $searchterms[$key] = preg_replace('/^\+/','',$searchterm);
    }
}
$strippedsearch = implode(' ', $searchterms);    // Rebuild the string

foreach ($posts as $post) {

    // Replace the simple subject with the three items quora name -> thread name -> subject
    // (if all three are appropriate) each as a link.
    if (! $discussion = $DB->get_record('quora_discussions', array('id' => $post->discussion))) {
        print_error('invaliddiscussionid', 'quora');
    }
    if (! $quora = $DB->get_record('quora', array('id' => "$discussion->quora"))) {
        print_error('invalidquoraid', 'quora');
    }

    if (!$cm = get_coursemodule_from_instance('quora', $quora->id)) {
        print_error('invalidcoursemodule');
    }

    $post->subject = highlight($strippedsearch, $post->subject);
    $discussion->name = highlight($strippedsearch, $discussion->name);

    $fullsubject = "<a href=\"view.php?f=$quora->id\">".format_string($quora->name,true)."</a>";
    if ($quora->type != 'single') {
        $fullsubject .= " -> <a href=\"discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a>";
        if ($post->parent != 0) {
            $fullsubject .= " -> <a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a>";
        }
    }

    $post->subject = $fullsubject;
    $post->subjectnoformat = true;

    //add the ratings information to the post
    //Unfortunately seem to have do this individually as posts may be from different quoras
    if ($quora->assessed != RATING_AGGREGATE_NONE) {
        $modcontext = context_module::instance($cm->id);
        $ratingoptions->context = $modcontext;
        $ratingoptions->items = array($post);
        $ratingoptions->aggregate = $quora->assessed;//the aggregation method
        $ratingoptions->scaleid = $quora->scale;
        $ratingoptions->assesstimestart = $quora->assesstimestart;
        $ratingoptions->assesstimefinish = $quora->assesstimefinish;
        $postswithratings = $rm->get_ratings($ratingoptions);

        if ($postswithratings && count($postswithratings)==1) {
            $post = $postswithratings[0];
        }
    }

    // Identify search terms only found in HTML markup, and add a warning about them to
    // the start of the message text. However, do not do the highlighting here. quora_print_post
    // will do it for us later.
    $missing_terms = "";

    $options = new stdClass();
    $options->trusted = $post->messagetrust;
    $post->message = highlight($strippedsearch,
                    format_text($post->message, $post->messageformat, $options, $course->id),
                    0, '<fgw9sdpq4>', '</fgw9sdpq4>');

    foreach ($searchterms as $searchterm) {
        if (preg_match("/$searchterm/i",$post->message) && !preg_match('/<fgw9sdpq4>'.$searchterm.'<\/fgw9sdpq4>/i',$post->message)) {
            $missing_terms .= " $searchterm";
        }
    }

    $post->message = str_replace('<fgw9sdpq4>', '<span class="highlight">', $post->message);
    $post->message = str_replace('</fgw9sdpq4>', '</span>', $post->message);

    if ($missing_terms) {
        $strmissingsearchterms = get_string('missingsearchterms','quora');
        $post->message = '<p class="highlight2">'.$strmissingsearchterms.' '.$missing_terms.'</p>'.$post->message;
    }

    // Prepare a link to the post in context, to be displayed after the quora post.
    $fulllink = "<a href=\"discuss.php?d=$post->discussion#p$post->id\">".get_string("postincontext", "quora")."</a>";

    // Now pring the post.
    quora_print_post($post, $discussion, $quora, $cm, $course, false, false, false,
            $fulllink, '', -99, false);
}

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $url);

echo $OUTPUT->footer();


 /**
  * Print a full-sized search form for the specified course.
  *
  * @param stdClass $course The Course that will be searched.
  * @return void The function prints the form.
  */
function quora_print_big_search_form($course) {
    global $CFG, $DB, $words, $subject, $phrase, $user, $userid, $fullwords, $notwords, $datefrom, $dateto, $PAGE, $OUTPUT;

    echo $OUTPUT->box(get_string('searchquoraintro', 'quora'), 'searchbox boxaligncenter', 'intro');

    echo $OUTPUT->box_start('generalbox boxaligncenter');

    echo html_writer::script('', $CFG->wwwroot.'/mod/quora/quora.js');

    echo '<form id="searchform" action="search.php" method="get">';
    echo '<table cellpadding="10" class="searchbox" id="form">';

    echo '<tr>';
    echo '<td class="c0"><label for="words">'.get_string('searchwords', 'quora').'</label>';
    echo '<input type="hidden" value="'.$course->id.'" name="id" alt="" /></td>';
    echo '<td class="c1"><input type="text" size="35" name="words" id="words"value="'.s($words, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="phrase">'.get_string('searchphrase', 'quora').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="phrase" id="phrase" value="'.s($phrase, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="notwords">'.get_string('searchnotwords', 'quora').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="notwords" id="notwords" value="'.s($notwords, true).'" alt="" /></td>';
    echo '</tr>';

    if ($DB->get_dbfamily() == 'mysql' || $DB->get_dbfamily() == 'postgres') {
        echo '<tr>';
        echo '<td class="c0"><label for="fullwords">'.get_string('searchfullwords', 'quora').'</label></td>';
        echo '<td class="c1"><input type="text" size="35" name="fullwords" id="fullwords" value="'.s($fullwords, true).'" alt="" /></td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<td class="c0">'.get_string('searchdatefrom', 'quora').'</td>';
    echo '<td class="c1">';
    if (empty($datefrom)) {
        $datefromchecked = '';
        $datefrom = make_timestamp(2000, 1, 1, 0, 0, 0);
    }else{
        $datefromchecked = 'checked="checked"';
    }

    echo '<input name="timefromrestrict" type="checkbox" value="1" alt="'.get_string('searchdatefrom', 'quora').'" onclick="return lockoptions(\'searchform\', \'timefromrestrict\', timefromitems)" '.  $datefromchecked . ' /> ';
    $selectors = html_writer::select_time('days', 'fromday', $datefrom)
               . html_writer::select_time('months', 'frommonth', $datefrom)
               . html_writer::select_time('years', 'fromyear', $datefrom)
               . html_writer::select_time('hours', 'fromhour', $datefrom)
               . html_writer::select_time('minutes', 'fromminute', $datefrom);
    echo $selectors;
    echo '<input type="hidden" name="hfromday" value="0" />';
    echo '<input type="hidden" name="hfrommonth" value="0" />';
    echo '<input type="hidden" name="hfromyear" value="0" />';
    echo '<input type="hidden" name="hfromhour" value="0" />';
    echo '<input type="hidden" name="hfromminute" value="0" />';

    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0">'.get_string('searchdateto', 'quora').'</td>';
    echo '<td class="c1">';
    if (empty($dateto)) {
        $datetochecked = '';
        $dateto = time()+3600;
    }else{
        $datetochecked = 'checked="checked"';
    }

    echo '<input name="timetorestrict" type="checkbox" value="1" alt="'.get_string('searchdateto', 'quora').'" onclick="return lockoptions(\'searchform\', \'timetorestrict\', timetoitems)" ' .$datetochecked. ' /> ';
    $selectors = html_writer::select_time('days', 'today', $dateto)
               . html_writer::select_time('months', 'tomonth', $dateto)
               . html_writer::select_time('years', 'toyear', $dateto)
               . html_writer::select_time('hours', 'tohour', $dateto)
               . html_writer::select_time('minutes', 'tominute', $dateto);
    echo $selectors;

    echo '<input type="hidden" name="htoday" value="0" />';
    echo '<input type="hidden" name="htomonth" value="0" />';
    echo '<input type="hidden" name="htoyear" value="0" />';
    echo '<input type="hidden" name="htohour" value="0" />';
    echo '<input type="hidden" name="htominute" value="0" />';

    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="menuquoraid">'.get_string('searchwhichquoras', 'quora').'</label></td>';
    echo '<td class="c1">';
    echo html_writer::select(quora_menu_list($course), 'quoraid', '', array(''=>get_string('allquoras', 'quora')));
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="subject">'.get_string('searchsubject', 'quora').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="subject" id="subject" value="'.s($subject, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="c0"><label for="user">'.get_string('searchuser', 'quora').'</label></td>';
    echo '<td class="c1"><input type="text" size="35" name="user" id="user" value="'.s($user, true).'" alt="" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="submit" colspan="2" align="center">';
    echo '<input type="submit" value="'.get_string('searchquoras', 'quora').'" alt="" /></td>';
    echo '</tr>';

    echo '</table>';
    echo '</form>';

    echo html_writer::script(js_writer::function_call('lockoptions_timetoitems'));
    echo html_writer::script(js_writer::function_call('lockoptions_timefromitems'));

    echo $OUTPUT->box_end();
}

/**
 * This function takes each word out of the search string, makes sure they are at least
 * two characters long and returns an string of the space-separated search
 * terms.
 *
 * @param string $words String containing space-separated strings to search for.
 * @param string $prefix String to prepend to the each token taken out of $words.
 * @return string The filtered search terms, separated by spaces.
 * @todo Take the hardcoded limit out of this function and put it into a user-specified parameter.
 */
function quora_clean_search_terms($words, $prefix='') {
    $searchterms = explode(' ', $words);
    foreach ($searchterms as $key => $searchterm) {
        if (strlen($searchterm) < 2) {
            unset($searchterms[$key]);
        } else if ($prefix) {
            $searchterms[$key] = $prefix.$searchterm;
        }
    }
    return trim(implode(' ', $searchterms));
}

 /**
  * Retrieve a list of the quoras that this user can view.
  *
  * @param stdClass $course The Course to use.
  * @return array A set of formatted quora names stored against the quora id.
  */
function quora_menu_list($course)  {
    $menu = array();

    $modinfo = get_fast_modinfo($course);
    if (empty($modinfo->instances['quora'])) {
        return $menu;
    }

    foreach ($modinfo->instances['quora'] as $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);
        if (!has_capability('mod/quora:viewdiscussion', $context)) {
            continue;
        }
        $menu[$cm->instance] = format_string($cm->name);
    }

    return $menu;
}

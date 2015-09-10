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
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/quora/lib.php');

    $settings->add(new admin_setting_configselect('quora_displaymode', get_string('displaymode', 'quora'),
                       get_string('configdisplaymode', 'quora'), FORUM_MODE_NESTED, quora_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('quora_replytouser', get_string('replytouser', 'quora'),
                       get_string('configreplytouser', 'quora'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('quora_shortpost', get_string('shortpost', 'quora'),
                       get_string('configshortpost', 'quora'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('quora_longpost', get_string('longpost', 'quora'),
                       get_string('configlongpost', 'quora'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('quora_manydiscussions', get_string('manydiscussions', 'quora'),
                       get_string('configmanydiscussions', 'quora'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->quora_maxbytes)) {
            $maxbytes = $CFG->quora_maxbytes;
        }
        $settings->add(new admin_setting_configselect('quora_maxbytes', get_string('maxattachmentsize', 'quora'),
                           get_string('configmaxbytes', 'quora'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all quoras
    $settings->add(new admin_setting_configtext('quora_maxattachments', get_string('maxattachments', 'quora'),
                       get_string('configmaxattachments', 'quora'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[FORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'quora');
    $options[FORUM_TRACKING_OFF] = get_string('trackingoff', 'quora');
    $options[FORUM_TRACKING_FORCED] = get_string('trackingon', 'quora');
    $settings->add(new admin_setting_configselect('quora_trackingtype', get_string('trackingtype', 'quora'),
                       get_string('configtrackingtype', 'quora'), FORUM_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('quora_trackreadposts', get_string('trackquora', 'quora'),
                       get_string('configtrackreadposts', 'quora'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('quora_allowforcedreadtracking', get_string('forcedreadtracking', 'quora'),
                       get_string('forcedreadtracking_desc', 'quora'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('quora_oldpostdays', get_string('oldpostdays', 'quora'),
                       get_string('configoldpostdays', 'quora'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('quora_usermarksread', get_string('usermarksread', 'quora'),
                       get_string('configusermarksread', 'quora'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('quora_cleanreadtime', get_string('cleanreadtime', 'quora'),
                       get_string('configcleanreadtime', 'quora'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'quora'),
                       get_string('configdigestmailtime', 'quora'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'quora').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'quora');
    }
    $settings->add(new admin_setting_configselect('quora_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'quora'),
            2 => get_string('posts', 'quora')
        );
        $settings->add(new admin_setting_configselect('quora_rsstype', get_string('rsstypedefault', 'quora'),
                get_string('configrsstypedefault', 'quora'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('quora_rssarticles', get_string('rssarticles', 'quora'),
                get_string('configrssarticlesdefault', 'quora'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('quora_enabletimedposts', get_string('timedposts', 'quora'),
                       get_string('configenabletimedposts', 'quora'), 0));
}


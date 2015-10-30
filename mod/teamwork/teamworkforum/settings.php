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
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/teamworkforum/lib.php');

    $settings->add(new admin_setting_configselect('teamworkforum_displaymode', get_string('displaymode', 'teamworkforum'),
                       get_string('configdisplaymode', 'teamworkforum'), FORUM_MODE_NESTED, teamworkforum_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('teamworkforum_replytouser', get_string('replytouser', 'teamworkforum'),
                       get_string('configreplytouser', 'teamworkforum'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('teamworkforum_shortpost', get_string('shortpost', 'teamworkforum'),
                       get_string('configshortpost', 'teamworkforum'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('teamworkforum_longpost', get_string('longpost', 'teamworkforum'),
                       get_string('configlongpost', 'teamworkforum'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('teamworkforum_manydiscussions', get_string('manydiscussions', 'teamworkforum'),
                       get_string('configmanydiscussions', 'teamworkforum'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->teamworkforum_maxbytes)) {
            $maxbytes = $CFG->teamworkforum_maxbytes;
        }
        $settings->add(new admin_setting_configselect('teamworkforum_maxbytes', get_string('maxattachmentsize', 'teamworkforum'),
                           get_string('configmaxbytes', 'teamworkforum'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all teamworkforums
    $settings->add(new admin_setting_configtext('teamworkforum_maxattachments', get_string('maxattachments', 'teamworkforum'),
                       get_string('configmaxattachments', 'teamworkforum'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[FORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'teamworkforum');
    $options[FORUM_TRACKING_OFF] = get_string('trackingoff', 'teamworkforum');
    $options[FORUM_TRACKING_FORCED] = get_string('trackingon', 'teamworkforum');
    $settings->add(new admin_setting_configselect('teamworkforum_trackingtype', get_string('trackingtype', 'teamworkforum'),
                       get_string('configtrackingtype', 'teamworkforum'), FORUM_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('teamworkforum_trackreadposts', get_string('trackteamworkforum', 'teamworkforum'),
                       get_string('configtrackreadposts', 'teamworkforum'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('teamworkforum_allowforcedreadtracking', get_string('forcedreadtracking', 'teamworkforum'),
                       get_string('forcedreadtracking_desc', 'teamworkforum'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('teamworkforum_oldpostdays', get_string('oldpostdays', 'teamworkforum'),
                       get_string('configoldpostdays', 'teamworkforum'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('teamworkforum_usermarksread', get_string('usermarksread', 'teamworkforum'),
                       get_string('configusermarksread', 'teamworkforum'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('teamworkforum_cleanreadtime', get_string('cleanreadtime', 'teamworkforum'),
                       get_string('configcleanreadtime', 'teamworkforum'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'teamworkforum'),
                       get_string('configdigestmailtime', 'teamworkforum'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'teamworkforum').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'teamworkforum');
    }
    $settings->add(new admin_setting_configselect('teamworkforum_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'teamworkforum'),
            2 => get_string('posts', 'teamworkforum')
        );
        $settings->add(new admin_setting_configselect('teamworkforum_rsstype', get_string('rsstypedefault', 'teamworkforum'),
                get_string('configrsstypedefault', 'teamworkforum'), 0, $options));

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
        $settings->add(new admin_setting_configselect('teamworkforum_rssarticles', get_string('rssarticles', 'teamworkforum'),
                get_string('configrssarticlesdefault', 'teamworkforum'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('teamworkforum_enabletimedposts', get_string('timedposts', 'teamworkforum'),
                       get_string('configenabletimedposts', 'teamworkforum'), 0));
}


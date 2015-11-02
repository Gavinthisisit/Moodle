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
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/twf/lib.php');

    $settings->add(new admin_setting_configselect('twf_displaymode', get_string('displaymode', 'twf'),
                       get_string('configdisplaymode', 'twf'), FORUM_MODE_NESTED, twf_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('twf_replytouser', get_string('replytouser', 'twf'),
                       get_string('configreplytouser', 'twf'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('twf_shortpost', get_string('shortpost', 'twf'),
                       get_string('configshortpost', 'twf'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('twf_longpost', get_string('longpost', 'twf'),
                       get_string('configlongpost', 'twf'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('twf_manydiscussions', get_string('manydiscussions', 'twf'),
                       get_string('configmanydiscussions', 'twf'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->twf_maxbytes)) {
            $maxbytes = $CFG->twf_maxbytes;
        }
        $settings->add(new admin_setting_configselect('twf_maxbytes', get_string('maxattachmentsize', 'twf'),
                           get_string('configmaxbytes', 'twf'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all twfs
    $settings->add(new admin_setting_configtext('twf_maxattachments', get_string('maxattachments', 'twf'),
                       get_string('configmaxattachments', 'twf'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[FORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'twf');
    $options[FORUM_TRACKING_OFF] = get_string('trackingoff', 'twf');
    $options[FORUM_TRACKING_FORCED] = get_string('trackingon', 'twf');
    $settings->add(new admin_setting_configselect('twf_trackingtype', get_string('trackingtype', 'twf'),
                       get_string('configtrackingtype', 'twf'), FORUM_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('twf_trackreadposts', get_string('tracktwf', 'twf'),
                       get_string('configtrackreadposts', 'twf'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('twf_allowforcedreadtracking', get_string('forcedreadtracking', 'twf'),
                       get_string('forcedreadtracking_desc', 'twf'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('twf_oldpostdays', get_string('oldpostdays', 'twf'),
                       get_string('configoldpostdays', 'twf'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('twf_usermarksread', get_string('usermarksread', 'twf'),
                       get_string('configusermarksread', 'twf'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('twf_cleanreadtime', get_string('cleanreadtime', 'twf'),
                       get_string('configcleanreadtime', 'twf'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'twf'),
                       get_string('configdigestmailtime', 'twf'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'twf').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'twf');
    }
    $settings->add(new admin_setting_configselect('twf_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'twf'),
            2 => get_string('posts', 'twf')
        );
        $settings->add(new admin_setting_configselect('twf_rsstype', get_string('rsstypedefault', 'twf'),
                get_string('configrsstypedefault', 'twf'), 0, $options));

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
        $settings->add(new admin_setting_configselect('twf_rssarticles', get_string('rssarticles', 'twf'),
                get_string('configrssarticlesdefault', 'twf'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('twf_enabletimedposts', get_string('timedposts', 'twf'),
                       get_string('configenabletimedposts', 'twf'), 0));
}


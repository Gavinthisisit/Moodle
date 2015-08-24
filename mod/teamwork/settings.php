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
 * The teamwork module configuration variables
 *
 * The values defined here are often used as defaults for all module instances.
 *
 * @package    mod_teamwork
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/teamwork/locallib.php');

    $grades = teamwork::available_maxgrades_list();

    $settings->add(new admin_setting_configselect('teamwork/grade', get_string('submissiongrade', 'teamwork'),
                        get_string('configgrade', 'teamwork'), 80, $grades));

    $settings->add(new admin_setting_configselect('teamwork/gradinggrade', get_string('gradinggrade', 'teamwork'),
                        get_string('configgradinggrade', 'teamwork'), 20, $grades));

    $options = array();
    for ($i = 5; $i >= 0; $i--) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('teamwork/gradedecimals', get_string('gradedecimals', 'teamwork'),
                        get_string('configgradedecimals', 'teamwork'), 0, $options));

    if (isset($CFG->maxbytes)) {
        $maxbytes = get_config('teamwork', 'maxbytes');
        $options = get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes);
        $settings->add(new admin_setting_configselect('teamwork/maxbytes', get_string('maxbytes', 'teamwork'),
                            get_string('configmaxbytes', 'teamwork'), 0, $options));
    }

    $settings->add(new admin_setting_configselect('teamwork/strategy', get_string('strategy', 'teamwork'),
                        get_string('configstrategy', 'teamwork'), 'accumulative', teamwork::available_strategies_list()));

    $options = teamwork::available_example_modes_list();
    $settings->add(new admin_setting_configselect('teamwork/examplesmode', get_string('examplesmode', 'teamwork'),
                        get_string('configexamplesmode', 'teamwork'), teamwork::EXAMPLES_VOLUNTARY, $options));

    // include the settings of allocation subplugins
    $allocators = core_component::get_plugin_list('teamworkallocation');
    foreach ($allocators as $allocator => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('teamworkallocationsetting'.$allocator,
                    get_string('allocation', 'teamwork') . ' - ' . get_string('pluginname', 'teamworkallocation_' . $allocator), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading strategy subplugins
    $strategies = core_component::get_plugin_list('teamworkform');
    foreach ($strategies as $strategy => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('teamworkformsetting'.$strategy,
                    get_string('strategy', 'teamwork') . ' - ' . get_string('pluginname', 'teamworkform_' . $strategy), ''));
            include($settingsfile);
        }
    }

    // include the settings of grading evaluation subplugins
    $evaluations = core_component::get_plugin_list('teamworkeval');
    foreach ($evaluations as $evaluation => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('teamworkevalsetting'.$evaluation,
                    get_string('evaluation', 'teamwork') . ' - ' . get_string('pluginname', 'teamworkeval_' . $evaluation), ''));
            include($settingsfile);
        }
    }

}

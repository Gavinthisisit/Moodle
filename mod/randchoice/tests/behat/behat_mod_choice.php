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
 * Steps definitions for randchoice activity.
 *
 * @package   mod_randchoice
 * @category  test
 * @copyright 2013 David Monllaó
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given;

/**
 * Choice activity definitions.
 *
 * @package   mod_randchoice
 * @category  test
 * @copyright 2013 David Monllaó
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_randchoice extends behat_base {

    /**
     * Chooses the specified option from the randchoice activity named as specified. You should be located in the activity's course page.
     *
     * @Given /^I choose "(?P<option_string>(?:[^"]|\\")*)" from "(?P<randchoice_activity_string>(?:[^"]|\\")*)" randchoice activity$/
     * @param string $option
     * @param string $randchoiceactivity
     * @return array
     */
    public function I_choose_option_from_activity($option, $randchoiceactivity) {

        // Escaping again the strings as backslashes have been removed by the automatic transformation.
        return array(
            new Given('I follow "' . $this->escape($randchoiceactivity) . '"'),
            new Given('I set the field "' . $this->escape($option) . '" to "1"'),
            new Given('I press "' . get_string('savemyrandchoice', 'randchoice') . '"')
        );
    }

    /**
     * Chooses the specified option from the randchoice activity named as specified and save the randchoice.
     * You should be located in the activity's course page.
     *
     * @Given /^I choose options (?P<option_string>"(?:[^"]|\\")*"(?:,"(?:[^"]|\\")*")*) from "(?P<randchoice_activity_string>(?:[^"]|\\")*)" randchoice activity$/
     * @param string $option
     * @param string $randchoiceactivity
     * @return array
     */
    public function I_choose_options_from_activity($option, $randchoiceactivity) {
        // Escaping again the strings as backslashes have been removed by the automatic transformation.
        $return = array(new Given('I follow "' . $this->escape($randchoiceactivity) . '"'));
        $options = explode('","', trim($option, '"'));
        foreach ($options as $option) {
            $return[] = new Given('I set the field "' . $this->escape($option) . '" to "1"');
        }
        $return[] = new Given('I press "' . get_string('savemyrandchoice', 'randchoice') . '"');
        return $return;
    }

}

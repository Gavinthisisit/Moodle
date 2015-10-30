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
 * Steps definitions related with the teamworkforum activity.
 *
 * @package    mod_teamworkforum
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode;
/**
 * Forum-related steps definitions.
 *
 * @package    mod_teamworkforum
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_teamworkforum extends behat_base {

    /**
     * Adds a topic to the teamworkforum specified by it's name. Useful for the News teamworkforum and blog-style teamworkforums.
     *
     * @Given /^I add a new topic to "(?P<teamworkforum_name_string>(?:[^"]|\\")*)" teamworkforum with:$/
     * @param string $teamworkforumname
     * @param TableNode $table
     */
    public function i_add_a_new_topic_to_teamworkforum_with($teamworkforumname, TableNode $table) {
        return $this->add_new_discussion($teamworkforumname, $table, get_string('addanewtopic', 'teamworkforum'));
    }

    /**
     * Adds a discussion to the teamworkforum specified by it's name with the provided table data (usually Subject and Message). The step begins from the teamworkforum's course page.
     *
     * @Given /^I add a new discussion to "(?P<teamworkforum_name_string>(?:[^"]|\\")*)" teamworkforum with:$/
     * @param string $teamworkforumname
     * @param TableNode $table
     */
    public function i_add_a_teamworkforum_discussion_to_teamworkforum_with($teamworkforumname, TableNode $table) {
        return $this->add_new_discussion($teamworkforumname, $table, get_string('addanewdiscussion', 'teamworkforum'));
    }

    /**
     * Adds a reply to the specified post of the specified teamworkforum. The step begins from the teamworkforum's page or from the teamworkforum's course page.
     *
     * @Given /^I reply "(?P<post_subject_string>(?:[^"]|\\")*)" post from "(?P<teamworkforum_name_string>(?:[^"]|\\")*)" teamworkforum with:$/
     * @param string $postname The subject of the post
     * @param string $teamworkforumname The teamworkforum name
     * @param TableNode $table
     */
    public function i_reply_post_from_teamworkforum_with($postsubject, $teamworkforumname, TableNode $table) {

        return array(
            new Given('I follow "' . $this->escape($teamworkforumname) . '"'),
            new Given('I follow "' . $this->escape($postsubject) . '"'),
            new Given('I follow "' . get_string('reply', 'teamworkforum') . '"'),
            new Given('I set the following fields to these values:', $table),
            new Given('I press "' . get_string('posttoteamworkforum', 'teamworkforum') . '"'),
            new Given('I wait to be redirected')
        );

    }

    /**
     * Returns the steps list to add a new discussion to a teamworkforum.
     *
     * Abstracts add a new topic and add a new discussion, as depending
     * on the teamworkforum type the button string changes.
     *
     * @param string $teamworkforumname
     * @param TableNode $table
     * @param string $buttonstr
     * @return Given[]
     */
    protected function add_new_discussion($teamworkforumname, TableNode $table, $buttonstr) {

        // Escaping $teamworkforumname as it has been stripped automatically by the transformer.
        return array(
            new Given('I follow "' . $this->escape($teamworkforumname) . '"'),
            new Given('I press "' . $buttonstr . '"'),
            new Given('I set the following fields to these values:', $table),
            new Given('I press "' . get_string('posttoteamworkforum', 'teamworkforum') . '"'),
            new Given('I wait to be redirected')
        );

    }

}

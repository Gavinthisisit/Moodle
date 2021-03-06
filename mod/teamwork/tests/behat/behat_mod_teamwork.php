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
 * Steps definitions related to mod_teamwork.
 *
 * @package    mod_teamwork
 * @category   test
 * @copyright  2014 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode;

/**
 * Steps definitions related to mod_teamwork.
 *
 * @package    mod_teamwork
 * @category   test
 * @copyright  2014 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_teamwork extends behat_base {
    /**
     * Changes the submission phase for the teamwork.
     *
     * @When /^I change phase in teamwork "(?P<teamwork_name_string>(?:[^"]|\\")*)" to "(?P<phase_name_string>(?:[^"]|\\")*)"$/
     * @param string $questiontype
     * @param string $teamworkname
     */
    public function i_change_phase_in_teamwork_to($teamworkname, $phase) {
        $teamworkname = $this->escape($teamworkname);
        $phaseliteral = $this->getSession()->getSelectorsHandler()->xpathLiteral($phase);
        $switchphase = $this->getSession()->getSelectorsHandler()->xpathLiteral(get_string('switchphase', 'teamwork'));

        $xpath = "//*[@class='userplan']/descendant::div[./span[contains(.,$phaseliteral)]]/".
                "descendant-or-self::a[./img[@alt=$switchphase]]";
        $continue = $this->escape(get_string('continue'));
        return array(
            new Given("I follow \"$teamworkname\""),
            new Given("I click on \"$xpath\" \"xpath_element\""),
            new Given("I press \"$continue\""),
        );
    }

    /**
     * Adds or edits a student teamwork submission.
     *
     * @When /^I add a submission in teamwork "(?P<teamwork_name_string>(?:[^"]|\\")*)" as:"$/
     * @param string $teamworkname
     * @param TableNode $table data to fill the submission form with, must contain 'Title'
     */
    public function i_add_a_submission_in_teamwork_as($teamworkname, $table) {
        $teamworkname = $this->escape($teamworkname);
        $savechanges = $this->escape(get_string('savechanges'));
        $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' ownsubmission ')]/descendant::input[@type='submit']";
        return array(
            new Given("I follow \"$teamworkname\""),
            new Given("I click on \"$xpath\" \"xpath_element\""),
            new Given("I set the following fields to these values:", $table),
            new Given("I press \"$savechanges\""),
        );
    }

    /**
     * Sets the teamwork assessment form.
     *
     * @When /^I edit assessment form in teamwork "(?P<teamwork_name_string>(?:[^"]|\\")*)" as:"$/
     * @param string $teamworkname
     * @param TableNode $table data to fill the submission form with, must contain 'Title'
     */
    public function i_edit_assessment_form_in_teamwork_as($teamworkname, $table) {
        $teamworkname = $this->escape($teamworkname);
        $editassessmentform = $this->escape(get_string('editassessmentform', 'teamwork'));
        $saveandclose = $this->escape(get_string('saveandclose', 'teamwork'));
        return array(
            new Given("I follow \"$teamworkname\""),
            new Given("I follow \"$editassessmentform\""),
            new Given("I set the following fields to these values:", $table),
            new Given("I press \"$saveandclose\""),
        );
    }

    /**
     * Peer-assesses a teamwork submission.
     *
     * @When /^I assess submission "(?P<submission_string>(?:[^"]|\\")*)" in teamwork "(?P<teamwork_name_string>(?:[^"]|\\")*)" as:"$/
     * @param string $submission
     * @param string $teamworkname
     * @param TableNode $table
     */
    public function i_assess_submission_in_teamwork_as($submission, $teamworkname, TableNode $table) {
        $teamworkname = $this->escape($teamworkname);
        $submissionliteral = $this->getSession()->getSelectorsHandler()->xpathLiteral($submission);
        $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' assessment-summary ') ".
                "and contains(.,$submissionliteral)]";
        $assess = $this->escape(get_string('assess', 'teamwork'));
        $saveandclose = $this->escape(get_string('saveandclose', 'teamwork'));
        return array(
            new Given("I follow \"$teamworkname\""),
            new Given("I click on \"$assess\" \"button\" in the \"$xpath\" \"xpath_element\""),
            new Given("I set the following fields to these values:", $table),
            new Given("I press \"$saveandclose\""),
        );
    }

    /**
     * Checks that the user has particular grade set by his reviewing peer in teamwork
     *
     * @Then /^I should see grade "(?P<grade_string>[^"]*)" for teamwork participant "(?P<participant_name_string>(?:[^"]|\\")*)" set by peer "(?P<reviewer_name_string>(?:[^"]|\\")*)"$/
     * @param string $grade
     * @param string $participant
     * @param string $reviewer
     */
    public function i_should_see_grade_for_teamwork_participant_set_by_peer($grade, $participant, $reviewer) {
        $participantliteral = $this->getSession()->getSelectorsHandler()->xpathLiteral($participant);
        $reviewerliteral = $this->getSession()->getSelectorsHandler()->xpathLiteral($reviewer);
        $gradeliteral = $this->getSession()->getSelectorsHandler()->xpathLiteral($grade);
        $participantselector = "contains(concat(' ', normalize-space(@class), ' '), ' participant ') ".
                "and contains(.,$participantliteral)";
        $trxpath = "//table/tbody/tr[td[$participantselector]]";
        $tdparticipantxpath = "//table/tbody/tr/td[$participantselector]";
        $tdxpath = "/td[contains(concat(' ', normalize-space(@class), ' '), ' receivedgrade ') and contains(.,$reviewerliteral)]/".
                "descendant::span[contains(concat(' ', normalize-space(@class), ' '), ' grade ') and .=$gradeliteral]";

        $tr = $this->find('xpath', $trxpath);
        $rowspan = $this->find('xpath', $tdparticipantxpath)->getAttribute('rowspan');

        $xpath = $trxpath.$tdxpath;
        if (!empty($rowspan)) {
            for ($i = 1; $i < $rowspan; $i++) {
                $xpath .= ' | '.$trxpath."/following-sibling::tr[$i]".$tdxpath;
            }
        }
        $this->find('xpath', $xpath);
    }
}

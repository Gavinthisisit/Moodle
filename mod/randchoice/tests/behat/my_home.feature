@mod @mod_randchoice @javascript
Feature: Test the display of the randchoice module on my home
  In order to know my status in a randchoice activity
  As a user
  I need to see it in My dashboard.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Choice" to section "1"
    And I expand all fieldsets
    And I click on "id_timerestrict" "checkbox"
    And I set the following fields to these values:
      | Choice name | Test randchoice name |
      | Description | Test randchoice description |
      | timeclose[day] | 1 |
      | timeclose[month] | January |
      | timeclose[year] | 2030 |
      | timeclose[hour] | 08 |
      | timeclose[minute] | 00 |
      | Allow randchoice to be updated | No |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I press "Save and return to course"
    And I log out

  @javascript
  Scenario: View my home as a student before answering the randchoice
    Given I log in as "student1"
    When I click on "Dashboard" "link" in the "Navigation" "block"
    Then I should see "You have Choices that need attention"
    And I click on ".collapsibleregioncaption" "css_element"
    And I should see "Not answered yet"
    And I log out

  Scenario: View my home as a student after answering the randchoice
    Given I log in as "student1"
    And I follow "Course 1"
    And I choose "Option 1" from "Test randchoice name" randchoice activity
    And I should see "Your selection: Option 1"
    And I should see "Your randchoice has been saved"
    And "Save my randchoice" "button" should not exist
    When I click on "Dashboard" "link" in the "Navigation" "block"
    Then I should not see "You have Choices that need attention"
    And I log out

  @javascript
  Scenario: View my home as a teacher
    Given I log in as "student1"
    And I follow "Course 1"
    And I choose "Option 1" from "Test randchoice name" randchoice activity
    And I should see "Your selection: Option 1"
    And I should see "Your randchoice has been saved"
    And "Save my randchoice" "button" should not exist
    And I log out
    When I log in as "teacher1"
    And I click on "Dashboard" "link" in the "Navigation" "block"
    Then I should see "You have Choices that need attention"
    And I click on ".collapsibleregioncaption" "css_element"
    And I should see "View 1 responses"
    And I log out

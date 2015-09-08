@mod @mod_randchoice
Feature: Allow randchoice preview
  In order to allow students to preview options before a randchoice activity is opened for submission
  As a teacher
  I need to enable the randchoice preview option

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

  @javascript
  Scenario: Enable the randchoice preview option and view the activity as a student before the opening time
    Given I add a "Choice" to section "1" and I fill the form with:
      | Choice name | Choice name |
      | Description | Choice Description |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
      | Restrict answering to this time period | 1 |
      | timeopen[day] | 31 |
      | timeopen[month] | December |
      | timeopen[year] | 2037 |
      | Show preview | 1 |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Choice name"
    Then I should see "This is just a preview of the available options for this activity"
    And the "randchoice_1" "radio" should be disabled
    And the "randchoice_2" "radio" should be disabled
    And "Save my randchoice" "button" should not exist

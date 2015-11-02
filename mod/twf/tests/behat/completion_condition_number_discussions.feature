@mod @mod_twf
Feature: Set a certain number of discussions as a completion condition for a twf
  In order to ensure students are participating on twfs
  As a teacher
  I need to set a minimum number of discussions to mark the twf activity as completed

  @javascript
  Scenario: Set X number of discussions as a condition
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following config values are set as admin:
      | enablecompletion   | 1 |
      | enableavailability | 1 |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I click on "Edit settings" "link" in the "Administration" "block"
    And I set the following fields to these values:
      | Enable completion tracking | Yes |
    And I press "Save and display"
    When I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test twf name |
      | Description | Test twf description |
      | Completion tracking | Show activity as complete when conditions are met |
      | completiondiscussionsenabled | 1 |
      | completiondiscussions | 2 |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    Then I hover "//li[contains(concat(' ', normalize-space(@class), ' '), ' modtype_twf ')]/descendant::img[@alt='Not completed: Test twf name']" "xpath_element"
    And I add a new discussion to "Test twf name" twf with:
      | Subject | Post 1 subject |
      | Message | Body 1 content |
    And I add a new discussion to "Test twf name" twf with:
      | Subject | Post 2 subject |
      | Message | Body 2 content |
    And I follow "Course 1"
    And I hover "//li[contains(concat(' ', normalize-space(@class), ' '), ' modtype_twf ')]/descendant::img[contains(@alt, 'Completed: Test twf name')]" "xpath_element"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And "Student 1" user has completed "Test twf name" activity

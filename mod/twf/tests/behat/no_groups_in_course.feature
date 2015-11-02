@mod @mod_twf
Feature: Posting to twfs in a course with no groups behaves correctly

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
    And the following "activities" exist:
      | activity   | name                   | intro                         | course | idnumber     | groupmode |
      | twf      | Standard twf         | Standard twf description    | C1     | nogroups     | 0         |
      | twf      | Visible twf          | Visible twf description     | C1     | visgroups    | 2         |
      | twf      | Separate twf         | Separate twf description    | C1     | sepgroups    | 1         |

  Scenario: Teachers can post in standard twf
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Standard twf"
    When I click on "Add a new discussion topic" "button"
    Then I should not see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Teacher -> All participants |
      | Message | Teacher -> All participants |
    And I press "Post to twf"
    And I wait to be redirected
    And I should see "Teacher -> All participants"

  Scenario: Teachers can post in twf with separate groups
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Separate twf"
    When I click on "Add a new discussion topic" "button"
    Then I should not see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Teacher -> All participants |
      | Message | Teacher -> All participants |
    And I press "Post to twf"
    And I wait to be redirected
    And I should see "Teacher -> All participants"

  Scenario: Teachers can post in twf with visible groups
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Visible twf"
    When I click on "Add a new discussion topic" "button"
    Then I should not see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Teacher -> All participants |
      | Message | Teacher -> All participants |
    And I press "Post to twf"
    And I wait to be redirected
    And I should see "Teacher -> All participants"

  Scenario: Students can post in standard twf
    Given I log in as "student1"
    And I follow "Course 1"
    And I follow "Standard twf"
    When I click on "Add a new discussion topic" "button"
    Then I should not see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Student -> All participants |
      | Message | Student -> All participants |
    And I press "Post to twf"
    And I wait to be redirected
    And I should see "Student -> All participants"

  Scenario: Students cannot post in twf with separate groups
    Given I log in as "student1"
    And I follow "Course 1"
    When I follow "Separate twf"
    Then I should see "You do not have permission to add a new discussion topic for all participants."
    And I should not see "Add a new discussion topic"

  Scenario: Teachers can post in twf with visible groups
    Given I log in as "student1"
    And I follow "Course 1"
    When I follow "Visible twf"
    Then I should see "You do not have permission to add a new discussion topic for all participants."
    And I should not see "Add a new discussion topic"

@mod @mod_teamworkforum
Feature: Single simple teamworkforum discussion type
  In order to restrict the discussion topic to one
  As a teacher
  I need to create a teamworkforum with a single simple discussion

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
      | activity   | name                         | intro                               | type    | course | idnumber     |
      | teamworkforum      | Single discussion teamworkforum name | Single discussion teamworkforum description | single  | C1     | teamworkforum        |

  Scenario: Teacher can start the single simple discussion
    Given I log in as "teacher1"
    And I follow "Course 1"
    When I follow "Single discussion teamworkforum name"
    Then I should see "Single discussion teamworkforum description" in the "div.firstpost.starter" "css_element"
    And I should not see "Add a new discussion topic"

  Scenario: Student can not add more discussions
    And I log in as "student1"
    And I follow "Course 1"
    When I reply "Single discussion teamworkforum name" post from "Single discussion teamworkforum name" teamworkforum with:
      | Subject | Reply to single discussion subject |
      | Message | Reply to single discussion message |
    Then I should not see "Add a new discussion topic"
    And I should see "Reply" in the "div.firstpost.starter" "css_element"
    And I should see "Reply to single discussion message"

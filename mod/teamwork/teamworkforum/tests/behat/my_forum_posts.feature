@mod @mod_teamworkforum
Feature: A user can view their posts and discussions
  In order to ensure a user can view their posts and discussions
  As a student
  I need to view my post and discussions

  Scenario: View the student's posts and discussions
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity   | name                   | intro       | course | idnumber     | groupmode |
      | teamworkforum      | Test teamworkforum name        | Test teamworkforum  | C1     | teamworkforum        | 0         |
    And I log in as "student1"
    And I follow "Course 1"
    And I add a new discussion to "Test teamworkforum name" teamworkforum with:
      | Subject | Forum discussion 1 |
      | Message | How awesome is this teamworkforum discussion? |
    And I reply "Forum discussion 1" post from "Test teamworkforum name" teamworkforum with:
      | Message | Actually, I've seen better. |
    When I follow "Profile" in the user menu
    And I follow "Forum posts"
    Then I should see "How awesome is this teamworkforum discussion?"
    And I should see "Actually, I've seen better."
    And I follow "Profile" in the user menu
    And I follow "Forum discussions"
    And I should see "How awesome is this teamworkforum discussion?"
    And I should not see "Actually, I've seen better."

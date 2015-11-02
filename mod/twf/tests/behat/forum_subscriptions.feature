@mod @mod_twf
Feature: A user can control their own subscription preferences for a twf
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my twf subscriptions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student   | One      | student.one@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on

  Scenario: A disallowed subscription twf cannot be subscribed to
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test twf name |
      | Forum type        | Standard twf for general use |
      | Description       | Test twf description |
      | Subscription mode | Subscription disabled |
    And I add a new discussion to "Test twf name" twf with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test twf name"
    Then I should not see "Subscribe to this twf"
    And I should not see "Unsubscribe from this twf"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist in the "Test post subject" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist in the "Test post subject" "table_row"

  Scenario: A forced subscription twf cannot be subscribed to
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test twf name |
      | Forum type        | Standard twf for general use |
      | Description       | Test twf description |
      | Subscription mode | Forced subscription |
    And I add a new discussion to "Test twf name" twf with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test twf name"
    Then I should not see "Subscribe to this twf"
    And I should not see "Unsubscribe from this twf"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist in the "Test post subject" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist in the "Test post subject" "table_row"

  Scenario: An optional twf can be subscribed to
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test twf name |
      | Forum type        | Standard twf for general use |
      | Description       | Test twf description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test twf name" twf with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test twf name"
    Then I should see "Subscribe to this twf"
    And I should not see "Unsubscribe from this twf"
    And I follow "Subscribe to this twf"
    And I follow "Continue"
    And I should see "Unsubscribe from this twf"
    And I should not see "Subscribe to this twf"

  Scenario: An Automatic twf can be unsubscribed from
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test twf name |
      | Forum type        | Standard twf for general use |
      | Description       | Test twf description |
      | Subscription mode | Auto subscription |
    And I add a new discussion to "Test twf name" twf with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test twf name"
    Then I should see "Unsubscribe from this twf"
    And I should not see "Subscribe to this twf"
    And I follow "Unsubscribe from this twf"
    And I follow "Continue"
    And I should see "Subscribe to this twf"
    And I should not see "Unsubscribe from this twf"

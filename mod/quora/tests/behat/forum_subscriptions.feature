@mod @mod_quora
Feature: A user can control their own subscription preferences for a quora
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my quora subscriptions

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

  Scenario: A disallowed subscription quora cannot be subscribed to
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test quora name |
      | Forum type        | Standard quora for general use |
      | Description       | Test quora description |
      | Subscription mode | Subscription disabled |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test quora name"
    Then I should not see "Subscribe to this quora"
    And I should not see "Unsubscribe from this quora"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist in the "Test post subject" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist in the "Test post subject" "table_row"

  Scenario: A forced subscription quora cannot be subscribed to
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test quora name |
      | Forum type        | Standard quora for general use |
      | Description       | Test quora description |
      | Subscription mode | Forced subscription |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test quora name"
    Then I should not see "Subscribe to this quora"
    And I should not see "Unsubscribe from this quora"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist in the "Test post subject" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist in the "Test post subject" "table_row"

  Scenario: An optional quora can be subscribed to
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test quora name |
      | Forum type        | Standard quora for general use |
      | Description       | Test quora description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test quora name"
    Then I should see "Subscribe to this quora"
    And I should not see "Unsubscribe from this quora"
    And I follow "Subscribe to this quora"
    And I follow "Continue"
    And I should see "Unsubscribe from this quora"
    And I should not see "Subscribe to this quora"

  Scenario: An Automatic quora can be unsubscribed from
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name        | Test quora name |
      | Forum type        | Standard quora for general use |
      | Description       | Test quora description |
      | Subscription mode | Auto subscription |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test quora name"
    Then I should see "Unsubscribe from this quora"
    And I should not see "Subscribe to this quora"
    And I follow "Unsubscribe from this quora"
    And I follow "Continue"
    And I should see "Subscribe to this quora"
    And I should not see "Unsubscribe from this quora"

@mod @mod_quora
Feature: A teacher can set one of 3 possible options for tracking read quora posts
  In order to ease the quora posts follow up
  As a user
  I need to distinct the unread posts from the read ones

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email | trackquoras |
      | student1 | Student | 1 | student1@example.com | 1 |
      | student2 | Student | 2 | student2@example.com | 0 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on

  Scenario: Tracking quora posts off
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
      | Read tracking | Off |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test quora name"
    And I should not see "Track unread posts"

  Scenario: Tracking quora posts optional with user tracking on
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
      | Read tracking | Optional |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "Test quora name"
    And I follow "Don't track unread posts"
    And I wait to be redirected
    And I follow "Course 1"
    And I should not see "1 unread post"
    And I follow "Test quora name"
    And I follow "Track unread posts"
    And I wait to be redirected
    And I click on "1" "link" in the "Admin User" "table_row"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking quora posts optional with user tracking off
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
      | Read tracking | Optional |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test quora name"
    And I should not see "Track unread posts"

  Scenario: Tracking quora posts forced with user tracking on
    Given the following config values are set as admin:
      | quora_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
      | Read tracking | Force |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking quora posts forced with user tracking off
    Given the following config values are set as admin:
      | quora_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
      | Read tracking | Force |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking quora posts forced (with force disabled) with user tracking on
    Given the following config values are set as admin:
      | quora_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
      | Read tracking | Force |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And the following config values are set as admin:
      | quora_allowforcedreadtracking | 0 |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "Test quora name"
    And I follow "Don't track unread posts"
    And I wait to be redirected
    And I follow "Course 1"
    And I should not see "1 unread post"
    And I follow "Test quora name"
    And I follow "Track unread posts"
    And I wait to be redirected
    And I click on "1" "link" in the "Admin User" "table_row"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking quora posts forced (with force disabled) with user tracking off
    Given the following config values are set as admin:
      | quora_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
      | Read tracking | Force |
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Test post subject |
      | Message | Test post message |
    And the following config values are set as admin:
      | quora_allowforcedreadtracking | 0 |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test quora name"
    And I should not see "Track unread posts"

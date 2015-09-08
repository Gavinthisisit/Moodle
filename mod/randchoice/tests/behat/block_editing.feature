@mod @mod_randchoice
Feature: Add randchoice activity
  In order to customise randchoice page
  As a teacher or admin
  I need to add remove block from the randchoice page

  # This tests that the hacky block editing is not borked by legacy forms in randchoice activity.
  @javascript
  Scenario: Add a randchoice activity as admin and check blog menu block should contain link.
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Choice" to section "1" and I fill the form with:
      | Choice name | Choice name 1 |
      | Description | Choice Description 1 |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I follow "Choice name 1"
    And I add the "Blog menu" block
    And I should see "View all entries about this Choice"
    When I configure the "Blog menu" block
    And I press "Save changes"
    Then I should see "View all entries about this Choice"
    And I open the "Blog menu" blocks action menu
    And I click on "Delete" "link" in the "Blog menu" "block"
    And I press "Yes"
    And I should not see "View all entries about this Choice"
    And I should see "Choice Description 1"

  @javascript
  Scenario: Add a randchoice activity as teacher and check blog menu block contain randchoice link.
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
    And I add a "Choice" to section "1" and I fill the form with:
      | Choice name | Choice name 1 |
      | Description | Choice Description 1 |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I follow "Choice name 1"
    And I add the "Blog menu" block
    And I should see "View all entries about this Choice"
    When I configure the "Blog menu" block
    And I press "Save changes"
    Then I should see "View all entries about this Choice"
    And I open the "Blog menu" blocks action menu
    And I click on "Delete" "link" in the "Blog menu" "block"
    And I press "Yes"
    And I should not see "View all entries about this Choice"
    And I should see "Choice Description 1"

  @javascript
  Scenario: Add a randchoice activity as teacher (with dual role) and check blog menu block contain randchoice link.
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | teacher1 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Choice" to section "1" and I fill the form with:
      | Choice name | Choice name 1 |
      | Description | Choice Description 1 |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I follow "Choice name 1"
    And I add the "Blog menu" block
    And I should see "View all entries about this Choice"
    When I configure the "Blog menu" block
    And I press "Save changes"
    Then I should see "View all entries about this Choice"
    And I open the "Blog menu" blocks action menu
    And I click on "Delete" "link" in the "Blog menu" "block"
    And I press "Yes"
    And I should not see "View all entries about this Choice"
    And I should see "Choice Description 1"

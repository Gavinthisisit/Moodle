@mod @mod_quora
Feature: Students can edit or delete their quora posts within a set time limit
  In order to refine quora posts
  As a user
  I need to edit or delete my quora posts within a certain period of time after posting

  Background:
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
      | activity   | name                   | intro                   | course  | idnumber  |
      | quora      | Test quora name        | Test quora description  | C1      | quora     |
    And I log in as "student1"
    And I follow "Course 1"
    And I add a new discussion to "Test quora name" quora with:
      | Subject | Forum post subject |
      | Message | This is the body |

  Scenario: Edit quora post
    Given I follow "Forum post subject"
    And I follow "Edit"
    When I set the following fields to these values:
      | Subject | Edited post subject |
      | Message | Edited post body |
    And I press "Save changes"
    And I wait to be redirected
    Then I should see "Edited post subject"
    And I should see "Edited post body"

  Scenario: Delete quora post
    Given I follow "Forum post subject"
    When I follow "Delete"
    And I press "Continue"
    Then I should not see "Forum post subject"

  @javascript
  Scenario: Time limit expires
    Given I log out
    And I log in as "admin"
    And I expand "Site administration" node
    And I expand "Security" node
    And I follow "Site policies"
    And I set the field "Maximum time to edit posts" to "1 minutes"
    And I press "Save changes"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test quora name |
      | Forum type | Standard quora for general use |
      | Description | Test quora description |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    When I wait "61" seconds
    And I follow "Forum post subject"
    Then I should not see "Edit" in the "region-main" "region"
    And I should not see "Delete" in the "region-main" "region"

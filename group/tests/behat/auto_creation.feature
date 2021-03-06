@core @core_group
Feature: Automatic creation of groups
  In order to quickly create groups
  As a teacher
  I need to create groups automatically and allocate them in groupings if necessary

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student0 | Student | 0 | student0@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
      | student3 | Student | 3 | student3@example.com |
      | student4 | Student | 4 | student4@example.com |
      | student5 | Student | 5 | student5@example.com |
      | student6 | Student | 6 | student6@example.com |
      | student7 | Student | 7 | student7@example.com |
      | student8 | Student | 8 | student8@example.com |
      | student9 | Student | 9 | student9@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student0 | C1 | student |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |
      | student4 | C1 | student |
      | student5 | C1 | student |
      | student6 | C1 | student |
      | student7 | C1 | student |
      | student8 | C1 | student |
      | student9 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I expand "Users" node
    And I follow "Groups"
    When I press "Auto-create groups"
    And I expand all fieldsets

  @javascript
  Scenario: Split automatically the course users in groups and add the groups to a new grouping
    Given I set the following fields to these values:
      | Auto create based on | Number of groups |
      | Group/member count | 2 |
      | Grouping of auto-created groups | New grouping |
      | Grouping name | Grouping name |
    And I press "Preview"
    Then I should see "Group members"
    And I should see "User count"
    And I should see "Group A"
    And I should see "Group B"
    And I press "Submit"
    And the "groups" select box should contain "Group A (5)"
    And the "groups" select box should contain "Group B (5)"
    And I follow "Groupings"
    And I should see "Grouping name"
    And I click on "Show groups in grouping" "link" in the "Grouping name" "table_row"
    And the "removeselect" select box should contain "Group A"
    And the "removeselect" select box should contain "Group B"

  @javascript
  Scenario: Split automatically the course users in groups based on group member count
    Given I set the following fields to these values:
      | Auto create based on | Members per group |
      | Group/member count | 4 |
      | Grouping of auto-created groups | New grouping |
      | Grouping name | Grouping name |
    And I press "Preview"
    Then I should see "Group members"
    And I should see "User count"
    And I should see "Group A" in the ".generaltable" "css_element"
    And I should see "Group B" in the ".generaltable" "css_element"
    And I should see "Group C" in the ".generaltable" "css_element"
    And I should see "4" in the "Group A" "table_row"
    And I should see "4" in the "Group B" "table_row"
    And I should see "2" in the "Group C" "table_row"
    And I set the field "Prevent last small group" to "1"
    And I press "Preview"
    And I should see "Group A" in the ".generaltable" "css_element"
    And I should see "Group B" in the ".generaltable" "css_element"
    And I should see "5" in the "Group A" "table_row"
    And I should see "5" in the "Group B" "table_row"

  @javascript
  Scenario: Split automatically the course users in groups that are not in groups
    Given I press "Cancel"
    And I press "Create group"
    And I set the following fields to these values:
      | Group name | Group 1 |
    And I press "Save changes"
    And I press "Create group"
    And I set the following fields to these values:
      | Group name | Group 2 |
    And I press "Save changes"
    When I add "Student 0" user to "Group 1" group members
    And I add "Student 1" user to "Group 1" group members
    And I add "Student 2" user to "Group 2" group members
    And I add "Student 3" user to "Group 2" group members
    And I press "Auto-create groups"
    And I expand all fieldsets
    And I set the field "Auto create based on" to "Number of groups"
    And I set the field "Group/member count" to "2"
    And I set the field "Grouping of auto-created groups" to "No grouping"
    And I set the field "Ignore users in groups" to "1"
    And I press "Submit"
    And the "groups" select box should contain "Group A (3)"
    And the "groups" select box should contain "Group B (3)"

  @javascript
  Scenario: Split users into groups based on existing groups or groupings
    Given I set the following fields to these values:
      | Naming scheme | Group @ |
      | Auto create based on | Number of groups |
      | Group/member count | 2 |
      | Grouping of auto-created groups | No grouping |
    And I press "Submit"
    And I press "Auto-create groups"
    And I set the following fields to these values:
      | Naming scheme | Test @ |
      | Auto create based on | Number of groups |
      | Group/member count | 2 |
      | groupid | Group A |
      | Grouping of auto-created groups | New grouping |
      | Grouping name | Sub Grouping |
    And I press "Submit"
    And the "groups" select box should contain "Test A (3)"
    And the "groups" select box should contain "Test B (2)"
    And I press "Auto-create groups"
    And I set the following fields to these values:
      | Naming scheme | Test # |
      | Auto create based on | Number of groups |
      | Group/member count | 2 |
      | Select members from grouping | Sub Grouping |
      | Grouping of auto-created groups | No grouping |
    And I press "Submit"
    And the "groups" select box should contain "Test 1 (3)"
    And the "groups" select box should contain "Test 2 (2)"
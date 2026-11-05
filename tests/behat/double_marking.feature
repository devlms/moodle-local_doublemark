@local @local_doublemark
Feature: Double marking an assignment
  In order to mark fairly
  As a module lead
  I need two markers to mark each submission on their own, then agree and release one mark

  Background:
    Given the following "courses" exist:
      | fullname     | shortname |
      | Ethics 101   | ETH101    |
    And the following "users" exist:
      | username | firstname | lastname |
      | lead     | Lea       | Lead     |
      | mia      | Mia       | One      |
      | max      | Max       | Two      |
      | sam      | Sam       | Student  |
    And the following "course enrolments" exist:
      | user | course | role           |
      | lead | ETH101 | editingteacher |
      | mia  | ETH101 | teacher        |
      | max  | ETH101 | teacher        |
      | sam  | ETH101 | student        |
    And the following "activities" exist:
      | activity | course | name           | grade | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled |
      | assign   | ETH101 | Final essay    | 100   | 1                                   | 1                               |

  @javascript @accessibility
  Scenario: The lead turns double marking on and shares students out
    Given I am on the "Final essay" "assign activity" page logged in as "lead"
    When I navigate to "Double marking" in current page administration
    Then I should see "Two markers mark each submission on their own"
    And the page should meet accessibility standards
    And I set the field "Double marking" to "1"
    And I set the field "Tolerance" to "5"
    And I press "Save changes"
    And I should see "Share out students without markers"
    And the page should meet accessibility standards
    And I set the field "Lea Lead" to "0"
    And I press "Share out"
    And I should see "Students given two markers: 1."
    And I click on "Overview" "link" in the "#region-main" "css_element"
    And I should see "Mia One" in the "Sam Student" "table_row"
    And I should see "Max Two" in the "Sam Student" "table_row"
    And I should see "Being marked" in the "Sam Student" "table_row"
    And the page should meet accessibility standards

  @javascript @accessibility
  Scenario: Two markers mark on their own, then the lead releases the agreed mark
    Given I am on the "Final essay" "assign activity" page logged in as "lead"
    And I navigate to "Double marking" in current page administration
    And I set the field "Double marking" to "1"
    And I set the field "Tolerance" to "5"
    And I press "Save changes"
    And I set the field "Lea Lead" to "0"
    And I press "Share out"
    And I log out
    When I am on the "Final essay" "assign activity" page logged in as "mia"
    And I navigate to "Double marking" in current page administration
    And I should see "1 left to mark"
    And the page should meet accessibility standards
    And I click on "Mark" "link" in the "Sam Student" "table_row"
    And I should see "stays hidden until you have both marked"
    And the page should meet accessibility standards
    And I set the field "Your mark (out of 100)" to "60"
    And I set the field "Your comment" to "Clear, but thin on sources."
    And I press "Save my mark"
    And I should see "Your mark for Sam Student is saved."
    And I should see "Hidden until both are in" in the "Sam Student" "table_row"
    And I log out
    And I am on the "Final essay" "assign activity" page logged in as "max"
    And I navigate to "Double marking" in current page administration
    And I click on "Mark" "link" in the "Sam Student" "table_row"
    And I should not see "Clear, but thin on sources."
    And I set the field "Your mark (out of 100)" to "62"
    And I press "Save my mark"
    And I should see "60" in the "Sam Student" "table_row"
    And I should see "Agreed" in the "Sam Student" "table_row"
    And I log out
    And I am on the "Final essay" "assign activity" page logged in as "lead"
    And I navigate to "Double marking" in current page administration
    And I should see "61" in the "Sam Student" "table_row"
    And the page should meet accessibility standards
    And I click on "Review" "link" in the "Sam Student" "table_row"
    And I should see "Clear, but thin on sources."
    And the page should meet accessibility standards
    And I set the field "Feedback for the student" to "Good structure. Use more sources."
    And I press "Save agreed mark"
    And I should see "Decided by lead" in the "Sam Student" "table_row"
    And I press "Release agreed marks (1)"
    And I should see "Agreed marks released to the assignment: 1."
    And I should see "Released" in the "Sam Student" "table_row"
    And I am on the "Final essay" "assign activity" page
    And I navigate to "Submissions" in current page administration
    And I should see "61.00" in the "Sam Student" "table_row"

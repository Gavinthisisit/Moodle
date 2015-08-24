<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for teamwork api class defined in mod/teamwork/locallib.php
 *
 * @package    mod_teamwork
 * @category   phpunit
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/teamwork/locallib.php'); // Include the code to test
require_once(__DIR__ . '/fixtures/testable.php');


/**
 * Test cases for the internal teamwork api
 */
class mod_teamwork_internal_api_testcase extends advanced_testcase {

    /** teamwork instance emulation */
    protected $teamwork;

    /** setup testing environment */
    protected function setUp() {
        parent::setUp();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $teamwork = $this->getDataGenerator()->create_module('teamwork', array('course' => $course));
        $cm = get_coursemodule_from_instance('teamwork', $teamwork->id, $course->id, false, MUST_EXIST);
        $this->teamwork = new testable_teamwork($teamwork, $cm, $course);
    }

    protected function tearDown() {
        $this->teamwork = null;
        parent::tearDown();
    }

    public function test_aggregate_submission_grades_process_notgraded() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => null);
        //$DB->expectNever('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_single() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => 10.12345);
        $expected = 10.12345;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_null_doesnt_influence() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => 45.54321);
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 1, 'grade' => null);
        $expected = 45.54321;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_weighted_single() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 12, 'submissiongrade' => null, 'weight' => 4, 'grade' => 14.00012);
        $expected = 14.00012;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_mean() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 56.12000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 12.59000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 0.00000);
        $expected = 19.67750;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_mean_changed() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 56.12000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 12.59000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 12.57750, 'weight' => 1, 'grade' => 0.00000);
        $expected = 19.67750;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_mean_nochange() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 56.12000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 12.59000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => 19.67750, 'weight' => 1, 'grade' => 0.00000);
        //$DB->expectNever('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_rounding() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 4.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 2.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 1.00000);
        $expected = 2.33333;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_submission_grades_process_weighted_mean() {
        $this->resetAfterTest(true);

        // fixture set-up
        $batch = array();   // batch of a submission's assessments
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 3, 'grade' => 12.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 2, 'grade' => 30.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 1, 'grade' => 10.00000);
        $batch[] = (object)array('submissionid' => 45, 'submissiongrade' => null, 'weight' => 0, 'grade' => 1000.00000);
        $expected = 17.66667;
        //$DB->expectOnce('update_record');
        // exercise SUT
        $this->teamwork->aggregate_submission_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_nograding() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>2, 'gradinggrade'=>null, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        // expectation
        //$DB->expectNever('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_single_grade_new() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>3, 'gradinggrade'=>82.87670, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        // expectation
        $now = time();
        $expected = new stdclass();
        $expected->teamworkid = $this->teamwork->id;
        $expected->userid = 3;
        $expected->gradinggrade = 82.87670;
        $expected->timegraded = $now;
        //$DB->expectOnce('insert_record', array('teamwork_aggregations', $expected));
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch, $now);
    }

    public function test_aggregate_grading_grades_process_single_grade_update() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>3, 'gradinggrade'=>90.00000, 'gradinggradeover'=>null, 'aggregationid'=>1, 'aggregatedgrade'=>82.87670);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_single_grade_uptodate() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>3, 'gradinggrade'=>90.00000, 'gradinggradeover'=>null, 'aggregationid'=>1, 'aggregatedgrade'=>90.00000);
        // expectation
        //$DB->expectNever('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_single_grade_overridden() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>4, 'gradinggrade'=>91.56700, 'gradinggradeover'=>82.32105, 'aggregationid'=>2, 'aggregatedgrade'=>91.56700);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_new() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>99.45670, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>87.34311, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>51.12000, 'gradinggradeover'=>null, 'aggregationid'=>null, 'aggregatedgrade'=>null);
        // expectation
        $now = time();
        $expected = new stdclass();
        $expected->teamworkid = $this->teamwork->id;
        $expected->userid = 5;
        $expected->gradinggrade = 79.3066;
        $expected->timegraded = $now;
        //$DB->expectOnce('insert_record', array('teamwork_aggregations', $expected));
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch, $now);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_update() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>56.23400, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>79.30660);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>87.34311, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>79.30660);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>51.12000, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>79.30660);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_overriden() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>56.23400, 'gradinggradeover'=>99.45670, 'aggregationid'=>2, 'aggregatedgrade'=>64.89904);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>87.34311, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>64.89904);
        $batch[] = (object)array('reviewerid'=>5, 'gradinggrade'=>51.12000, 'gradinggradeover'=>null, 'aggregationid'=>2, 'aggregatedgrade'=>64.89904);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_one_missing() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>50.00000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>null, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>52.20000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_aggregate_grading_grades_process_multiple_grades_missing_overridden() {
        $this->resetAfterTest(true);
        // fixture set-up
        $batch = array();
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>50.00000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>null, 'gradinggradeover'=>69.00000, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        $batch[] = (object)array('reviewerid'=>6, 'gradinggrade'=>52.20000, 'gradinggradeover'=>null, 'aggregationid'=>3, 'aggregatedgrade'=>100.00000);
        // expectation
        //$DB->expectOnce('update_record');
        // excersise SUT
        $this->teamwork->aggregate_grading_grades_process($batch);
    }

    public function test_percent_to_value() {
        $this->resetAfterTest(true);
        // fixture setup
        $total = 185;
        $percent = 56.6543;
        // exercise SUT
        $part = teamwork::percent_to_value($percent, $total);
        // verify
        $this->assertEquals($part, $total * $percent / 100);
    }

    public function test_percent_to_value_negative() {
        $this->resetAfterTest(true);
        // fixture setup
        $total = 185;
        $percent = -7.098;
        // set expectation
        $this->setExpectedException('coding_exception');
        // exercise SUT
        $part = teamwork::percent_to_value($percent, $total);
    }

    public function test_percent_to_value_over_hundred() {
        $this->resetAfterTest(true);
        // fixture setup
        $total = 185;
        $percent = 121.08;
        // set expectation
        $this->setExpectedException('coding_exception');
        // exercise SUT
        $part = teamwork::percent_to_value($percent, $total);
    }

    public function test_lcm() {
        $this->resetAfterTest(true);
        // fixture setup + exercise SUT + verify in one step
        $this->assertEquals(teamwork::lcm(1,4), 4);
        $this->assertEquals(teamwork::lcm(2,4), 4);
        $this->assertEquals(teamwork::lcm(4,2), 4);
        $this->assertEquals(teamwork::lcm(2,3), 6);
        $this->assertEquals(teamwork::lcm(6,4), 12);
    }

    public function test_lcm_array() {
        $this->resetAfterTest(true);
        // fixture setup
        $numbers = array(5,3,15);
        // excersise SUT
        $lcm = array_reduce($numbers, 'teamwork::lcm', 1);
        // verify
        $this->assertEquals($lcm, 15);
    }

    public function test_prepare_example_assessment() {
        $this->resetAfterTest(true);
        // fixture setup
        $fakerawrecord = (object)array(
            'id'                => 42,
            'submissionid'      => 56,
            'weight'            => 0,
            'timecreated'       => time() - 10,
            'timemodified'      => time() - 5,
            'grade'             => null,
            'gradinggrade'      => null,
            'gradinggradeover'  => null,
            'feedbackauthor'    => null,
            'feedbackauthorformat' => 0,
            'feedbackauthorattachment' => 0,
        );
        // excersise SUT
        $a = $this->teamwork->prepare_example_assessment($fakerawrecord);
        // verify
        $this->assertTrue($a instanceof teamwork_example_assessment);
        $this->assertTrue($a->url instanceof moodle_url);

        // modify setup
        $fakerawrecord->weight = 1;
        $this->setExpectedException('coding_exception');
        // excersise SUT
        $a = $this->teamwork->prepare_example_assessment($fakerawrecord);
    }

    public function test_prepare_example_reference_assessment() {
        global $USER;
        $this->resetAfterTest(true);
        // fixture setup
        $fakerawrecord = (object)array(
            'id'                => 38,
            'submissionid'      => 56,
            'weight'            => 1,
            'timecreated'       => time() - 100,
            'timemodified'      => time() - 50,
            'grade'             => 0.75000,
            'gradinggrade'      => 1.00000,
            'gradinggradeover'  => null,
            'feedbackauthor'    => null,
            'feedbackauthorformat' => 0,
            'feedbackauthorattachment' => 0,
        );
        // excersise SUT
        $a = $this->teamwork->prepare_example_reference_assessment($fakerawrecord);
        // verify
        $this->assertTrue($a instanceof teamwork_example_reference_assessment);

        // modify setup
        $fakerawrecord->weight = 0;
        $this->setExpectedException('coding_exception');
        // excersise SUT
        $a = $this->teamwork->prepare_example_reference_assessment($fakerawrecord);
    }

    /**
     * Tests user restrictions, as they affect lists of users returned by
     * core API functions.
     *
     * This includes the groupingid option (when group mode is in use), and
     * standard activity restrictions using the availability API.
     */
    public function test_user_restrictions() {
        global $DB, $CFG;

        $this->resetAfterTest();

        // Use existing sample course from setUp.
        $courseid = $this->teamwork->course->id;

        // Make a test grouping and two groups.
        $generator = $this->getDataGenerator();
        $grouping = $generator->create_grouping(array('courseid' => $courseid));
        $group1 = $generator->create_group(array('courseid' => $courseid));
        groups_assign_grouping($grouping->id, $group1->id);
        $group2 = $generator->create_group(array('courseid' => $courseid));
        groups_assign_grouping($grouping->id, $group2->id);

        // Group 3 is not in the grouping.
        $group3 = $generator->create_group(array('courseid' => $courseid));

        // Enrol some students.
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();
        $generator->enrol_user($student1->id, $courseid, $roleids['student']);
        $generator->enrol_user($student2->id, $courseid, $roleids['student']);
        $generator->enrol_user($student3->id, $courseid, $roleids['student']);

        // Place students in groups (except student 3).
        groups_add_member($group1, $student1);
        groups_add_member($group2, $student2);
        groups_add_member($group3, $student3);

        // The existing teamwork doesn't have any restrictions, so user lists
        // should include all three users.
        $allusers = get_enrolled_users(context_course::instance($courseid));
        $result = $this->teamwork->get_grouped($allusers);
        $this->assertCount(4, $result);
        $users = array_keys($result[0]);
        sort($users);
        $this->assertEquals(array($student1->id, $student2->id, $student3->id), $users);
        $this->assertEquals(array($student1->id), array_keys($result[$group1->id]));
        $this->assertEquals(array($student2->id), array_keys($result[$group2->id]));
        $this->assertEquals(array($student3->id), array_keys($result[$group3->id]));

        // Test get_users_with_capability_sql (via get_potential_authors).
        $users = $this->teamwork->get_potential_authors(false);
        $this->assertCount(3, $users);
        $users = $this->teamwork->get_potential_authors(false, $group2->id);
        $this->assertEquals(array($student2->id), array_keys($users));

        // Create another test teamwork with grouping set.
        $teamworkitem = $this->getDataGenerator()->create_module('teamwork',
                array('course' => $courseid, 'groupmode' => SEPARATEGROUPS,
                'groupingid' => $grouping->id));
        $cm = get_coursemodule_from_instance('teamwork', $teamworkitem->id,
                $courseid, false, MUST_EXIST);
        $teamworkgrouping = new testable_teamwork($teamworkitem, $cm, $this->teamwork->course);

        // This time the result should only include users and groups in the
        // selected grouping.
        $result = $teamworkgrouping->get_grouped($allusers);
        $this->assertCount(3, $result);
        $users = array_keys($result[0]);
        sort($users);
        $this->assertEquals(array($student1->id, $student2->id), $users);
        $this->assertEquals(array($student1->id), array_keys($result[$group1->id]));
        $this->assertEquals(array($student2->id), array_keys($result[$group2->id]));

        // Test get_users_with_capability_sql (via get_potential_authors).
        $users = $teamworkgrouping->get_potential_authors(false);
        $userids = array_keys($users);
        sort($userids);
        $this->assertEquals(array($student1->id, $student2->id), $userids);
        $users = $teamworkgrouping->get_potential_authors(false, $group2->id);
        $this->assertEquals(array($student2->id), array_keys($users));

        // Enable the availability system and create another test teamwork with
        // availability restriction on grouping.
        $CFG->enableavailability = true;
        $teamworkitem = $this->getDataGenerator()->create_module('teamwork',
                array('course' => $courseid, 'availability' => json_encode(
                    \core_availability\tree::get_root_json(array(
                    \availability_grouping\condition::get_json($grouping->id)),
                    \core_availability\tree::OP_AND, false))));
        $cm = get_coursemodule_from_instance('teamwork', $teamworkitem->id,
                $courseid, false, MUST_EXIST);
        $teamworkrestricted = new testable_teamwork($teamworkitem, $cm, $this->teamwork->course);

        // The get_grouped function isn't intended to apply this restriction,
        // so it should be the same as the base teamwork. (Note: in reality,
        // get_grouped is always run with the parameter being the result of
        // one of the get_potential_xxx functions, so it works.)
        $result = $teamworkrestricted->get_grouped($allusers);
        $this->assertCount(4, $result);
        $this->assertCount(3, $result[0]);

        // The get_users_with_capability_sql-based functions should apply it.
        $users = $teamworkrestricted->get_potential_authors(false);
        $userids = array_keys($users);
        sort($userids);
        $this->assertEquals(array($student1->id, $student2->id), $userids);
        $users = $teamworkrestricted->get_potential_authors(false, $group2->id);
        $this->assertEquals(array($student2->id), array_keys($users));
    }

    /**
     * Test the teamwork reset feature.
     */
    public function test_reset_phase() {
        $this->resetAfterTest(true);

        $this->teamwork->switch_phase(teamwork::PHASE_CLOSED);
        $this->assertEquals(teamwork::PHASE_CLOSED, $this->teamwork->phase);

        $settings = (object)array(
            'reset_teamwork_phase' => 0,
        );
        $status = $this->teamwork->reset_userdata($settings);
        $this->assertEquals(teamwork::PHASE_CLOSED, $this->teamwork->phase);

        $settings = (object)array(
            'reset_teamwork_phase' => 1,
        );
        $status = $this->teamwork->reset_userdata($settings);
        $this->assertEquals(teamwork::PHASE_SETUP, $this->teamwork->phase);
        foreach ($status as $result) {
            $this->assertFalse($result['error']);
        }
    }

    /**
     * Test deleting assessments related data on teamwork reset.
     */
    public function test_reset_userdata_assessments() {
        global $DB;
        $this->resetAfterTest(true);

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student1->id, $this->teamwork->course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $this->teamwork->course->id);

        $teamworkgenerator = $this->getDataGenerator()->get_plugin_generator('mod_teamwork');

        $subid1 = $teamworkgenerator->create_submission($this->teamwork->id, $student1->id);
        $subid2 = $teamworkgenerator->create_submission($this->teamwork->id, $student2->id);

        $asid1 = $teamworkgenerator->create_assessment($subid1, $student2->id);
        $asid2 = $teamworkgenerator->create_assessment($subid2, $student1->id);

        $settings = (object)array(
            'reset_teamwork_assessments' => 1,
        );
        $status = $this->teamwork->reset_userdata($settings);

        foreach ($status as $result) {
            $this->assertFalse($result['error']);
        }

        $this->assertEquals(2, $DB->count_records('teamwork_submissions', array('teamworkid' => $this->teamwork->id)));
        $this->assertEquals(0, $DB->count_records('teamwork_assessments'));
    }

    /**
     * Test deleting submissions related data on teamwork reset.
     */
    public function test_reset_userdata_submissions() {
        global $DB;
        $this->resetAfterTest(true);

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student1->id, $this->teamwork->course->id);
        $this->getDataGenerator()->enrol_user($student2->id, $this->teamwork->course->id);

        $teamworkgenerator = $this->getDataGenerator()->get_plugin_generator('mod_teamwork');

        $subid1 = $teamworkgenerator->create_submission($this->teamwork->id, $student1->id);
        $subid2 = $teamworkgenerator->create_submission($this->teamwork->id, $student2->id);

        $asid1 = $teamworkgenerator->create_assessment($subid1, $student2->id);
        $asid2 = $teamworkgenerator->create_assessment($subid2, $student1->id);

        $settings = (object)array(
            'reset_teamwork_submissions' => 1,
        );
        $status = $this->teamwork->reset_userdata($settings);

        foreach ($status as $result) {
            $this->assertFalse($result['error']);
        }

        $this->assertEquals(0, $DB->count_records('teamwork_submissions', array('teamworkid' => $this->teamwork->id)));
        $this->assertEquals(0, $DB->count_records('teamwork_assessments'));
    }
}

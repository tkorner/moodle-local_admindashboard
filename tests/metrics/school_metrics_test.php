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
 * Tests for school_metrics.
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admincockpit\metrics;

/**
 * Class school_metrics_test
 */
final class school_metrics_test extends \advanced_testcase {
    /**
     * Purges the MUC cache school_metrics now reads through (see
     * classes/metrics/school_metrics.php, Schritt 7d) before every test.
     * resetAfterTest() rolls back the database, including id sequences, so
     * two test methods can end up computing school_metrics for the exact
     * same cohortid/categoryid - without this, the second one would see the
     * first one's cached result instead of its own fixtures.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        \core_cache\cache::make('local_admincockpit', 'dashboarddata')->purge();
    }

    /**
     * membercount includes every member regardless of when they were added;
     * newmembers only counts those added within the configured time range.
     *
     * @covers \local_admincockpit\metrics\school_metrics::get_metrics
     * @return void
     */
    public function test_member_counts_and_new_members(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cohort = $this->getDataGenerator()->create_cohort();
        $recentmember1 = $this->getDataGenerator()->create_user();
        $recentmember2 = $this->getDataGenerator()->create_user();
        $oldmember = $this->getDataGenerator()->create_user();

        cohort_add_member($cohort->id, $recentmember1->id);
        cohort_add_member($cohort->id, $recentmember2->id);
        cohort_add_member($cohort->id, $oldmember->id);
        $DB->set_field(
            'cohort_members',
            'timeadded',
            time() - (200 * DAYSECS),
            ['cohortid' => $cohort->id, 'userid' => $oldmember->id]
        );

        $category = $this->getDataGenerator()->create_category();

        $result = school_metrics::get_metrics($cohort->id, $category->id, 90);

        $this->assertSame(3, $result->membercount);
        $this->assertSame(2, $result->newmembers);
    }

    /**
     * Only members whose user account has lastaccess within the last 4
     * weeks count as active.
     *
     * @covers \local_admincockpit\metrics\school_metrics::get_metrics
     * @return void
     */
    public function test_active_members_counts_lastaccess_within_4_weeks(): void {
        $this->resetAfterTest(true);

        $cohort = $this->getDataGenerator()->create_cohort();
        $activemember = $this->getDataGenerator()->create_user(['lastaccess' => time() - DAYSECS]);
        $staleuser = $this->getDataGenerator()->create_user(['lastaccess' => time() - (5 * WEEKSECS)]);
        $nevermember = $this->getDataGenerator()->create_user(['lastaccess' => 0]);

        cohort_add_member($cohort->id, $activemember->id);
        cohort_add_member($cohort->id, $staleuser->id);
        cohort_add_member($cohort->id, $nevermember->id);

        $category = $this->getDataGenerator()->create_category();

        $result = school_metrics::get_metrics($cohort->id, $category->id, 90);

        $this->assertSame(1, $result->activemembers);
    }

    /**
     * coursecount and newcourses must include courses from subcategories,
     * not just courses directly in the top-level category (SPEC section 8,
     * item 1). This is the test that actually exercises the category.path
     * prefix matching.
     *
     * @covers \local_admincockpit\metrics\school_metrics::get_metrics
     * @return void
     */
    public function test_course_counts_include_subcategory_courses(): void {
        $this->resetAfterTest(true);

        $cohort = $this->getDataGenerator()->create_cohort();
        $topcategory = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $topcategory->id]);

        $this->getDataGenerator()->create_course([
            'category' => $topcategory->id,
            'timecreated' => time() - DAYSECS,
        ]);
        $this->getDataGenerator()->create_course([
            'category' => $subcategory->id,
            'timecreated' => time() - (200 * DAYSECS),
        ]);

        $result = school_metrics::get_metrics($cohort->id, $topcategory->id, 90);

        $this->assertSame(2, $result->coursecount);
        $this->assertSame(1, $result->newcourses);
    }
}

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
 * Tests for school_matcher.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard;

/**
 * Class school_matcher_test
 */
final class school_matcher_test extends \advanced_testcase {
    /**
     * A cohort and a top-level category sharing the same idnumber are a
     * "matched" pair, and neither shows up in the one-sided lists.
     *
     * @covers \local_admindashboard\school_matcher::get_matches
     * @return void
     */
    public function test_full_pair_is_matched(): void {
        $this->resetAfterTest(true);

        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'TBZ', 'name' => 'TBZ Cohort']);
        $category = $this->getDataGenerator()->create_category(['idnumber' => 'TBZ', 'name' => 'TBZ Category']);

        $result = school_matcher::get_matches();

        $this->assertArrayHasKey('TBZ', $result->matched);
        $this->assertSame($cohort->id, $result->matched['TBZ']->cohortid);
        $this->assertSame('TBZ Cohort', $result->matched['TBZ']->cohortname);
        $this->assertSame($category->id, $result->matched['TBZ']->categoryid);
        $this->assertSame('TBZ Category', $result->matched['TBZ']->categoryname);
        $this->assertArrayNotHasKey('TBZ', $result->cohortonly);
        $this->assertArrayNotHasKey('TBZ', $result->categoryonly);
    }

    /**
     * A cohort without a matching top-level category ends up in cohortonly.
     *
     * @covers \local_admindashboard\school_matcher::get_matches
     * @return void
     */
    public function test_cohort_without_category_is_cohort_only(): void {
        $this->resetAfterTest(true);

        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'ORPHANCOHORT']);

        $result = school_matcher::get_matches();

        $this->assertArrayHasKey('ORPHANCOHORT', $result->cohortonly);
        $this->assertSame($cohort->id, $result->cohortonly['ORPHANCOHORT']->cohortid);
        $this->assertArrayNotHasKey('ORPHANCOHORT', $result->matched);
        $this->assertArrayNotHasKey('ORPHANCOHORT', $result->categoryonly);
    }

    /**
     * A top-level category without a matching cohort ends up in categoryonly.
     *
     * @covers \local_admindashboard\school_matcher::get_matches
     * @return void
     */
    public function test_category_without_cohort_is_category_only(): void {
        $this->resetAfterTest(true);

        $category = $this->getDataGenerator()->create_category(['idnumber' => 'ORPHANCATEGORY']);

        $result = school_matcher::get_matches();

        $this->assertArrayHasKey('ORPHANCATEGORY', $result->categoryonly);
        $this->assertSame($category->id, $result->categoryonly['ORPHANCATEGORY']->categoryid);
        $this->assertArrayNotHasKey('ORPHANCATEGORY', $result->matched);
        $this->assertArrayNotHasKey('ORPHANCATEGORY', $result->cohortonly);
    }

    /**
     * A cohort and a category with an empty idnumber are ignored entirely,
     * even though nothing stops them from otherwise coexisting.
     *
     * @covers \local_admindashboard\school_matcher::get_matches
     * @return void
     */
    public function test_empty_idnumber_is_ignored(): void {
        $this->resetAfterTest(true);

        $this->getDataGenerator()->create_cohort(['idnumber' => '']);
        $this->getDataGenerator()->create_category(['idnumber' => '']);

        $result = school_matcher::get_matches();

        $this->assertSame([], $result->matched);
        $this->assertSame([], $result->cohortonly);
        $this->assertSame([], $result->categoryonly);
    }

    /**
     * A category-context cohort (not system-wide) must not be treated as a
     * school candidate, even if its idnumber matches a top-level category.
     *
     * @covers \local_admindashboard\school_matcher::get_matches
     * @return void
     */
    public function test_non_system_cohort_is_ignored(): void {
        $this->resetAfterTest(true);

        $category = $this->getDataGenerator()->create_category(['idnumber' => 'LOCALCOHORT']);
        $context = \core\context\coursecat::instance($category->id);
        $this->getDataGenerator()->create_cohort(['idnumber' => 'LOCALCOHORT', 'contextid' => $context->id]);

        $result = school_matcher::get_matches();

        $this->assertArrayNotHasKey('LOCALCOHORT', $result->matched);
        $this->assertArrayHasKey('LOCALCOHORT', $result->categoryonly);
        $this->assertArrayNotHasKey('LOCALCOHORT', $result->cohortonly);
    }
}

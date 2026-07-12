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
 * Tests for user_metrics.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard\metrics;

/**
 * Class user_metrics_test
 */
final class user_metrics_test extends \advanced_testcase {
    /**
     * Purges the MUC cache user_metrics now reads through (see
     * classes/metrics/user_metrics.php, Schritt 7d) before every test, so no
     * test can see another test's cached numbers - resetAfterTest() rolls
     * back the database but does not touch the application cache.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->purge_cache();
    }

    /**
     * Purges the same cache definition user_metrics uses.
     *
     * @return void
     */
    private function purge_cache(): void {
        \cache::make('local_admindashboard', 'dashboarddata')->purge();
    }

    /**
     * Baseline counts (admin user only, guest excluded) so assertions work
     * against deltas instead of hardcoding Moodle's fixture internals.
     *
     * @return \stdClass
     */
    private function baseline(): \stdClass {
        return user_metrics::get_metrics(90);
    }

    /**
     * Deleted accounts, the guest account, and remote MNet accounts must not
     * be counted; suspended accounts must be counted (see docblock decision
     * in user_metrics).
     *
     * @covers \local_admindashboard\metrics\user_metrics::get_metrics
     * @return void
     */
    public function test_total_users_excludes_deleted_guest_and_remote_but_keeps_suspended(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $before = $this->baseline();

        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user(['deleted' => 1]);
        $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->getDataGenerator()->create_user(['mnethostid' => $CFG->mnet_localhost_id + 1]);

        $this->purge_cache();
        $after = user_metrics::get_metrics(90);

        // Two plain users plus one suspended user; deleted and remote-host accounts are excluded.
        $this->assertSame($before->totalusers + 3, $after->totalusers);
    }

    /**
     * Only accounts with lastaccess in the last 4 weeks count as active;
     * never-logged-in (lastaccess = 0) and stale accounts do not.
     *
     * @covers \local_admindashboard\metrics\user_metrics::get_metrics
     * @return void
     */
    public function test_active_users_counts_lastaccess_within_4_weeks(): void {
        $this->resetAfterTest(true);

        $before = $this->baseline();

        $this->getDataGenerator()->create_user(['lastaccess' => time() - DAYSECS]);
        $this->getDataGenerator()->create_user(['lastaccess' => time() - (5 * WEEKSECS)]);
        $this->getDataGenerator()->create_user(['lastaccess' => 0]);

        $this->purge_cache();
        $after = user_metrics::get_metrics(90);

        $this->assertSame($before->activeusers + 1, $after->activeusers);
    }

    /**
     * Only accounts created within the configured time range count as new.
     *
     * @covers \local_admindashboard\metrics\user_metrics::get_metrics
     * @return void
     */
    public function test_new_users_counts_timecreated_within_period(): void {
        $this->resetAfterTest(true);

        $before = $this->baseline();

        $this->getDataGenerator()->create_user(['timecreated' => time() - (10 * DAYSECS)]);
        $this->getDataGenerator()->create_user(['timecreated' => time() - (200 * DAYSECS)]);

        $this->purge_cache();
        $after = user_metrics::get_metrics(90);

        $this->assertSame($before->newusers + 1, $after->newusers);
    }
}

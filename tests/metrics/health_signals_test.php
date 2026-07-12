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
 * Tests for health_signals.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard\metrics;

/**
 * Class health_signals_test
 */
final class health_signals_test extends \advanced_testcase {
    /**
     * Purges the MUC cache health_signals now reads through (see
     * classes/metrics/health_signals.php, Schritt 7d) before every test.
     * These four cache keys are static (not parameterised by anything
     * test-specific), so without this, a test could see a previous test's
     * (or a previous real request's) cached result instead of its own
     * fixtures - resetAfterTest() rolls back the database but not the
     * application cache.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        \cache::make('local_admindashboard', 'dashboarddata')->purge();
    }

    /**
     * Two accounts sharing an email are reported; a third, unrelated account
     * and a deleted account that (before deletion) shared one of the emails
     * must not affect the result.
     *
     * @covers \local_admindashboard\metrics\health_signals::duplicate_emails
     * @return void
     */
    public function test_duplicate_emails_finds_shared_addresses(): void {
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);
        $user2 = $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);
        $this->getDataGenerator()->create_user(['email' => 'unique@example.com']);
        $this->getDataGenerator()->create_user(['email' => 'shared@example.com', 'deleted' => 1]);

        $result = health_signals::duplicate_emails();

        $this->assertSame(1, $result->count);
        $this->assertCount(2, $result->details);
        $ids = array_map(fn($row) => $row->userid, $result->details);
        sort($ids);
        $expected = [$user1->id, $user2->id];
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertSame('shared@example.com', $result->details[0]->email);
    }

    /**
     * Courses with enddate = 0 are reported with their category name;
     * courses with a real enddate are not.
     *
     * @covers \local_admindashboard\metrics\health_signals::courses_without_enddate
     * @return void
     */
    public function test_courses_without_enddate_finds_open_ended_courses(): void {
        $this->resetAfterTest(true);

        $category = $this->getDataGenerator()->create_category(['name' => 'Test Category']);
        $openended = $this->getDataGenerator()->create_course(['category' => $category->id, 'enddate' => 0]);
        $this->getDataGenerator()->create_course(['category' => $category->id, 'enddate' => time() + WEEKSECS]);

        $result = health_signals::courses_without_enddate();

        $this->assertSame(1, $result->count);
        $this->assertCount(1, $result->details);
        $this->assertSame($openended->id, $result->details[0]->courseid);
        $this->assertSame('Test Category', $result->details[0]->categoryname);
    }

    /**
     * Every real security check the core API returns must end up in exactly
     * one of the three buckets - this can't fake individual check statuses
     * (they depend on the real php.ini/config.php of whatever environment
     * runs the test), so it verifies the bucketing is lossless instead.
     *
     * @covers \local_admindashboard\metrics\health_signals::security_overview_summary
     * @return void
     */
    public function test_security_overview_summary_accounts_for_every_check(): void {
        $this->resetAfterTest(true);

        $checks = \core\check\manager::get_checks('security');
        $result = health_signals::security_overview_summary();

        $this->assertSame(count($checks), $result->ok + $result->warning + $result->error);
    }

    /**
     * lastrunat reflects tool_task's lastcronstart config, and
     * failedtasks24h only counts failed task_log rows from the last 24h.
     *
     * @covers \local_admindashboard\metrics\health_signals::cron_status
     * @return void
     */
    public function test_cron_status_reads_lastcronstart_and_recent_failures(): void {
        $this->resetAfterTest(true);

        $lastcron = time() - (2 * MINSECS);
        set_config('lastcronstart', $lastcron, 'tool_task');

        $this->insert_task_log_row(1, time() - HOURSECS);
        $this->insert_task_log_row(1, time() - (2 * DAYSECS));
        $this->insert_task_log_row(0, time() - HOURSECS);

        $result = health_signals::cron_status();

        $this->assertSame($lastcron, $result->lastrunat);
        $this->assertSame(1, $result->failedtasks24h);
    }

    /**
     * Inserts a minimal task_log row directly - no data generator exists
     * for this table.
     *
     * @param int $result 0 = pass, 1 = fail
     * @param int $timestart
     * @return void
     */
    private function insert_task_log_row(int $result, int $timestart): void {
        global $DB;

        $DB->insert_record('task_log', (object) [
            'type' => 0,
            'component' => 'local_admindashboard',
            'classname' => '\\local_admindashboard\\fake_task_for_testing',
            'userid' => 0,
            'timestart' => $timestart,
            'timeend' => $timestart + 1,
            'dbreads' => 0,
            'dbwrites' => 0,
            'result' => $result,
            'output' => '',
            'hostname' => 'testhost',
            'pid' => 1,
        ]);
    }
}

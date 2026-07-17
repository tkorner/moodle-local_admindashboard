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
 * Tests for dashboard_viewed.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard\event;

/**
 * Class dashboard_viewed_test
 */
final class dashboard_viewed_test extends \advanced_testcase {
    /**
     * With no 'page' given in 'other', the event must behave exactly as it
     * did before this event was shared with the drill-down pages - pointing
     * at index.php - so the one existing call site that predates that
     * sharing keeps working unchanged.
     *
     * @covers \local_admindashboard\event\dashboard_viewed
     * @return void
     */
    public function test_defaults_to_index_page_when_none_given(): void {
        $this->resetAfterTest(true);

        $event = dashboard_viewed::create(['context' => \core\context\system::instance()]);
        $event->trigger();

        $this->assertStringContainsString('/local/admindashboard/index.php', $event->get_url()->out(false));
        $this->assertStringContainsString('index.php', $event->get_description());
    }

    /**
     * A drill-down page passing its own name in 'other' gets that name back
     * from get_url()/get_description(), not the hardcoded index.php this
     * event used to always point at regardless of which page fired it.
     *
     * @covers \local_admindashboard\event\dashboard_viewed
     * @return void
     */
    public function test_reports_the_page_that_was_actually_viewed(): void {
        $this->resetAfterTest(true);

        $event = dashboard_viewed::create([
            'context' => \core\context\system::instance(),
            'other' => ['page' => 'duplicateemails.php'],
        ]);
        $event->trigger();

        $this->assertStringContainsString('/local/admindashboard/duplicateemails.php', $event->get_url()->out(false));
        $this->assertStringContainsString('duplicateemails.php', $event->get_description());
    }
}

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
 * Tests for dashboard_page.
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admincockpit\output;

/**
 * Class dashboard_page_test
 *
 * Covers the ground previously only exercised by cli/verify_emptystate.php
 * and cli/verify_navitems.php - both ad-hoc scripts, not a substitute for
 * real tests, and the former mutated live site config (activeschools) with
 * only a try/finally to restore it, a real hazard on a kill mid-run. These
 * tests use resetAfterTest() instead, which is free of that risk.
 */
final class dashboard_page_test extends \advanced_testcase {
    /**
     * Returns the plugin's own renderer.
     *
     * @return \core\output\renderer_base
     */
    private function renderer(): \core\output\renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('local_admincockpit');
    }

    /**
     * A freshly installed instance has no 'activeschools' configured - the
     * dashboard must say so cleanly rather than showing empty/broken cards.
     *
     * @covers \local_admincockpit\output\dashboard_page::export_for_template
     * @return void
     */
    public function test_export_for_template_empty_schools_state(): void {
        $this->resetAfterTest(true);
        set_config('activeschools', '', 'local_admincockpit');

        $context = (new dashboard_page(180))->export_for_template($this->renderer());

        $this->assertTrue($context['noschoolsconfigured']);
        $this->assertSame([], $context['schools']);
    }

    /**
     * 'navitems' having never been saved (get_config() === false, e.g. right
     * after this setting was added by an upgrade) must fall back to the
     * built-in default rather than showing an empty navigation section -
     * existing installations keep their links without any manual step.
     *
     * @covers \local_admincockpit\output\dashboard_page::export_for_template
     * @return void
     */
    public function test_export_for_template_navitems_never_saved_falls_back_to_default(): void {
        $this->resetAfterTest(true);
        unset_config('navitems', 'local_admincockpit');

        $context = (new dashboard_page(180))->export_for_template($this->renderer());

        $this->assertFalse($context['nonavitemsconfigured']);
        $this->assertNotEmpty($context['navgroups']);
    }

    /**
     * An admin explicitly saving 'navitems' as an empty string is a
     * different, deliberate state from "never configured" - it must show
     * the empty-state hint, not silently fall back to the default links.
     *
     * @covers \local_admincockpit\output\dashboard_page::export_for_template
     * @return void
     */
    public function test_export_for_template_navitems_explicitly_emptied_shows_empty_state(): void {
        $this->resetAfterTest(true);
        set_config('navitems', '', 'local_admincockpit');

        $context = (new dashboard_page(180))->export_for_template($this->renderer());

        $this->assertTrue($context['nonavitemsconfigured']);
        $this->assertSame([], $context['navgroups']);
    }

    /**
     * A nav item with a capability the current user does not have is
     * filtered out; one with no capability at all is always shown to anyone
     * who can already see the dashboard.
     *
     * @covers \local_admincockpit\output\dashboard_page::export_for_template
     * @return void
     */
    public function test_export_for_template_filters_navitems_by_capability(): void {
        $this->resetAfterTest(true);
        set_config(
            'navitems',
            "Everyone|/course/edit.php|Group\nAdmins only|/admin/index.php|Group|moodle/site:config",
            'local_admincockpit'
        );

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = (new dashboard_page(180))->export_for_template($this->renderer());

        $this->assertCount(1, $context['navgroups']);
        $labels = array_map(fn($item) => $item->label, $context['navgroups'][0]->items);
        $this->assertSame(['Everyone'], $labels);

        $this->setAdminUser();
        $context = (new dashboard_page(180))->export_for_template($this->renderer());
        $labels = array_map(fn($item) => $item->label, $context['navgroups'][0]->items);
        $this->assertSame(['Everyone', 'Admins only'], $labels);
    }

    /**
     * Cron never having run at all is reported as 'error' severity, not
     * merely 'warning' - aligned with core's own \tool_task\check\cronrunning,
     * which computes its delta as time() - lastcronstart and so always lands
     * in its own most severe (CRITICAL) bucket when lastcronstart was never
     * set (verified directly against that class's get_result()).
     *
     * @covers \local_admincockpit\output\dashboard_page::export_for_template
     * @return void
     */
    public function test_export_for_template_cron_never_run_is_error_severity(): void {
        $this->resetAfterTest(true);
        unset_config('lastcronstart', 'tool_task');
        \core_cache\cache::make('local_admincockpit', 'dashboarddata')->purge();

        $context = (new dashboard_page(180))->export_for_template($this->renderer());

        $crontile = $context['healthsignals'][3];
        $this->assertSame('danger', $crontile->severitybgclass);
    }

    /**
     * A group whose only item gets filtered out by capability must not
     * appear as an empty card with a heading and nothing under it.
     *
     * @covers \local_admincockpit\output\dashboard_page::export_for_template
     * @return void
     */
    public function test_export_for_template_drops_groups_left_with_no_visible_items(): void {
        $this->resetAfterTest(true);
        set_config(
            'navitems',
            "Admins only|/admin/index.php|Restricted group|moodle/site:config\n" .
                "Everyone|/course/edit.php|Open group",
            'local_admincockpit'
        );

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = (new dashboard_page(180))->export_for_template($this->renderer());

        $titles = array_map(fn($group) => $group->title, $context['navgroups']);
        $this->assertSame(['Open group'], $titles);
    }
}

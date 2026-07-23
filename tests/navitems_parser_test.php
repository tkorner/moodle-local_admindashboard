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
 * Tests for navitems_parser.
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admincockpit;

/**
 * Class navitems_parser_test
 */
final class navitems_parser_test extends \advanced_testcase {
    /**
     * A single well-formed line becomes one group with one item; no
     * capability segment means an empty capability string, not null/unset.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_single_line(): void {
        $result = navitems_parser::parse('Upload users|/admin/tool/uploaduser/index.php|User management');

        $this->assertSame(0, $result->errorlines);
        $this->assertCount(1, $result->groups);
        $this->assertSame('User management', $result->groups[0]->title);
        $this->assertCount(1, $result->groups[0]->items);
        $item = $result->groups[0]->items[0];
        $this->assertSame('Upload users', $item->label);
        $this->assertSame('', $item->capability);
        $this->assertStringContainsString('/admin/tool/uploaduser/index.php', $item->url);
    }

    /**
     * Whitespace around segments must not matter - "Title | URL | Group"
     * parses the same as "Title|URL|Group" (matches how core's own
     * custommenuitems parsing trims each segment).
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_trims_segment_whitespace(): void {
        $result = navitems_parser::parse('  Upload users  |  /admin/tool/uploaduser/index.php  |  User management  ');

        $this->assertSame(0, $result->errorlines);
        $this->assertSame('User management', $result->groups[0]->title);
        $this->assertSame('Upload users', $result->groups[0]->items[0]->label);
    }

    /**
     * A 4th segment is captured as the capability string.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_captures_optional_capability(): void {
        $result = navitems_parser::parse('Upload users|/admin/tool/uploaduser/index.php|User management|moodle/user:create');

        $this->assertSame(0, $result->errorlines);
        $this->assertSame('moodle/user:create', $result->groups[0]->items[0]->capability);
    }

    /**
     * Two lines sharing a group name end up as two items under one group
     * object, not two separate groups.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_groups_items_sharing_a_group_name(): void {
        $result = navitems_parser::parse(
            "Upload users|/admin/tool/uploaduser/index.php|User management\n" .
            "Cohorts|/cohort/index.php|User management"
        );

        $this->assertCount(1, $result->groups);
        $this->assertCount(2, $result->groups[0]->items);
    }

    /**
     * Group order in the output follows first appearance in the setting,
     * not alphabetical or any other implicit ordering.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_group_order_is_first_appearance(): void {
        $result = navitems_parser::parse(
            "Item Z|/z.php|Zeta\n" .
            "Item A|/a.php|Alpha\n" .
            "Item Z2|/z2.php|Zeta"
        );

        $titles = array_map(fn($group) => $group->title, $result->groups);
        $this->assertSame(['Zeta', 'Alpha'], $titles);
    }

    /**
     * Blank lines (including whitespace-only ones) are silently skipped -
     * not counted as errors - so an admin can use them to visually separate
     * groups in the textarea.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_skips_blank_lines_without_counting_them_as_errors(): void {
        $result = navitems_parser::parse(
            "Upload users|/admin/tool/uploaduser/index.php|User management\n" .
            "\n" .
            "   \n" .
            "Cohorts|/cohort/index.php|User management"
        );

        $this->assertSame(0, $result->errorlines);
        $this->assertCount(2, $result->groups[0]->items);
    }

    /**
     * Lines with fewer than 3 or more than 4 pipe-separated segments are
     * skipped and counted as errors.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_rejects_wrong_segment_count(): void {
        $result = navitems_parser::parse(
            "Too few|/a.php\n" .
            "Way|/too/many.php|Segments|here|and|here"
        );

        $this->assertSame(2, $result->errorlines);
        $this->assertCount(0, $result->groups);
    }

    /**
     * A line with exactly 3 or 4 segments but an empty title, URL, or group
     * (after trimming) is still rejected - a structurally valid line with
     * meaningless content is not useful to render.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_rejects_empty_required_segments(): void {
        $result = navitems_parser::parse(
            "|/a.php|Group\n" .
            "Title||Group\n" .
            "Title|/a.php|"
        );

        $this->assertSame(3, $result->errorlines);
        $this->assertCount(0, $result->groups);
    }

    /**
     * A URL \core\url itself cannot parse (verified directly against its
     * constructor: an invalid port makes PHP's parse_url() return false,
     * which \core\url turns into a moodle_exception) is skipped and counted,
     * not left to bubble up as a fatal error for the whole settings/dashboard
     * page over one bad line.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_rejects_unparseable_url(): void {
        $result = navitems_parser::parse('Bad|http://example.com:notaport/path|Group');

        $this->assertSame(1, $result->errorlines);
        $this->assertCount(0, $result->groups);
    }

    /**
     * A non-http(s) scheme is rejected even though \core\url itself parses
     * it without complaint - verified directly: 'javascript:alert(1)'
     * becomes the equally-live 'javascript://alert(1)' rather than being
     * rejected by the URL parser itself, so this plugin has to reject it.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_rejects_non_http_scheme(): void {
        $result = navitems_parser::parse('Bad|javascript:alert(1)|Group');

        $this->assertSame(1, $result->errorlines);
        $this->assertCount(0, $result->groups);
    }

    /**
     * A capability that does not exist (typo, or a plugin that provided it
     * no longer installed) is rejected rather than silently hiding the link
     * forever and spamming a debugging() notice on every dashboard view.
     *
     * @covers \local_admincockpit\navitems_parser::parse
     * @return void
     */
    public function test_parse_rejects_unknown_capability(): void {
        $result = navitems_parser::parse('Bad|/course/edit.php|Group|moodle/site:thiscapabilitydoesnotexist');

        $this->assertSame(1, $result->errorlines);
        $this->assertCount(0, $result->groups);
        $this->assertDebuggingNotCalled();
    }

    /**
     * default_value() must itself parse back cleanly with zero error lines,
     * and include the tool_mergeusers / theme_boost_union lines if and only
     * if those plugins are actually installed on the instance running the
     * test - matching the pre-Schritt-7h hardcoded export_nav_groups()'s
     * conditional presence.
     *
     * @covers \local_admincockpit\navitems_parser::default_value
     * @return void
     */
    public function test_default_value_parses_cleanly_and_matches_installed_plugins(): void {
        $this->resetAfterTest(true);

        $raw = navitems_parser::default_value();
        $result = navitems_parser::parse($raw);

        $this->assertSame(0, $result->errorlines);

        $alllabels = [];
        foreach ($result->groups as $group) {
            foreach ($group->items as $item) {
                $alllabels[] = $item->label;
            }
        }

        $pluginman = \core\plugin_manager::instance();
        $mergeuserslabel = get_string('pluginname', 'tool_mergeusers');
        $boostunionlabel = get_string('boostunionsettings', 'local_admincockpit');

        $this->assertSame(
            (bool) $pluginman->get_plugin_info('tool_mergeusers'),
            in_array($mergeuserslabel, $alllabels, true)
        );
        $this->assertSame(
            (bool) $pluginman->get_plugin_info('theme_boost_union'),
            in_array($boostunionlabel, $alllabels, true)
        );

        // None of the default lines carry a capability restriction (Schritt 7h: this preserves the
        // pre-existing behaviour of showing every link unconditionally to anyone who can see the page).
        foreach ($result->groups as $group) {
            foreach ($group->items as $item) {
                $this->assertSame('', $item->capability);
            }
        }
    }
}

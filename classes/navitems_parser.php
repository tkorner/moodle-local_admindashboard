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
 * Parses the 'navitems' setting.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard;

/**
 * Turns the 'navitems' setting's raw text into grouped nav links.
 *
 * Deliberately does not check capabilities - that needs the current $USER
 * and belongs in the output layer (classes/output/dashboard_page.php),
 * exactly like the existing tool_mergeusers/theme_boost_union presence
 * checks it already does. This class only turns text into structure.
 */
class navitems_parser {
    /**
     * Line format, modelled on how core itself parses $CFG->custommenuitems
     * (see \core\output\custom_menu::convert_text_to_menu_nodes()): one item
     * per line, pipe-separated, each segment trimmed individually so
     * "Title | URL" works the same as "Title|URL".
     *
     * Title|URL|Group|Capability(optional)
     *
     * Lines that are blank (after trimming) are silently skipped - this
     * lets an admin use blank lines to visually separate groups in the
     * textarea. Lines that are not blank but do not have 3 or 4 non-empty
     * Title/URL/Group segments, or whose URL cannot be parsed at all, are
     * skipped and counted in errorlines so the settings page can warn about
     * them.
     *
     * @param string $rawtext the raw setting value
     * @return \stdClass groups (array of stdClass: title, items - array of
     *         stdClass: label, url, capability), errorlines (int)
     */
    public static function parse(string $rawtext): \stdClass {
        $groups = [];
        $errorlines = 0;

        foreach (explode("\n", $rawtext) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $segments = array_map('trim', explode('|', $line));
            if (count($segments) < 3 || count($segments) > 4) {
                $errorlines++;
                continue;
            }

            [$label, $rawurl, $group] = $segments;
            $capability = $segments[3] ?? '';
            if ($label === '' || $rawurl === '' || $group === '') {
                $errorlines++;
                continue;
            }

            try {
                $url = (new \core\url($rawurl))->out(false);
            } catch (\core\exception\moodle_exception $e) {
                $errorlines++;
                continue;
            }

            if (!isset($groups[$group])) {
                $groups[$group] = (object) ['title' => $group, 'items' => []];
            }
            $groups[$group]->items[] = (object) [
                'label' => $label,
                'url' => $url,
                'capability' => $capability,
            ];
        }

        return (object) [
            'groups' => array_values($groups),
            'errorlines' => $errorlines,
        ];
    }

    /**
     * Builds the default 'navitems' value: exactly the links that used to be
     * hardcoded in dashboard_page.php before Schritt 7h, so existing
     * installations (including our own) keep the same navigation after the
     * update without having to touch the settings page. None of these lines
     * carry a capability restriction, matching the pre-Schritt-7h behaviour
     * of showing all of them unconditionally to anyone who can see the page.
     *
     * The tool_mergeusers and theme_boost_union lines are only included if
     * those plugins are actually installed on this instance - same
     * conditional presence the old hardcoded export_nav_groups() had.
     *
     * @return string
     */
    public static function default_value(): string {
        $pluginman = \core\plugin_manager::instance();

        $lines = [
            self::line(get_string('pluginname', 'tool_uploaduser'), '/admin/tool/uploaduser/index.php',
                get_string('navgroup_users', 'local_admindashboard')),
            self::line(get_string('cohorts', 'cohort'), '/cohort/index.php',
                get_string('navgroup_users', 'local_admindashboard')),
            self::line(get_string('uploadcohorts', 'cohort'), '/cohort/upload.php',
                get_string('navgroup_users', 'local_admindashboard')),
        ];
        if ($pluginman->get_plugin_info('tool_mergeusers')) {
            $lines[] = self::line(get_string('pluginname', 'tool_mergeusers'), '/admin/tool/mergeusers/index.php',
                get_string('navgroup_users', 'local_admindashboard'));
        }

        $lines[] = self::line(get_string('addnewcourse', 'core'), '/course/edit.php',
            get_string('navgroup_courses', 'local_admindashboard'));
        $lines[] = self::line(get_string('managecategories', 'core'), '/course/management.php',
            get_string('navgroup_courses', 'local_admindashboard'));
        // SYSCONTEXTID (defined by lib/setup.php on every request) is always the system context's id -
        // core itself relies on this being a stable, well-known value (e.g. lib/accesslib.php), so baking
        // it into a static default is safe, unlike e.g. a category id which varies per instance.
        $lines[] = self::line(get_string('restorecourse', 'admin'), '/backup/restorefile.php?contextid=' . SYSCONTEXTID,
            get_string('navgroup_courses', 'local_admindashboard'));

        $lines[] = self::line(get_string('customreports', 'core_reportbuilder'), '/reportbuilder/index.php',
            get_string('navgroup_reports', 'local_admindashboard'));
        $lines[] = self::line(get_string('logs', 'core'), '/report/log/index.php',
            get_string('navgroup_reports', 'local_admindashboard'));
        $lines[] = self::line(get_string('pluginname', 'report_configlog'), '/report/configlog/index.php',
            get_string('navgroup_reports', 'local_admindashboard'));

        $lines[] = self::line(get_string('scheduledtasks', 'tool_task'), '/admin/tool/task/scheduledtasks.php',
            get_string('navgroup_system', 'local_admindashboard'));

        if ($pluginman->get_plugin_info('theme_boost_union')) {
            $lines[] = self::line(get_string('boostunionsettings', 'local_admindashboard'),
                '/theme/boost_union/settings_overview.php', get_string('navgroup_theme', 'local_admindashboard'));
        }

        return implode("\n", $lines);
    }

    /**
     * Builds one "Title|URL|Group" default line.
     *
     * @param string $label
     * @param string $url
     * @param string $group
     * @return string
     */
    private static function line(string $label, string $url, string $group): string {
        return "{$label}|{$url}|{$group}";
    }
}

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
 * Assembles all dashboard data into a template context.
 *
 * This is deliberately an output-layer class (classes/output/), not a
 * metrics class - it is allowed to know about pages, URLs, and how the
 * numbers should be labelled/grouped, which is exactly what
 * classes/metrics/*.php must NOT know about.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard\output;

use local_admindashboard\school_matcher;
use local_admindashboard\metrics\health_signals;
use local_admindashboard\metrics\school_metrics;
use local_admindashboard\metrics\user_metrics;

/**
 * Builds the mustache context for the main dashboard template.
 */
class dashboard_page implements \core\output\renderable, \core\output\templatable {
    /** @var int */
    private $timerangedays;

    /**
     * Constructor.
     *
     * @param int $timerangedays effective time range for this page view
     *        (the configured default, or a temporary GET override)
     */
    public function __construct(int $timerangedays) {
        $this->timerangedays = $timerangedays;
    }

    /**
     * Builds the full template context for templates/dashboard.mustache.
     *
     * @param \core\output\renderer_base $output
     * @return array
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        return [
            'timerangedays' => $this->timerangedays,
            'timerangeoptions' => $this->export_timerange_options(),
            'dashboardurl' => (new \core\url('/local/admindashboard/index.php'))->out(false),
            'usermetrics' => $this->export_user_metrics(),
            'schools' => $this->export_schools(),
            'noschoolsconfigured' => empty($this->get_active_matched_schools()),
            'settingsurl' => (new \core\url('/admin/settings.php', ['section' => 'local_admindashboard_settings']))->out(false),
            'healthsignals' => $this->export_health_signals(),
            'navgroups' => $this->export_nav_groups(),
        ];
    }

    /**
     * Builds the options for the time range dropdown.
     *
     * @return array of stdClass: value, label, selected
     */
    private function export_timerange_options(): array {
        $options = [];
        foreach ([30, 90, 180, 360] as $days) {
            $options[] = (object) [
                'value' => $days,
                'label' => get_string('numdays', 'core', $days),
                'selected' => $days === $this->timerangedays,
            ];
        }
        return $options;
    }

    /**
     * Exports the global user metrics tile row.
     *
     * @return array of stdClass: label, value
     */
    private function export_user_metrics(): array {
        $metrics = user_metrics::get_metrics($this->timerangedays);

        return [
            (object) ['label' => get_string('tile_totalusers', 'local_admindashboard'), 'value' => $metrics->totalusers],
            (object) ['label' => get_string('tile_activeusers', 'local_admindashboard'), 'value' => $metrics->activeusers],
            (object) ['label' => get_string('tile_newusers', 'local_admindashboard'), 'value' => $metrics->newusers],
        ];
    }

    /**
     * Fetches the active school codes (from settings) that are still fully
     * matched right now, in the order they were configured.
     *
     * A code can become stale between being selected in settings and being
     * shown here (e.g. someone deletes the cohort) - stale codes are
     * silently skipped rather than shown broken; the settings page's
     * one-sided-match warning is where that gets surfaced.
     *
     * @return array of stdClass (idnumber, cohortid, cohortname, categoryid,
     *         categoryname), keyed by idnumber
     */
    private function get_active_matched_schools(): array {
        $activecodes = array_filter(explode(',', (string) get_config('local_admindashboard', 'activeschools')));
        if (empty($activecodes)) {
            return [];
        }

        $matches = school_matcher::get_matches();

        $schools = [];
        foreach ($activecodes as $idnumber) {
            if (isset($matches->matched[$idnumber])) {
                $schools[$idnumber] = $matches->matched[$idnumber];
            }
        }
        return $schools;
    }

    /**
     * Builds the per-school tile groups.
     *
     * @return array of stdClass: idnumber, name, tiles (array of
     *         stdClass: label, value), coursemanagementurl
     */
    private function export_schools(): array {
        $schools = [];

        foreach ($this->get_active_matched_schools() as $school) {
            $metrics = school_metrics::get_metrics($school->cohortid, $school->categoryid, $this->timerangedays);

            $schools[] = (object) [
                'idnumber' => $school->idnumber,
                'name' => $school->cohortname,
                'tiles' => [
                    (object) [
                        'label' => get_string('schooltile_membercount', 'local_admindashboard'),
                        'value' => $metrics->membercount,
                    ],
                    (object) [
                        'label' => get_string('schooltile_newmembers', 'local_admindashboard'),
                        'value' => $metrics->newmembers,
                    ],
                    (object) [
                        'label' => get_string('schooltile_activemembers', 'local_admindashboard'),
                        'value' => $metrics->activemembers,
                    ],
                    (object) [
                        'label' => get_string('schooltile_coursecount', 'local_admindashboard'),
                        'value' => $metrics->coursecount,
                    ],
                    (object) [
                        'label' => get_string('schooltile_newcourses', 'local_admindashboard'),
                        'value' => $metrics->newcourses,
                    ],
                ],
                'coursemanagementurl' => (new \core\url(
                    '/course/management.php',
                    ['categoryid' => $school->categoryid]
                ))->out(false),
            ];
        }

        return $schools;
    }

    /**
     * Builds the four health signal tiles.
     *
     * @return array of stdClass, see make_signal_tile()
     */
    private function export_health_signals(): array {
        $duplicates = health_signals::duplicate_emails();
        $noenddate = health_signals::courses_without_enddate();
        $security = health_signals::security_overview_summary();
        $cron = health_signals::cron_status();

        return [
            $this->make_signal_tile(
                get_string('duplicateemails', 'local_admindashboard'),
                $duplicates->count,
                '/local/admindashboard/duplicateemails.php',
                $duplicates->count > 0 ? 'warning' : 'ok'
            ),
            $this->make_signal_tile(
                get_string('courseswithoutenddate', 'local_admindashboard'),
                $noenddate->count,
                '/local/admindashboard/courseswithoutenddate.php',
                $noenddate->count > 0 ? 'warning' : 'ok'
            ),
            $this->make_signal_tile(
                get_string('signal_security', 'local_admindashboard'),
                $this->format_security_value($security),
                '/report/security/index.php',
                $security->error > 0 ? 'error' : ($security->warning > 0 ? 'warning' : 'ok')
            ),
            $this->make_signal_tile(
                get_string('signal_cron', 'local_admindashboard'),
                $this->format_cron_value($cron),
                '/admin/tool/task/scheduledtasks.php',
                $this->cron_severity($cron),
                $cron->lastrunat > 0 ? userdate($cron->lastrunat) : ''
            ),
        ];
    }

    /**
     * Builds one health signal tile, mapping its 'ok'/'warning'/'error'
     * severity onto the badge markup core itself uses for check statuses
     * (see \core\check\result and its lib/templates/check/result/*.mustache
     * partials: <span class="badge bg-{colour} {textclass}">{label}</span>,
     * no icon).
     *
     * The icon added on top (for colour-blind accessibility) deliberately
     * does NOT go through $OUTPUT->pix_icon()'s semantic identifiers (e.g.
     * 'i/valid', 'i/warning'). Checked core's own icon map
     * (\core\output\icon_system_fontawesome::get_core_icon_map()): those
     * identifiers bake in a fixed colour class ('i/valid' => 'fa-check
     * text-success', 'i/warning' => 'fa-triangle-exclamation text-warning').
     * That's fine for an icon standing alone on a neutral background, but
     * inside our own bg-success/bg-warning badge it means e.g. a
     * text-success (green) icon on a bg-success (green) badge - nearly
     * invisible, which is exactly the colour-blind-unfriendly outcome this
     * icon was added to avoid. Using the bare FontAwesome glyph class
     * without a colour class lets the icon inherit the badge's own
     * text-white/text-dark colour instead, matching the label text next to
     * it. The glyph names themselves (fa-check, fa-triangle-exclamation)
     * are still the same ones core uses, just without the clashing colour.
     *
     * @param string $label
     * @param int|string $value
     * @param string $path site-relative path for the tile's click-through
     * @param string $severity 'ok', 'warning', or 'error'
     * @param string $valuetitle optional title/tooltip attribute for the value
     * @return \stdClass label, value, valuetitle, url, severitybgclass,
     *         severitytextclass, severityiconclass, severitylabel
     */
    private function make_signal_tile(
        string $label,
        $value,
        string $path,
        string $severity,
        string $valuetitle = ''
    ): \stdClass {
        // Colour + text-contrast pairing matches core\check\result's own bg-success/text-white,
        // bg-warning/text-dark, bg-danger/text-white convention; icon glyph names match core's
        // i/valid and i/warning mapping, minus their colour classes (see docblock above).
        $severitymap = [
            'ok' => ['bgclass' => 'success', 'textclass' => 'text-white', 'icon' => 'fa-check', 'labelkey' => 'ok'],
            'warning' => ['bgclass' => 'warning', 'textclass' => 'text-dark', 'icon' => 'fa-triangle-exclamation', 'labelkey' => 'warning'],
            'error' => ['bgclass' => 'danger', 'textclass' => 'text-white', 'icon' => 'fa-triangle-exclamation', 'labelkey' => 'error'],
        ];
        $map = $severitymap[$severity] ?? $severitymap['error'];

        return (object) [
            'label' => $label,
            'value' => $value,
            'valuetitle' => $valuetitle,
            'url' => (new \core\url($path))->out(false),
            'severitybgclass' => $map['bgclass'],
            'severitytextclass' => $map['textclass'],
            'severityiconclass' => $map['icon'],
            'severitylabel' => get_string($map['labelkey'], 'core'),
        ];
    }

    /**
     * Formats the security overview health signal's display value as a
     * single compact line, e.g. "15 OK · 4 Warnungen" - error is only
     * shown when non-zero (0 errors is the expected, unremarkable case);
     * ok and warning are always shown since either could legitimately be 0.
     *
     * @param \stdClass $security as returned by
     *        health_signals::security_overview_summary()
     * @return string
     */
    private function format_security_value(\stdClass $security): string {
        $parts = [
            get_string('signal_security_ok', 'local_admindashboard', $security->ok),
            get_string('signal_security_warning', 'local_admindashboard', $security->warning),
        ];
        if ($security->error > 0) {
            $parts[] = get_string('signal_security_error', 'local_admindashboard', $security->error);
        }

        return implode(' · ', $parts);
    }

    /**
     * Formats the cron health signal's display value.
     *
     * The "last run" part uses the same format_time(time() - $timestamp)
     * idiom core itself uses for relative timestamps (see
     * admin/classes/reportbuilder/local/systemreports/users.php's lastaccess
     * column), e.g. "2 hours 15 mins", rather than a full date/time - the
     * full timestamp is still available via the tile's title attribute
     * (see export_health_signals()).
     *
     * @param \stdClass $cron as returned by health_signals::cron_status()
     * @return string
     */
    private function format_cron_value(\stdClass $cron): string {
        $lastrun = $cron->lastrunat > 0
            ? get_string('signal_cron_lastrun', 'local_admindashboard', format_time(time() - $cron->lastrunat))
            : get_string('signal_cron_neverrun', 'local_admindashboard');

        return $lastrun . ' ' . get_string('signal_cron_failedtasks', 'local_admindashboard', $cron->failedtasks24h);
    }

    /**
     * Decides the cron tile's severity.
     *
     * Own heuristic (not a reuse of \tool_task\check\cronrunning's verdict,
     * which is a separate report page one click away): any recent failure
     * is an error; cron never having run, or being overdue by more than
     * $CFG->expectedcronfrequency (the same config core's own check reads),
     * is a warning.
     *
     * @param \stdClass $cron as returned by health_signals::cron_status()
     * @return string 'ok', 'warning', or 'error'
     */
    private function cron_severity(\stdClass $cron): string {
        global $CFG;

        if ($cron->failedtasks24h > 0) {
            return 'error';
        }

        $expectedfrequency = $CFG->expectedcronfrequency ?? MINSECS;
        if ($cron->lastrunat === 0 || (time() - $cron->lastrunat) > $expectedfrequency + MINSECS) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Builds the "Navigation" boxes (SPEC section 5).
     *
     * tool_mergeusers and theme_boost_union are not shipped with core, so
     * their links are only included when \core\plugin_manager confirms they
     * are actually installed - same approach as duplicateemails.php.
     *
     * @return array of stdClass: title, items (array of stdClass: label, url)
     */
    private function export_nav_groups(): array {
        $pluginman = \core\plugin_manager::instance();

        $usermanagement = [
            (object) [
                'label' => get_string('pluginname', 'tool_uploaduser'),
                'url' => (new \core\url('/admin/tool/uploaduser/index.php'))->out(false),
            ],
            (object) [
                'label' => get_string('cohorts', 'cohort'),
                'url' => (new \core\url('/cohort/index.php'))->out(false),
            ],
            (object) [
                'label' => get_string('uploadcohorts', 'cohort'),
                'url' => (new \core\url('/cohort/upload.php'))->out(false),
            ],
        ];
        if ($pluginman->get_plugin_info('tool_mergeusers')) {
            $usermanagement[] = (object) [
                'label' => get_string('pluginname', 'tool_mergeusers'),
                'url' => (new \core\url('/admin/tool/mergeusers/index.php'))->out(false),
            ];
        }

        $coursemanagement = [
            (object) [
                'label' => get_string('addnewcourse', 'core'),
                'url' => (new \core\url('/course/edit.php'))->out(false),
            ],
            (object) [
                'label' => get_string('managecategories', 'core'),
                'url' => (new \core\url('/course/management.php'))->out(false),
            ],
            (object) [
                'label' => get_string('restorecourse', 'admin'),
                'url' => (new \core\url(
                    '/backup/restorefile.php',
                    ['contextid' => \core\context\system::instance()->id]
                ))->out(false),
            ],
        ];

        $reports = [
            (object) [
                'label' => get_string('customreports', 'core_reportbuilder'),
                'url' => (new \core\url('/reportbuilder/index.php'))->out(false),
            ],
            (object) [
                'label' => get_string('logs', 'core'),
                'url' => (new \core\url('/report/log/index.php'))->out(false),
            ],
            (object) [
                'label' => get_string('pluginname', 'report_configlog'),
                'url' => (new \core\url('/report/configlog/index.php'))->out(false),
            ],
        ];

        $system = [
            (object) [
                'label' => get_string('scheduledtasks', 'tool_task'),
                'url' => (new \core\url('/admin/tool/task/scheduledtasks.php'))->out(false),
            ],
        ];

        $navgroups = [
            (object) ['title' => get_string('navgroup_users', 'local_admindashboard'), 'items' => $usermanagement],
            (object) ['title' => get_string('navgroup_courses', 'local_admindashboard'), 'items' => $coursemanagement],
            (object) ['title' => get_string('navgroup_reports', 'local_admindashboard'), 'items' => $reports],
            (object) ['title' => get_string('navgroup_system', 'local_admindashboard'), 'items' => $system],
        ];

        if ($pluginman->get_plugin_info('theme_boost_union')) {
            $navgroups[] = (object) [
                'title' => get_string('navgroup_theme', 'local_admindashboard'),
                'items' => [
                    (object) [
                        'label' => get_string('boostunionsettings', 'local_admindashboard'),
                        'url' => (new \core\url('/theme/boost_union/settings_overview.php'))->out(false),
                    ],
                ],
            ];
        }

        return $navgroups;
    }
}

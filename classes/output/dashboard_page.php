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
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admincockpit\output;

use local_admincockpit\navitems_parser;
use local_admincockpit\school_matcher;
use local_admincockpit\metrics\health_signals;
use local_admincockpit\metrics\school_metrics;
use local_admincockpit\metrics\user_metrics;

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
        $grouping = $this->grouping_label();
        $navgroups = $this->export_nav_groups();

        return [
            'introtext' => get_string('dashboardintro', 'local_admincockpit', $grouping),
            'groupinglabel' => $grouping,
            'timerangedays' => $this->timerangedays,
            'timerangeoptions' => $this->export_timerange_options(),
            'dashboardurl' => (new \core\url('/local/admincockpit/index.php'))->out(false),
            'usermetrics' => $this->export_user_metrics($output),
            'schools' => $this->export_schools($output),
            'noschoolsconfigured' => empty($this->get_active_matched_schools()),
            'settingsurl' => (new \core\url('/admin/settings.php', ['section' => 'local_admincockpit_settings']))->out(false),
            'lastcomputedtext' => $this->export_lastcomputedtext(),
            'sesskey' => sesskey(),
            'healthsignals' => $this->export_health_signals($output),
            'navgroups' => $navgroups,
            'nonavitemsconfigured' => empty($navgroups),
        ];
    }

    /**
     * Reads the configurable term for what a "school" is called on this
     * instance (Schritt 9: generalisation away from a hardcoded "school").
     * Falls back to the setting's own default if not yet set (e.g. right
     * after install, before the first upgrade has written it).
     *
     * @return string
     */
    private function grouping_label(): string {
        return (string) get_config('local_admincockpit', 'groupinglabel') ?: school_matcher::DEFAULT_GROUPING_LABEL;
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
     * @param \core\output\renderer_base $output
     * @return array of stdClass: label, value, helpicon
     */
    private function export_user_metrics(\core\output\renderer_base $output): array {
        $metrics = user_metrics::get_metrics($this->timerangedays);

        return [
            (object) [
                'label' => get_string('tile_totalusers', 'local_admincockpit'),
                'value' => $metrics->totalusers,
                'helpicon' => '',
            ],
            (object) [
                'label' => get_string('tile_activeusers', 'local_admincockpit'),
                'value' => $metrics->activeusers,
                'helpicon' => $output->help_icon('activeusers', 'local_admincockpit'),
            ],
            (object) [
                'label' => get_string('tile_newusers', 'local_admincockpit'),
                'value' => $metrics->newusers,
                'helpicon' => $output->help_icon('newinperiod', 'local_admincockpit'),
            ],
        ];
    }

    /**
     * Builds the "Stand: ..." note shown below the global metrics.
     *
     * Every cached value on this page shares the same 1-day TTL and (in the
     * common case of a cold cache) gets computed together on the same
     * request, so the global user metrics' computedat is used as a
     * representative timestamp for the whole page rather than tracking one
     * per section - simpler, and accurate enough for "are these numbers
     * roughly a day old or fresh" at a glance.
     *
     * @return string
     */
    private function export_lastcomputedtext(): string {
        $metrics = user_metrics::get_metrics($this->timerangedays);
        return get_string('lastcomputed', 'local_admincockpit', userdate($metrics->computedat));
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
     * Known edge case, not fixed: admin_setting_configmultiselect stores its
     * selection as a plain comma-joined string (verified in
     * lib/adminlib.php - get_setting()/write_setting() do a bare
     * explode(',')/implode(',') with no escaping), so an idnumber that
     * itself contains a comma would corrupt this list. idnumbers containing
     * commas are unusual enough, and changing the storage format invasive
     * enough, that this is left as a documented limitation rather than
     * fixed.
     *
     * @return array of stdClass (idnumber, cohortid, cohortname, categoryid,
     *         categoryname), keyed by idnumber
     */
    private function get_active_matched_schools(): array {
        $activecodes = array_filter(explode(',', (string) get_config('local_admincockpit', 'activeschools')));
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
     * @param \core\output\renderer_base $output
     * @return array of stdClass: idnumber, name, tiles (array of
     *         stdClass: label, value, helpicon), coursemanagementurl
     */
    private function export_schools(\core\output\renderer_base $output): array {
        $schools = [];

        foreach ($this->get_active_matched_schools() as $school) {
            $metrics = school_metrics::get_metrics($school->cohortid, $school->categoryid, $this->timerangedays);

            $schools[] = (object) [
                'idnumber' => $school->idnumber,
                'name' => $school->cohortname,
                'tiles' => [
                    (object) [
                        'label' => get_string('schooltile_membercount', 'local_admincockpit'),
                        'value' => $metrics->membercount,
                        'helpicon' => '',
                    ],
                    (object) [
                        'label' => get_string('schooltile_newmembers', 'local_admincockpit'),
                        'value' => $metrics->newmembers,
                        'helpicon' => $output->help_icon('newinperiod', 'local_admincockpit'),
                    ],
                    (object) [
                        'label' => get_string('schooltile_activemembers', 'local_admincockpit'),
                        'value' => $metrics->activemembers,
                        'helpicon' => $output->help_icon('activeusers', 'local_admincockpit'),
                    ],
                    (object) [
                        'label' => get_string('schooltile_coursecount', 'local_admincockpit'),
                        'value' => $metrics->coursecount,
                        'helpicon' => '',
                    ],
                    (object) [
                        'label' => get_string('schooltile_newcourses', 'local_admincockpit'),
                        'value' => $metrics->newcourses,
                        'helpicon' => $output->help_icon('newinperiod', 'local_admincockpit'),
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
     * @param \core\output\renderer_base $output
     * @return array of stdClass, see make_signal_tile()
     */
    private function export_health_signals(\core\output\renderer_base $output): array {
        $duplicates = health_signals::duplicate_emails();
        $noenddate = health_signals::courses_without_enddate();
        $security = health_signals::security_overview_summary();
        $cron = health_signals::cron_status();

        return [
            $this->make_signal_tile(
                get_string('duplicateemails', 'local_admincockpit'),
                $duplicates->count,
                '/local/admincockpit/duplicateemails.php',
                $duplicates->count > 0 ? 'warning' : 'ok'
            ),
            $this->make_signal_tile(
                get_string('courseswithoutenddate', 'local_admincockpit'),
                $noenddate->count,
                '/local/admincockpit/courseswithoutenddate.php',
                $noenddate->count > 0 ? 'warning' : 'ok'
            ),
            $this->make_signal_tile(
                get_string('signal_security', 'local_admincockpit'),
                $this->format_security_value($security),
                '/report/security/index.php',
                $security->error > 0 ? 'error' : ($security->warning > 0 ? 'warning' : 'ok'),
                '',
                $output->help_icon('signal_security', 'local_admincockpit')
            ),
            $this->make_signal_tile(
                get_string('signal_cron', 'local_admincockpit'),
                $this->format_cron_value($cron),
                '/admin/tool/task/scheduledtasks.php',
                $this->cron_severity($cron),
                $cron->lastrunat > 0 ? userdate($cron->lastrunat) : '',
                $output->help_icon('signal_cron', 'local_admincockpit')
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
     * @param string $helpicon optional pre-rendered help icon HTML (see
     *        $OUTPUT->help_icon())
     * @return \stdClass label, value, valuetitle, helpicon, url,
     *         severitybgclass, severitytextclass, severityiconclass,
     *         severitylabel
     */
    private function make_signal_tile(
        string $label,
        $value,
        string $path,
        string $severity,
        string $valuetitle = '',
        string $helpicon = ''
    ): \stdClass {
        // Colour + text-contrast pairing matches core\check\result's own bg-success/text-white,
        // bg-warning/text-dark, bg-danger/text-white convention; icon glyph names match core's
        // i/valid and i/warning mapping, minus their colour classes (see docblock above).
        $severitymap = [
            'ok' => [
                'bgclass' => 'success', 'textclass' => 'text-white', 'icon' => 'fa-check', 'labelkey' => 'ok',
            ],
            'warning' => [
                'bgclass' => 'warning', 'textclass' => 'text-dark', 'icon' => 'fa-triangle-exclamation',
                'labelkey' => 'warning',
            ],
            'error' => [
                'bgclass' => 'danger', 'textclass' => 'text-white', 'icon' => 'fa-triangle-exclamation',
                'labelkey' => 'error',
            ],
        ];
        $map = $severitymap[$severity] ?? $severitymap['error'];

        return (object) [
            'label' => $label,
            'value' => $value,
            'valuetitle' => $valuetitle,
            'helpicon' => $helpicon,
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
            get_string('signal_security_ok', 'local_admincockpit', $security->ok),
            get_string('signal_security_warning', 'local_admincockpit', $security->warning),
        ];
        if ($security->error > 0) {
            $parts[] = get_string('signal_security_error', 'local_admincockpit', $security->error);
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
            ? get_string('signal_cron_lastrun', 'local_admincockpit', format_time(time() - $cron->lastrunat))
            : get_string('signal_cron_neverrun', 'local_admincockpit');

        return $lastrun . ' ' . get_string('signal_cron_failedtasks', 'local_admincockpit', $cron->failedtasks24h);
    }

    /**
     * Decides the cron tile's severity.
     *
     * Own heuristic (not a reuse of \tool_task\check\cronrunning's verdict,
     * which is a separate report page one click away), but aligned with it
     * on one point: any recent failure, or cron never having run even once,
     * is an error - cronrunning's own get_result() computes its delta as
     * time() - get_config('tool_task', 'lastcronstart'), and an unset config
     * (never run) makes that delta huge enough to always exceed its own
     * DAYSECS threshold, i.e. core's most severe (CRITICAL) state. Merely
     * being overdue by more than $CFG->expectedcronfrequency (the same
     * config core's own check reads) having run before is a lower-severity
     * warning - a transient blip is not the same problem as cron.php never
     * having been set up at all.
     *
     * @param \stdClass $cron as returned by health_signals::cron_status()
     * @return string 'ok', 'warning', or 'error'
     */
    private function cron_severity(\stdClass $cron): string {
        global $CFG;

        if ($cron->failedtasks24h > 0 || $cron->lastrunat === 0) {
            return 'error';
        }

        $expectedfrequency = $CFG->expectedcronfrequency ?? MINSECS;
        if ((time() - $cron->lastrunat) > $expectedfrequency + MINSECS) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Builds the "Navigation" boxes (SPEC section 5) from the 'navitems'
     * setting (Schritt 7h: configurable instead of hardcoded).
     *
     * Groups and their items come from navitems_parser::parse() in the
     * order they first appear in the setting. Items with a configured
     * capability are only included if the current user actually has it
     * (system context - this dashboard is deliberately for system-wide
     * administrators only, see SPEC section 9's "explicitly out of scope").
     * Groups that end up with zero visible items (all filtered out, or none
     * ever parsed for that group) are dropped rather than shown as an empty
     * card with a heading and nothing under it.
     *
     * @return array of stdClass: title, items (array of stdClass: label, url)
     */
    private function export_nav_groups(): array {
        $rawnavitems = get_config('local_admincockpit', 'navitems');
        if ($rawnavitems === false) {
            // Never saved (e.g. right after this setting was added by an upgrade) - not the same as an
            // admin having since deliberately emptied it, which must stay empty (see 'nonavitemsconfigured'
            // in export_for_template()). A loose ?: check would conflate the two, unlike here.
            $rawnavitems = navitems_parser::default_value();
        }

        $navgroups = [];
        foreach (navitems_parser::parse((string) $rawnavitems)->groups as $group) {
            $items = [];
            foreach ($group->items as $item) {
                if ($item->capability !== '' && !has_capability($item->capability, \core\context\system::instance())) {
                    continue;
                }
                $items[] = (object) ['label' => $item->label, 'url' => $item->url];
            }
            if (!empty($items)) {
                $navgroups[] = (object) ['title' => $group->title, 'items' => $items];
            }
        }

        return $navgroups;
    }
}

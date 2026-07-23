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
 * Main admin cockpit page.
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_admincockpit\event\dashboard_viewed;
use local_admincockpit\output\dashboard_page;

admin_externalpage_setup('local_admincockpit');

// The dropdown only ever offers these four values (matches the settings page); an untrusted GET
// value outside this list is ignored, falling back to the configured default. The "?: 180" guards
// against 'timerangedays' never having been saved (get_config() === false, e.g. right after install
// on a site that never visited the settings page) - without it, (int) false is 0, which isn't in
// $allowedtimeranges either, so the allowlist check below would "fall back" to that same broken 0.
$allowedtimeranges = [30, 90, 180, 360];
$defaulttimerangedays = (int) (get_config('local_admincockpit', 'timerangedays') ?: 180);
$timerangedays = optional_param('timerangedays', $defaulttimerangedays, PARAM_INT);
if (!in_array($timerangedays, $allowedtimeranges, true)) {
    $timerangedays = $defaulttimerangedays;
}

// Same capability as the page itself (admin_externalpage_setup() above already enforced it) -
// a separate capability just for "refresh the numbers now" would be over-engineering for an
// action anyone who can already see the dashboard should reasonably be able to trigger.
if (optional_param('purgecache', 0, PARAM_BOOL)) {
    require_sesskey();
    \core_cache\cache::make('local_admincockpit', 'dashboarddata')->purge();
    redirect(
        new \core\url('/local/admincockpit/index.php', ['timerangedays' => $timerangedays]),
        get_string('cachepurged', 'local_admincockpit'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Fired here, not right after admin_externalpage_setup() above, so a purge-cache request
// (which redirects away without ever rendering the dashboard) doesn't log a spurious "viewed"
// event - the capability check already happened via admin_externalpage_setup().
dashboard_viewed::create([
    'context' => \core\context\system::instance(),
    'other' => ['page' => 'index.php'],
])->trigger();

$page = new dashboard_page($timerangedays);
$renderer = $PAGE->get_renderer('local_admincockpit');

echo $OUTPUT->header();
echo $renderer->render_dashboard_page($page);
echo $OUTPUT->footer();

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
 * Main admin dashboard page.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_admindashboard\output\dashboard_page;

admin_externalpage_setup('local_admindashboard');

// The dropdown only ever offers these four values (matches the settings page); an untrusted GET
// value outside this list is ignored, falling back to the configured default.
$allowedtimeranges = [30, 90, 180, 360];
$defaulttimerangedays = (int) get_config('local_admindashboard', 'timerangedays');
$timerangedays = optional_param('timerangedays', $defaulttimerangedays, PARAM_INT);
if (!in_array($timerangedays, $allowedtimeranges, true)) {
    $timerangedays = $defaulttimerangedays;
}

$page = new dashboard_page($timerangedays);
$renderer = $PAGE->get_renderer('local_admindashboard');

echo $OUTPUT->header();
echo $renderer->render_dashboard_page($page);
echo $OUTPUT->footer();

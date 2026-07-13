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
 * Ad-hoc visual check of the dashboard's empty state (Schritt 10): renders
 * the full page with 'activeschools' temporarily forced to empty, to catch
 * the "freshly installed instance, nothing configured yet" scenario without
 * touching the running instance's actual configuration.
 *
 *   docker exec -it claude-moodle-1 php local/admindashboard/cli/verify_emptystate.php
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use local_admindashboard\output\dashboard_page;

$original = get_config('local_admindashboard', 'activeschools');
set_config('activeschools', '', 'local_admindashboard');

try {
    $renderer = $PAGE->get_renderer('local_admindashboard');
    $page = new dashboard_page(180);
    $context = $page->export_for_template($renderer);

    echo 'noschoolsconfigured: ' . var_export($context['noschoolsconfigured'], true) . PHP_EOL;
    echo 'schools: ' . count($context['schools']) . PHP_EOL;
    echo 'usermetrics (should stay unaffected): ' . count($context['usermetrics']) . PHP_EOL;
    echo 'healthsignals (should stay unaffected): ' . count($context['healthsignals']) . PHP_EOL;

    // Renders through the real mustache template, same as index.php does - catches template-side
    // breakage (e.g. a loop assuming at least one school) that export_for_template() alone would not.
    $renderer->render_dashboard_page($page);
    echo 'Template rendered without error.' . PHP_EOL;
} finally {
    set_config('activeschools', $original, 'local_admindashboard');
}

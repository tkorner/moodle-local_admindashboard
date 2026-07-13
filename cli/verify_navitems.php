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
 * Ad-hoc visual check of navitems_parser::parse() against the running
 * instance's actual 'navitems' setting (or the built-in default, if it has
 * never been saved):
 *   docker exec -it claude-moodle-1 php local/admindashboard/cli/verify_navitems.php
 *
 * Does not simulate capability filtering - that needs a real $USER and
 * happens in dashboard_page.php at render time, not here.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use local_admindashboard\navitems_parser;

$raw = get_config('local_admindashboard', 'navitems');
if ($raw === false) {
    echo "'navitems' has never been saved - showing the built-in default value instead." . PHP_EOL;
    $raw = navitems_parser::default_value();
} else if ($raw === '') {
    echo "'navitems' is saved as an empty string - the dashboard will show its empty state." . PHP_EOL;
}

echo '--- raw value ---' . PHP_EOL;
echo $raw . PHP_EOL;

$parsed = navitems_parser::parse((string) $raw);

echo PHP_EOL . '--- parsed ---' . PHP_EOL;
foreach ($parsed->groups as $group) {
    echo "== {$group->title} ==" . PHP_EOL;
    foreach ($group->items as $item) {
        $capability = $item->capability !== '' ? " (capability: {$item->capability})" : '';
        echo "  - {$item->label} -> {$item->url}{$capability}" . PHP_EOL;
    }
}

echo PHP_EOL . "Unparseable lines skipped: {$parsed->errorlines}" . PHP_EOL;

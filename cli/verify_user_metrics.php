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
 * Ad-hoc visual check of user_metrics::get_metrics() against the real user
 * accounts of the running instance.
 *
 * Not a substitute for tests/metrics/user_metrics_test.php - this script
 * exists to let you eyeball the current instance's numbers immediately, e.g.:
 *   docker exec -it claude-moodle-1 php local/admincockpit/cli/verify_user_metrics.php
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use local_admincockpit\metrics\user_metrics;

$timerangedays = (int) (get_config('local_admincockpit', 'timerangedays') ?: 180);

$result = user_metrics::get_metrics($timerangedays);

echo "== user_metrics (timerangedays = {$timerangedays}) ==" . PHP_EOL;
var_export($result);
echo PHP_EOL;

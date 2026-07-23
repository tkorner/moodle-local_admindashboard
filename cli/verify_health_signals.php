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
 * Ad-hoc visual check of all four health_signals methods against the real
 * data of the running instance.
 *
 * Not a substitute for tests/metrics/health_signals_test.php - this script
 * exists to let you eyeball the current instance's numbers immediately, e.g.:
 *   docker exec -it claude-moodle-1 php local/admincockpit/cli/verify_health_signals.php
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use local_admincockpit\metrics\health_signals;

echo '== duplicate_emails ==' . PHP_EOL;
var_export(health_signals::duplicate_emails());
echo PHP_EOL;

echo '== courses_without_enddate ==' . PHP_EOL;
var_export(health_signals::courses_without_enddate());
echo PHP_EOL;

echo '== security_overview_summary ==' . PHP_EOL;
var_export(health_signals::security_overview_summary());
echo PHP_EOL;

echo '== cron_status ==' . PHP_EOL;
$cronstatus = health_signals::cron_status();
var_export($cronstatus);
echo PHP_EOL;
echo 'lastrunat as date: ' . ($cronstatus->lastrunat ? date('c', $cronstatus->lastrunat) : 'never') . PHP_EOL;

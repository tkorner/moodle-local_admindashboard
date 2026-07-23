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
 * Ad-hoc visual check of school_metrics::get_metrics() against a real school
 * (cohort + top-level category pair) of the running instance.
 *
 * Not a substitute for tests/metrics/school_metrics_test.php - this script
 * exists to let you eyeball the current instance's numbers immediately for
 * every fully matched school code, e.g.:
 *   docker exec -it claude-moodle-1 php local/admincockpit/cli/verify_school_metrics.php
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use local_admincockpit\metrics\school_metrics;
use local_admincockpit\school_matcher;

$timerangedays = (int) (get_config('local_admincockpit', 'timerangedays') ?: 180);
$matches = school_matcher::get_matches();

if (empty($matches->matched)) {
    echo 'No fully matched school codes found (no cohort + top-level category '
        . 'sharing an idnumber) - nothing to verify.' . PHP_EOL;
    exit(0);
}

foreach ($matches->matched as $idnumber => $school) {
    echo "== {$idnumber} (cohortid={$school->cohortid}, categoryid={$school->categoryid}, "
        . "timerangedays={$timerangedays}) ==" . PHP_EOL;
    var_export(school_metrics::get_metrics($school->cohortid, $school->categoryid, $timerangedays));
    echo PHP_EOL;
}

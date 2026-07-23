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
 * Ad-hoc visual check of school_matcher::get_matches() against the real
 * cohorts/categories of the running instance.
 *
 * Not a substitute for tests/school_matcher_test.php - this script exists to
 * let you eyeball the current instance's matching result immediately, e.g.:
 *   docker exec -it claude-moodle-1 php local/admincockpit/cli/verify_school_matcher.php
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

use local_admincockpit\school_matcher;

$result = school_matcher::get_matches();

echo '== matched (idnumber => cohortid/categoryid) ==' . PHP_EOL;
var_export($result->matched);
echo PHP_EOL;

echo '== cohortonly (cohort without a matching top-level category) ==' . PHP_EOL;
var_export($result->cohortonly);
echo PHP_EOL;

echo '== categoryonly (top-level category without a matching cohort) ==' . PHP_EOL;
var_export($result->categoryonly);
echo PHP_EOL;

printf(
    'Summary: %d matched, %d cohort-only, %d category-only.' . PHP_EOL,
    count($result->matched),
    count($result->cohortonly),
    count($result->categoryonly)
);

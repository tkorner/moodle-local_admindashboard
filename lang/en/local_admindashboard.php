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
 * Strings for component 'local_admindashboard', language 'en'.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activeschools'] = 'Active school codes';
$string['activeschools_desc'] = 'Only fully matched codes (cohort and top-level category share the same idnumber) can be selected here. They are shown on the dashboard.';
$string['activeschools_option'] = '{$a->idnumber} ({$a->cohortname} / {$a->categoryname})';
$string['admindashboard:view'] = 'View the admin dashboard';
$string['backtodashboard'] = 'Back to dashboard';
$string['boostunionsettings'] = 'Boost Union theme settings';
$string['courseswithoutenddate'] = 'Courses without an end date';
$string['courseswithoutenddate_none'] = 'No courses without an end date - nothing to report here.';
$string['duplicateemails'] = 'Duplicate email addresses';
$string['duplicateemails_none'] = 'No duplicate email addresses found - nothing to report here.';
$string['mergeusershint'] = 'This list is a starting point for identifying accounts to merge. The '
    . '"Merge user accounts" admin tool (tool_mergeusers) is not installed on this instance, so no direct '
    . 'link is shown here - install it to actually merge two accounts.';
$string['mergeusershint_link'] = 'This list is a starting point for identifying accounts to merge. Use {$a} to actually merge two accounts.';
$string['mergeuserslinktext'] = 'Merge user accounts';
$string['navgroup_courses'] = 'Course management';
$string['navgroup_reports'] = 'Reports/Logs';
$string['navgroup_system'] = 'System';
$string['navgroup_theme'] = 'Theme/Appearance';
$string['navgroup_users'] = 'User management';
$string['noschoolsconfigured'] = '0 active school codes are configured.';
$string['noschoolsconfigured_linktext'] = 'Go to settings';
$string['onesided_categoryonly'] = '{$a}: a top-level category exists, but no matching cohort';
$string['onesided_cohortonly'] = '{$a}: a cohort exists, but no matching top-level category';
$string['onesided_intro'] = 'These codes are only maintained on one side (cohort or category), not both, and cannot be selected as an active school:';
$string['onesided_none'] = 'All cohorts and top-level categories with an idnumber are fully matched - nothing to report here.';
$string['onesidedwarning'] = 'One-sided matches';
$string['pluginname'] = 'Admin Dashboard';
$string['schoolcard_coursemanagement'] = 'Course management';
$string['schooltile_activemembers'] = 'Active members';
$string['schooltile_coursecount'] = 'Courses';
$string['schooltile_membercount'] = 'Members';
$string['schooltile_newcourses'] = 'New courses';
$string['schooltile_newmembers'] = 'New members';
$string['section_globalusers'] = 'Global user metrics';
$string['section_healthsignals'] = 'Health signals';
$string['section_navigation'] = 'Navigation';
$string['section_schools'] = 'Per school';
$string['signal_cron'] = 'Cron status';
$string['signal_cron_failedtasks'] = '{$a} failed task(s) in the last 24h.';
$string['signal_cron_lastrun'] = 'Last run: {$a}.';
$string['signal_cron_neverrun'] = 'Cron has never run.';
$string['signal_security'] = 'Security overview';
$string['signal_security_value'] = '{$a->ok} ok / {$a->warning} warning / {$a->error} error';
$string['tile_activeusers'] = 'Active users';
$string['tile_newusers'] = 'New users';
$string['tile_totalusers'] = 'Total users';
$string['timerange_label'] = 'Time range:';
$string['timerange_submit'] = 'Update';
$string['timerangedays'] = 'Time range for "new in period" counts';
$string['timerangedays_desc'] = 'Used by the dashboard to determine which users, cohort members, and courses count as "new". Can be temporarily overridden on the dashboard page itself without changing this default.';

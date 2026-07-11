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
 * Drill-down list for the "duplicate emails" health signal.
 *
 * tool_mergeusers is not shipped with core, so it may not be installed on
 * every instance. This page checks via core\plugin_manager and only shows a
 * real link to it when it is actually present, falling back to a plain-text
 * hint otherwise - avoids linking to a URL that might 404.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_admindashboard\metrics\health_signals;

require_login();
$context = \core\context\system::instance();
require_capability('local/admindashboard:view', $context);

$PAGE->set_url(new \core\url('/local/admindashboard/duplicateemails.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('duplicateemails', 'local_admindashboard'));
$PAGE->set_heading(get_string('pluginname', 'local_admindashboard'));

$signal = health_signals::duplicate_emails();

$mergeusersinfo = \core\plugin_manager::instance()->get_plugin_info('tool_mergeusers');
if ($mergeusersinfo) {
    $mergeuserslink = \core\output\html_writer::link(
        new \core\url('/admin/tool/mergeusers/index.php'),
        get_string('mergeuserslinktext', 'local_admindashboard')
    );
    $mergeusershint = get_string('mergeusershint_link', 'local_admindashboard', $mergeuserslink);
} else {
    $mergeusershint = get_string('mergeusershint', 'local_admindashboard');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('duplicateemails', 'local_admindashboard'));
echo $OUTPUT->notification($mergeusershint, 'info');

if (empty($signal->details)) {
    echo $OUTPUT->notification(get_string('duplicateemails_none', 'local_admindashboard'), 'success');
} else {
    $table = new \core_table\output\html_table();
    $table->head = [get_string('fullnameuser', 'core'), get_string('email', 'core')];
    foreach ($signal->details as $row) {
        $profileurl = new \core\url('/user/profile.php', ['id' => $row->userid]);
        $table->data[] = [
            \core\output\html_writer::link($profileurl, $row->fullname),
            $row->email,
        ];
    }
    echo \core\output\html_writer::table($table);
}

echo \core\output\html_writer::link(
    new \core\url('/local/admindashboard/index.php'),
    get_string('backtodashboard', 'local_admindashboard')
);

echo $OUTPUT->footer();

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
 * Drill-down list for the "courses without an enddate" health signal.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_admindashboard\event\dashboard_viewed;
use local_admindashboard\metrics\health_signals;

require_login();
$context = \core\context\system::instance();
require_capability('local/admindashboard:view', $context);

$PAGE->set_url(new \core\url('/local/admindashboard/courseswithoutenddate.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('courseswithoutenddate', 'local_admindashboard'));
$PAGE->set_heading(get_string('pluginname', 'local_admindashboard'));

dashboard_viewed::create([
    'context' => $context,
    'other' => ['page' => 'courseswithoutenddate.php'],
])->trigger();

$signal = health_signals::courses_without_enddate();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseswithoutenddate', 'local_admindashboard'));

if (empty($signal->details)) {
    echo $OUTPUT->notification(get_string('courseswithoutenddate_none', 'local_admindashboard'), 'success');
} else {
    if ($signal->detailstruncated) {
        echo $OUTPUT->notification(
            get_string('courseswithoutenddate_truncated', 'local_admindashboard', $signal->count),
            'info'
        );
    }

    $table = new \core_table\output\html_table();
    $table->head = [get_string('course', 'core'), get_string('category', 'core')];
    foreach ($signal->details as $row) {
        $editurl = new \core\url('/course/edit.php', ['id' => $row->courseid]);
        $coursecontext = \core\context\course::instance($row->courseid);
        $categorycontext = \core\context\coursecat::instance($row->categoryid);
        $table->data[] = [
            \core\output\html_writer::link($editurl, format_string($row->fullname, true, ['context' => $coursecontext])),
            format_string($row->categoryname, true, ['context' => $categorycontext]),
        ];
    }
    echo \core\output\html_writer::table($table);
}

echo \core\output\html_writer::link(
    new \core\url('/local/admindashboard/index.php'),
    get_string('backtodashboard', 'local_admindashboard')
);

echo $OUTPUT->footer();

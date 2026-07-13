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
 * Registers the dashboard report page and the plugin configuration page.
 *
 * The dashboard itself is an admin_externalpage (Site administration >
 * Reports) because it renders a live view, not configuration values. It is
 * registered unconditionally (not behind $hassiteconfig) - only its own
 * 'local/admindashboard:view' capability should gate access, matching
 * report_security/settings.php's exact same pattern: a plain "reports"
 * page has no reason to additionally require moodle/site:config. Wrapping
 * it in $hassiteconfig too (an earlier mistake here) meant a Manager with
 * local/admindashboard:view but without moodle/site:config could never
 * actually reach index.php, even though the capability model promises
 * otherwise. The admin_settingpage below DOES need $hassiteconfig, since it
 * holds real config fields that only site:config should be able to edit.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage(
    'local_admindashboard',
    get_string('pluginname', 'local_admindashboard'),
    new moodle_url('/local/admindashboard/index.php'),
    'local/admindashboard:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_admindashboard_settings',
        get_string('pluginname', 'local_admindashboard')
    );

    // Building the school choice list and the one-sided-match warning both need a live
    // idnumber match, so only do it when the full settings page is actually rendered
    // (not on every admin tree build, e.g. for navigation or search).
    if ($ADMIN->fulltree) {
        // The default is a literal value, not a get_string() lookup - it is the actual stored
        // config value (what this instance currently calls the grouping), not translatable UI
        // copy, so it must not vary by the admin's interface language.
        $settings->add(new admin_setting_configtext(
            'local_admindashboard/groupinglabel',
            get_string('groupinglabel', 'local_admindashboard'),
            get_string('groupinglabel_desc', 'local_admindashboard'),
            'Schule',
            PARAM_TEXT
        ));
        $grouping = (string) get_config('local_admindashboard', 'groupinglabel') ?: 'Schule';

        $settings->add(new admin_setting_configselect(
            'local_admindashboard/timerangedays',
            get_string('timerangedays', 'local_admindashboard'),
            get_string('timerangedays_desc', 'local_admindashboard'),
            180,
            [
                30 => get_string('numdays', 'core', 30),
                90 => get_string('numdays', 'core', 90),
                180 => get_string('numdays', 'core', 180),
                360 => get_string('numdays', 'core', 360),
            ]
        ));

        $matches = \local_admindashboard\school_matcher::get_matches();

        $schoolchoices = [];
        foreach ($matches->matched as $idnumber => $school) {
            $schoolchoices[$idnumber] = get_string('activeschools_option', 'local_admindashboard', (object) [
                'idnumber' => $idnumber,
                'cohortname' => $school->cohortname,
                'categoryname' => $school->categoryname,
            ]);
        }

        $settings->add(new admin_setting_configmultiselect(
            'local_admindashboard/activeschools',
            get_string('activeschools', 'local_admindashboard', $grouping),
            get_string('activeschools_desc', 'local_admindashboard'),
            [],
            $schoolchoices
        ));

        $onesided = [];
        foreach ($matches->cohortonly as $idnumber => $school) {
            $onesided[] = get_string('onesided_cohortonly', 'local_admindashboard', s($idnumber));
        }
        foreach ($matches->categoryonly as $idnumber => $school) {
            $onesided[] = get_string('onesided_categoryonly', 'local_admindashboard', s($idnumber));
        }

        if (empty($onesided)) {
            $warningtext = get_string('onesided_none', 'local_admindashboard');
        } else {
            $warningtext = get_string('onesided_intro', 'local_admindashboard', $grouping)
                . \core\output\html_writer::alist($onesided);
        }

        $settings->add(new admin_setting_description(
            'local_admindashboard/onesidedwarning',
            get_string('onesidedwarning', 'local_admindashboard'),
            $warningtext
        ));
    }

    $ADMIN->add('localplugins', $settings);
}

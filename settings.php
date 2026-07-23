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
 * 'local/admincockpit:view' capability should gate access, matching
 * report_security/settings.php's exact same pattern: a plain "reports"
 * page has no reason to additionally require moodle/site:config. Wrapping
 * it in $hassiteconfig too (an earlier mistake here) meant a Manager with
 * local/admincockpit:view but without moodle/site:config could never
 * actually reach index.php, even though the capability model promises
 * otherwise. The admin_settingpage below DOES need $hassiteconfig, since it
 * holds real config fields that only site:config should be able to edit.
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage(
    'local_admincockpit',
    get_string('pluginname', 'local_admincockpit'),
    new moodle_url('/local/admincockpit/index.php'),
    'local/admincockpit:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_admincockpit_settings',
        get_string('pluginname', 'local_admincockpit')
    );

    // Building the school choice list and the one-sided-match warning both need a live
    // idnumber match, so only do it when the full settings page is actually rendered
    // (not on every admin tree build, e.g. for navigation or search).
    if ($ADMIN->fulltree) {
        // The default is a literal value, not a get_string() lookup - it is the actual stored
        // config value (what this instance currently calls the grouping), not translatable UI
        // copy, so it must not vary by the admin's interface language.
        $settings->add(new admin_setting_configtext(
            'local_admincockpit/groupinglabel',
            get_string('groupinglabel', 'local_admincockpit'),
            get_string('groupinglabel_desc', 'local_admincockpit'),
            \local_admincockpit\school_matcher::DEFAULT_GROUPING_LABEL,
            PARAM_TEXT
        ));
        $grouping = (string) get_config('local_admincockpit', 'groupinglabel')
            ?: \local_admincockpit\school_matcher::DEFAULT_GROUPING_LABEL;

        $settings->add(new admin_setting_configselect(
            'local_admincockpit/timerangedays',
            get_string('timerangedays', 'local_admincockpit'),
            get_string('timerangedays_desc', 'local_admincockpit'),
            180,
            [
                30 => get_string('numdays', 'core', 30),
                90 => get_string('numdays', 'core', 90),
                180 => get_string('numdays', 'core', 180),
                360 => get_string('numdays', 'core', 360),
            ]
        ));

        $matches = \local_admincockpit\school_matcher::get_matches();

        $schoolchoices = [];
        foreach ($matches->matched as $idnumber => $school) {
            $schoolchoices[$idnumber] = get_string('activeschools_option', 'local_admincockpit', (object) [
                'idnumber' => $idnumber,
                'cohortname' => $school->cohortname,
                'categoryname' => $school->categoryname,
            ]);
        }

        $settings->add(new admin_setting_configmultiselect(
            'local_admincockpit/activeschools',
            get_string('activeschools', 'local_admincockpit', $grouping),
            get_string('activeschools_desc', 'local_admincockpit'),
            [],
            $schoolchoices
        ));

        $onesided = [];
        foreach ($matches->cohortonly as $idnumber => $school) {
            $onesided[] = get_string('onesided_cohortonly', 'local_admincockpit', s($idnumber));
        }
        foreach ($matches->categoryonly as $idnumber => $school) {
            $onesided[] = get_string('onesided_categoryonly', 'local_admincockpit', s($idnumber));
        }

        if (empty($onesided)) {
            $warningtext = get_string('onesided_none', 'local_admincockpit');
        } else {
            // The German onesided_intro string deliberately does not use {$a}: "aktive Schule" vs.
            // "aktiver Standort" vs. "aktive Abteilung" need different adjective endings depending on
            // the configured word's grammatical gender, which a free-text setting can't guarantee. The
            // $grouping argument below is simply ignored by that string (get_string() allows passing an
            // unused $a). A comment explaining this can't live in the lang file itself - Moodle's
            // LangFilesOrdering sniff flags any comment interspersed between $string[] lines.
            $warningtext = get_string('onesided_intro', 'local_admincockpit', $grouping)
                . \core\output\html_writer::alist($onesided);
        }

        $settings->add(new admin_setting_description(
            'local_admincockpit/onesidedwarning',
            get_string('onesidedwarning', 'local_admincockpit'),
            $warningtext
        ));

        // Config never having been saved (get_config() === false, e.g. right after this setting was
        // added by an upgrade) is treated the same as "use the built-in default" - not the same as an
        // admin having since deliberately saved the textarea empty, which must stay empty (Schritt 10-
        // style empty state, see dashboard_page.php). A loose ?: check would conflate the two.
        $navitemsraw = get_config('local_admincockpit', 'navitems');
        if ($navitemsraw === false) {
            $navitemsraw = \local_admincockpit\navitems_parser::default_value();
        }
        $parsed = \local_admincockpit\navitems_parser::parse($navitemsraw);
        if ($parsed->errorlines > 0) {
            $settings->add(new admin_setting_description(
                'local_admincockpit/navitems_parseerror',
                '',
                get_string('navitems_parseerror', 'local_admincockpit', $parsed->errorlines)
            ));
        }

        // The navitems_parser::default_value() call below builds its labels via get_string() in
        // whatever language is active for this request. That's only "live" (reflecting the admin's language)
        // until the very first time this textarea is actually saved - saving freezes it as plain text
        // in whichever language rendered the form at that moment, exactly like $CFG->custommenuitems
        // itself. Documented here rather than fixed: matching core's own custommenuitems convention is
        // more consistent than inventing a per-request re-localisation scheme for one setting.
        $settings->add(new admin_setting_configtextarea(
            'local_admincockpit/navitems',
            get_string('navitems', 'local_admincockpit'),
            get_string('navitems_desc', 'local_admincockpit'),
            \local_admincockpit\navitems_parser::default_value(),
            PARAM_RAW
        ));
    }

    $ADMIN->add('localplugins', $settings);
}

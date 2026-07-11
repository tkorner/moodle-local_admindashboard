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
 * Strings for component 'local_admindashboard', language 'de'.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activeschools'] = 'Aktive Schul-Kürzel';
$string['activeschools_desc'] = 'Nur vollständig gepaarte Kürzel (Kohorte und Top-Level-Kategorie mit identischer idnumber) stehen hier zur Auswahl. Sie werden auf dem Dashboard angezeigt.';
$string['activeschools_option'] = '{$a->idnumber} ({$a->cohortname} / {$a->categoryname})';
$string['admindashboard:view'] = 'Admin-Dashboard ansehen';
$string['backtodashboard'] = 'Zurück zum Dashboard';
$string['courseswithoutenddate'] = 'Kurse ohne Enddatum';
$string['courseswithoutenddate_none'] = 'Keine Kurse ohne Enddatum gefunden - hier gibt es nichts zu melden.';
$string['duplicateemails'] = 'Doppelte E-Mail-Adressen';
$string['duplicateemails_none'] = 'Keine doppelten E-Mail-Adressen gefunden - hier gibt es nichts zu melden.';
$string['mergeusershint'] = 'Diese Liste ist ein Ausgangspunkt, um zusammenzuführende Konten zu identifizieren. '
    . 'Das Admin-Tool "Merge user accounts" (tool_mergeusers) ist auf dieser Instanz nicht installiert, daher '
    . 'wird hier kein direkter Link angezeigt - installiere es, um Konten tatsächlich zusammenzuführen.';
$string['mergeusershint_link'] = 'Diese Liste ist ein Ausgangspunkt, um zusammenzuführende Konten zu identifizieren. Nutze {$a} für die eigentliche Zusammenführung.';
$string['mergeuserslinktext'] = 'Merge user accounts';
$string['onesided_categoryonly'] = '{$a}: Top-Level-Kategorie vorhanden, aber keine passende Kohorte';
$string['onesided_cohortonly'] = '{$a}: Kohorte vorhanden, aber keine passende Top-Level-Kategorie';
$string['onesided_intro'] = 'Diese Kürzel sind nur einseitig gepflegt (Kohorte oder Kategorie, nicht beides) und können nicht als aktive Schule ausgewählt werden:';
$string['onesided_none'] = 'Alle Kohorten und Top-Level-Kategorien mit idnumber sind vollständig gepaart - hier gibt es nichts zu melden.';
$string['onesidedwarning'] = 'Einseitige Zuordnungen';
$string['pluginname'] = 'Admin Dashboard';
$string['timerangedays'] = 'Zeitraum für Neu-Zählungen';
$string['timerangedays_desc'] = 'Bestimmt, welche Nutzer, Kohorten-Mitglieder und Kurse auf dem Dashboard als "neu" gelten. Kann auf der Dashboard-Seite selbst temporär überschrieben werden, ohne diesen Standardwert zu ändern.';

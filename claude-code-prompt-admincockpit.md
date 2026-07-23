# Claude Code Prompt – local_admincockpit

Diese Datei enthält die Schritt-für-Schritt-Sequenz für die Implementierung, basierend auf `SPEC-admincockpit.md`. Jeder Schritt ist als eigener Prompt für Claude Code gedacht – erst nach Bestätigung/Review des einen Schritts zum nächsten übergehen.

Vor Schritt 1: `SPEC-admincockpit.md` in dasselbe Verzeichnis wie `CLAUDE.md` legen, damit Claude Code beides als Kontext hat. Ein `CLAUDE.md` für dieses Projekt sollte enthalten: Zielumgebung (Moodle 5.2.x in Docker, `/var/www/html/public/local/`), Coding-Standard (core-contribution-taugliche Qualität, Moodle Coding Style), und den Hinweis, dass dies ein Reporting-/Navigations-Plugin ohne eigenes DB-Schema ist.

**Testing-Hinweis (Stand: kein GUI-Zugriff, PHPUnit lokal nicht installierbar):** Statt lokaler PHPUnit-Ausführung werden zwei Ebenen genutzt:
1. **CLI-Smoke-Skripte** unter `cli/verify_*.php` für sofortiges Feedback während der Session (`docker exec -it claude-moodle-1 php /var/www/html/local/admincockpit/cli/verify_*.php`)
2. **GitHub Actions mit `moodlehq/moodle-plugin-ci`** für die echte PHPUnit-/Behat-/Codechecker-Ausführung bei jedem Push (siehe Schritt 0b)

PHPUnit-Testdateien werden trotzdem geschrieben (laufen nur nicht lokal, sondern über CI) – nicht weglassen.

**Fortschritt:** Schritt 0 bis 8 sowie 9 und 10 sind umgesetzt (siehe Release 1.0.0 und 1.1.0). Offen: Schritt 7h (Navigation konfigurierbar machen), 11 (Plugin-Directory-Voraussetzungen), 12 (Abschluss-Review).

---

## Schritt 0 – Plugin-Grundgerüst ✅ erledigt

```
Erstelle das Grundgerüst für ein neues Moodle-Plugin vom Typ "local", Komponente
local_admincockpit, für Moodle 5.2.

Erstelle:
- version.php (component local_admincockpit, aktuelle Version, requires für Moodle 5.2)
- lib.php (leer/Platzhalter, mit den üblichen Callback-Stubs falls später benötigt)
- classes/ (leeres Verzeichnis mit .gitkeep)
- lang/en/local_admincockpit.php mit Basiseinträgen (pluginname)
- lang/de/local_admincockpit.php mit den deutschen Übersetzungen der gleichen Strings
- settings.php: registriert eine eigene Admin-Seite unter Site administration > Reports
  (admin_externalpage, nicht admin_settingpage, da es keine reinen Konfigurationswerte
  sondern eine Dashboard-Ansicht ist) plus eine separate Settings-Seite für die
  Konfiguration (Zeitraum, aktive Schul-Kürzel) unter Site administration > Plugins >
  Local plugins > Admin Cockpit
- db/access.php mit der Capability local/admincockpit:view
  (CONTEXT_SYSTEM, archetypes: manager => CAP_ALLOW)

Folge den Moodle-Coding-Guidelines und der Standard-Verzeichnisstruktur für local-Plugins.
Committe noch nichts, ich möchte das Ergebnis erst reviewen.
```

---

## Schritt 1 – Kürzel-Erkennung (Kohorte ↔ Kategorie Matching) ✅ erledigt

```
Implementiere die Klasse classes/school_matcher.php (Namespace local_admincockpit).

Aufgabe der Klasse:
- Findet alle system-weiten Kohorten (cohort-Tabelle, contextid = System-Context) mit
  nicht-leerer idnumber
- Findet alle Top-Level-Kurskategorien (core_course_category, parent = 0) mit
  nicht-leerer idnumber
- Liefert drei Listen zurück:
  1. "matched": Kürzel, bei denen beide Seiten existieren (idnumber-Übereinstimmung),
     inkl. Kohorten-ID und Kategorie-ID
  2. "cohort_only": Kürzel mit Kohorte, aber ohne passende Top-Level-Kategorie
  3. "category_only": Kürzel mit Top-Level-Kategorie, aber ohne passende Kohorte

Nutze die Moodle Data Manipulation API ($DB->get_records_sql oder get_records mit
Bedingungen), keine rohen mysqli-Aufrufe. Schreibe dazu einen PHPUnit-Test
(tests/school_matcher_test.php), der mit dem Moodle-Testdaten-Generator
(get_data_generator()->create_cohort(), create_category()) mindestens folgende Fälle
abdeckt: vollständiges Paar, Kohorte ohne Kategorie, Kategorie ohne Kohorte,
Kürzel mit leerer idnumber wird ignoriert.
```

---

## Schritt 0b – CI-Pipeline einrichten (nachträglich, jetzt nachholen)

```
Richte eine GitHub-Actions-Pipeline für dieses Plugin ein, basierend auf
moodlehq/moodle-plugin-ci.

Erstelle .github/workflows/ci.yml nach dem Standard-Template von
moodle-plugin-ci (siehe https://github.com/moodlehq/moodle-plugin-ci für die
aktuelle empfohlene Workflow-Vorlage). Berücksichtige dabei:
- Matrix mindestens für die PHP-Version und Moodle-Version, die zur Zielumgebung
  passen (Moodle 5.2.x-Linie; passende PHP-Version dazu recherchieren, nicht raten)
- MariaDB als DB-Service (passend zur Zielumgebung, nicht Postgres annehmen)
- Standard-Schritte: phplint, phpcpd, phpcs (moodle-Ruleset), phpdoc, validate,
  savepoints, mustache-Lint, grunt, phpunit, behat
- Workflow soll bei jedem Push und bei Pull Requests laufen

Erstelle zusätzlich ein kurzes cli/verify_school_matcher.php als Sofort-Check
(kein Testframework, reines Ausführungsskript mit CLI_SCRIPT-Konstante und
Ausgabe via print_r), das ich lokal per
`docker exec -it claude-moodle-1 php ...` laufen lassen kann, ohne auf den
CI-Lauf warten zu müssen. Dieses Skript testet school_matcher::get_matched()
gegen die tatsächlichen Daten der laufenden Instanz (kein Testdaten-Generator,
sondern echte Kohorten/Kategorien) – rein zur Sichtprüfung, kein Ersatz für den
PHPUnit-Test aus Schritt 1.
```

---

## Schritt 2 – Einstellungsseite (Zeitraum + aktive Kürzel)

```
Baue die Konfigurationsseite für local_admincockpit (Site administration > Plugins >
Local plugins > Admin Cockpit).

Enthält:
- Auswahlfeld "Zeitraum für Neu-Zählungen" mit Optionen 30/90/180/360 Tage
  (Setting-Name: local_admincockpit/timerangedays, Default 180)
- Mehrfachauswahl "Aktive Schul-Kürzel": Optionen dynamisch aus school_matcher::get_matched()
  befüllt (nicht hartcodiert), gespeichert als kommagetrennter String oder JSON in
  local_admincockpit/activeschools
- Schreibgeschützter Hinweisblock, der cohort_only und category_only aus school_matcher
  auflistet ("Diese Kürzel sind nur einseitig gepflegt: ...") – als admin_setting_description
  oder eigenes admin_setting-Element

Verwende die Moodle Admin Settings API (admin_setting_configmultiselect o.ä.), keine
eigene Custom-Form, sofern die Standard-Elemente ausreichen.
```

---

## Schritt 3 – Globale Nutzer-Kennzahlen

```
Implementiere classes/metrics/user_metrics.php mit einer Klasse, die folgende Werte
berechnet und als einfaches Datenobjekt/Array zurückgibt:
- Anzahl aktiver Nutzerkonten (deleted = 0, suspended = 0 -- prüfe im Code, ob suspended
  hier mitgezählt werden soll oder nicht, und dokumentiere die Entscheidung im Docblock)
- Anzahl Nutzer mit lastaccess innerhalb der letzten 4 Wochen
- Anzahl Nutzer mit timecreated innerhalb des konfigurierten Zeitraums
  (Zeitraum aus dem Setting local_admincockpit/timerangedays)

Schreibe effiziente SQL-Queries (COUNT-Abfragen, keine vollständigen Recordsets laden).
Ergänze einen PHPUnit-Test mit Testdaten für alle drei Werte (läuft über CI, siehe
Schritt 0b). Ergänze zusätzlich cli/verify_user_metrics.php für die Sofortprüfung
gegen die echten Daten der laufenden Instanz.
```

---

## Schritt 4 – Pro-Schule-Kennzahlen

```
Implementiere classes/metrics/school_metrics.php. Für ein gegebenes Kürzel (mit
Kohorten-ID und Kategorie-ID aus school_matcher) berechnet die Klasse:
- Mitgliederzahl der Kohorte (cohort_members Count)
- Neuzugänge: cohort_members.timeadded innerhalb des konfigurierten Zeitraums
- Aktive Mitglieder: Join cohort_members -> user, lastaccess innerhalb der letzten 4 Wochen
- Kurszahl: Kurse in der Kategorie INKLUSIVE Subkategorien (Nutzung des category.path
  Präfix-Matchings), aber ohne Aufschlüsselung nach Subkategorie in der Rückgabe
- Neue Kurse: course.timecreated innerhalb des Zeitraums, gleiche Kategorie-Filterung

Wichtig: Bestätige in einem Kommentar im Code die Annahme "Kurszahl inkl. Subkategorien"
explizit, damit das bei Bedarf leicht revidierbar ist.

Ergänze PHPUnit-Tests mit einer Kategorie, die mindestens eine Subkategorie mit Kursen
enthält, um das Path-Matching zu verifizieren (läuft über CI, siehe Schritt 0b).
Ergänze zusätzlich cli/verify_school_metrics.php für die Sofortprüfung gegen ein
tatsächliches Schul-Kürzel der laufenden Instanz.
```

---

## Schritt 5 – Health-Signale

```
Implementiere classes/metrics/health_signals.php mit vier Methoden:

1. duplicate_emails(): liefert Anzahl E-Mail-Adressen, die von mehr als einem aktiven
   Nutzerkonto verwendet werden, sowie eine Detail-Liste (userid, email, fullname) für
   die Drill-down-Ansicht
2. courses_without_enddate(): Anzahl und Detail-Liste (courseid, fullname, categoryname)
   von Kursen mit enddate = 0
3. security_overview_summary(): Wiederverwendung der bestehenden Core-Klasse/Funktion
   für den Security-Overview-Report (NICHT neu implementieren) – recherchiere zuerst im
   Moodle-Core-Code (admin/report/security/), welche Klasse/Methode die Checks ausführt
   und liefere daraus eine aggregierte Ampel (ok/warning/error mit Anzahl je Status)
4. cron_status(): Zeitpunkt des letzten Cron-Laufs und Anzahl fehlgeschlagener Tasks in
   den letzten 24h, basierend auf der Core-Task-Log-Infrastruktur (recherchiere die
   passende Tabelle/Klasse, statt Tabellennamen zu raten)

Für Punkt 3 und 4: wenn du beim Code-Review feststellst, dass die passende Core-API
schwerer wiederzuverwenden ist als gedacht, sag mir das explizit, statt eine eigene
Parallel-Implementierung der Security-Checks zu schreiben.
```

---

## Schritt 6 – Drill-down-Seiten für Health-Signale

```
Erstelle zwei einfache Seiten (keine Blöcke):
- duplicateemails.php: zeigt die Detail-Liste aus health_signals::duplicate_emails(),
  je Zeile ein Link zum Nutzerprofil (user/profile.php?id=X) und ein Hinweis, dass
  tool_mergeusers für die eigentliche Zusammenführung genutzt werden kann
- courseswithoutenddate.php: zeigt die Detail-Liste aus
  health_signals::courses_without_enddate(), je Zeile ein Link direkt in die
  Kurseinstellungen (course/edit.php?id=X)

Beide Seiten benötigen die Capability local/admincockpit:view und einen Zurück-Link
zur Haupt-Dashboard-Seite. Nutze eine einfache table-Ausgabe (core html_table oder
core_reportbuilder system_report, je nachdem was mit weniger Code auskommt für diesen
einfachen Fall).
```

---

## Schritt 7 – Haupt-Dashboard-Seite (Rendering)

```
Baue index.php + classes/output/renderer.php + die Mustache-Templates für die
Haupt-Dashboard-Seite, die alle bisherigen Bausteine zusammenführt:

Layout (von oben nach unten):
1. Globale Nutzer-Kennzahlen (Kachel-Reihe)
2. Pro Schule (nur aktive Kürzel aus den Settings): Kachel-Gruppe mit den 5 Werten aus
   school_metrics + Link zur Kursverwaltung der Kategorie (course/management.php?categoryid=X)
3. Health-Signale (4 Kacheln, jede mit Klick-Ziel wie in Schritt 5/6 definiert)
4. Navigation, gruppiert in Boxen: Nutzerverwaltung, Kursverwaltung, Berichte/Logs,
   System, Theme/Erscheinungsbild (siehe SPEC Abschnitt 5 für die konkreten Linkziele –
   recherchiere die exakten URLs/Parameter im Moodle-Core bzw. im installierten
   theme_boost_union, rate sie nicht)

Zeitraum-Auswahl (30/90/180/360 Tage) soll als Dropdown oben auf der Seite änderbar sein
und die Seite mit dem gewählten Zeitraum neu laden (GET-Parameter, überschreibt temporär
den Default aus den Settings, ohne die Einstellung dauerhaft zu ändern).

Zeige "0 Kürzel aktiv konfiguriert" mit Link zur Settings-Seite, falls noch keine Schule
ausgewählt wurde.
```

---

## Schritt 7b – Styling-Verfeinerung: Moodle-/Bootstrap-5-konform statt Custom-CSS

```
Die Haupt-Dashboard-Seite ist bereits umgesetzt (Schritt 7), funktioniert aber teilweise
mit eigenem CSS statt Moodle-/Bootstrap-Bordmitteln. Überarbeite das Styling wie folgt,
ohne die Datenlogik (Metrik-Klassen) anzufassen:

1. Health-Signal-Kacheln: aktuell farbige Rahmen (grün/orange) vermutlich über eigenes
   CSS umgesetzt. Ersetze das durch $OUTPUT->notification($message, $type) mit den
   Standard-Typen 'notifysuccess' / 'notifywarning' / 'notifyproblem', ODER falls das
   Kachel-Layout dadurch bricht, durch Bootstrap-5-Klassen 'border-success' /
   'border-warning' / 'border-danger' in Kombination mit 'card'. Recherchiere zuerst im
   Moodle-Core (z.B. wie report/security oder andere Core-Reports Ampel-Status
   darstellen), um ein bestehendes Muster zu übernehmen statt ein neues zu erfinden.

2. Ergänze in jeder Health-Signal-Kachel ein Icon zusätzlich zur Randfarbe
   (Font Awesome, in Moodle über $OUTPUT->pix_icon() oder die 'fa'-Klassen direkt
   verfügbar) - Häkchen für ok, Warndreieck für Warnung/Fehler. Grund:
   reine Farbcodierung ist für farbenblinde Nutzer nicht ausreichend unterscheidbar.

3. Prüfe alle verwendeten Bootstrap-Klassen im gesamten Dashboard (Kennzahlen-Kacheln,
   Navigation-Boxen, Health-Signale) auf Bootstrap-5-Konformität, NICHT Bootstrap-4:
   - text-left/text-right -> text-start/text-end
   - ml-*/mr-* -> ms-*/me-*
   - custom-select -> form-select
   - Weitere BS4-Klassennamen, falls vorhanden, ebenfalls aktualisieren
   (siehe https://moodledev.io/docs/5.0/guides/bs5migration für die vollständige Liste,
   falls unsicher)

4. Kennzahlen-Kacheln (Nutzer gesamt/Aktive Nutzer/Neue Nutzer): prüfe, ob 'card'
   plus 'card-body' (Bootstrap-Standard-Komponente) eine konsistentere Optik ergibt
   als die aktuelle freistehende Box-Lösung, im Vergleich zu anderen Moodle-Core-Reports.
   Kein Muss, aber bewerte es kurz und begründe die gewählte Lösung.

Committe erst nach Review. Zeig mir vorher kurz, welche konkrete Core-Stelle du als
Vorbild für die Ampel-Darstellung (Punkt 1) gefunden hast.
```

---

## Schritt 7c – Fix: Icons in Health-Signal-Badges & inkonsistente Kachel-Höhen

```
Nach Schritt 7b zeigt sich im Browser: die Icons in den OK/Warnung-Badges rendern
nicht (leerer Platzhalter vor dem Text), was zusätzlich die Zentrierung des Badges
verschiebt. Ausserdem sind die Security-Overview- und Cron-Status-Kacheln deutlich
höher als die anderen beiden, weil ihr Text mehrzeilig umbricht.

1. Icon-Rendering: Recherchiere zuerst, welche Font-Awesome-Version/welchen
   Klassen-Präfix Moodle 5.2 core aktuell für Icons einbindet (z.B. ob 'fa fa-check'
   noch funktioniert oder ob 'fa-solid fa-check' nötig ist) - schau dir dazu an, wie
   ein Core-Template mit Icon (z.B. in einem Standard-Notification oder einem
   Core-Report) das Icon einbindet, statt die Klasse zu raten. Korrigiere die
   Badge-Icons entsprechend. Alternative, falls robuster: $OUTPUT->pix_icon() mit
   einem passenden Core-Icon-Namen (z.B. 'i/valid' / 'i/warning') statt direkter
   Font-Awesome-Klassen - bewerte kurz, welcher Weg in diesem Codebase konsistenter
   ist, und begründe die Wahl.

2. Badge-Zentrierung: stelle sicher, dass Icon + Text im Badge über eine
   Flex-Container-Klasse zentriert sind (z.B. 'd-inline-flex align-items-center
   justify-content-center'), damit die Zentrierung auch bei variabler Icon-Breite
   stabil bleibt.

3. Text kürzen: 
   - Security-Overview-Kachel: statt "15 ok / 4 Warnung(en) / 0 Fehler" kompakter
     darstellen, z.B. "15 OK · 4 Warnungen" auf einer Zeile (0 Fehler kann weggelassen
     werden, wenn 0, oder nur bei > 0 angezeigt werden)
   - Cron-Status-Kachel: den vollen Zeitstempel ("Saturday, 11. July 2026, 19:11.")
     durch ein kompakteres Format ersetzen (z.B. relative Zeit "vor 2 Stunden" über
     Moodle's core userdate()-Funktion mit relativem Format, falls vorhanden -
     recherchieren statt raten) und den vollen Zeitstempel stattdessen als
     title-Attribut/Tooltip anbieten

4. Kachel-Höhen angleichen: setze alle vier Health-Signal-Kacheln in eine gemeinsame
   Flex-Reihe mit 'align-items-stretch' und 'h-100' auf den Karten, damit alle vier
   gleich hoch werden unabhängig von der Textlänge - Badge jeweils am unteren Rand der
   Karte ausgerichtet (z.B. via 'd-flex flex-column justify-content-between' auf der
   Card selbst).

Zeig mir vor dem Commit einen Screenshot oder beschreibe kurz, welche Icon-Lösung
(Font-Awesome-Klasse vs. pix_icon) du gewählt hast und warum.
```

---

## Schritt 7d – Caching der Berechnungen (Moodle Cache API, TTL 1 Tag)

```
Aktuell werden alle Kennzahlen und Health-Signale bei jedem Seitenaufruf neu berechnet.
Führe Caching über die Moodle Cache API (MUC) ein, TTL 1 Tag (86400 Sekunden).

1. Definiere db/caches.php mit einer 'application'-Cache-Definition (nicht 'request'
   oder 'session', da die Werte über Nutzer/Sessions hinweg gleich sein sollen), z.B.
   'dashboarddata', mit ttl => 86400.

2. Baue die bestehenden Berechnungsklassen (user_metrics, school_metrics,
   health_signals) so um, dass sie zuerst im Cache nachsehen (cache::make('local_
   admincockpit', 'dashboarddata')->get($key)) und nur bei Cache-Miss neu rechnen
   und das Ergebnis zurückschreiben. Cache-Key muss den aktuell gewählten Zeitraum
   (GET-Parameter) mit einschliessen, da unterschiedliche Zeiträume unterschiedliche
   Ergebnisse liefern (z.B. Key-Schema 'usermetrics_' . $timerangedays).

3. Baue einen "Cache jetzt leeren"-Button direkt auf der Dashboard-Seite (nicht nur
   über die generelle Moodle-Cache-Verwaltung erreichbar). Der Button:
   - erfordert sesskey-Prüfung (require_sesskey())
   - erfordert dieselbe Capability wie die Dashboard-Seite (local/admincockpit:view)
     oder eine eigene lokale Kaskade dafür, falls sinnvoll -entscheide, was
     konsistenter ist
   - ruft $cache->purge() nur auf die eigene Cache-Definition auf, NICHT
     purge_all_caches() (das würde die gesamte Instanz treffen, nicht nur dieses
     Plugin)
   - zeigt danach eine Bestätigungsmeldung ($OUTPUT->notification(..., 'notifysuccess'))
     und lädt die Seite mit frisch berechneten Werten neu

4. Zeige auf der Seite dezent an, wann die angezeigten Werte zuletzt berechnet wurden
   (z.B. "Stand: <Zeitstempel>, wird täglich aktualisiert" unterhalb der Kennzahlen),
   damit für den Admin klar ist, dass die Zahlen nicht live sind.

5. WICHTIG zur Platzierung: Der "Cache jetzt leeren"-Button wirkt sich auf die
   GESAMTE Seite aus (globale Kennzahlen, alle Schulen, alle Health-Signale - eine
   einzige Cache-Definition wird komplett geleert). Platziere ihn deshalb NICHT
   innerhalb oder direkt unter dem Block "Globale Nutzer-Kennzahlen" (das würde
   fälschlich suggerieren, er beträfe nur diesen Block), sondern ganz oben auf der
   Seite auf derselben Zeile wie die Zeitraum-Auswahl, zusammen mit dem
   "Stand: ..."-Zeitstempel-Hinweis aus Punkt 4. Begründung: beide Elemente
   (Zeitraum-Umschalter und Cache-Leeren-Button) wirken seitenweit, nicht auf einen
   einzelnen Block - sie gehören daher optisch zusammen, oberhalb aller
   Inhalts-Blöcke.

Recherchiere die exakte aktuelle Moodle-Cache-API-Syntax (cache::make(), Definition in
db/caches.php) im Core-Code, falls unsicher, statt aus dem Gedächtnis zu implementieren
- die API hat sich über Moodle-Versionen leicht verändert.
```

---

## Schritt 7e – Bootstrap-4-Reste vollständig entfernen

```
Durchsuche das gesamte Plugin (alle Mustache-Templates, ggf. eigenes CSS/SCSS) nach
verbliebenen Bootstrap-4-Klassennamen und ersetze sie konsequent durch die
Bootstrap-5-Äquivalente. Prüfe insbesondere:
- text-left/text-right -> text-start/text-end
- ml-*/mr-*, pl-*/pr-* -> ms-*/me-*, ps-*/pe-*
- float-left/float-right -> float-start/float-end
- border-left/border-right -> border-start/border-end
- rounded-left/rounded-right -> rounded-start/rounded-end
- custom-select -> form-select
- sr-only -> visually-hidden
- .close (Button-Klasse) -> .btn-close
- font-weight-* -> fw-*
- font-italic -> fst-italic
- no-gutters -> g-0

Nutze https://moodledev.io/docs/5.0/guides/bs5migration als Referenz für die
vollständige Liste, falls im Code weitere BS4-Muster auftauchen, die hier nicht
aufgeführt sind. Liste am Ende kurz auf, was du gefunden und ersetzt hast, damit ich
das gegenlesen kann, bevor committet wird.
```

---

## Schritt 7f – Moodle-Event für Dashboard-Aufrufe

```
Implementiere ein Standard-Moodle-Event nach Core-Konvention:

1. classes/event/dashboard_viewed.php - Event-Klasse, die von \core\event\base
   erbt, CRUD 'r' (read), Edulevel 'other' (kein Lern-bezogenes Event), Objekttabelle
   nicht zutreffend (kein DB-Objekt, das betrachtet wird - orientiere dich an einem
   Core-Beispiel für ein reines "Seite aufgerufen"-Event ohne zugehörigen Datensatz,
   z.B. wie andere Report-Seiten das lösen, statt es zu raten)

2. Löse das Event in index.php aus, sobald die Seite erfolgreich mit gültiger
   Capability aufgerufen wird (nach dem Capability-Check, vor dem Rendering)

3. lang/en/ und lang/de/ um die Event-Beschreibung ergänzen
   (get_string('eventdashboardviewed', ...))

4. Kurz verifizieren: das Event sollte danach in Site administration > Reports >
   Logs auftauchen, wenn die Dashboard-Seite aufgerufen wird - das im Anschluss an
   die Umsetzung einmal testen und mir kurz Bescheid geben, ob es erscheint.
```

---

## Schritt 7g – Fix: Cache-Key für Pro-Schule-Werte fehlt Zeitraum-Komponente

```
Beim Caching in Schritt 7d wurde der Zeitraum nur im Cache-Key der globalen
Nutzer-Kennzahlen berücksichtigt, nicht bei school_metrics. Das führt dazu, dass beim
Umschalten des Zeitraums (30/90/180/360 Tage) die Werte "Neuzugänge im Zeitraum" und
"Neue Kurse im Zeitraum" pro Schule weiterhin die zuvor gecachten Zahlen des alten
Zeitraums anzeigen, obwohl der Rest der Seite bereits den neuen Zeitraum nutzt.

Korrigiere den Cache-Key in school_metrics so, dass er sowohl das Schul-Kürzel als
auch den gewählten Zeitraum enthält, z.B. 'schoolmetrics_' . $kuerzel . '_' .
$timerangedays.

Prüfe im Gegenzug health_signals: dort hängt keiner der vier Werte vom
Zeitraum-Parameter ab (Duplikate, Kurse ohne Enddatum, Security-Overview, Cron-Status
sind zeitraum-unabhängig) - stelle sicher, dass dort der Cache-Key NICHT unnötig den
Zeitraum enthält, sonst würden diese Werte öfter neu berechnet als nötig, ohne
Mehrwert.

Teste nach der Korrektur manuell: Zeitraum umschalten, prüfen ob sich "Neuzugänge"/
"Neue Kurse" pro Schule tatsächlich ändern, nicht nur die globalen Werte.
```

---

## Schritt 7h – Navigation konfigurierbar machen (Textarea-Setting statt hartcodierter Links)

```
Die Navigation-Links sind aktuell hartcodiert im Renderer/Templates. Mache sie
konfigurierbar, nach dem Muster von Moodles eigenem Custom-Menu
($CFG->custommenuitems), damit das Plugin auch auf anderen Moodle-Instanzen ohne
Code-Änderung nutzbar ist.

1. Neue Einstellung 'navitems' vom Typ admin_setting_configtextarea in
   settings.php. Format pro Zeile (Pipe-getrennt), 3 oder 4 Segmente (Capability
   optional, bei 3 Segmenten wird kein Capability-Check durchgeführt):
   Titel|URL|Gruppe|Capability(optional)
   Jedes Segment beim Parsen trimmen (führende/nachfolgende Leerzeichen entfernen),
   damit "Titel | URL" genauso funktioniert wie "Titel|URL".
   Beispiel:
   Nutzer hochladen|/admin/user/user_bulk.php|Nutzerverwaltung|moodle/user:create
   Kohorten verwalten|/cohort/index.php|Nutzerverwaltung|moodle/cohort:manage
   Scheduled Tasks|/admin/tool/task/scheduledtasks.php|System|moodle/site:config

   Orientiere dich am Parsing-Ansatz, den Moodle-Core für custommenuitems selbst
   verwendet (im Core-Code nachsehen, wie dort Zeilen/Pipes zerlegt werden), statt
   eine komplett eigene Parsing-Logik zu erfinden.

2. Fülle 'navitems' mit einem sinnvollen Default vor, der genau die aktuell
   hartcodierten Links enthält (alle bisherigen Einträge aus Abschnitt 5 der SPEC),
   damit bestehende Installationen (auch unsere eigene) nach dem Update ohne
   Handarbeit weiterlaufen. Die Boost-Union-Theme-Einstellungen-Zeile nur vorbelegen,
   wenn theme_boost_union tatsächlich installiert ist (sonst weglassen).

3. Renderer: Navigation-Gruppen (Kartenüberschriften) werden dynamisch aus den in
   'navitems' vorkommenden Gruppennamen gebildet, nicht mehr hartcodiert. Reihenfolge
   der Gruppen: Erstauftreten in der Einstellung.

4. Vor jedem Link-Rendering: falls eine Capability angegeben ist, mit
   has_capability() prüfen (Kontext: System, da wir hier bewusst bei
   system-weiten Administratoren bleiben, keine Kategorie-/kontextsensitive Prüfung
   nötig) und den Link nur zeigen, wenn erfüllt. Ohne Capability-Angabe: immer zeigen
   (Fallback für Admins, die die Capability-Spalte nicht nutzen wollen).

5. Fehlerhafte/unparsebare Zeilen (falsches Format, mehr/weniger als 3-4 Segmente):
   nicht zum Fatal Error führen, sondern die Zeile überspringen und optional eine
   admin_setting_description mit einem Hinweis "X Zeile(n) konnten nicht geparst
   werden" oberhalb der Textarea anzeigen.

6. Ist 'navitems' komplett leer (z.B. absichtlich vom Admin geleert), muss die
   Navigation-Sektion sauber leer bleiben (kein Fatal Error, keine leeren Boxen mit
   Überschrift ohne Inhalt) - am besten komplett ausblenden mit optionalem Hinweistext
   "Keine Navigationselemente konfiguriert" plus Link zu den Einstellungen.

Ergänze ein cli/verify_navitems.php zur Sofortprüfung des Parsings gegen die
konfigurierten Werte der laufenden Instanz.
```

---

## Schritt 9 – Generalisierung: "Schule" durch konfigurierbaren Begriff ersetzen

```
Der Begriff "Schule" ist aktuell an mehreren Stellen im UI hartcodiert (Kachel-
Überschriften, Settings-Beschriftungen, ggf. Sprachdateien). Für die Veröffentlichung
soll das Plugin für beliebige Gruppierungen (Standorte, Abteilungen, Mandanten,
Fakultäten) nutzbar sein, nicht nur für Schulen.

1. Neue Einstellung 'groupinglabel' (Freitext, admin_setting_configtext), Default-Wert
   "Schule" (damit sich für unsere eigene Instanz nichts ändert), Beschreibung z.B.
   "Bezeichnung für die Gruppierung aus Kohorte + Kategorie (z.B. Schule, Standort,
   Abteilung, Fakultät)"

2. Ersetze alle hartcodierten Vorkommen von "Schule"/"Schulen" im UI (Kachel-
   Überschrift "Pro Schule", Settings-Beschriftungen wie "Aktive Schul-Kürzel") durch
   dynamische Verwendung von get_config('local_admincockpit', 'groupinglabel')
   bzw. eine entsprechende Sprachstring-Platzhalter-Lösung (get_string mit $a-Platzhalter
   statt hartcodiertem Substantiv).

3. Rein interne Bezeichner (Variablennamen wie $kuerzel, Methodennamen wie
   school_matcher, school_metrics) NICHT umbenennen - das ist reine UI-Generalisierung,
   kein Rename der internen Architektur. Aufwand sonst unnötig hoch für keinen
   Nutzerwert.

4. Prüfe Sprachdateien (en/de) auf verbleibende hartcodierte "school"/"Schule"-Strings
   in UI-sichtbaren get_string()-Werten und passe sie auf die generische Formulierung
   an (z.B. "Kohorten-/Kategorie-Gruppierungen" als Fallback-Formulierung, wenn kein
   Platzhalter sinnvoll einsetzbar ist).
```

---

## Schritt 10 – Leerer-Zustand-Test bei 0 konfigurierten Gruppen

```
Teste und stelle sicher, dass das Dashboard sauber funktioniert, wenn 'activeschools'
(bzw. wie in Schritt 9 ggf. umbenannt) komplett leer ist - der Zustand einer frisch
installierten Instanz ohne konfigurierte Gruppierungen.

Erwartetes Verhalten:
- Kein Fatal Error, keine leere/kaputte "Pro Schule"-Sektion
- Stattdessen ein Hinweis mit Link zur Einstellungsseite, z.B. "Keine [groupinglabel]
  konfiguriert. Zu den Einstellungen." (Text nutzt den generischen Begriff aus
  Schritt 9)
- Globale Nutzer-Kennzahlen und Health-Signale bleiben davon unberührt und
  funktionieren weiterhin normal

Falls beim Testen ein Fehler auftritt (z.B. weil eine Berechnung von mindestens einem
Element in der Kürzel-Liste ausgeht), fixe die betroffene Stelle in school_metrics
bzw. dem Renderer.
```

---

## Schritt 11 – Formale Voraussetzungen für Moodle Plugin Directory

```
Ergänze die für eine Veröffentlichung im Moodle Plugin Directory zwingend nötigen
Bestandteile:

1. classes/privacy/provider.php: da das Plugin selbst keine personenbezogenen Daten
   SPEICHERT (nur zur Laufzeit aus bestehenden Core-Tabellen liest/aggregiert),
   implementiere \core_privacy\local\metadata\null_provider mit einer klaren
   Begründung als Sprachstring (get_string('privacy:metadata', ...) mit Erklärung,
   warum keine eigenen Daten gespeichert werden). Recherchiere zuerst im Moodle-Core,
   wie andere reine Report-/Dashboard-Plugins ohne eigene Datenspeicherung ihre
   Privacy-Provider-Klasse aufbauen, statt es zu raten.

2. README.md im Repo-Root: Kurzbeschreibung, Voraussetzungen (Moodle-Version,
   ggf. PHP-Version), Installationsanleitung, Hinweis auf die Konfiguration
   (Zeitraum, Gruppierungs-Kürzel, Navigation-Textarea), Lizenzhinweis, mindestens
   1-2 Screenshots (Platzhalter-Verweis, falls Bilder separat eingefügt werden müssen)

3. LICENSE-Datei: GPLv3-Volltext (Standard-Lizenz für Moodle-Plugins)

4. Vervollständige lang/en/local_admincockpit.php als vollständige Basissprache -
   das ist Pflicht für den Directory-Eintrag, auch wenn lang/de/ die primäre
   Nutzungssprache bleibt

5. Prüfe, ob irgendwo externe JS-Bibliotheken eingebunden wurden (z.B. für Chart-
   Darstellung, falls verwendet) - falls ja, thirdpartylibs.xml ergänzen mit
   Lizenzangaben. Falls keine externen Libraries verwendet werden, kurz bestätigen.

6. version.php: $plugin->maturity (z.B. MATURITY_STABLE) und $plugin->release
   sauber gesetzt, falls noch nicht geschehen.
```

---

## Schritt 12 – Review, Sprachdateien, Abschluss

```
1. Vervollständige lang/en/ und lang/de/ mit allen bisher verwendeten get_string()-Keys
2. Prüfe alle Schritte gegen die Moodle Coding Guidelines (phpcs mit dem
   moodle-Ruleset, falls lokal verfügbar)
3. Liste alle Stellen im Code auf, die mit "TODO: verify" oder ähnlichen Markierungen
   auf offene Annahmen aus der SPEC hinweisen (siehe SPEC Abschnitt 8), damit ich diese
   gezielt gegenlesen kann, bevor das Plugin in Produktion geht
4. Erstelle KEINEN Runbook-/Doku-Eintrag automatisch – das mache ich separat, sobald
   das Plugin final getestet ist
```

---

## Hinweise für die Session

- Plugin-Namen, Capability-Namen und Tabellennamen aus dieser Datei sind Vorschläge,
  keine fixen Vorgaben – wenn ein Moodle-Konventions-Check etwas anderes nahelegt,
  abweichen und kurz begründen.
- Bei Unsicherheit über exakte Core-APIs (Security Overview, Task-Log, Boost Union
  Settings-URL): recherchieren statt raten, und wenn unklar, explizit nachfragen statt
  eine Vermutung zu implementieren.
- Jeder Schritt sollte für sich lauffähig/testbar sein, bevor der nächste beginnt.

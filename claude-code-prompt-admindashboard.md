# Claude Code Prompt – local_admindashboard

Diese Datei enthält die Schritt-für-Schritt-Sequenz für die Implementierung, basierend auf `SPEC-admindashboard.md`. Jeder Schritt ist als eigener Prompt für Claude Code gedacht – erst nach Bestätigung/Review des einen Schritts zum nächsten übergehen.

Vor Schritt 1: `SPEC-admindashboard.md` in dasselbe Verzeichnis wie `CLAUDE.md` legen, damit Claude Code beides als Kontext hat. Ein `CLAUDE.md` für dieses Projekt sollte enthalten: Zielumgebung (Moodle 5.2.x in Docker, `/var/www/html/public/local/`), Coding-Standard (core-contribution-taugliche Qualität, Moodle Coding Style), und den Hinweis, dass dies ein Reporting-/Navigations-Plugin ohne eigenes DB-Schema ist.

---

## Schritt 0 – Plugin-Grundgerüst

```
Erstelle das Grundgerüst für ein neues Moodle-Plugin vom Typ "local", Komponente
local_admindashboard, für Moodle 5.2.

Erstelle:
- version.php (component local_admindashboard, aktuelle Version, requires für Moodle 5.2)
- lib.php (leer/Platzhalter, mit den üblichen Callback-Stubs falls später benötigt)
- classes/ (leeres Verzeichnis mit .gitkeep)
- lang/en/local_admindashboard.php mit Basiseinträgen (pluginname)
- lang/de/local_admindashboard.php mit den deutschen Übersetzungen der gleichen Strings
- settings.php: registriert eine eigene Admin-Seite unter Site administration > Reports
  (admin_externalpage, nicht admin_settingpage, da es keine reinen Konfigurationswerte
  sondern eine Dashboard-Ansicht ist) plus eine separate Settings-Seite für die
  Konfiguration (Zeitraum, aktive Schul-Kürzel) unter Site administration > Plugins >
  Local plugins > Admin Dashboard
- db/access.php mit der Capability local/admindashboard:view
  (CONTEXT_SYSTEM, archetypes: manager => CAP_ALLOW)

Folge den Moodle-Coding-Guidelines und der Standard-Verzeichnisstruktur für local-Plugins.
Committe noch nichts, ich möchte das Ergebnis erst reviewen.
```

---

## Schritt 1 – Kürzel-Erkennung (Kohorte ↔ Kategorie Matching)

```
Implementiere die Klasse classes/school_matcher.php (Namespace local_admindashboard).

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

## Schritt 2 – Einstellungsseite (Zeitraum + aktive Kürzel)

```
Baue die Konfigurationsseite für local_admindashboard (Site administration > Plugins >
Local plugins > Admin Dashboard).

Enthält:
- Auswahlfeld "Zeitraum für Neu-Zählungen" mit Optionen 30/90/180/360 Tage
  (Setting-Name: local_admindashboard/timerangedays, Default 180)
- Mehrfachauswahl "Aktive Schul-Kürzel": Optionen dynamisch aus school_matcher::get_matched()
  befüllt (nicht hartcodiert), gespeichert als kommagetrennter String oder JSON in
  local_admindashboard/activeschools
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
  (Zeitraum aus dem Setting local_admindashboard/timerangedays)

Schreibe effiziente SQL-Queries (COUNT-Abfragen, keine vollständigen Recordsets laden).
Ergänze einen PHPUnit-Test mit Testdaten für alle drei Werte.
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
enthält, um das Path-Matching zu verifizieren.
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

Beide Seiten benötigen die Capability local/admindashboard:view und einen Zurück-Link
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

## Schritt 8 – Review, Sprachdateien, Abschluss

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

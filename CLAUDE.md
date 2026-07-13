# CLAUDE.md – local_admindashboard

Kontext-Datei für Claude Code Sessions in diesem Projekt. Vor Arbeitsbeginn lesen.

---

## Projektüberblick

Moodle-Plugin `local_admindashboard`: Navigations- und Kennzahlen-Dashboard für Administratoren. Zeigt Nutzer-/Kurs-Kennzahlen pro "Schule" (Kohorte + Top-Level-Kategorie, verknüpft über `idnumber`), Health-Signale mit Call-to-Action sowie Direktlinks zu häufig genutzten Verwaltungsseiten.

**Quelle der Wahrheit für Anforderungen:** `SPEC-admindashboard.md` im selben Verzeichnis. Bei Widersprüchen zwischen dieser Datei und der Spec gilt die Spec – hier nachfragen statt selbst zu entscheiden.

**Umsetzungssequenz:** `claude-code-prompt-admindashboard.md` im selben Verzeichnis. Enthält die Schritte, die **einzeln nacheinander** abgearbeitet werden – nicht mehrere Schritte auf einmal umsetzen, auch wenn der Kontext das hergeben würde. Nach jedem Schritt wird das Ergebnis reviewt, bevor der nächste beginnt. Die Liste ist im Verlauf gewachsen (Zwischenschritte wie 0b, 7b–7h kamen dazu) – die "Fortschritt"-Zeile weiter unten und die Datei selbst sind die Quelle der Wahrheit für den aktuellen Stand, nicht eine fixe Schrittanzahl.

---

## Zielumgebung

- **Moodle-Version:** 5.2.x
- **Lokaler Plugin-Ordner:** `/Users/tkorner/Documents/claude/plugins/local/admindashboard/`
- **Docker-Mount-Ziel:** `/var/www/html/public/local/admindashboard/` (Moodle 5.x `public/`-Verzeichnisstruktur - verifiziert via `docker inspect claude-moodle-1`, nicht die ältere flache `/var/www/html/local/`-Struktur)
- **Docker-Compose-Mount:** `./plugins/local:/var/www/html/public/local`
- **Container:** `claude-moodle-1` (Image `erseco/alpine-moodle`), DB in `claude-mariadb-1`
- **PHP/DB:** Standard-Alpine-Moodle-Setup, keine Sonderkonfiguration bekannt

---

## Git

- **Repo:** `tkorner/moodle-local_admindashboard` (privat)
- Repo-Root = Plugin-Root (also `version.php` direkt im Repo-Root, kein `local/admindashboard/`-Unterordner im Git-Repo selbst – Moodle-Konvention für Einzelplugin-Repos)
- Nach jedem abgeschlossenen und reviewten Schritt aus dem Prompt-Dokument committen, damit einzelne Schritte bei Bedarf zurückgerollt werden können

---

## Architektur-Grundprinzipien (aus der Spec)

- **Kein eigenes DB-Schema.** Alle Kennzahlen werden zur Laufzeit berechnet (`timecreated`-Filter statt Snapshot-Historie). Falls im Verlauf der Eindruck entsteht, dass doch eine Tabelle nötig wäre – anhalten und nachfragen, das widerspricht einer bewussten Scope-Entscheidung.
- **Kohorte↔Kategorie-Matching ausschliesslich über `idnumber`**, nicht über Anzeigenamen.
- **Business-Logik strikt getrennt von Ausgabe:** Metrik-Klassen (`classes/metrics/...`) kennen keine Seiten/Blöcke. Grund: eine spätere Umwandlung von "eigene Seite" zu "Block" ist offen (aktuell startet die Umsetzung bewusst mit einer Seite, nicht einem Block) und soll die Berechnungslogik unverändert lassen können.
- **Health-Signale sind immer Zahl + Klick-Ziel**, nie reine Statistik ohne Handlungsmöglichkeit.

---

## Zwingende Recherche-Punkte (nicht raten)

Folgende Dinge dürfen nicht aus Trainingsdaten/Vermutung implementiert werden, sondern müssen im tatsächlichen Moodle-Core-Code bzw. der installierten Instanz verifiziert werden. Alle vier waren bei Implementierungsstart offen; Stand Schritt 12 sind alle verifiziert und im Code referenziert (siehe README "Reused core APIs" für Details):

- ✅ Core-Klasse/-Methode hinter dem Security-Overview-Report (`admin/report/security/`) – wiederverwendet: `\core\check\manager::get_checks('security')` (`classes/metrics/health_signals.php`)
- ✅ Core-Tabelle/-Klasse für Task-Log/Cron-Status – `get_config('tool_task', 'lastcronstart')` + `task_log.result` (`classes/metrics/health_signals.php`)
- ✅ Exakte URL/Parameter für die Scheduled-Tasks-Übersicht – `/admin/tool/task/scheduledtasks.php` (`classes/navitems_parser.php`)
- ✅ Exakte Section-URL der `theme_boost_union`-Einstellungen – `/theme/boost_union/settings_overview.php` (`classes/navitems_parser.php`)
- Bei Unsicherheit über zukünftige, noch unverifizierte Punkte: explizit nachfragen statt einer plausibel klingenden, aber ungeprüften Annahme

---

## Testing-Strategie

Kein lokales GUI, PHPUnit lokal nicht installierbar. Deshalb zwei Ebenen:

1. **CLI-Smoke-Skripte** (`cli/verify_*.php`) – sofortiges Feedback während der Session, direkt gegen die echten Daten der laufenden Docker-Instanz, ohne Testframework:
   ```bash
   docker exec -it claude-moodle-1 php /var/www/html/public/local/admindashboard/cli/verify_school_matcher.php
   ```
   Kein Ersatz für echte Tests, nur Sichtprüfung während der Entwicklung.

2. **GitHub Actions mit `moodlehq/moodle-plugin-ci`** – führt bei jedem Push/PR die eigentliche Absicherung durch: PHPUnit, Behat, phpcs (moodle-Ruleset), phplint, mustache-Lint etc. PHPUnit-Testdateien werden trotzdem geschrieben (siehe Prompt-Dokument Schritt 1/3/4) – sie laufen nur nicht lokal, sondern über CI. Ergebnis im GitHub-Actions-Tab prüfen, nicht lokal erwarten.

**Fortschritt:** Schritt 0 bis 11 sind umgesetzt und released (siehe Release 1.0.0/1.1.0-Commits); Schritt 12 (dieser Review-/Abschluss-Schritt) läuft gerade.

---

## Coding-Standards

- Moodle Coding Guidelines / Moodle Coding Style (phpcs mit moodle-Ruleset, falls lokal verfügbar)
- Core-contribution-taugliche Qualität von Anfang an, auch wenn dies (anders als `qbank_cffpoc`) nicht für einen Core-Merge vorgesehen ist
- Moodle Data Manipulation API für alle DB-Zugriffe (kein rohes mysqli, keine ungefilterten String-Konkatenationen in SQL)
- PHPUnit-Tests für alle Metrik-/Matching-Klassen (siehe Prompt-Dokument, Schritt 1, 3, 4)
- Sprachdateien: `lang/en/` und `lang/de/` parallel pflegen, keine hartcodierten Strings im Code

---

## Bekannte offene Annahmen (aus SPEC Abschnitt 8)

Stand Schritt 12:

1. Kurszahl pro Schule inkl. Subkategorien – Annahme "ja", im Code explizit kommentiert (`classes/metrics/school_metrics.php`), bewusst revidierbar, nicht "offen" im Sinne von unentschieden
2. Security-Overview-Aggregation – ✅ identifiziert, siehe Recherche-Punkte oben
3. Scheduled-Tasks-URL – ✅ verifiziert, siehe Recherche-Punkte oben
4. Boost-Union-Settings-URL – ✅ verifiziert, siehe Recherche-Punkte oben

Weitere im Verlauf dokumentierte (nicht mehr offene, aber bewusste) Entscheidungen stehen in README.md unter "Known open assumptions", u.a. zur Interpretation der SPEC-Navigationspunkte und zur fehlenden Capability-Einschränkung der Default-Navigationslinks (Schritt 7h).

Diese Liste bei Bedarf ergänzen, wenn im Verlauf der Implementierung neue offene Punkte auftauchen – nicht stillschweigend Annahmen treffen und weitermachen.

---

## Ergänzende externe Referenz

Für Security- und CI-Best-Practices zusätzlich konsultieren (falls lokal verfügbar/geklont):
**MoMoPDA** – "Modular Moodle Plugin Development Assistant", ursprünglich von wilenius
(https://github.com/wilenius/momopda), geforkt unter https://github.com/tkorner/momopda,
GPL-3.0. Relevante generische Bausteine: `.prompts/core/security-checklist.md`,
`.prompts/core/ci-validation.md`, `.prompts/patterns/database.md`,
`.prompts/patterns/forms.md`, `.prompts/patterns/navigation.md`,
`.prompts/patterns/api-usage.md`. Kein plugin-spezifischer `local`-Guide vorhanden
(Stand aktuell) – die dortigen Guides decken block/enrol/filter/mod/qbank/qtype/report/
tiny ab, lokale Plugins nur über die generischen Core-Dateien. Bei Bedarf als
zusätzliche Cross-Referenz nutzen, nicht als primäre Anleitung (diese bleibt SPEC +
claude-code-prompt-admindashboard.md).

---

## Nicht tun

- Keine Runbook-/Doku-Einträge automatisch erzeugen – das erfolgt separat, nach Abschluss und eigenem Review
- Keine Block-Variante parallel bauen – v1 ist bewusst nur die Seite
- Keine zusätzlichen Health-Signale über die in der Spec genannten vier hinaus ergänzen, ohne Rückfrage (bewusst reduzierter v1-Scope)

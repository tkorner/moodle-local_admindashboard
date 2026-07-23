# CLAUDE.md – local_admincockpit

Kontext-Datei für Claude Code Sessions in diesem Projekt. Vor Arbeitsbeginn lesen.

---

## Projektüberblick

Moodle-Plugin `local_admincockpit`: Navigations- und Kennzahlen-Dashboard für Administratoren. Zeigt Nutzer-/Kurs-Kennzahlen pro "Schule" (Kohorte + Top-Level-Kategorie, verknüpft über `idnumber`), Health-Signale mit Call-to-Action sowie Direktlinks zu häufig genutzten Verwaltungsseiten.

**Quelle der Wahrheit für Anforderungen:** `SPEC-admincockpit.md` im selben Verzeichnis. Bei Widersprüchen zwischen dieser Datei und der Spec gilt die Spec – hier nachfragen statt selbst zu entscheiden.

**Umsetzungssequenz:** `claude-code-prompt-admincockpit.md` im selben Verzeichnis. Enthält die Schritte, die **einzeln nacheinander** abgearbeitet werden – nicht mehrere Schritte auf einmal umsetzen, auch wenn der Kontext das hergeben würde. Nach jedem Schritt wird das Ergebnis reviewt, bevor der nächste beginnt. Die Liste ist im Verlauf gewachsen (Zwischenschritte wie 0b, 7b–7h kamen dazu) – die "Fortschritt"-Zeile weiter unten und die Datei selbst sind die Quelle der Wahrheit für den aktuellen Stand, nicht eine fixe Schrittanzahl.

---

## Zielumgebung

- **Moodle-Version:** 5.2.x
- **Lokaler Plugin-Ordner:** `/Users/tkorner/Documents/claude/plugins/local/admincockpit/`
- **Docker-Mount-Ziel:** `/var/www/html/public/local/admincockpit/` (Moodle 5.x `public/`-Verzeichnisstruktur - verifiziert via `docker inspect claude-moodle-1`, nicht die ältere flache `/var/www/html/local/`-Struktur)
- **Docker-Compose-Mount:** `./plugins/local:/var/www/html/public/local`
- **Container:** `claude-moodle-1` (Image `erseco/alpine-moodle`), DB in `claude-mariadb-1`
- **PHP/DB:** Standard-Alpine-Moodle-Setup, keine Sonderkonfiguration bekannt

---

## Git

- **Repo:** `tkorner/moodle-local_admincockpit` (privat)
- Repo-Root = Plugin-Root (also `version.php` direkt im Repo-Root, kein `local/admincockpit/`-Unterordner im Git-Repo selbst – Moodle-Konvention für Einzelplugin-Repos)
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

Kein lokales GUI. Drei Ebenen (Stand Code-Review 2026-07-16 – entgegen der langjährigen Annahme in diesem Dokument ist PHPUnit auf der laufenden Docker-Instanz tatsächlich installiert und lauffähig, siehe Punkt 2):

1. **CLI-Smoke-Skripte** (`cli/verify_*.php`) – sofortiges Feedback während der Session, direkt gegen die echten Daten der laufenden Docker-Instanz, ohne Testframework:
   ```bash
   docker exec -it claude-moodle-1 php /var/www/html/public/local/admincockpit/cli/verify_school_matcher.php
   ```
   Kein Ersatz für echte Tests, nur Sichtprüfung während der Entwicklung.

2. **PHPUnit direkt im Container** – lauffähig, `vendor/bin/phpunit` existiert:
   ```bash
   docker exec -it claude-moodle-1 sh -c "cd /var/www/html && vendor/bin/phpunit --configuration phpunit.xml --testsuite local_admincockpit_testsuite"
   ```
   Sofortiges, vollständiges Testfeedback ohne auf CI zu warten – neue `*_test.php`-Dateien unter `tests/` werden automatisch erkannt (die registrierte Testsuite scannt per Datei-Suffix), kein `--buildconfig` nötig; nur bei einer komplett neuen Testsuite/Plugin-Komponente wäre das erforderlich. Nach jeder Änderung an `version.php` (z.B. Versionsbump) meldet PHPUnit "was initialised for different version" und muss einmalig neu initialisiert werden:

   ```bash
   docker exec -it claude-moodle-1 sh -c "cd /var/www/html && php public/admin/tool/phpunit/cli/init.php"
   ```

3. **GitHub Actions mit `moodlehq/moodle-plugin-ci`** – zusätzliche Absicherung bei jedem Push/PR (unabhängige Umgebung/Matrix: PHP 8.3/8.4 × Moodle 5.1/5.2 × MariaDB/PostgreSQL, seit der Marketplace-Vorbereitung 2026-07-22 auch PostgreSQL, nicht mehr nur MariaDB): PHPUnit, Behat, phpcs (moodle-Ruleset), phplint, mustache-Lint etc. Ergebnis im GitHub-Actions-Tab prüfen, auch wenn Punkt 2 bereits lokal grün war – andere PHP-/Moodle-Versionen und DB-Engine können abweichen.

**Fortschritt:** Schritt 0 bis 12 sind umgesetzt und released (siehe Release 1.0.0/1.1.0-Commits); der Code-Review-Nacharbeitungs-Durchgang (2026-07-16) ist abgeschlossen und released (1.1.1). Seit 2026-07-22 läuft die Marketplace-Submission-Vorbereitung, siehe neuer Abschnitt "Marketplace-Submission" unten.

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

## Marketplace-Submission

Checkliste liegt ausserhalb dieses Repos (`Marketplace.md` im Claude-Projektverzeichnis, nicht im Plugin-Repo selbst). Stand 2026-07-22:

- **Compliance-Audit durchgeführt:** Lizenz-Header (alle `.php`), GPLv3-`LICENSE`, Privacy-Provider (`null_provider`, da kein eigenes DB-Schema, siehe oben), keine Treffer für `eval()`/`unserialize()`/rohes `$_REQUEST`/`$_GET`/`$_POST`, keine rohe SQL-Konkatenation (`$DB->...` mit Platzhaltern durchgängig), Settings ausschliesslich über `get_config('local_admincockpit', ...)`/`config_plugins`, kein `composer.json` nötig, öffentlicher GitHub-Issue-Tracker vorhanden (Repo `tkorner/moodle-local_admincockpit`, öffentlich, Issues aktiviert) – alles ✅, keine Fixes nötig.
- **CI-Matrix erweitert:** vorher nur MariaDB, jetzt zusätzlich PostgreSQL (Guideline verlangt beide Cross-DB-Engines) – siehe `.github/workflows/ci.yml`.
- **Reifegrad angehoben:** `version.php` von `MATURITY_RC`/`1.1.1` auf `MATURITY_STABLE`/`1.2.0` für die Submission.
- **Bewusste Abweichung dokumentiert:** `lang/de/` wird schon vor offizieller AMOS-Freigabe mitgeliefert – siehe README.md "Known open assumptions", letzter Punkt.
- **Nicht automatisiert (bewusst, siehe Rückfrage 2026-07-22):** Screenshots (Dashboard + Settings) macht der Nutzer selbst per Browser-Login; das tatsächliche Release-Zip auf einer frischen Instanz testen (Checklisten-Punkt 8 – der `admin_externalpage_setup()`-Fehlerklasse) bleibt ebenfalls manuelle Aufgabe vor dem nächsten Tag.
- **Git-Tag + GitHub-Release + tatsächliche Einreichung bei marketplace.moodle.com:** explizit erst nach erneuter Rücksprache, nicht automatisch am Ende dieser Session.
- **Frankenstyle-Rename (2026-07-23):** `local_admindashboard` war bereits von zwei fremden GitHub-Repos belegt (`UzainAliSiddiqui/moodle-local_admindashboard`, eingebettetes Plugin in `Integer-Training/integermoodle1`) und damit für die Marketplace-Einreichung nicht nutzbar. Neuer, kollisionsfrei geprüfter Name: `local_admincockpit`, sichtbarer Produktname konsistent auf "Admin Cockpit" geändert. GitHub-Repo entsprechend umbenannt (`tkorner/moodle-local_admincockpit`). Version auf `2026072300`/`2.0.0` angehoben (Major-Bump statt Patch, da der Rename für bestehende Installationen ein Breaking Change ist – altes Plugin muss vor der Installation von `local_admincockpit` deinstalliert werden). Alte Tags/Releases `v1.0.0`–`v1.2.0` bleiben unter dem alten Namen als historische Commits bestehen (nicht gelöscht/umgeschrieben); neue Historie ab `v2.0.0` läuft unter `local_admincockpit`.

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
claude-code-prompt-admincockpit.md).

---

## Nicht tun

- Keine Runbook-/Doku-Einträge automatisch erzeugen – das erfolgt separat, nach Abschluss und eigenem Review
- Keine Block-Variante parallel bauen – v1 ist bewusst nur die Seite
- Keine zusätzlichen Health-Signale über die in der Spec genannten vier hinaus ergänzen, ohne Rückfrage (bewusst reduzierter v1-Scope)

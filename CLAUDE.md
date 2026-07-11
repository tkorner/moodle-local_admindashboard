# CLAUDE.md – local_admindashboard

Kontext-Datei für Claude Code Sessions in diesem Projekt. Vor Arbeitsbeginn lesen.

---

## Projektüberblick

Moodle-Plugin `local_admindashboard`: Navigations- und Kennzahlen-Dashboard für Administratoren. Zeigt Nutzer-/Kurs-Kennzahlen pro "Schule" (Kohorte + Top-Level-Kategorie, verknüpft über `idnumber`), Health-Signale mit Call-to-Action sowie Direktlinks zu häufig genutzten Verwaltungsseiten.

**Quelle der Wahrheit für Anforderungen:** `SPEC-admindashboard.md` im selben Verzeichnis. Bei Widersprüchen zwischen dieser Datei und der Spec gilt die Spec – hier nachfragen statt selbst zu entscheiden.

**Umsetzungssequenz:** `claude-code-prompt-admindashboard.md` im selben Verzeichnis. Enthält 8 Schritte, die **einzeln nacheinander** abgearbeitet werden – nicht mehrere Schritte auf einmal umsetzen, auch wenn der Kontext das hergeben würde. Nach jedem Schritt wird das Ergebnis reviewt, bevor der nächste beginnt.

---

## Zielumgebung

- **Moodle-Version:** 5.1.x und 5.2.x
- **Lokaler Plugin-Ordner:** `/Users/tkorner/Documents/claude/plugins/local/admindashboard/`
- **Docker-Mount-Ziel:** `/var/www/html/local/admindashboard/`
- **Docker-Compose-Mount:** `./plugins/local:/var/www/html/local` (nicht die alte, flache `./plugins:/var/www/html/local`-Zeile verwenden)
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

Folgende Dinge dürfen nicht aus Trainingsdaten/Vermutung implementiert werden, sondern müssen im tatsächlichen Moodle-Core-Code bzw. der installierten Instanz verifiziert werden:

- Core-Klasse/-Methode hinter dem Security-Overview-Report (`admin/report/security/`) – wiederverwenden, nicht neu implementieren
- Core-Tabelle/-Klasse für Task-Log/Cron-Status
- Exakte URL/Parameter für die Scheduled-Tasks-Übersicht
- Exakte Section-URL der `theme_boost_union`-Einstellungen (mehrere Tabs, nicht raten)
- Bei Unsicherheit: explizit nachfragen statt einer plausibel klingenden, aber ungeprüften Annahme

---

## Testing-Strategie

Kein lokales GUI, PHPUnit lokal nicht installierbar. Deshalb zwei Ebenen:

1. **CLI-Smoke-Skripte** (`cli/verify_*.php`) – sofortiges Feedback während der Session, direkt gegen die echten Daten der laufenden Docker-Instanz, ohne Testframework:
   ```bash
   docker exec -it claude-moodle-1 php /var/www/html/local/admindashboard/cli/verify_school_matcher.php
   ```
   Kein Ersatz für echte Tests, nur Sichtprüfung während der Entwicklung.

2. **GitHub Actions mit `moodlehq/moodle-plugin-ci`** – führt bei jedem Push/PR die eigentliche Absicherung durch: PHPUnit, Behat, phpcs (moodle-Ruleset), phplint, mustache-Lint etc. PHPUnit-Testdateien werden trotzdem geschrieben (siehe Prompt-Dokument Schritt 1/3/4) – sie laufen nur nicht lokal, sondern über CI. Ergebnis im GitHub-Actions-Tab prüfen, nicht lokal erwarten.

**Fortschritt:** Schritt 0 (Grundgerüst) und Schritt 1 (`school_matcher`) sind bereits umgesetzt. CI-Pipeline (Schritt 0b) wird nachträglich ergänzt.

---

## Coding-Standards

- Moodle Coding Guidelines / Moodle Coding Style (phpcs mit moodle-Ruleset, falls lokal verfügbar)
- Core-contribution-taugliche Qualität von Anfang an, auch wenn dies (anders als `qbank_cffpoc`) nicht für einen Core-Merge vorgesehen ist
- Moodle Data Manipulation API für alle DB-Zugriffe (kein rohes mysqli, keine ungefilterten String-Konkatenationen in SQL)
- PHPUnit-Tests für alle Metrik-/Matching-Klassen (siehe Prompt-Dokument, Schritt 1, 3, 4)
- Sprachdateien: `lang/en/` und `lang/de/` parallel pflegen, keine hartcodierten Strings im Code

---

## Bekannte offene Annahmen (aus SPEC Abschnitt 8)

1. Kurszahl pro Schule inkl. Subkategorien – Annahme "ja", im Code explizit kommentiert, revidierbar
2. Security-Overview-Aggregation – API noch zu identifizieren
3. Scheduled-Tasks-URL – noch zu verifizieren
4. Boost-Union-Settings-URL – noch zu verifizieren

Diese Liste bei Bedarf ergänzen, wenn im Verlauf der Implementierung neue offene Punkte auftauchen – nicht stillschweigend Annahmen treffen und weitermachen.

---

## Nicht tun

- Keine Runbook-/Doku-Einträge automatisch erzeugen – das erfolgt separat, nach Abschluss und eigenem Review
- Keine Block-Variante parallel bauen – v1 ist bewusst nur die Seite
- Keine zusätzlichen Health-Signale über die in der Spec genannten vier hinaus ergänzen, ohne Rückfrage (bewusst reduzierter v1-Scope)

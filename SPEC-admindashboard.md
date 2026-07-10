# SPEC – local_admindashboard

Navigations- und Kennzahlen-Dashboard für Moodle-Administratoren. Bündelt Nutzer-/Kurs-Kennzahlen pro "Schule", Health-Signale mit Call-to-Action sowie Direktlinks zu häufig genutzten Verwaltungsseiten.

---

## 1. Zweck

Ein Admin verbringt aktuell Zeit damit, über mehrere Menüpunkte verteilt den Zustand der Instanz zu erfassen (Nutzerzahlen, Kurszahlen pro Schule, Datenhygiene-Probleme). Das Dashboard bündelt das auf einer Seite:

- **Kennzahlen** – Ist-Zustand + Veränderung über einen einstellbaren Zeitraum
- **Health-Signale** – Zahl + direkter Klick zur Behebung/Prüfung (kein reines Reporting)
- **Navigation** – Kurzwege zu Verwaltungsaufgaben, gruppiert nach Aufgabentyp und pro Schule

Kein Nachbau von Moodle Workplace Report Builder – bewusst schlanker, auf die konkreten Bedürfnisse dieser Instanz zugeschnitten.

---

## 2. Grundkonzept "Schule"

Eine Schule besteht aus zwei unabhängig gepflegten Moodle-Objekten, die über ein gemeinsames Kürzel (`idnumber`) verknüpft werden:

- einer **globalen Kohorte** (`idnumber` = Kürzel, z. B. `TBZ`)
- einer **Top-Level-Kurskategorie** (`idnumber` = dasselbe Kürzel)

Es gibt keine feste Namenskonvention (Anzeigenamen können frei bleiben), das Matching läuft ausschliesslich über `idnumber`. `idnumber` ist ein freier String (alphanumerisch), Kürzel wie `TBZ` sind also problemlos möglich.

**Wichtig:** Nicht jede Top-Level-Kategorie und nicht jede globale Kohorte ist zwangsläufig eine "Schule" – es können weitere Kategorien/Kohorten ohne Dashboard-Bezug existieren. Das Plugin muss daher:

1. Alle Kürzel ermitteln, bei denen sowohl eine Kohorte als auch eine Top-Level-Kategorie mit identischer `idnumber` existieren (= vollständige Paare)
2. Kürzel mit nur einseitigem Match (Kohorte ohne Kategorie oder umgekehrt) in den Plugin-Einstellungen als Warnung anzeigen, nicht stillschweigend ignorieren
3. Aus den vollständigen Paaren eine Auswahl-Liste bauen, aus der der Admin die auf dem Dashboard anzuzeigenden Kürzel per Mehrfachauswahl wählt

Es wird **keine eigene Zuordnungstabelle** in der Datenbank benötigt – die Zuordnung wird zur Laufzeit über `idnumber` aufgelöst; nur die Auswahl ("welche Kürzel sind aktiv") wird als Plugin-Setting gespeichert.

---

## 3. Kennzahlen

Alle "im Zeitraum"-Werte beziehen sich auf einen global einstellbaren Zeitraum (Standardwerte 30 / 90 / 180 / 360 Tage, als Admin-Setting wählbar, keine Snapshot-Historie – reiner `timecreated`-Filter).

### Global
| Kennzahl | Quelle / Logik |
|---|---|
| Nutzer gesamt | Anzahl aktive (nicht gelöschte) Nutzerkonten |
| davon aktiv | `lastaccess` innerhalb der letzten 4 Wochen |
| Neue Nutzer im Zeitraum | `timecreated` innerhalb des gewählten Zeitraums |

### Pro Schule (für jedes ausgewählte Kürzel)
| Kennzahl | Quelle / Logik |
|---|---|
| Mitgliederzahl | Kohorten-Mitglieder (`cohort_members`) |
| Neuzugänge im Zeitraum | `cohort_members.timeadded` innerhalb Zeitraum |
| Aktive Mitglieder | Mitglieder mit `lastaccess` innerhalb der letzten 4 Wochen |
| Kurszahl | Kurse in der zugeordneten Top-Level-Kategorie, **ohne** Subkategorie-Aufschlüsselung – zu klären: zählen Kurse in Subkategorien mit oder nur direkt in der Top-Level-Kategorie? (Annahme: inkl. Subkategorien, aber ohne separate Anzeige je Subkategorie – bitte bei Umsetzung bestätigen) |
| Neue Kurse im Zeitraum | `course.timecreated` innerhalb Zeitraum, gefiltert auf die Kategorie |

---

## 4. Health-Signale (v1)

Jedes Health-Signal ist eine Zahl **mit Klick-Ziel** (Call-to-Action) – keine reine Statistik-Kachel.

| Signal | Logik | Klick-Ziel |
|---|---|---|
| Doppelte E-Mail-Adressen | Nutzer mit identischer `email`, gruppiert | Eigene Liste im Plugin mit betroffenen Nutzerpaaren/-gruppen, als Vorbereitung für `tool_mergeusers` |
| Kurse ohne Enddatum | `course.enddate = 0` | Eigene gefilterte Kursliste im Plugin, von dort Sprung in die jeweiligen Kurseinstellungen |
| Security-Overview-Ampel | Aggregierter Status der Core-Security-Checks (grün/gelb/rot) | Direktlink zur bestehenden Seite Site administration → Reports → Security overview |
| Cron-Status | Zeitpunkt letzter Cron-Lauf + Anzahl fehlgeschlagener Scheduled Tasks | Direktlink zur bestehenden Scheduled-Tasks-Übersicht |

**Bewusst nicht in v1:** unbestätigte Konten, auth-Methoden-Übersicht, Plugin-Update-Übersicht, Kurse ohne Teilnehmer/Lehrperson (mögliche v2-Kandidaten).

**Offen bei Umsetzung:** exakte API/Query zur Aggregation der Security-Overview-Ergebnisse (Core-Klasse identifizieren, nicht neu erfinden) und exakte URL/Parameter der Scheduled-Tasks-Übersicht – vor dem Bau im Code verifizieren.

---

## 5. Navigation

Gruppiert nach Aufgabentyp, reine Links ohne Logik:

**Pro Schule** (für jedes ausgewählte Kürzel, direkt bei der Schul-Kennzahlengruppe)
- Link zur Kursverwaltung der zugeordneten Kategorie (`course/management.php?categoryid=X`)

**Nutzerverwaltung**
- Nutzer/innen hochladen
- Kohorten verwalten / hochladen
- Merge Users (`tool_mergeusers`)

**Kursverwaltung**
- Kurs anlegen
- Kategorien verwalten
- Kurs-Backup/-Restore

**Berichte/Logs**
- Report Builder (Custom Reports)
- Site-Logs
- Config-Change-Log (`report_configlog`)

**System**
- Scheduled Tasks Übersicht

**Theme/Erscheinungsbild**
- Direktlink zu den Boost-Union-Theme-Einstellungen (genaue Section-URL bei Umsetzung im Code prüfen, Theme hat mehrere Tabs)

---

## 6. Konfigurationsseite (Plugin-Settings)

- **Zeitraum** für "neu im Zeitraum"-Werte: Auswahl 30 / 90 / 180 / 360 Tage (einzelner globaler Wert für v1, kein individuelles Setting pro Kennzahl)
- **Aktive Schul-Kürzel**: Mehrfachauswahl aus allen gefundenen vollständigen Kohorte/Kategorie-Paaren
- **Warnliste**: schreibgeschützte Anzeige von Kürzeln mit nur einseitigem Match (Kohorte ohne Kategorie-Pendant oder umgekehrt)

---

## 7. Technischer Aufbau (Vorschlag)

- **Plugin-Typ:** `local_admindashboard` – eigene Admin-Seite (`admin_externalpage`) unter Site administration → Reports, kein Block (vermeidet Block-Regionen-/Theme-Constraints)
- **Kein eigenes DB-Schema nötig** – alle Werte werden zur Laufzeit berechnet (kein Snapshot-Mechanismus, da bewusst auf `timecreated`-Filter statt historischer Delta-Werte gesetzt)
- **Capability:** `local/admindashboard:view`, Standard-Kontext System, nur für Nutzer mit Admin-Rolle vorgesehen
- **Rendering:** eigener Renderer + Mustache-Templates für Kachel-Layout; Zahlen serverseitig berechnet, keine AJAX-Nachladelogik in v1
- **Wiederverwendung Core-APIs wo sinnvoll:** z. B. bestehende Security-Overview-Logik referenzieren statt Checks neu zu implementieren

---

## 8. Offene Punkte vor Implementierungsstart

1. Zählt die Kurszahl pro Schule Kurse aus Subkategorien mit? (Annahme: ja, siehe Abschnitt 3)
2. Exakte Core-Klasse/-API zur Security-Overview-Aggregation identifizieren
3. Exakte URL/Parameter für Scheduled-Tasks-Übersicht und für gefilterte Nutzerlisten (falls für Klick-Ziele der Health-Signale benötigt)
4. Exakte Section-URL der Boost-Union-Theme-Einstellungen

---

## 9. Explizit ausserhalb des Scopes (v1)

- Historische Trend-/Delta-Werte über Snapshot-Tabelle (siehe frühere Diskussion) – nur einfache `timecreated`-Filter
- Weitere Health-Signale (unbestätigte Konten, auth-Mismatch, Plugin-Updates, Kurse ohne Teilnehmer/Lehrperson)
- Automatisierte Zuordnung Kohorte↔Kategorie über etwas anderes als `idnumber`
- Block-Variante (nur eigene Admin-Seite in v1)

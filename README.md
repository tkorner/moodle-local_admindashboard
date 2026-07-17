[![Moodle Plugin CI](https://github.com/tkorner/moodle-local_admindashboard/actions/workflows/ci.yml/badge.svg)](https://github.com/tkorner/moodle-local_admindashboard/actions/workflows/ci.yml)

# Admin Dashboard (Moodle local plugin)

A single-page overview for Moodle administrators: global and per-group user
metrics, data-hygiene and infrastructure health signals (each with a
click-through to fix or investigate), and a grouped set of shortcuts to the
admin pages you end up needing most.

## Why this plugin exists

Getting a picture of a Moodle instance's health today means visiting half a
dozen different pages: user counts, cohort membership per group, the
security overview report, the scheduled tasks page, and so on. This plugin
puts the numbers that matter on one page and makes every health signal
clickable - a number on its own is a statistic, a number with a click-target
is something you can act on.

## What a "school" (or whatever you call it) is

Everything below calls this a "school" because that's this plugin's
historical default term, and it's still what all the internal class/file
names (`school_matcher`, `school_metrics`, ...) use - renaming those was
deliberately out of scope, see "Known open assumptions" below. The word
shown in the dashboard UI itself is a separate, free-text setting
(`groupinglabel`, e.g. "Site", "Department", "Faculty") and defaults to
"Schule"/"School" purely for backwards compatibility with earlier versions
of this plugin, not because the concept is school-specific.

Whatever you call it, a "school" is **two independently maintained Moodle
objects sharing one code**: a system-wide cohort and a top-level course
category, matched purely by their `idnumber` (never by display name). Codes
with only one side present (a cohort with no matching category, or vice
versa) show up as a warning on the settings page rather than being silently
ignored.

## Design principles / guardrails

- **No database schema of its own.** Every number is computed at request
  time (`timecreated`/`timeadded`/`lastaccess` filters), not a snapshot
  history - a deliberate scope decision, not an oversight.
- **Cohort↔category matching is `idnumber`-only.** Display names are never
  used for matching.
- **Metric classes (`classes/metrics/*.php`) know nothing about pages,
  URLs, or rendering.** All of that lives in `classes/output/`. This keeps
  the door open for a possible future "page → block" conversion without
  touching a single calculation.
- **A health signal is always a number with a click-target**, never a bare
  statistic.
- **Core APIs are reused, not re-implemented**, wherever one already
  exists for the job - see below.

## Reused core APIs (verified against actual core source, not guessed)

| Signal / feature | Core API actually used | Where it was verified |
|---|---|---|
| Security overview traffic light | `\core\check\manager::get_checks('security')` - the same API `report_security/index.php` itself uses | `lib/classes/check/manager.php`, `report/security/index.php` |
| Cron status | `get_config('tool_task', 'lastcronstart')` + `task_log.result` - not a derived `MAX(timeend)`, which would be misleading if cron ran but nothing was due | `admin/tool/task/classes/check/cronrunning.php` |
| Subcategory-inclusive course counts | `course_categories.path LIKE '{path}/%'` - the same prefix-matching technique `core_course_category` uses internally | `course/classes/category.php` |
| Health signal traffic-light badges | `<span class="badge bg-success/bg-warning/bg-danger ...">` - the exact markup `\core\check\result`'s own mustache partials use | `lib/templates/check/result/*.mustache` |
| Relative "last run X ago" | `format_time(time() - $timestamp)` - the same idiom core's own `lastaccess` report columns use | `admin/classes/reportbuilder/local/systemreports/users.php` |
| Restore-course shortcut | `backup/restorefile.php?contextid={system context}` - the exact link Site administration → Courses uses for a course-agnostic restore entry point | `admin/settings/courses.php` |

## Known open assumptions

A handful of judgment calls are deliberately not hidden away - each is
documented at the point of decision in the relevant class's docblock:

- Course counts include subcategories (`classes/metrics/school_metrics.php`).
- Suspended accounts are counted as "active" users (`classes/metrics/user_metrics.php`).
- The cron and security-overview severity thresholds are this plugin's own
  heuristics, not a reuse of an existing core verdict (`classes/output/dashboard_page.php`).
- Two SPEC navigation bullets ("Kohorten verwalten / hochladen" and "Kurs-Backup/-Restore")
  are each interpreted as either two links or one, depending on whether a
  course-agnostic entry point exists (`classes/navitems_parser.php::default_value()`).
- The default navigation links carry no capability restriction, matching the
  pre-Schritt-7h behaviour of showing all of them to anyone who can already
  see the dashboard; an admin can add one per link via the optional 4th
  `|`-separated segment (`classes/navitems_parser.php`).

## Capability

`local/admindashboard:view` (system context, granted to the Manager
archetype by default) gates the dashboard and its two drill-down pages. The
settings page additionally requires `moodle/site:config`, same as any
other plugin configuration page.

## Install

1. Place the folder at `moodle/local/admindashboard`.
2. Site administration → Notifications, to trigger the install/upgrade.
3. Configure active school codes, the default time range, what to call a
   "school" on this instance (e.g. Site, Department, Faculty), and
   (optionally) the navigation links shown at the bottom of the dashboard,
   at Site administration → Plugins → Local plugins → Admin Dashboard.
4. Open the dashboard at Site administration → Reports → Admin Dashboard.

## Screenshots

To be added: main dashboard view and the settings page.

## Compatibility

Targets Moodle 5.1 and 5.2, PHP 8.3 and 8.4 - enforced by the CI matrix
below (`version.php`'s `requires` is pinned to the Moodle 5.1.0 branching
version). Also manually live-verified end-to-end (real HTTP sessions
against a running instance, not just unit tests) throughout development
against a Moodle 5.2.1 container. No third-party JS/CSS libraries and no
additional PHP dependencies - nothing beyond Moodle core is used.

## Tests & CI

Uses [Moodle Plugin CI](https://moodlehq.github.io/moodle-plugin-ci/) on
GitHub Actions across PHP 8.3/8.4 × Moodle 5.1/5.2 on MariaDB: PHP lint,
Moodle coding style (moodle-cs), PHPDoc checker, upgrade savepoints,
Mustache lint, PHPUnit, and Behat.

For fast local feedback without waiting on CI, the PHPUnit suite itself can
be run directly against the running container (a PHPUnit test environment
turned out to already be installed there, contrary to this project's
earlier assumption throughout development that only CI could run it):

```bash
docker exec -it claude-moodle-1 sh -c "cd /var/www/html && vendor/bin/phpunit --configuration phpunit.xml --testsuite local_admindashboard_testsuite"
```

`cli/verify_*.php` scripts remain for eyeballing each metrics/matching
class against the real data of a running instance - a sanity check, not a
substitute for the tests above:

```bash
docker exec -it claude-moodle-1 php /var/www/html/public/local/admindashboard/cli/verify_school_matcher.php
docker exec -it claude-moodle-1 php /var/www/html/public/local/admindashboard/cli/verify_user_metrics.php
docker exec -it claude-moodle-1 php /var/www/html/public/local/admindashboard/cli/verify_school_metrics.php
docker exec -it claude-moodle-1 php /var/www/html/public/local/admindashboard/cli/verify_health_signals.php
docker exec -it claude-moodle-1 php /var/www/html/public/local/admindashboard/cli/verify_navitems.php
```

## Development

Built with [Claude Code](https://claude.com/claude-code) (Anthropic's AI
coding assistant) in an iterative, step-by-step process with human review
after each step. Every core API claim in this README was verified against
actual Moodle core source rather than assumed, and most steps were also
live-tested end-to-end against a running Moodle instance (real HTTP
sessions, real data) before being committed.

## License

GPL v3 or later - see [`LICENSE`](LICENSE).

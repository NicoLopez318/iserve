# Validation record

2026-10-02: 20 automated tests passed, 219 assertions. Tests use an isolated in-memory SQLite database and fake private storage.

Coverage includes public screen rendering, service search/unit filters, office-scoped announcement publication and future visibility; private activity drafts, retained files, owner isolation and conversion to submitted requests; moderator editing authorization and organization isolation; authentication, staff authorization, document access, request review/revision/cancellation, scholarship calculation, report snapshots, community hours/four journal modules, event verification and EXP, organization posts and two-factor challenges.

Browser verification: public homepage and directory rendered, directory navigation opened a complaint form, and Next displayed the details/document step. Public homepage at 390px viewport reported document client/scroll width 375px with no missing images. Earlier desktop/mobile portal and authentication captures remain under design-reference/screenshots.

The local .fig source is now fully inventoried. Full pixel-level matching of every frame remains unverified; see DESIGN_COVERAGE.md for concrete differences. Live SMTP, Google credentials, official finance data and APK delivery are not configured or externally verified. Local mail uses the log driver.

Packaging excludes cached views, sessions and logs, verifies ZIP CRCs, and writes a SHA-256 checksum. The ZIP includes source, vendor dependencies, local assets and SQLite database.


## MySQL/phpMyAdmin conversion — 2026-10-02
- Generated schema directly from Laravel MySQL migration grammar; no inserted records or destructive statements in the import SQL.
- Imported into local MariaDB 10.4.32; verified 18 tables all with zero rows, including migration history.
- Verified transactional user/request creation and JSON round trip, then rolled back and reset test sequences.
- Full feature suite also passed on a separate, newly created MySQL validation database: 20 tests, 219 assertions. That validation database was removed afterward; the application database remains empty.
- Events use DATETIME for proposed start/end values to avoid implicit TIMESTAMP defaults on MariaDB.
- Application config and .env now use MySQL. Legacy empty SQLite is retained but inactive.

## Assistant/admin routing — 2026-10-03
24 tests passed, 446 assertions on both isolated SQLite and MySQL databases. Covers all service-request kinds at unit approval/assistant forwarding stages, blocked admin/assistant bypasses, own-unit restrictions, private file monitoring, read-only assistant restrictions, revision restart, final approval benefits exactly once, and head report revision/forward/final approval. MySQL tests used a newly created validation database which was removed afterward. Existing live records and staff accounts were preserved. No schema migration or re-import is needed.

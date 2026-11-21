# iServe staff portal

This separate `staff_system` application connects to the existing `pre_defense_system` MySQL/MariaDB database used by `pre-defense_system`.

## Run on this computer

1. Start MySQL in XAMPP.
2. Keep `staff_system` and `pre-defense_system` beside each other.
3. Double-click `staff_system/start-staff-system.bat`.
4. Open http://127.0.0.1:8002. Use the existing staff account and password.
5. Run the normal-user application with its own `start-system.bat` on http://127.0.0.1:8001.

PHP dependencies are included in `vendor`; no Composer install is needed for this local package. PHP 8.2+ with PDO MySQL, mbstring, fileinfo, OpenSSL and XML is required.

## Existing accounts

- `sfuhead@gmail.com`: Scholarship & Financial Unit.
- `sluhead@gmail.com`: Student Leadership Unit.
- `cvuhead@gmail.com`: Character & Values Unit.
- `assistant@gmail.com`: Assistant.
- `admin@gmail.com`: Admin.

Existing password hashes and accounts are retained. These are application logins, not newly created Gmail accounts. Staff registration is disabled. Normal users sign in to the normal-user application.

## Shared data and attachments

Both applications use database `pre_defense_system` at `127.0.0.1:3306`. Local XAMPP defaults are configured in `.env`. Staff sessions have a separate cookie.

Private attachments are shared from `../pre-defense_system/storage/app/private`. If you move the normal-user folder, set `SHARED_PRIVATE_ROOT` to its absolute private upload directory in the staff `.env`; set `SHARED_PUBLIC_ROOT` similarly for shared public uploads. Do not copy or clear existing uploads.

Do not re-import SQL into your existing database. `database/pre_defense_system.sql` is an empty structure for a new installation only; it contains no account passwords or transaction data. Do not run `migrate:fresh`, `db:seed`, or a reset script against the live database.

The startup script makes one idempotent compatibility change: `points.amount` accepts signed integers for reviewed point penalties. It does not recreate a table or remove rows. The upgrade script checks the existing range before changing it.

## Workflow

Normal user → assigned Unit Head → Assistant → Admin. Unit approval and Assistant forwarding do not apply the final benefit. Final Admin approval applies it once. A transaction returned for revision goes back through the Unit Head and Assistant.

Each Unit Head submits period reports with source snapshots and private supporting files. Assistant checks and forwards reports or returns them to the Unit Head. Admin can acknowledge, decline, or return a report to the Assistant. Resubmission retains version history and previous files.

The staff portal includes the five role dashboards, scoped transactions and documents, scholarship review, complaints, lost-and-found, readmission and ID replacement, community service and verification, CVU seminars, clearance schedules, organizations, proposals, events, achievements, points, reports, content and settings. Counts come from the shared database; Figma demonstration rows are not seeded.

## Design and remaining limits

Reference: the supplied `newfigma.fig`, exported October 3, 2026. See `DESIGN_COVERAGE.md` for screen mappings and known differences. The original fonts, vector assets and colors are bundled. This build has functional screens and approval flows, but is not yet a verified pixel-for-pixel reproduction of every contextual Figma panel.

Student lookup verifies existing application records; an external university MIS connector is not configured. Email currently uses Laravel's log mailer. Configure SMTP before relying on delivered verification codes or password-reset emails. In-app workflow notifications work locally. Printable slips and reports use the browser's Print / Save as PDF.

Point rule settings are stored; achievement scoring and verified event attendance scoring are implemented. Other rules need explicit business triggers before they can be applied automatically. Organization and community-service creation currently save live records rather than draft records. Some Figma context panels are implemented as detail pages or native dialogs. These differences are listed for review rather than claimed as completed design fidelity.

## Validation

Run `C:\xampp\php\php.exe artisan test` from this folder. The staff suite uses an in-memory SQLite test database. `validate-isolated-mysql.php` creates a randomly named temporary MySQL database, runs the suite there, and drops only that temporary database. Neither test command resets the live database.

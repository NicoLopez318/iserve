# iServe — pre-defense_system

A separate Laravel 12 application for the university services in the supplied Figma design. Includes bundled PHP dependencies, local design assets and fonts, an empty MySQL/MariaDB database schema. The previous system and its databases are not used.

## Run on this computer
Double-click **start-system.bat**, or run:
```powershell
cd C:\Users\kennethtaro\final_system\pre-defense_system
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8001
```
Open http://127.0.0.1:8001/ for the public website; choose Student Portal to sign in. If the port is occupied by the existing preview, open that URL directly.

No Node, npm or Vite build is needed. MySQL/MariaDB must be running. Required PHP extensions include pdo_mysql, fileinfo, openssl, mbstring and xml; PHP 8.2 or newer.

## phpMyAdmin / MySQL setup
1. Start Apache and MySQL in XAMPP.
2. Open http://localhost/phpmyadmin.
3. Create an empty database named `pre_defense_system` with `utf8mb4_unicode_ci` collation.
4. Select that database, click Import, choose `database/pre_defense_system.sql`, and click Go.
5. Run `start-system.bat`, then open http://127.0.0.1:8001.

The SQL contains structures only, with zero rows and no demo accounts. It does not delete existing tables. Import into an empty database. If that database already contains records, choose another name and update DB_DATABASE instead.

Local XAMPP connection: 127.0.0.1:3306, database pre_defense_system, user root, blank password. Change DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env if needed, then run `php artisan config:clear`.

Migration history is empty as requested. The launcher uses the imported schema without running migrations. Do not run `artisan migrate` against it: existing tables would conflict. Register a new account to begin. Old demo credentials no longer work. The empty SQLite file is a legacy copy and is not active.

## Service flow
- Register → verify email → complete student/employee/guardian profile → choose a transaction.
- Forms validate details and required documents → a unique ticket is created → the assigned office reviews it.
- Unit heads review their own unit and approve at unit level → assistant monitors all units and returns for revision or forwards unit-approved requests → admin gives final approval only to assistant-forwarded requests. Returning for revision resets the chain after the applicant resubmits. Final decisions cannot silently be changed.
- The SFU head recommends a validated discount rate; the discount takes effect only after final admin approval. Tuition calculations use integer cents and the administrator's configurable fee schedule. Semester reports preserve award snapshots, pass through assistant review/forwarding, then reach admin for final approval.
- Final admin approval of leadership connects the student to an organization. Approved activity proposals publish events. Event registration respects capacity and date windows; time in/out and reflection go to staff verification before EXP is credited.
- Final admin achievement approvals award EXP once. Organization posts and comments are restricted to members.
- Community records are assigned by CVU. Photo evidence accompanies time in/out. Staff approve hours; journal modules unlock from verified progress. All required hours and four approved modules are required for final completion. Active records block clearance applications.
- Clearance slots have limited capacity. Seminar registrations go through Leadership review and verified attendance.

## Email and Google sign-in
Default MAIL_MAILER=log writes verification links, password reset links, and email sign-in codes to storage/logs/laravel.log for local development. It does not send real email.

For live delivery, configure the standard Laravel SMTP variables in .env: MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_FROM_ADDRESS.

Google OAuth is implemented but enabled only after GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are configured. Set the provider redirect URI to APP_URL/auth/google/callback. Use HTTPS for a real deployment. Email sign-in codes can be enabled after verifying the account email.

## Validation
```text
php artisan test
```
Tests use an in-memory SQLite database and fake local storage. They cover registration, authorization, private files, request transitions, revised submissions, scholarship math, community verification, event capacity/windows, unique EXP awards, organization membership, report reviews and fee settings.

## Design source and coverage
See DESIGN_COVERAGE.md. The supplied Untitled.fig was decoded locally, including all 4,974 nodes and the seven bitmap reference screens. Public Home, Services, three Units, Announcements and About use native HTML with photo/emblem crops from those original assets. No full-page design screenshot is used as an application UI.

Student portal and transaction screen families have functional routes. Activity proposals support private drafts, retained documents and submission for office review. Shared templates implement repeated states. A full pixel-by-pixel match of every frame has not been verified; the activity editor and achievement archive use shared page layouts instead of reproducing every Figma dialog. Edit Moderators supports same-organization selection by the organization president or authorized Leadership staff.

The Android banner opens the responsive community portal: no mobile APK was provided. SMS delivery and official finance integration are not configured. The fee schedule begins with demo values and must be reviewed by the university.

## Packaging
The full ZIP includes vendor dependencies, source, local assets, tests, empty MySQL schema and a local .env. The packager excludes sessions, logs, cached views and test upload fixtures, recreates empty runtime directories, verifies its entries, and records a SHA-256 checksum. Export MySQL and back up storage/app/private together for real submissions.


## Assistant and admin workflow update — 2026-10-03
The existing local database now has provisioned SFU, SLU, CVU, assistant and admin accounts. Their credentials were supplied privately in the chat and are not stored in this source or in the empty SQL import file.

Update the application code using this ZIP while retaining your existing .env, MySQL database and uploaded documents. Do not re-import the empty SQL into your existing database. No schema change or migration is required for this workflow update.

Staff roles are sfu_head, slu_head, cvu_head, assistant and admin. Request states now include unit_approved (awaiting assistant) and awaiting_admin (forwarded by assistant). Assistant cannot give final approval or manage staff/settings. Admin cannot approve a request before assistant forwarding. All unit/assistant/admin reviews record an audit entry; review history is visible on each request. The assistant can monitor all requests, private attachments, last reviewers and audit history.

Student-facing request review and backend permissions are implemented in the current application. The separate portal using the new Figma design awaits that design link/file and folder name.

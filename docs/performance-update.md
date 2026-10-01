# Performance update — 1 October 2026

## What changes for users

- Notification badges and popups refresh about once a minute while the tab is visible. Hidden/offline tabs do not poll. A failed request backs off to two, four, then five minutes; successful requests restore the normal interval.
- Citizen and barangay dashboards fetch only the five/ten recent reports they display. Totals still count the full matching report set. Full report lists and CSV exports retain their existing scope.
- Barangay status counts now apply date, risk, and search filters in the correct parameter order.
- Database updates are recorded in the database instead of depending on a writable local marker file. Normal requests skip repeated user/device/activity/notification schema checks.
- Notification endpoints release the session lock after authentication and, for changes, CSRF validation.
- Existing report and authentication emails keep sending directly on InfinityFree. Email settings are reused within each request; provider connections have a five-second connection timeout and the existing thirty-second total timeout.

These changes reduce application load. They do not establish the cause of the intermittent 502 errors or guarantee that InfinityFree outages will stop. Email fan-out still happens during requests while the optional queue is disabled.

## Upload to InfinityFree

The update ZIP is a patch for the existing site, not a full installation.

1. Back up the current site files and database using your hosting panel.
2. Extract `dist/performance-update-20261001.zip` locally. Upload its contents into the existing app root, preserving the folders and replacing matching files. Upload during a quiet period so visitors do not encounter a partially updated set of files. New helper files must accompany `config/database.php`.
3. Keep your existing `config/env.php` and `uploads/`. Neither is in the update ZIP. No configuration edit is needed for InfinityFree. Leave `EMAIL_QUEUE_ENABLED` undefined or `false`.
4. Open the site once after upload. The first database connection performs any missing schema updates and saves `_app_schema_version` in `system_settings`. Later requests skip them. The old `config/.schema_version` file is no longer used.
5. Sign in, check each dashboard, apply a barangay date/risk filter, open notifications, and try marking one read. Hard-refresh if the browser still shows old behavior. Check report submission and email delivery using your normal test account.

If the first load reports a database-update error, inspect the hosting PHP error log. Do not repeatedly refresh or import a replacement database. The update requires the same CREATE/ALTER permissions as the previous runtime migrations; MySQL advisory locks prevent concurrent schema updates.

### Changed application files

```text
assets/js/notification-polling.js             NEW
config/database.php
controllers/AuthController.php
controllers/LiveSyncController.php
controllers/NotificationController.php
controllers/ReportController.php
helpers/EmailQueue.php                       NEW, disabled unless opted in
helpers/SchemaMigration.php                  NEW, required
helpers/SettingsHelper.php
models/ActivityLog.php
models/Notification.php
models/Report.php
models/UserDevice.php
views/barangay/dashboard.php
views/citizen/dashboard.php
views/layouts/sidebar.php
scripts/email-worker.php                     NEW, CLI only
```

`config/env.php.example` documents the optional queue setting. Do not replace your live `env.php` with that example. Test files are kept in the repository and omitted from the upload ZIP. The existing full-site ZIP is not refreshed; use this update ZIP for these changes.

## Optional report-email queue on hosting with a PHP scheduler

Keep this disabled on InfinityFree: its [cron feature is disabled](https://forum.infinityfree.com/t/cron-job-feature-is-now-disabled/80495/). Enabling a queue without a scheduled sender leaves emails waiting.

On a host with PHP CLI and scheduled jobs:

1. Run `php scripts/email-worker.php --install` from the app directory. This creates only the email outbox table, after the normal app migrations.
2. Schedule `php /absolute/app/path/scripts/email-worker.php --run` every minute. Check the scheduler output and configure alerts for failure. Verify a successful run before enabling the queue.
3. Add `define('EMAIL_QUEUE_ENABLED', true);` to that deployment's `config/env.php`. Only report receipts/status/official notifications use the queue. Login, OTP, password-reset and gateway-test emails still send immediately.
4. Check `php scripts/email-worker.php --status`. It reports counts and the oldest creation time (Unix timestamp) for pending, sending, sent, and failed messages. Watch for pending mail older than a few minutes as well as failed scheduler runs.

The worker processes up to ten messages per run, starts no new sends after fifty seconds, and allows an in-flight provider call to finish. Overlapping workers use atomic claims with two-minute leases. Failed sends wait before retrying, up to five attempts; exhausted messages remain in `failed`. After fixing the provider problem, run `php scripts/email-worker.php --retry-failed` to try them again.

Delivery is **at least once**: if a provider accepts an email but its acknowledgement is lost, a retry can send a duplicate. A successful send clears the queued recipient/body; completed rows expire after seven days. Failed messages retain content for retry and should be reviewed by the operator. No email provider credentials are stored in queue rows.

If the queue cannot accept a message, the application attempts direct delivery. Turning the setting off stops new enqueues; keep the scheduled worker running until existing pending messages are handled. The worker is inaccessible through a browser.

## Verification

- `tests/backend-performance.php`: isolated local MariaDB on port 13308, disposable database and copied app settings. Tests legacy schema upgrades, repeat/failing migrations, report limits and totals, scoped notification counts, activity/device writes, overlapping senders, retry delays, exhaustion, and interrupted-worker recovery. No real email is sent.
- `node tests/notification-polling.test.js`: simulated browser/timers verify the interval, hidden/offline tabs, initial empty notification history, backoff/recovery, timeouts, overlapping-request prevention, and expired sessions.
- PHP syntax checks and JavaScript syntax checks cover the changed files.

The live InfinityFree server and its logs were not accessed, so production performance and the 502 cause remain unverified.

# Request reliability update — 8 October 2026

This update addresses request stalls found in the application. The live 502 was
not reproduced locally, and hosting logs were not available. It does not certify
that the hosting proxy or PHP service can never return another 502.

## Changes

- Login and registration check email domain syntax without unbounded PHP DNS
  lookups. Delivery and the existing OTP check still verify access to the chosen
  channel; OTP expiration, rate limits and single-use checks remain enforced.
- Interactive email, SMS and gateway-validation calls share a 15-second delivery
  window, capped at 10 seconds per call and three seconds to connect. Calls also
  respect the time remaining in the request. There are no automatic delivery
  retries, which avoids sending duplicate OTPs. CLI workers keep their separate
  per-call budget. A timeout means the provider did not confirm acceptance; it
  does not prove a message was never sent.
- Delivery calls persist and release an active session lock, then reload the
  session before callers continue writing. Registration callers that already
  released their session stay closed. Concurrent session changes are preserved.
- Password-reset links use the configured email gateway instead of PHP mail().
- Remember-me storage is created in the versioned migration, not during login.
  Normal requests keep using one shared database connection per request.
- Database metadata and row-lock waits stop after five seconds. Web SELECTs have
  a 10-second limit on MySQL; MariaDB's corresponding statement limit covers all
  statements after migration. Unsupported timeout settings are logged. No PHP
  memory or execution limits are raised.
- Notification ordering, OTP verification, report-image lookup and report status
  queries gain indexes. Equivalent indexes are reused. Competing schema updates
  return a temporary-unavailable response immediately rather than waiting behind
  the migration lock.
- Shared request handling logs slow requests (five seconds or longer), uncaught
  exceptions and fatal errors. Logs include an identifier, page/action, elapsed
  time and peak memory; they omit request contents and exception messages.
  Responses include X-Request-ID. AJAX failures receive structured JSON; ordinary
  requests receive a readable error. Database failures return 503 with Retry-After.
  Fatal errors may retain HTTP 500, depending on PHP's response handling.

## Redeploy together

```text
config/config.php
config/database.php
controllers/AuthController.php
controllers/SettingsController.php
helpers/RequestRuntime.php       NEW, required by config/config.php
helpers/SchemaMigration.php
helpers/SettingsHelper.php
```

Upload all seven files during a quiet period. Keep the deployed config/env.php,
database credentials and uploads. Do not import a replacement database. The
first request updates missing tables/indexes and records schema version
2026-10-08-1. Other requests may temporarily receive 503 while that update runs.
If migration fails, inspect the PHP log rather than repeatedly refreshing.

## Verify after deployment

Check login (including Remember me), registration email OTP, optional SMS OTP,
resend, profile verification, report actions, each role's dashboard, analytics,
map pages and notifications. Inspect any 502's timestamp and requested page in
the hosting access/error log. Match it to [Request] entries and X-Request-ID when
present. A proxy-killed PHP worker may not execute shutdown handlers or return
an identifier, so absence of an application entry does not prove code succeeded.

If 502s persist with no corresponding application error, hosting support must
check the proxy deadline, PHP worker availability and account resource limits.
Do not assume every 502 is an OTP error. If a provider call times out, wait for
the existing cooldown before requesting another OTP.

## Local validation

- tests/request-runtime.php: real local HTTP responses, fatal/exception handling,
  response identifiers, error privacy, delivery budgets, session release/reload
  and preservation of concurrent session changes. Provider calls are mocked.
- tests/registration-otp.test.php: registration/resend/verification, isolated OTPs,
  rate limits, storage failures, and actual stalled local providers returning
  after 10 seconds. No real email or SMS is sent.
- tests/backend-performance.php: a disposable MariaDB database on port 13308,
  legacy/repeated/failed/competing migrations, new indexes, lock settings,
  scoped report counts, activity/device records and queue behavior.
- tests/report-email-batch.php: private batches, recipient deduplication and
  single-recipient compatibility using mocked providers.
- Existing report analytics, submission-limit and notification ownership checks,
  plus PHP syntax checks across the repository.

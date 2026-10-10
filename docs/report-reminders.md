# Report follow-up reminders

In **Settings → Report Settings**, enable reminders and choose 1–365 days (default 3). This setting is independent of citizen submission rate limits.

- Pending reports: age starts at submission; notify active staff in the report's Barangay.
- Escalated reports (awaiting approval or accepted): age starts at escalation; notify active MENRO/admin staff and staff in the originating Barangay.
- Under Review, In Progress, Resolved, Rejected, Cancelled and archived reports do not produce overdue reminders.
- A reminder is sent once per recipient and report stage, even if its notification is read or deleted. Accepting an escalation does not restart its clock. A new escalation timestamp starts a new reminder cycle.
- Dashboard follow-ups show the current overdue queue, including reports whose notifications were already read. Barangay access to escalated reports stays view-only.

Deploy the changed PHP/JS/CSS files together. The existing schema migrator creates `report_reminder_deliveries` and `report_reminder_checks` automatically once; the database account needs its usual migration permissions.

Staff page loads and the existing notification poll check reminders automatically, at most once per minute per account. Open dashboards refresh their follow-up panel with that poll. Notifications are in-app; no SMS or email is sent by this feature.

For delivery while nobody is logged in, configure the hosting scheduler to run the following every 5 minutes, using the host's PHP path and full project path:

```sh
php /path/to/environmental-reporting-app/scripts/report-reminders.php
```

The worker is CLI-only. No public cron URL or access token is needed. Without a hosting scheduler, reminders are generated when each staff member next opens the system; dates and thresholds remain based on the database clock.

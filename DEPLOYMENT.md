# Deployment Guide — InfinityFree

Step-by-step guide to put the Sierra Environmental Reporting System live on
[InfinityFree](https://www.infinityfree.com) (free, Apache + PHP + MySQL).

> **Important:** the application hardcodes the folder name
> `environmental-reporting-app` in 90+ places (PHP `require` paths, form
> actions, AJAX URLs). It **must** be uploaded to a folder with that exact name
> under your web root (`htdocs`). You do **not** need to edit any of those paths.

---

## 1. Requirements

- An InfinityFree account (free). It includes 5 MySQL databases.
- The app requires **PHP 8.0+** (it uses `match()` and other PHP 8 syntax).
  InfinityFree runs PHP 8.x by default — no action needed.

---

## 2. Create your site (if you haven't already)

1. Log in to the InfinityFree **control panel**.
2. **Create Account / Add Website** → enter your free subdomain
   (e.g. `sierra-lgu.great-site.net`). Wait for the account to be created
   (a few minutes). You'll get an email with FTP details and your site URL.

---

## 3. Set up the MySQL database

1. In the control panel open **MySQL Databases**.
2. Create a new database (e.g. `sierra`) if none exists, or note the one
   already assigned to you.
3. Click **Show Credentials** next to your database and copy:
   - **MySQL Hostname** (looks like `sqlXXX.epizy.com`)
   - **Database name** (looks like `if0_12345678_sierra`)
   - **Username** and **Password**

Keep these handy — you'll need them in step 5.

---

## 4. Upload the application files

1. Build (or use) the ready package `dist/environmental-reporting-app.zip`
   produced by this repo's preparation step, or upload the project folder
   contents directly.
2. Using **FileZilla** (or InfinityFree's **File Manager**), connect to your
   account via FTP. Your web root is the `htdocs` folder.
3. Create a folder named exactly: `environmental-reporting-app`
4. Upload **all files** into
   `htdocs/environmental-reporting-app/` (upload the *contents* of the zip,
   not the zip itself). Make sure hidden files like `.htaccess` are uploaded
   too (FileZilla shows them; File Manager hides them — toggle "Show hidden
   files").

Result: `https://<your-site>.great-site.net/environmental-reporting-app/`

---

## 5. Configure the database credentials

1. In the uploaded files, find `config/env.php.example`.
2. Copy/rename it to `config/env.php` (this file is gitignored, so your
   credentials stay private; it is also blocked from web access).
3. Edit it and fill in the four values from step 3:

   ```php
   define('DB_HOST',     'sqlXXX.epizy.com');
   define('DB_PORT',     '3306');
   define('DB_NAME',     'if0_12345678_sierra');
   define('DB_USER',     'if0_12345678');
   define('DB_PASSWORD', 'your-password');
   ```

4. Re-upload `config/env.php`.

---

## 6. Import the database schema

1. In the control panel, open **phpMyAdmin** next to your MySQL database.
2. Select your database from the left sidebar.
3. Go to the **Import** tab.
4. Choose the `database.sql` file from the uploaded files (it is blocked from
   direct web access, but you can open it from your local copy) and click
   **Go**. Wait for the import to finish.
5. The dump already contains seed data (9 barangays, categories, a default
   admin account, and settings) — no `CREATE DATABASE` statement is included
   because you import into the database assigned by InfinityFree.

---

## 7. Verify

Open in your browser:

```
https://<your-site>.great-site.net/environmental-reporting-app/
```

You should see the SIERRA landing page (map, stats, hero video).

**Default logins** (from the seed data — change these immediately):

| Role | Username | Password |
|------|----------|----------|
| MENRO Admin | `menro@envreport.com` | `password` |

Log in at `index.php?page=login`, then change the password via
`index.php?page=profile` and review Admin → **Settings**.

---

## 8. After going live

- **Change the default admin password** (step 7).
- **SMS notifications**: the app uses HTTP SMS gateways (iProg, Semaphore,
  Twilio, Chikka) configured under Admin → Settings → **Notifications**.
  These are plain HTTPS calls and work fine on InfinityFree.
- **Settings → Archiving**: there is no cron on InfinityFree. Run the archive
  job manually (Admin → Settings → Archiving → **Run Manual Archive**) when
  needed, or trigger it periodically yourself.
- **Hero video**: the bundled demo hero video is ~26 MB — **too large for
  InfinityFree's 10 MB per-file limit** and it slows first load. Switch the
  hero background to an image (Settings → Landing → Background Type = Image)
  or compress the video below 10 MB before uploading.

---

## Known InfinityFree limitations

- **Outbound email is not available** (`mail()` is disabled). The password
  reset **email** fallback will not deliver. Use the **SMS OTP** reset flow
  (Settings → Notifications → configure an SMS gateway) — the primary flow.
- **No cron jobs** on the free plan — see archiving note above.
- **10 MB per-file limit — this is a hard server limit that cannot be
  increased.** It applies to FTP uploads, the File Manager, and PHP file
  uploads. Any file larger than 10 MB is rejected (or silently dropped during
  a zip extraction). Keep every file under 10 MB:
  - The bundled hero video (~26 MB) **cannot** be hosted. Use an image
    background, or compress the video to under 10 MB (e.g. with the free
    HandBrake) before uploading.
  - Photo/video evidence uploads are capped at 5 MB (photos) and 10 MB
    (videos) by the app to match this limit.
- MySQL/InnoDB only; the included `database.sql` is MariaDB-compatible and
  imports cleanly into InfinityFree's MySQL.

---

## Updating an existing deployment

To ship new code changes, re-upload the changed files over FTP. Do **not**
re-import `database.sql` unless you want to reset the data, and **never**
overwrite `config/env.php` with a stale local copy.

## Local development stays intact

On local XAMPP (`localhost`), the app detects `development` mode and still
shows errors on screen; it falls back to the default DB credentials
(`localhost / root / no password`, database `env_reporting_system`) when
`config/env.php` is absent. `APP_ENV` and DB constants are automatically
resolved from `config/env.php` when present.

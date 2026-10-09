# BP — Blood Pressure Tracker

**English** | [ภาษาไทย](README.th.md)

A web app for recording and monitoring blood pressure and basic health
measurements for individuals, families and community groups. Built for
village health volunteers (อสม.) and family caregivers, and works offline.

Built on [Now.js](https://www.nowjs.net) (front end) and
[Kotchasan](https://www.kotchasan.com) (PHP back end).

## Features

- **Record measurements**: systolic/diastolic pressure, pulse, weight, height,
  temperature, waist, blood glucose and SpO₂, with notes and tags.
- **Family and groups**: manage family members and organise them into groups
  (village, moo, address, map coordinates).
- **Home visits**: link measurements to a visit.
- **Care dashboard**: shows who is high, who needs a referral and who has not
  been measured recently (30 days).
- **History and reports**: per-person history, averages over a configurable
  number of days, and export.
- **Configurable thresholds**: normal, high and referral levels for BP.
- **Offline-first PWA**: records made offline sync later. Each record carries a
  device-generated `client_uuid`, so replays never create duplicates.
- **Notifications**: LINE and Telegram bots, plus email (PHPMailer) and SMS.
- **Admin tools**: user and permission management, social login (Google /
  Facebook), login-attempt lockout, languages (Thai / English), email
  templates, categories and an optional AI assistant.
- **Calendar and document viewer** components.

## Requirements

- PHP **7.4** or later
- MySQL 5.5.3+ or MariaDB 5.5+ (utf8mb4)
- Apache with `mod_rewrite` and `.htaccess` enabled (an equivalent Nginx
  rewrite also works)
- HTTPS recommended; required for PWA installation and the service worker

## Installation

1. Copy the project into your web root.
2. Make `settings/` and `datas/` writable by the web server.
3. Open `http://your-host/install/` and follow the steps: system check,
   database settings, then the admin account.
4. Delete or protect the `install/` directory once installation is complete.
5. Sign in with the admin account you created.

Database tables are defined in
[modules/bp/install/database.sql](modules/bp/install/database.sql).

### Upgrading

1. **Back up the database and the `settings/` and `datas/` folders.**
2. Replace the code, keeping `settings/` and `datas/`.
3. Open `/install/` again. It detects the older version and runs the upgrade.

For a command-line upgrade see [install/cli-upgrade.php](install/cli-upgrade.php).

## Configuration

Settings live in `settings/config.php` and `settings/database.php`, created by
the installer and **not committed to git**. Most options can be changed in the
app under *Settings*. Blood-pressure thresholds use the `bp_*` keys:

| Key | Default | Meaning |
|---|---|---|
| `bp_sys_max` / `bp_dia_max` | 120 / 80 | Upper limit of normal |
| `bp_sys_hight` / `bp_dia_hight` | 140 / 90 | High |
| `bp_referral_sys` / `bp_referral_dia` | 180 / 110 | Refer to a clinic |
| `bp_avg_days` | 7 | Days used for averages |
| `bp_sync_enabled` | true | Offline sync |

Integrations (LINE, Telegram, SMS, email, AI, Google/Facebook login) are
configured in the admin UI. Their webhooks are
[line/webhook.php](line/webhook.php) and
[telegram/webhook.php](telegram/webhook.php).

## Project structure

```
modules/bp/        BP module: controllers (API), models, SQL
modules/index/     users, auth, settings, languages
modules/download/  file upload / download
modules/export/    export
templates/         HTML pages (templates/bp for BP pages)
language/          en / th translations
Gcms/              shared base classes (API, DB, mail, LINE, Telegram, AI)
Kotchasan/         PHP framework
Now/               Now.js front-end framework (source in Now/js, builds in Now/dist)
install/           installer, upgrader, CLI tools
js/  css/          application scripts and styles
```

API endpoints are served under `api/bp/*` (for example `api/bp/record/save`,
`api/bp/history`, `api/bp/care`, `api/bp/sync/push`).

## Front-end build

Only needed if you change files in `Now/js` or `Now/css`. Built files in
`Now/dist` are committed.

```bash
npm install
npm run build        # all bundles
npm run dev          # dev server
npm test             # vitest
```

## Medical disclaimer

BP is a recording and screening aid. It does **not** diagnose, and it does not
replace advice from a doctor or other healthcare professional. If someone is
unwell or readings are very high, seek medical care. The default thresholds
are general guidance; check them against your local clinical guidelines.

## Privacy

The app stores personal health data. If you run it, you are responsible for
consent, access control, backups and compliance with local law (for example
Thailand's PDPA). Use HTTPS, strong admin passwords, and restrict access to
`settings/` and `datas/`.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as
described in [SECURITY.md](SECURITY.md).

## License

[MIT](LICENSE) © 2026 Goragod Wiriya

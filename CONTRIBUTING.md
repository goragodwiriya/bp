# Contributing

Thanks for helping improve BP.

1. Open an issue first for larger changes.
2. Fork, then branch from `main`.
3. Follow the surrounding code style (Kotchasan conventions for PHP, existing
   patterns in `Now/js`).
4. Add every user-facing string to both `language/en` and `language/th`.
5. Never commit `settings/`, `datas/`, credentials, or real patient data.
6. If you change `Now/js` or `Now/css`, run `npm run build` and commit the
   updated `Now/dist` files.
7. Run `npm test` and describe how you tested in the pull request.

Database changes go in `modules/bp/install/database.sql` with a matching
upgrade step in `modules/bp/install/upgrade.php`.

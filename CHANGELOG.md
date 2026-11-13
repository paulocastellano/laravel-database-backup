# Release Notes

## [Unreleased](https://github.com/paulocastellano/laravel-database-backup/compare/v1.0.0...main)

## v1.0.0

Initial release: `php artisan backup:database` dumps PostgreSQL, MySQL or MariaDB, gzipped while it streams, to a private S3-compatible bucket on a schedule, prunes old backups and announces every run by email and on Discord.

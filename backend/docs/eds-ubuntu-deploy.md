# Ubuntu EDS Deployment

This project uses two PHP runtimes on Ubuntu:

- `php8.3` for the Laravel application
- `php8.2` for the sidecar Kalkan verifier

## Files To Prepare

Before full initialization, prepare these files on the server:

- `storage/app/private/KATO_*.csv` input files required by import commands
- `kalkancrypt.so` for PHP 8.2 NTS Linux
- 4 NUC certificates:
  - `root_rsa_2020.cer`
  - `root_gost_2022.cer`
  - `nca_rsa_2022.cer`
  - `nca_gost_2022.cer`

## Verifier Layout

The verifier is expected at:

- `/root/kalkan-verifier/public/index.php`
- `/root/kalkan-verifier/kalkancrypt.so`
- `/root/kalkan-verifier/certs/*.cer`

Tracked template file: `tools/kalkan-verifier/index.php`

## Bootstrap

Use `tools/bootstrap-ubuntu.sh` on a clean Ubuntu server.

Required environment variables:

- `REPO_URL`
- `APP_URL`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Example:

```bash
REPO_URL=git@github.com:org/repo.git \
APP_URL=http://example.com \
DB_DATABASE=school_platform \
DB_USERNAME=school_platform \
DB_PASSWORD=secret \
bash tools/bootstrap-ubuntu.sh
```

## Supervisor

The bootstrap script also installs `supervisor` and creates a Laravel queue worker config:

- `/etc/supervisor/conf.d/school-platform-worker.conf`

It runs:

- `php8.3 /var/www/school-platform/backend/artisan queue:work --sleep=3 --tries=3 --timeout=120`

Useful commands:

```bash
supervisorctl status
supervisorctl restart school-platform-worker:*
```

## After Bootstrap

1. Copy the verifier template to `/root/kalkan-verifier/public/index.php`.
2. Copy `kalkancrypt.so` to `/root/kalkan-verifier/kalkancrypt.so`.
3. Copy the 4 NUC certificates to `/root/kalkan-verifier/certs/`.
4. Add the same certificates into Ubuntu trust store and run `update-ca-certificates`.
5. Put required KATO files into `storage/app/private`.
6. Restart verifier: `systemctl restart kalkan-verifier`.
7. Set the correct `QUEUE_CONNECTION` in `.env` if background jobs are used.
8. Verify supervisor worker: `supervisorctl status`.
9. Seed demo data if needed: `php artisan setup:demo`.

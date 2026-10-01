# Deploying to production

This guide sets up the CRM on one Ubuntu 24.04 server (DigitalOcean,
AWS Lightsail, Hostinger VPS or similar; 2 GB RAM is plenty to start). Every
file it mentions is in the `deploy/` folder.

> **Prefer not to manage a server?** [Laravel Forge](https://forge.laravel.com)
> or [Laravel Cloud](https://cloud.laravel.com) do steps 1, 4, 5 and 6 for
> you. You still need steps 2, 3, 7 and 8.

## 1. Install the server software

```bash
sudo apt update && sudo apt upgrade -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y nginx mysql-server supervisor unzip git certbot python3-certbot-nginx \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-bcmath \
  php8.4-curl php8.4-zip php8.4-gd php8.4-intl
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

## 2. Create the database

```bash
sudo mysql
```
```sql
CREATE DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON crm.* TO 'crm'@'localhost';
```

## 3. Get the code and configure it

```bash
sudo mkdir -p /var/www/crm && sudo chown $USER:www-data /var/www/crm
git clone https://github.com/YOUR-ACCOUNT/YOUR-REPO.git /var/www/crm
cd /var/www/crm
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
php artisan key:generate
php artisan crm:vapid-keys          # paste the two lines into .env
nano .env                           # fill in every value
php artisan migrate --force
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
```

Important values in `.env`:

| Setting | Why it matters |
|---|---|
| `APP_DEBUG=false` | Error pages would otherwise show secrets |
| `APP_URL=https://…` | Links in emails and webhooks |
| `PLATFORM_ADMIN_EMAILS` | Your email: opens the owner panel at `/platform` |
| `MAIL_*` | Sign-up, invitations, reminders, password reset |
| `BACKUP_ARCHIVE_PASSWORD` + `BACKUP_DISKS=backups,s3` + `AWS_*` | Encrypted, off-site backups |
| `SENTRY_LARAVEL_DSN` | Email alerts when errors happen (free tier is enough) |

## 4. Web server and HTTPS

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/crm
sudo nano /etc/nginx/sites-available/crm     # your domain, PHP version
sudo ln -s /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/
sudo certbot --nginx -d crm.example.com      # free HTTPS certificate, auto-renews
sudo nginx -t && sudo systemctl reload nginx
```

Point your domain's DNS A record to the server's IP address first.

## 5. Background workers

```bash
sudo cp deploy/supervisor-queue.conf /etc/supervisor/conf.d/crm-queue.conf
sudo supervisorctl reread && sudo supervisorctl update
```

## 6. Scheduler (one cron line)

```bash
sudo crontab -u www-data -e
# paste the line from deploy/crontab
```

## 7. Check everything

```bash
php artisan crm:health
```

Every line should show ✓. The same checks appear in the owner panel
(`/platform` → System health). Then:

- Sign up for your own workspace on the live site.
- Add an uptime monitor (UptimeRobot or Better Stack, both free) for
  `https://crm.example.com/up`. It returns an error if the app or the
  database is down.
- Run `php artisan backup:run --only-db`, then restore it once on your own
  computer, so you know the backups work.

## 8. Connect the channels

Customers connect their own WhatsApp, Facebook and Google accounts under
**Settings → Integrations** (step-by-step instructions are on each page).
Before launch, test each one with your own accounts:

- **WhatsApp:** save the credentials, click **Test connection**, message
  the number from your phone, and reply from the inbox.
- **Facebook lead ads:** use Meta's *Lead Ads Testing Tool*.
- **Google lead forms:** click *Send test data* in Google Ads.

## Updating

```bash
cd /var/www/crm && ./deploy/deploy.sh
```

The script backs up the database, migrates, caches and restarts the
workers, with a short maintenance page while it runs.

## If something goes wrong

| Symptom | Look at |
|---|---|
| Reminders or WhatsApp messages not sending | `php artisan crm:health` (scheduler / queue lines), `sudo supervisorctl status` |
| A job keeps failing | `php artisan queue:failed`, then `php artisan queue:retry all` once fixed |
| Errors in the app | Sentry, or `storage/logs/laravel-*.log` |
| Restoring a backup | Unzip the archive from `storage/app/backups` or your S3 bucket (`BACKUP_ARCHIVE_PASSWORD`), then `mysql crm < db-dumps/mysql-crm.sql` |

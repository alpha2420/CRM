# Putting the CRM online

You need three things: a **server** (free on Oracle Cloud, or Azure), a
**domain name**, and about **20 minutes**. One command does the rest:
web server, database, free HTTPS, background workers, the scheduler and
the firewall.

1. [Get a server](#1-get-a-server): Oracle Cloud (recommended, free) or Azure
2. [Point your domain at it](#2-point-your-domain-at-the-server)
3. [Run the installer](#3-run-the-installer)
4. [After installing](#4-after-installing): email, backups, alerts, WhatsApp
5. [Updating](#updating), [if something goes wrong](#if-something-goes-wrong)

---

## 1. Get a server

### Option A: Oracle Cloud Always Free (₹0, recommended)

1. Sign up at **cloud.oracle.com**. A card is asked for to verify you;
   Always Free resources are not charged. Pick an Indian **home region**
   (Mumbai or Hyderabad): it cannot be changed later.
2. Open **Compute → Instances → Create instance**.
   - **Image:** click *Change image* → **Canonical Ubuntu 24.04**.
   - **Shape:** click *Change shape* → **Ampere** → `VM.Standard.A1.Flex`.
     Choose 1–2 OCPUs and 6–12 GB memory, staying inside the
     *Always Free-eligible* amount the console shows.
   - **Networking:** keep "Create new virtual cloud network" and
     "Assign a public IPv4 address".
   - **SSH keys:** *Generate a key pair for me* → **Save private key**.
   - Click **Create**. If it says *Out of capacity*, try another
     availability domain or try again later (free servers are popular).
3. Open the ports for web traffic: on the instance page click the
   **Subnet** → **Security Lists** → **Default Security List** →
   **Add Ingress Rules**: Source CIDR `0.0.0.0/0`, IP protocol **TCP**,
   destination port range `80,443` → **Add**.
4. Copy the instance's **Public IP address**.
5. Connect from your computer (the user name is `ubuntu`):
   ```bash
   chmod 600 ~/Downloads/ssh-key-*.key
   ssh -i ~/Downloads/ssh-key-*.key ubuntu@PUBLIC_IP
   ```

> Oracle's Ubuntu images also block web traffic inside the server
> itself. The installer opens ports 80 and 443 there for you.

### Option B: Microsoft Azure

1. In **portal.azure.com**: **Create a resource → Virtual machine**.
   - **Region:** Central India. **Image:** Ubuntu Server 24.04 LTS.
   - **Size:** B1s is in the free account's 12-month offer; B2s (2 vCPU,
     4 GB) is more comfortable for real customers. The installer adds
     swap on small servers.
   - **Authentication:** SSH public key (download the key when asked).
   - **Inbound ports:** allow **SSH (22), HTTP (80), HTTPS (443)**.
2. **Review + create**, then copy the **Public IP address**.
3. Connect: `ssh -i ~/Downloads/your-key.pem azureuser@PUBLIC_IP`

Any other Ubuntu 24.04 server (AWS Lightsail, DigitalOcean, Hostinger VPS)
works the same way.

## 2. Point your domain at the server

Buy a domain if you don't have one (GoDaddy, Hostinger, Namecheap or
Cloudflare; a `.in` costs about ₹800 a year). In its **DNS settings** add:

| Type | Name | Value |
|---|---|---|
| A | `crm` (gives crm.yourdomain.in) | the server's public IP |

Wait until it works: `nslookup crm.yourdomain.in` must show the IP.
It usually takes 5–30 minutes.

## 3. Run the installer

On the server (from the SSH window):

```bash
curl -fsSL https://raw.githubusercontent.com/alpha2420/CRM/main/deploy/install.sh -o install.sh
sudo bash install.sh --domain crm.yourdomain.in --email you@yourdomain.in --gemini-key YOUR_GEMINI_KEY
```

`--gemini-key` is optional (it turns on the AI assistant and voice-note
transcripts; you can add it later). The installer takes 5–10 minutes and
ends with **✓ Installed** and your next steps. It is safe to run again:
it keeps your settings and data, so if something was not ready (for
example DNS), fix it and run the same command again.

<details>
<summary>The GitHub repository is private?</summary>

The download link above only works for public repositories. For a private
one, give the server a read-only *deploy key* first:

```bash
sudo mkdir -p /var/www/.ssh && sudo chown www-data:www-data /var/www/.ssh
sudo -u www-data ssh-keygen -t ed25519 -N '' -f /var/www/.ssh/id_ed25519
sudo -u www-data sh -c 'ssh-keyscan github.com >> /var/www/.ssh/known_hosts'
sudo cat /var/www/.ssh/id_ed25519.pub
```

Add the printed line on GitHub: **repository → Settings → Deploy keys →
Add deploy key** (leave "write access" off). Copy the installer from your
computer with `scp deploy/install.sh ubuntu@PUBLIC_IP:` and run it with
`--repo git@github.com:alpha2420/CRM.git` added.
</details>

**What the installer does:** installs nginx, PHP 8.4, MySQL, supervisor
and certbot; creates the database with a random password; downloads the
code; writes `.env` (random secrets, your domain and email); creates the
tables; sets up nginx with a free HTTPS certificate that renews itself;
starts two background workers and the once-a-minute scheduler; opens
ports 80/443 in the server's firewall; adds swap on small servers; and
turns on automatic security updates and SSH brute-force protection
(fail2ban). Finally it takes the first (encrypted) backup and runs the
health check.

## 4. After installing

Open **https://crm.yourdomain.in/register** and create your workspace with
the email you gave the installer: that account also opens the owner panel
at **/platform** (System health shows what still needs attention).

To change settings, edit `.env` on the server and apply it:

```bash
sudo nano /var/www/crm/.env
sudo bash /var/www/crm/deploy/deploy.sh
```

### Email (needed for sign-up, invitations, reminders, password reset)

Until email is set up, emails are only written to the log and new users
are not asked to verify their address. Pick one provider:

- **Brevo**: free for 300 emails a day.
- **Zoho ZeptoMail**: pay as you go, good delivery in India.
- **Amazon SES**: cheapest at volume.

Add the provider's SPF/DKIM records to your domain's DNS (they show
you which), then set in `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com      # your provider's SMTP host
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@yourdomain.in
CRM_REQUIRE_EMAIL_VERIFICATION=true
```

### Off-site backups

The database is backed up every night, encrypted with
`BACKUP_ARCHIVE_PASSWORD`. **Copy that password from `.env` into your
password manager now**: without it a backup cannot be opened. To keep
copies off the server, create a bucket on **Cloudflare R2** (10 GB free)
with an S3 API token and set:

```
BACKUP_DISKS=backups,s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=crm-backups
AWS_ENDPOINT=https://YOUR-ACCOUNT-ID.r2.cloudflarestorage.com
```

Test it: `sudo -u www-data php /var/www/crm/artisan backup:run --only-db`.
Once, restore a backup on your own computer, so you know it works.

### Alerts

- **Downtime:** add a free monitor on **UptimeRobot** (or Better Stack)
  for `https://crm.yourdomain.in/up` every 5 minutes. It alerts you when
  the site, the database, the scheduler or the background workers stop.
- **Errors:** create a free **Sentry** project for Laravel and set
  `SENTRY_LARAVEL_DSN`. Failing background jobs are also emailed to the
  owner (`PLATFORM_ADMIN_EMAILS`) once email works.

### WhatsApp, ads and IndiaMART

Each workspace connects its own accounts under **Settings → Integrations**;
every page there has step-by-step instructions and a **Test connection**
button. Test each one with your own accounts before customers do:

- **WhatsApp:** save the credentials, set the webhook shown on the page in
  your Meta app, message the number from your phone, reply from the inbox.
- **Facebook lead ads:** Meta's *Lead Ads Testing Tool*.
- **Google lead forms:** *Send test data* in Google Ads.
- **IndiaMART:** paste the CRM key and click **Check now**.

### Check it from outside

On your computer, in the project folder:

```bash
bash deploy/smoke-test.sh https://crm.yourdomain.in
```

It checks that the site answers, HTTPS is enforced, security headers are
sent and secret files such as `.env` cannot be downloaded.

## Updating

Push the new code to GitHub, then on the server:

```bash
sudo bash /var/www/crm/deploy/deploy.sh
```

It shows a short maintenance page, backs up the database, pulls the code,
updates the tables, refreshes caches, restarts the workers and runs the
health check.

## If something goes wrong

| Symptom | What to do |
|---|---|
| The site doesn't open at all (times out) | Ports 80/443 are closed in the cloud: Oracle → Security List ingress rule; Azure → Network security group. |
| "Could not get a certificate" during install | DNS doesn't point to the server yet, or the ports are closed. Fix it and run the install command again. |
| 502 Bad Gateway | `sudo systemctl restart php8.4-fpm`, then check `sudo journalctl -u php8.4-fpm -n 50`. |
| Reminders, WhatsApp messages or IndiaMART leads stopped | `sudo -u www-data php /var/www/crm/artisan crm:health` (scheduler and queue lines), `sudo supervisorctl status`. |
| A background job keeps failing | `sudo -u www-data php /var/www/crm/artisan queue:failed`, then `queue:retry all` once fixed. |
| Errors in the app | Sentry, or `/var/www/crm/storage/logs/laravel-*.log`. |
| Out of disk space | Health shows it; old backups are cleaned daily. `df -h` shows usage. |
| Restoring a backup | Unzip the archive from `storage/app/backups` or your bucket (password: `BACKUP_ARCHIVE_PASSWORD`), then `mysql crm < db-dumps/mysql-crm.sql`. |

The individual config files (`deploy/nginx.conf`, `deploy/supervisor-queue.conf`,
`deploy/crontab`) are kept for manual installs; the installer writes the
same settings itself.

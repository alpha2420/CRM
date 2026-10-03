# Putting Convera online

You need three things: a **server** (AWS Lightsail, Oracle Cloud or Azure),
a **domain name**, and about **20 minutes**. One command does the rest:
web server, database, free HTTPS, background workers, the scheduler and
the firewall.

1. [Get a server](#1-get-a-server): AWS Lightsail (recommended), Oracle Cloud or Azure
2. [Point your domain at it](#2-point-your-domain-at-the-server)
3. [Run the installer](#3-run-the-installer)
4. [After installing](#4-after-installing): email, backups, alerts, WhatsApp
5. [Updating](#updating), [if something goes wrong](#if-something-goes-wrong)

---

## 1. Get a server

### Option A: AWS Lightsail (recommended)

The simplest server on AWS: a fixed monthly price and an easy console.

1. **Create the AWS account** at **aws.amazon.com** → *Create an AWS account*.
   With an Indian address AWS must verify your identity: keep your **PAN
   card** ready (it must be linked to Aadhaar; a voter ID or driving licence
   also works) and type your name **exactly as printed on it**. AWS checks
   the card with a ₹2 charge that is refunded; UPI is also accepted.
2. If AWS opens **AWS Settings → Projects** (its new sign-up), click
   **Create project**, name it `convera` and open it. Projects for India run
   in **Asia Pacific (Sydney)**; that is fine. Under your name → *Manage
   profile*, finish **Customer verification** if it is still pending.
3. Type **Lightsail** in the console's search bar and open it →
   **Create instance**:
   - **Platform:** Linux/Unix → **OS Only** → **Ubuntu 24.04 LTS**.
   - **Plan:** **$12 a month (2 GB memory, 2 vCPUs, 60 GB SSD)**.
   - **Name:** `convera` → **Create instance**, and wait for *Running*.
4. Open the instance → **Networking**:
   - **Attach static IP** → create it, and copy the IP address (it stays
     the same when the server restarts).
   - Under **IPv4 Firewall** → **Add rule** → **HTTPS** → **Create**. SSH
     (22) and HTTP (80) are already open.
5. **Account → SSH keys** → **Download** the default key for the region.
6. Connect from your computer (the user name is `ubuntu`):
   ```bash
   chmod 600 ~/Downloads/LightsailDefaultKey-*.pem
   ssh -i ~/Downloads/LightsailDefaultKey-*.pem ubuntu@STATIC_IP
   ```
   (Or click **Connect using SSH** on the instance page to use a browser
   terminal.)

**What it costs:** new accounts get $100 of AWS credit (up to $200 by
completing AWS's starter tasks). On the **Paid plan** the first 3 months of
the $12 Lightsail plan are free; on the **Free plan** the credit pays for it.
After that it is about ₹1,000–1,200 a month including GST. Add an alert so
nothing surprises you: **Billing → Budgets → Create budget**, $5, email
alert. A Free plan account pauses when the credit or 6 months run out (data
is kept for 90 days): upgrade to the Paid plan before then.

### Option B: Oracle Cloud Always Free (₹0)

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

### Option C: Microsoft Azure

1. In **portal.azure.com**: **Create a resource → Virtual machine**.
   - **Region:** Central India. **Image:** Ubuntu Server 24.04 LTS.
   - **Size:** B1s is in the free account's 12-month offer; B2s (2 vCPU,
     4 GB) is more comfortable for real customers. The installer adds
     swap on small servers.
   - **Authentication:** SSH public key (download the key when asked).
   - **Inbound ports:** allow **SSH (22), HTTP (80), HTTPS (443)**.
2. **Review + create**, then copy the **Public IP address**.
3. Connect: `ssh -i ~/Downloads/your-key.pem azureuser@PUBLIC_IP`

Any other Ubuntu 24.04 server (DigitalOcean, Hostinger VPS, AWS EC2)
works the same way.

## 2. Point your domain at the server

Convera lives at **useconvera.com** (registered at GoDaddy, with email on
Google Workspace). The website and the app share that one address: the
home page is the website, `/dashboard` is the app, and
`www.useconvera.com` redirects to it.

In GoDaddy: **Domain Portfolio → useconvera.com → DNS**.

| Type | Name | Value | What to do |
|---|---|---|---|
| A | `@` | the server's public IP | GoDaddy shows two A records for `@` (its placeholder page). Edit one to your server's IP and **delete the other**. |
| CNAME | `www` | `@` | Already there: keep it. |
| TXT | `@` | `v=spf1 include:_spf.google.com ~all` | **Add it.** It is missing today; it tells inboxes that Google may send your mail. |
| MX | `@` | `smtp.google.com` | Already there (Gmail): don't touch it. |

If GoDaddy also shows **Forwarding** for the domain, remove it. Then wait
until `nslookup useconvera.com` shows your server's IP (usually 5–30
minutes).

Two other checks in GoDaddy:

- **Turn on auto-renew.** The domain expires on **19 July 2027**; if it
  lapses, the site and your Gmail stop working.
- **Domain privacy** keeps your phone number and address out of public
  WHOIS records.

> Using another domain? Point an A record for the name you want (for
> example `crm` → `crm.yourdomain.in`) at the server and use that name in
> the install command; add `--www` only for a bare domain.

## 3. Run the installer

On the server (from the SSH window):

```bash
curl -fsSL https://raw.githubusercontent.com/alpha2420/CRM/main/deploy/install.sh -o install.sh
sudo bash install.sh --domain useconvera.com --www --email you@useconvera.com --gemini-key YOUR_GEMINI_KEY
```

`--www` also answers on www.useconvera.com and redirects it to
useconvera.com (one free HTTPS certificate covers both).

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

Open **https://useconvera.com/register** and create your workspace with
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

**Authenticate the domain with the provider before sending.** useconvera.com
has a DMARC policy of `quarantine`, so mail that isn't authenticated goes
straight to spam. In Brevo: **Senders, domains & dedicated IPs → Domains →
Add a domain → useconvera.com**, then add the records it shows (DKIM and
a verification code) in GoDaddy DNS. Keep **one** SPF record and add Brevo
to it:

```
v=spf1 include:_spf.google.com include:spf.brevo.com ~all
```

Then set in `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com      # your provider's SMTP host
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@useconvera.com
CRM_REQUIRE_EMAIL_VERIFICATION=true
```

Customers see **support@useconvera.com** as the help address (in the
privacy policy and emails): create it in Google Workspace as an alias of
your mailbox (Admin console → Users → your user → Add alternate emails).

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
  for `https://useconvera.com/up` every 5 minutes. It alerts you when
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
bash deploy/smoke-test.sh https://useconvera.com
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
| The site doesn't open at all (times out) | Ports 80/443 are closed in the cloud: Lightsail → Networking → IPv4 Firewall (add HTTPS); Oracle → Security List ingress rule; Azure → Network security group. |
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

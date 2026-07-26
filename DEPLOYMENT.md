# Deployment — Railway

How Max Collection runs in production on Railway. One Railway project contains
three pieces: the web app, the queue worker, and MySQL.

## One-time setup

### 1. Project and database
1. Railway dashboard → **New Project** → **Deploy from GitHub repo** → select `cheskib/Maxcollection`.
2. In the same project: **Create → Database → MySQL**.

### 2. Web service (created by step 1)
- Railway builds from the repository `Dockerfile` automatically.
- **Settings → Volumes**: add a volume mounted at `/var/www/html/storage/app`
  (this is where uploaded photographs live; without it they vanish on redeploy).
- **Variables** (Settings → Variables):

  | Variable | Value |
  | --- | --- |
  | `APP_KEY` | run `php artisan key:generate --show` locally and paste the output |
  | `APP_ENV` | `production` |
  | `APP_DEBUG` | `false` |
  | `APP_URL` | `https://maxcollection.vsstechnology.com` |
  | `LOG_CHANNEL` | `stderr` |
  | `DB_CONNECTION` | `mysql` |
  | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
  | `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
  | `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
  | `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
  | `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
  | `SESSION_DRIVER` | `database` |
  | `QUEUE_CONNECTION` | `database` |
  | `OPENAI_API_KEY` | the real key |
  | `OPENAI_MODEL` | `gpt-4.1` |

### 3. Queue worker
No separate service. The entrypoint starts `php artisan queue:work` in the
background of the web container so it shares the photo volume — Railway
volumes attach to a single service, and a separate worker cannot read the
uploaded images. Do not create a second service from the repository.

### 4. Domain
1. Web service → **Settings → Networking → Custom Domain** →
   `maxcollection.vsstechnology.com`.
2. Railway shows a CNAME target; add that CNAME record wherever
   vsstechnology.com's DNS is managed.

## Ongoing
- Every push to the deployed branch redeploys automatically; the entrypoint
  runs migrations and the idempotent seeders on each boot.
- Photographs persist on the volume; the database persists in the MySQL
  service. Thumbnails are disposable and regenerate on demand.

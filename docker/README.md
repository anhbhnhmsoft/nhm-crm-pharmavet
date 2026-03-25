# Docker Deploy (Prod)

## 1) DNS
- Point A record: `crmquanly.nhmsoft.com` -> server IP.

## 2) APP_KEY
Run on server (inside repo):
```bash
php artisan key:generate --show
```
Put value into `APP_KEY` in `docker/.env.prod`.

## 3) Start stack (HTTP)
```bash
docker compose -f docker/docker-compose.yml up -d --build
```

## 4) Get SSL cert (Let's Encrypt)
```bash
docker compose -f docker/docker-compose.yml run --rm certbot certonly \
  --webroot -w /var/www/certbot \
  -d crmquanly.nhmsoft.com \
  --email bujhuyanh150400@gmail.com \
  --agree-tos --no-eff-email
```

## 5) Reload Nginx
```bash
docker compose -f docker/docker-compose.yml restart nginx
```

## 6) Migrate DB
```bash
docker compose -f docker/docker-compose.yml exec app php artisan migrate --force
```

## 7) Queue + Scheduler
Already running in compose (`queue`, `scheduler` services).

## 8) SSL auto-renew
Certbot container renews every 12h.

## Notes
- Assets are built by Vite in the Docker image.
- If domain changes, update `docker/nginx/conf.d/app.conf` and `APP_URL`, `META_REDIRECT` in `docker/.env.prod`.

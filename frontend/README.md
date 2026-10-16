# Asclepius Astro UI

Preview UI that consumes the PHP JSON API (`web/api.php`). The classic PHP pages remain the default until cutover.

## Commands

```bash
npm ci
npm run dev    # http://localhost:4321 — proxies /asclepius/* to PHP :8080
npm run build  # writes static files to ../web/app/
```

Point PHP at `web/`:

```bash
php -S 127.0.0.1:8080 -t ../web
```

Use a service API-key or an authenticated PHP session cookie. Ticket live updates use `ticket_poll` with `format=json`.

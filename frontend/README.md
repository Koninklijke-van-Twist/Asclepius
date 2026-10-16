# Astropius (Asclepius Astro UI)

Preview UI at `/astropius`, talking to the PHP JSON API at `/asclepius/api.php`. Never replaces the Asclepius PHP deploy on master.

## Commands

```bash
npm ci
npm run dev    # http://localhost:4321 — proxies /asclepius/* to PHP :8080
npm run build  # → ../astropius-dist/ with base /astropius/
```

```bash
php -S 127.0.0.1:8080 -t ../web
```

Use a service API-key or a session cookie that is visible to `/astropius`. Ticket live updates use `ticket_poll` with `format=json`.

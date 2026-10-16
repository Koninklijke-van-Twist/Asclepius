# Astropius (Asclepius Astro UI)

Preview UI at `/astropius`. **Live TicketStore:** `api.php` in the build `require`s Asclepius `api.php` (same SQLite). Never replaces the Asclepius PHP deploy on master.

## Commands

```bash
npm ci
npm run dev    # http://localhost:4321 — proxies API to PHP :8080
npm run build  # → ../astropius-dist/ (Astro + php-bridge)
```

```bash
php -S 127.0.0.1:8080 -t ../web
```

Open Asclepius, click **Astro** (`astropius_launch.php`) to hand over your session key, then use `/astropius`. Or paste a service API-key in the UI.

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

## Local

```bash
php -S 127.0.0.1:8080 -t ../web
npm run dev   # http://127.0.0.1:4321/astropius/
```

In the UI use API-key `local-dev-key` and e-mail `ict@kvt.nl` (stub `web/auth.php`). That hits the **local** SQLite via Vite proxy `/asclepius/api.php`.

To use a **production** Asclepius key against live tickets:

```bash
ASCLEPIUS_LIVE_ORIGIN=https://sleutels.kvt.nl npm run dev
```

Then paste your real API-key + ICT e-mail and click **Verbinden**.

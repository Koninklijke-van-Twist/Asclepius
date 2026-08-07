# Asclepius

Intern ICT-ticketingsysteem voor Koninklijke Van Twist (KVT), gepubliceerd onder `https://sleutels.kvt.nl/asclepius`.

## Wat is het?

Asclepius is een PHP-applicatie waarmee medewerkers meldingen en aanvragen kunnen registreren en opvolgen. Het systeem ondersteunt o.a.:

- ticket aanmaken, beantwoorden en afhandelen
- automatische toewijzing aan ICT-beheerders op basis van categorie/belasting
- bijlagen, inline afbeeldingen en bestandsvoorvertoning
- privé-tickets, deelnemers en gedeelde ticketlinks
- meertalige UI (NL, EN, DE, FR) en ticketvertaling
- API-toegang via `web/api.php`
- browser- en webpushnotificaties

## Belangrijkste eigenschappen

- **Opslag:** SQLite (`web/data/asclepius.sqlite`)
- **Backend:** PHP 8.2+
- **Auth/config:** via niet-geversioneerde `web/auth.php`
- **UI:** mobile-first, server-rendered PHP views + vanilla JS
- **Deployment:** GitHub Actions → FTP (alleen na geslaagde tests)

## Repository-structuur

```text
.
├── web/                         # Applicatie-root (deployment target)
│   ├── index.php                # Centrale controller (require-keten + HTML shell)
│   ├── admin.php                # Admin entrypoint (laadt index.php in admin-modus)
│   ├── api.php                  # HTTP JSON API
│   ├── TicketStore.php          # Datalaag + SQLite schema/migraties
│   ├── content/                 # Gescheiden app-logica en views
│   │   ├── bootstrap.php
│   │   ├── constants.php
│   │   ├── localization.php
│   │   ├── helpers.php
│   │   ├── variables.php
│   │   ├── actions.php
│   │   ├── data.php
│   │   └── views/
│   ├── docs/api.md              # API-documentatie
│   └── thirdparty/README.md     # Overzicht externe client-side libraries
├── tests/                       # Testbestanden + runner
└── .github/workflows/           # CI (tests) en deployworkflow
```

## Architectuuroverzicht

`web/index.php` fungeert als dunne controller en laadt in volgorde de logische modules uit `web/content/`. Hierdoor blijft de businesslogica uit de views:

1. `bootstrap.php` – sessie/init/auth-load
2. `constants.php` – globale configuratieconstanten
3. `localization.php` – vertalingen en `__()` helper
4. `helpers.php` – utilityfuncties (formatting, CSRF, URL-opbouw, etc.)
5. `variables.php` – request parsing, user context, filters, store init
6. `actions.php` – POST-afhandeling / redirects
7. `data.php` – dataopbouw voor views en polling-responses
8. `views/*` – presentatie

## Vereisten

- PHP 8.2 of hoger
- Extensies: `pdo_sqlite`, `sqlite3`, `curl`, `json`
- Composer (voor dependencies)
- Schrijfrechten op:
  - `web/data/`
  - `web/cache/` (indien user directory cache wordt gebruikt)

## Lokale setup

1. **Dependency-installatie**

   ```bash
   composer install --no-interaction --prefer-dist
   ```

   > Let op: `translated/lara-sdk` wordt via GitHub opgehaald. Zonder geldige GitHub-authenticatie kan Composer falen.

2. **Maak `web/auth.php` aan** (staat in `.gitignore`).

   De app verwacht minimaal configuratie voor ICT-gebruikers en API-keys, en optioneel mail/Graph/translation/webpush instellingen.

3. **Controleer datamappen**

   ```bash
   mkdir -p web/data web/cache
   chmod -R 775 web/data web/cache
   ```

4. **Start een lokale server**

   ```bash
   php -S 127.0.0.1:8080 -t web
   ```

5. **Open de app**

   - User-portaal: `http://127.0.0.1:8080/index.php`
   - Admin-portaal: `http://127.0.0.1:8080/admin.php`

   Voor lokale requests kun je een gebruiker simuleren met queryparameters:

   - `?dev_user=naam@kvt.nl`
   - `&dev_admin=1`

## Configuratie (`web/auth.php`)

`web/auth.php` is environment-specifiek en wordt niet gedeeld in git. In de code worden o.a. de volgende variabelen gebruikt:

- `$ictUsers` (lijst of map van ICT-e-mails)
- `$apiKeys` (service API-keys)
- `$mailSettings` (afzender + SMTP-instellingen)
- `$graphCredentials` (`tenantId`, `clientId`, `clientSecret`)
- `$laraTranslate` (`ID`, `Secret`)
- `$webPushSettings` (`vapid_public_key`, `vapid_private_pem`, `subject`)

Voor VAPID-sleutels is er een helper script: `generate_vapid_keys.ps1`.

## API

- Endpoint: `web/api.php`
- Volledige documentatie: `web/docs/api.md`
- In de UI bereikbaar via **Instellingen → API**

## Testen

Projecttests draaien via de custom testrunner:

```bash
php tests/run-tests.php
```

Dit voert alle `tests/test-*.php` en `tests/check_*.php` bestanden uit.

CI-workflow: `.github/workflows/tests.yml`

## Deploy

Deploy gebeurt automatisch op push naar `master` via `.github/workflows/deploy-ftp.yml`:

1. Unit-tests draaien eerst
2. Alleen bij succes start FTP-deploy
3. `web/` wordt gemirrord met exclusions (`data/`, `cache/`, `auth.php`, etc.)

## Troubleshooting

- **Composer faalt op `translated/lara-sdk`**
  - Voeg GitHub credentials toe voor Composer (private package toegang).
- **`Graph-credentials ontbreken in auth.php.`**
  - Vul `$graphCredentials` in of schakel Graph-afhankelijke paden uit.
- **Geen schrijfrechten op SQLite of uploads**
  - Controleer rechten op `web/data/` en onderliggende bestanden.
- **API geeft 401**
  - Controleer `X-API-Key` of `api_key` en de `$apiKeys` configuratie.

## Aanvullende documentatie

- `web/docs/api.md` – API
- `web/thirdparty/README.md` – gebruikte externe libraries
- `PREVIEW_SYSTEM.md` – overzicht bestandsvoorvertoning

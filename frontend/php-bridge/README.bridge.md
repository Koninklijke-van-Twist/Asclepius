# Astropius ↔ Asclepius live TicketStore

`api.php` here does **not** ship its own database. It `require`s Asclepius `api.php`, so every ticket read/write uses the live Asclepius `TicketStore` + SQLite.

Expected server layout:

```
/asclepius/   ← existing PHP app (TicketStore, data/asclepius.sqlite)
/astropius/   ← this build (static Astro + api.php bridge)
```

Optional: set env `ASTROPIUS_ASCLEPIUS_WEB` to the Asclepius web directory if the folder name differs.

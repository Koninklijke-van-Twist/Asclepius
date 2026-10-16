// @ts-check
import { defineConfig } from 'astro/config';

// Served at sleutels.kvt.nl/astropius — separate from Asclepius PHP under /asclepius.
export default defineConfig({
  base: '/astropius/',
  outDir: '../astropius-dist',
  publicDir: 'public',
  vite: {
    server: {
      proxy: {
        '/asclepius/api.php': 'http://127.0.0.1:8080',
        '/asclepius/session_keepalive.php': 'http://127.0.0.1:8080',
        '/asclepius/index.php': 'http://127.0.0.1:8080',
        '/asclepius/admin.php': 'http://127.0.0.1:8080',
      },
    },
  },
});

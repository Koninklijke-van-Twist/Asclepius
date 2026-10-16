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
        // Live TicketStore: bridge and Asclepius API both hit local PHP web/
        '/astropius/api.php': {
          target: 'http://127.0.0.1:8080',
          rewrite: () => '/api.php',
        },
        '/asclepius/api.php': {
          target: 'http://127.0.0.1:8080',
          rewrite: () => '/api.php',
        },
        '/asclepius/session_keepalive.php': {
          target: 'http://127.0.0.1:8080',
          rewrite: () => '/session_keepalive.php',
        },
        '/asclepius/astropius_launch.php': {
          target: 'http://127.0.0.1:8080',
          rewrite: () => '/astropius_launch.php',
        },
        '/asclepius/index.php': {
          target: 'http://127.0.0.1:8080',
          rewrite: () => '/index.php',
        },
        '/asclepius/admin.php': {
          target: 'http://127.0.0.1:8080',
          rewrite: () => '/admin.php',
        },
      },
    },
  },
});

// @ts-check
import { defineConfig } from 'astro/config';

// Optional: ASCLEPIUS_LIVE_ORIGIN=https://sleutels.kvt.nl
// Proxies /asclepius/api.php to production so a real API-key shows live tickets locally.
const liveOrigin = (process.env.ASCLEPIUS_LIVE_ORIGIN || '').replace(/\/$/, '');

// Served at sleutels.kvt.nl/astropius — separate from Asclepius PHP under /asclepius.
export default defineConfig({
  base: '/astropius/',
  outDir: '../astropius-dist',
  publicDir: 'public',
  vite: {
    server: {
      proxy: {
        '/asclepius/api.php': liveOrigin
          ? {
              target: liveOrigin,
              changeOrigin: true,
              secure: true,
            }
          : {
              target: 'http://127.0.0.1:8080',
              changeOrigin: true,
              rewrite: () => '/api.php',
            },
        '/astropius/api.php': liveOrigin
          ? {
              target: liveOrigin,
              changeOrigin: true,
              secure: true,
              rewrite: () => '/asclepius/api.php',
            }
          : {
              target: 'http://127.0.0.1:8080',
              changeOrigin: true,
              rewrite: () => '/api.php',
            },
        '/asclepius/session_keepalive.php': {
          target: 'http://127.0.0.1:8080',
          changeOrigin: true,
          rewrite: () => '/session_keepalive.php',
        },
        '/asclepius/astropius_launch.php': {
          target: 'http://127.0.0.1:8080',
          changeOrigin: true,
          rewrite: () => '/astropius_launch.php',
        },
        '/asclepius/index.php': {
          target: 'http://127.0.0.1:8080',
          changeOrigin: true,
          rewrite: () => '/index.php',
        },
        '/asclepius/admin.php': {
          target: 'http://127.0.0.1:8080',
          changeOrigin: true,
          rewrite: () => '/admin.php',
        },
      },
    },
  },
});

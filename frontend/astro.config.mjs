// @ts-check
import { defineConfig } from 'astro/config';

// Built assets land under web/app so PHP can serve them same-origin.
export default defineConfig({
  base: '/asclepius/app/',
  outDir: '../web/app',
  publicDir: 'public',
  server: {
    port: 4321,
    proxy: {
      '/asclepius/api.php': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
      '/asclepius/session_keepalive.php': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
    },
  },
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

import { cpSync, mkdirSync, existsSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dist = join(root, '..', 'astropius-dist');
const bridge = join(root, 'php-bridge');

if (!existsSync(dist)) {
  console.error('astropius-dist missing; run astro build first');
  process.exit(1);
}

for (const file of ['api.php', 'session_keepalive.php', '.htaccess', 'README.bridge.md']) {
  const from = join(bridge, file);
  if (existsSync(from)) {
    cpSync(from, join(dist, file));
  }
}

writeFileSync(
  join(dist, 'ASCLEPIUS_LIVE'),
  'Astropius uses the live Asclepius TicketStore via api.php → ../asclepius/api.php\n',
  'utf8'
);

console.log('Copied PHP bridge into astropius-dist (live Asclepius TicketStore).');

import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const input = process.argv[2] || process.env.DOMCLOUD_BACKEND_URL;
if (!input) throw new Error('Configura DOMCLOUD_BACKEND_URL=https://nombre-app.mnz.dom.my.id en Netlify, o pásala como argumento.');
const url = new URL(input);
if (url.protocol !== 'https:' || url.username || url.password || url.port || url.pathname !== '/' || url.search || url.hash || !/^[a-z0-9.-]+$/i.test(url.hostname)) {
  throw new Error('Usa únicamente el origen HTTPS del backend, sin ruta ni credenciales.');
}
const publicDir = fileURLToPath(new URL('../frontend/public/', import.meta.url));
mkdirSync(publicDir, { recursive: true });
writeFileSync(`${publicDir}/_redirects`, `/api/* ${url.origin}/api/:splat 200!\n/* /index.html 200\n`);
console.log(`Proxy Netlify preparado para ${url.origin}`);

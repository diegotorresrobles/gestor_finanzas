// Development-only UI fixture: /tests/cuentas-ui.html. Every finance request stays in memory.
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import Dashboard from '../src/app/layout/dashboard/Dashboard';
import DashboardIndex from '../src/app/layout/dashboard/DashboardIndex';
import Cuentas from '../src/app/layout/dashboard/Cuentas';
import CuentaDetalle from '../src/app/layout/dashboard/CuentaDetalle';
import Transacciones from '../src/app/layout/dashboard/Transacciones';
import TransaccionDetalle from '../src/app/layout/dashboard/TransaccionDetalle';
import { AuthProvider } from '../src/app/helpers/AuthContext';
import Account from '../src/app/layout/dashboard/Account';
import Admin from '../src/app/layout/dashboard/Admin';
import '../src/styles/style.css';

let cuentas = [
  { id: 1, nombre: 'Banco', tipo_cuenta_id: 5, balance: '1000.00', color: '1D4ED8', created_at: '2026-10-08 12:00:00', updated_at: null },
  { id: 2, nombre: 'Tarjeta', tipo_cuenta_id: 3, balance: '-100.00', limite_credito: '1000.00', color: '9333EA', created_at: '2026-10-08 12:00:00', updated_at: null },
  { id: 3, nombre: 'Billetera', tipo_cuenta_id: 1, balance: '200.00', color: '059669', created_at: '2026-10-08 12:00:00', updated_at: null },
];
const tipos = [{ id: 1, tipo: 'Efectivo' }, { id: 3, tipo: 'Tarjeta de crédito' }, { id: 5, tipo: 'Bancaria' }];
let transacciones = [{ id: 1, tipo: 'gasto', cuenta_id: 2, cuenta_destino_id: null, categoria_id: 1, monto: '100.00', descripcion: 'Compra de prueba', fecha: '2026-10-08', version: 1, created_at: '2026-10-08 12:00:00', updated_at: null }];
const detail = (transaction) => ({ ...transaction, cuenta_nombre: cuentas.find((cuenta) => Number(cuenta.id) === Number(transaction.cuenta_id))?.nombre ?? transaction.cuenta_nombre_historico, cuenta_destino_nombre: cuentas.find((cuenta) => Number(cuenta.id) === Number(transaction.cuenta_destino_id))?.nombre ?? transaction.destino_nombre_historico, categoria: 'General' });
const effects = (transaction) => ({ [transaction.cuenta_id]: (transaction.tipo === 'ingreso' ? 1 : -1) * Math.round(Number(transaction.monto) * 100), ...(transaction.tipo === 'transferencia' ? { [transaction.cuenta_destino_id]: Math.round(Number(transaction.monto) * 100) } : {}) });
if (new URLSearchParams(window.location.search).get('theme') === 'light') document.body.classList.remove('dark');
const originalFetch = window.fetch;
window.fetch = async (url, options = {}) => {
  if (!String(url).includes('/api/')) return originalFetch(url, options);
  if (options.signal?.aborted) throw new DOMException('Aborted', 'AbortError');
  const reply = (data, status = 200) => new Response(JSON.stringify({ ok: status < 400, message: status < 400 ? 'Todo bien' : 'Conflicto en los datos', data }), { status, headers: { 'Content-Type': 'application/json' } });
  const path = new URL(url, window.location.origin).pathname;
  const id = Number(path.split('/').pop());
  const role = new URLSearchParams(window.location.search).get('role') ?? 'user';
  if (path === '/api/auth/me') return reply({ user: { id: 1, nombre: 'Prueba', rol: role } });
  if (path === '/api/config') return reply({ name: 'DTR · Pruebas', logo: '' });
  if (path === '/api/realtime/ticket') return reply([], 503); // This fixture never opens a real socket.
  if (path === '/api/realtime/revision') return reply({ revision: '0' });
  if (path === '/api/account') return reply({ nombre: 'Prueba', apellido: 'UI', correo: 'fixture@example.test', telefono: '', username: 'fixture', rol: role });
  if (path === '/api/account/email') return reply({ message: 'Revisa tu correo actual para autorizar el cambio.' });
  if (path === '/api/account/password') return reply({ errors: { current_password: 'La contraseña actual es incorrecta' } }, 409);
  if (path === '/api/admin/metrics') return reply({ users: 120, active_users: 18 });
  if (path === '/api/admin/settings') return options.method === 'PUT' ? reply({ message: 'Configuración guardada.' }) : reply({ APP_NAME: 'DTR · Pruebas', APP_URL: 'http://localhost:5173', APP_DOMAIN: 'localhost', APP_LOGO: '', WS_URL: 'ws://localhost:3002', JWT_KEY: { configured: true }, DB_HOST: 'localhost', DB_NAME: 'fixture', DB_USER: 'fixture', DB_PASS: { configured: true }, MAIL_HOST: 'smtp.example.test', MAIL_PORT: '587', MAIL_USER: 'fixture', MAIL_PASS: { configured: true }, MAIL_FROM: 'fixture@example.test', MAIL_ENCRYPTION: 'tls' });
  if (path === '/api/transacciones/categorias') return reply([{ id: 1, categoria: 'General' }]);
  if (path.startsWith('/api/transacciones')) {
    if (options.method === 'DELETE') {
      const old = transacciones.find((transaction) => transaction.id === id);
      if (!old) return reply([], 404);
      Object.entries(effects(old)).forEach(([accountId, amount]) => { const account = cuentas.find((cuenta) => Number(cuenta.id) === Number(accountId)); if (account) account.balance = ((Math.round(Number(account.balance) * 100) - amount) / 100).toFixed(2); });
      transacciones = transacciones.filter((transaction) => transaction.id !== id);
      return reply({ id });
    }
    if (options.method === 'POST' || options.method === 'PUT') {
      const form = JSON.parse(options.body);
      if (form.tipo === 'transferencia' && Number(form.cuenta_id) === Number(form.cuenta_destino_id)) return reply({ errors: { cuenta_destino_id: 'El destino debe ser distinto' } }, 409);
      const old = transacciones.find((transaction) => transaction.id === id);
      const transaction = { ...form, version: (old?.version ?? 0) + 1, cuenta_nombre_historico: cuentas.find((cuenta) => Number(cuenta.id) === Number(form.cuenta_id))?.nombre, destino_nombre_historico: cuentas.find((cuenta) => Number(cuenta.id) === Number(form.cuenta_destino_id))?.nombre, id: id || Math.max(0, ...transacciones.map((transaction) => transaction.id)) + 1, created_at: old?.created_at ?? '2026-10-08 12:00:00', updated_at: old ? '2026-10-08 13:00:00' : null };
      const changes = effects(transaction);
      if (old) Object.entries(effects(old)).forEach(([accountId, amount]) => { changes[accountId] = (changes[accountId] ?? 0) - amount; });
      for (const [accountId, amount] of Object.entries(changes)) {
        const account = cuentas.find((cuenta) => Number(cuenta.id) === Number(accountId));
        if (account?.limite_credito != null && Number(account.balance) + amount / 100 < -Number(account.limite_credito)) return reply({ errors: { monto: 'La operación excede el límite de crédito' } }, 409);
      }
      Object.entries(changes).forEach(([accountId, amount]) => { const account = cuentas.find((cuenta) => Number(cuenta.id) === Number(accountId)); account.balance = ((Math.round(Number(account.balance) * 100) + amount) / 100).toFixed(2); });
      transacciones = [transaction, ...transacciones.filter((transaction) => transaction.id !== id)];
      return reply(transaction, old ? 200 : 201);
    }
    if (id) { const transaction = transacciones.find((transaction) => transaction.id === id); return transaction ? reply(detail(transaction)) : reply([], 404); }
    return reply(transacciones.map(detail));
  }
  if (!path.startsWith('/api/cuentas')) return reply([], 404);
  if (id) {
    const account = cuentas.find((cuenta) => cuenta.id === id);
    if (!account) return reply([], 404);
    if (options.method === 'PUT') Object.assign(account, JSON.parse(options.body), { color: JSON.parse(options.body).color.replace('#', '') });
    return reply({ ...account, tipo_cuenta: tipos.find((tipo) => Number(tipo.id) === Number(account.tipo_cuenta_id))?.tipo, transacciones: transacciones.filter((transaction) => Number(transaction.cuenta_id) === id || Number(transaction.cuenta_destino_id) === id).map(detail) });
  }
  if (String(url).endsWith('/tipos')) return reply(tipos);
  if (options.method === 'POST') {
    const form = JSON.parse(options.body);
    if (form.nombre === 'Error') return reply({ errors: { nombre: 'Nombre rechazado para probar la validación' } }, 409);
    const cuenta = { ...form, id: Math.max(0, ...cuentas.map((cuenta) => cuenta.id)) + 1, balance: Number(form.balance), created_at: '2026-10-07 12:00:00', updated_at: null };
    cuentas = [cuenta, ...cuentas];
    return reply(cuenta, 201);
  }
  if (options.method === 'DELETE') {
    const { id } = JSON.parse(options.body);
    const account = cuentas.find((cuenta) => cuenta.id === id);
    transacciones = transacciones.map((transaction) => ({ ...transaction, ...(Number(transaction.cuenta_id) === id ? { cuenta_id: null, cuenta_nombre_historico: account.nombre } : {}), ...(Number(transaction.cuenta_destino_id) === id ? { cuenta_destino_id: null, destino_nombre_historico: account.nombre } : {}) }));
    cuentas = cuentas.filter((cuenta) => cuenta.id !== id);
    return reply({ id });
  }
  return reply(cuentas);
};

createRoot(document.getElementById('root')).render(
  <StrictMode><MemoryRouter initialEntries={[new URLSearchParams(window.location.search).get('role') === 'admin' ? '/admin' : '/']}><AuthProvider><Routes>
    <Route element={<Dashboard />}>
      <Route path="/account" element={<Account />} />
      <Route path="/admin" element={<Admin />} />
      <Route path="/" element={<DashboardIndex />} />
      <Route path="/cuentas" element={<Cuentas />} />
      <Route path="/cuentas/:id" element={<CuentaDetalle />} />
      <Route path="/transacciones" element={<Transacciones />} />
      <Route path="/transacciones/:id" element={<TransaccionDetalle />} />
    </Route>
  </Routes></AuthProvider></MemoryRouter></StrictMode>,
);

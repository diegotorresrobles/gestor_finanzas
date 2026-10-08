import { useState } from 'react';
import useFinanceData from '../../helpers/useFinanceData';
import Input from '../../elements/Input';
import Button from '../../elements/Button';
import { apiRequest } from '../../helpers/cuentasApi';
const groups = [
  ['Aplicación', [['APP_NAME', 'Nombre'], ['APP_URL', 'URL del frontend'], ['APP_DOMAIN', 'Host / emisor JWT'], ['APP_LOGO', 'URL del logo'], ['WS_URL', 'URL WebSocket']]],
  ['Seguridad', [['JWT_KEY', 'JWT_SECRET', 'password']]],
  ['Base de datos', [['DB_HOST', 'Host'], ['DB_NAME', 'Base de datos'], ['DB_USER', 'Usuario'], ['DB_PASS', 'Contraseña', 'password']]],
  ['Servicio de correo SMTP', [['MAIL_HOST', 'Host SMTP'], ['MAIL_PORT', 'Puerto', 'number'], ['MAIL_USER', 'Usuario SMTP'], ['MAIL_PASS', 'Contraseña SMTP', 'password'], ['MAIL_FROM', 'Remitente', 'email'], ['MAIL_ENCRYPTION', 'Cifrado (tls / ssl)']]],
];
function Settings({ initial, onSaved }) {
  const [form, setForm] = useState(() => Object.fromEntries(Object.entries(initial).map(([key, value]) => [key, typeof value === 'object' ? '' : value])));
  const [busy, setBusy] = useState(false), [message, setMessage] = useState(''), [error, setError] = useState('');
  const save = async (e) => {
    e.preventDefault(); if (busy) return; setBusy(true); setMessage(''); setError('');
    const settings = Object.fromEntries(Object.entries(form).filter(([key, value]) => typeof initial[key] === 'object' ? value !== '' : value !== initial[key]));
    if (!Object.keys(settings).length) { setMessage('No hay cambios para guardar.'); setBusy(false); return; }
    try { const result = await apiRequest('/api/admin/settings', { method: 'PUT', body: { settings } }); setMessage(result.message); onSaved(); }
    catch (e) { setError(e.message); }
    finally { setForm((previous) => ({ ...previous, JWT_KEY: '', DB_PASS: '', MAIL_PASS: '' })); setBusy(false); }
  };
  return <form onSubmit={save} className="mt-8 space-y-6"><h2 className="text-2xl font-semibold">Configuración de la aplicación</h2><p className="text-sm text-gray-500 dark:text-gray-400">Las claves existentes nunca se muestran. Deja sus campos vacíos para conservarlas. Cambiar JWT_SECRET cierra todas las sesiones.</p>
    {error && <p role="alert" className="text-red-600 dark:text-red-400">{error}</p>}{message && <p role="status">{message}</p>}
    {groups.map(([title, fields]) => <fieldset key={title} className="rounded-xl border border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-800"><legend className="px-2 font-semibold">{title}</legend><div className="grid gap-5 md:grid-cols-2">{fields.map(([name, label, type = 'text']) => <Input key={name} name={name} label={label} type={type} value={form[name] ?? ''} autoComplete="off" placeholder={initial[name]?.configured ? 'Configurado; escribe para reemplazar' : ''} disabled={busy} onChange={(e) => setForm({ ...form, [name]: e.target.value })} />)}</div></fieldset>)}
    <Button type="submit" variant="primary" label={busy ? 'Guardando…' : 'Guardar configuración'} disabled={busy} />
  </form>;
}
export default function Admin() {
  const { data, loading, error, refresh } = useFinanceData(['/api/admin/metrics', '/api/admin/settings']);
  return <section className="max-w-5xl"><h1 className="text-3xl font-semibold">Administración DTR</h1><p className="mt-2 text-gray-500 dark:text-gray-400">Métricas agregadas y configuración de la aplicación.</p><p className="mt-4 text-sm">Ética DTR: los datos personales y financieros pertenecen a cada usuario. Este panel consulta únicamente conteos de IDs.</p>
    {loading && !data[0] ? <p role="status" className="mt-6">Cargando…</p> : error ? <p role="alert" className="mt-6">{error}</p> : data[0] && <><div className="mt-8 grid gap-5 sm:grid-cols-2">{[['Usuarios registrados', data[0].users], ['Usuarios activos · últimos 15 minutos', data[0].active_users]].map(([title, value]) => <article key={title} className="rounded-xl border border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-800"><h2 className="text-sm text-gray-500 dark:text-gray-400">{title}</h2><p className="mt-3 text-4xl font-semibold">{value}</p></article>)}</div><Settings initial={data[1]} onSaved={refresh} /></>}
  </section>;
}

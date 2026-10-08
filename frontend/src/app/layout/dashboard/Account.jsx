import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Button from '../../elements/Button';
import Input from '../../elements/Input';
import useFinanceData from '../../helpers/useFinanceData';
import { apiRequest } from '../../helpers/cuentasApi';
import { useAuth } from '../../helpers/AuthContext';
function SecurityForm({ kind }) {
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '', correo: '' });
  const [busy, setBusy] = useState(false), [message, setMessage] = useState(''), [error, setError] = useState('');
  const { setUser } = useAuth(); const navigate = useNavigate();
  const change = (event) => setForm({ ...form, [event.target.name]: event.target.value });
  const submit = async (event) => {
    event.preventDefault(); if (busy) return; setBusy(true); setMessage(''); setError('');
    try {
      const body = kind === 'password' ? { current_password: form.current_password, password: form.password, password_confirmation: form.password_confirmation } : { current_password: form.current_password, correo: form.correo };
      const result = await apiRequest(`/api/account/${kind}`, { method: 'POST', body });
      setMessage(result.message); setForm({ current_password: '', password: '', password_confirmation: '', correo: '' });
      if (kind === 'password') { setUser(null); navigate('/login', { replace: true, state: { message: result.message } }); }
    } catch (e) { setError(Object.values(e.errors ?? {})[0] || e.message); setForm((previous) => ({ ...previous, current_password: '', password: '', password_confirmation: '' })); }
    finally { setBusy(false); }
  };
  return <form onSubmit={submit} className="mt-6 space-y-5 rounded-xl border border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-800">
    <h2 className="text-xl font-semibold">{kind === 'password' ? 'Cambiar contraseña' : 'Cambiar correo'}</h2>
    <p className="text-sm text-gray-500 dark:text-gray-400">{kind === 'password' ? 'Se notificará el cambio por correo y se cerrarán todas tus sesiones.' : 'Primero autoriza el cambio desde tu correo actual y después verifica el nuevo. Tu correo actual seguirá vigente hasta completar ambos pasos.'}</p>
    {message && <p role="status" className="text-green-700 dark:text-green-400">{message}</p>}{error && <p role="alert" className="text-red-600 dark:text-red-400">{error}</p>}
    <Input type="password" name="current_password" label="Contraseña actual" autoComplete="current-password" required value={form.current_password} onChange={change} disabled={busy} />
    {kind === 'password' ? <><Input type="password" name="password" label="Nueva contraseña" autoComplete="new-password" minLength={12} maxLength={72} required value={form.password} onChange={change} disabled={busy} /><Input type="password" name="password_confirmation" label="Confirma la nueva contraseña" autoComplete="new-password" required value={form.password_confirmation} onChange={change} disabled={busy} /></> : <Input type="email" name="correo" label="Nuevo correo" autoComplete="email" required maxLength={255} value={form.correo} onChange={change} disabled={busy} />}
    <Button type="submit" variant="primary" label={busy ? 'Procesando…' : kind === 'password' ? 'Actualizar contraseña' : 'Solicitar cambio de correo'} disabled={busy} />
  </form>;
}
export default function Account() {
  const { data, loading, error } = useFinanceData(['/api/account']); const user = data[0];
  return <section className="max-w-3xl"><h1 className="text-3xl font-semibold">Mi cuenta</h1><p className="mt-2 text-gray-500 dark:text-gray-400">Tu información y la seguridad de tu acceso.</p>
    {loading ? <p className="mt-6" role="status">Cargando…</p> : error ? <p role="alert">{error}</p> : user && <><dl className="mt-6 space-y-4 rounded-xl border border-gray-300 p-6 dark:border-gray-600">{[['Nombre', `${user.nombre} ${user.apellido}`], ['Correo', user.correo], ['Teléfono', user.telefono || '—'], ['Usuario', user.username || '—']].map(([label, value]) => <div key={label} className="flex flex-wrap justify-between gap-3"><dt className="text-gray-500 dark:text-gray-400">{label}</dt><dd className="break-all">{value}</dd></div>)}</dl><SecurityForm kind="password" />{user.rol !== 'admin' && <SecurityForm kind="email" />}</>}
  </section>;
}

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import Button from '../elements/Button';
import { apiRequest } from '../helpers/cuentasApi';
export default function ConfirmEmailChange() {
  const [token, setToken] = useState(() => window.location.hash.slice(1));
  useEffect(() => { window.history.replaceState(null, '', window.location.pathname); }, []);
  const [message, setMessage] = useState(''), [error, setError] = useState(''), [busy, setBusy] = useState(false);
  const confirm = async () => {
    if (busy) return; setBusy(true); setError('');
    try { const result = await apiRequest('/api/account/email/confirm', { method: 'POST', body: { token } }); setMessage(result.message); setToken(''); }
    catch (e) { setError(e.message); } finally { setBusy(false); }
  };
  return <main className="mx-auto flex min-h-dvh max-w-xl flex-col justify-center gap-6 p-6 text-gray-800 dark:text-gray-200"><h1 className="text-3xl font-semibold">Confirmar cambio de correo</h1><p>Confirma que deseas continuar con el cambio de correo de tu cuenta.</p>{message && <p role="status">{message}</p>}{error && <p role="alert" className="text-red-600 dark:text-red-400">{error}</p>}{!message && <Button label={busy ? 'Confirmando…' : 'Confirmar'} variant="primary" disabled={busy || !token} action={confirm} />}<Link to="/login" className="text-blue-700 underline dark:text-blue-400">Ir al inicio de sesión</Link></main>;
}

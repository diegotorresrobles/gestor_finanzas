import { useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiRequest } from './cuentasApi';

export const localDate = () => {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

export function useTransactionForm(initial, onSaved, id = null) {
  const [form, setForm] = useState(initial);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const lock = useRef(false);
  const navigate = useNavigate();
  const change = (event) => {
    const { name, value } = event.target;
    setForm((previous) => ({ ...previous, [name]: value, ...((name === 'tipo' && value !== 'transferencia') || (name === 'cuenta_id' && value === previous.cuenta_destino_id) ? { cuenta_destino_id: '' } : {}) }));
    setErrors({}); setError('');
  };
  const submit = async (event) => {
    event.preventDefault();
    if (lock.current) return;
    lock.current = true; setBusy(true); setErrors({}); setError('');
    try {
      const result = await apiRequest(`/api/transacciones${id ? `/${id}` : ''}`, { method: id ? 'PUT' : 'POST', body: { ...form, cuenta_destino_id: form.tipo === 'transferencia' ? form.cuenta_destino_id : null } });
      onSaved(result);
    } catch (error) {
      setErrors(error.errors ?? {}); setError(error.message);
      if (error.status === 401) navigate('/login', { replace: true });
    } finally { lock.current = false; setBusy(false); }
  };
  const reset = () => { setForm(initial); setErrors({}); setError(''); };
  return { form, change, submit, reset, errors, error, busy, locked: lock };
}

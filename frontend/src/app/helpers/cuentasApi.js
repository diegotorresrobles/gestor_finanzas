const API_URL = import.meta.env.VITE_API_URL ?? (import.meta.env.DEV ? 'http://localhost:3001' : '');
let refreshPromise;

export class ApiError extends Error {
  constructor(message, status, errors = {}) { super(message); this.status = status; this.errors = errors; }
}

export async function apiRequest(path, { method = 'GET', body, signal, retry = true } = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    method, signal, credentials: 'include',
    headers: { ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (response.status === 401 && retry && !['/api/auth/login', '/api/auth/refresh', '/api/auth/logout'].includes(path)) {
    refreshPromise ??= apiRequest('/api/auth/refresh', { method: 'POST', retry: false }).finally(() => { refreshPromise = null; });
    try { await refreshPromise; return apiRequest(path, { method, body, signal, retry: false }); }
    catch (error) { if (error.status === 401) window.dispatchEvent(new Event('dtr:unauthorized')); throw error; }
  }
  let data;
  try {
    data = await response.json();
  } catch {
    throw new ApiError('El servidor devolvió una respuesta inválida. Intenta de nuevo.', response.status);
  }
  if (!response.ok || !data.ok) throw new ApiError(data.message || 'No se pudo completar la solicitud', response.status, data.data?.errors);
  if (path === '/api/admin/settings' && method === 'PUT') window.dispatchEvent(new Event('dtr:config'));
  if (!['GET', 'HEAD'].includes(method) && !path.startsWith('/api/auth') && path !== '/api/realtime/ticket') window.dispatchEvent(new Event('dtr:changed'));
  return data.data;
}

export const cuentasRequest = (path = '', options = {}) => apiRequest(`/api/cuentas${path}`, options);
export const transactionLabel = (tipo) => ({ ingreso: 'Ingreso', gasto: 'Gasto', transferencia: 'Transferencia' }[tipo] ?? tipo);

export const creditStatus = (balance, tipo = '') => {
  if (!tipo.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().includes('credito')) return '';
  return Number(balance) < 0 ? 'Deuda pendiente' : Number(balance) > 0 ? 'Saldo a favor' : 'Sin deuda ni saldo a favor';
};

export const formatBalance = (value) => {
  const amount = Number(value) || 0;
  return `${amount < 0 ? '-' : ''}$${new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.abs(amount))}`;
};
export const accountColor = (color) => /^[a-f0-9]{6}$/i.test(color ?? '') ? `#${color}` : '#1D4ED8';
export const formatAccountDate = (value) => {
  if (!value) return '—';
  const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(date);
};

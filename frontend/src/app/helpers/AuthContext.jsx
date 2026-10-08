import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { apiRequest } from './cuentasApi';
const AuthContext = createContext(null);
export function AuthProvider({ children }) {
  const [session, setSession] = useState({ user: null, loading: true, error: '' });
  const [config, setConfig] = useState({ name: 'Gestor de finanzas', logo: '' });
  const setUser = useCallback((user) => setSession({ user, loading: false, error: '' }), []);
  const reload = useCallback(async () => {
    try { const data = await apiRequest('/api/auth/me'); setSession({ user: data.user, loading: false, error: '' }); }
    catch (error) { setSession({ user: null, loading: false, error: error.status === 401 ? '' : 'No se pudo verificar la sesión. Reintenta.' }); }
  }, []);
  useEffect(() => {
    localStorage.removeItem('token'); // Remove tokens left by the previous version.
    // oxlint-disable-next-line react/set-state-in-effect -- Session state changes after the awaited server response.
    void reload();
    apiRequest('/api/config').then(setConfig).catch(() => {});
    const expired = () => setSession({ user: null, loading: false, error: '' });
    const configurationChanged = () => { apiRequest('/api/config').then(setConfig).catch(() => {}); };
    window.addEventListener('dtr:unauthorized', expired);
    window.addEventListener('dtr:config', configurationChanged);
    return () => { window.removeEventListener('dtr:unauthorized', expired); window.removeEventListener('dtr:config', configurationChanged); };
  }, [reload]);
  const userId = session.user?.id;
  useEffect(() => {
    if (!userId) return;
    let socket, timer, stopped = false, delay = 1000, connected = false, polling = false, revision;
    const poll = async () => {
      if (stopped || polling || connected || document.visibilityState !== 'visible') return;
      polling = true;
      try {
        const data = await apiRequest('/api/realtime/revision');
        if (stopped) return;
        if (revision === undefined || revision !== data.revision || session.user?.rol === 'admin') window.dispatchEvent(new Event('dtr:changed'));
        revision = data.revision;
      } catch { /* Authentication and network errors are handled by the API helper. */ }
      finally { polling = false; }
    };
    const connect = async () => {
      try {
        const ticket = await apiRequest('/api/realtime/ticket', { method: 'POST' });
        if (stopped || !ticket.url) return;
        socket = new WebSocket(ticket.url);
        socket.onopen = () => { delay = 1000; socket.send(JSON.stringify({ ticket: ticket.ticket })); };
        socket.onmessage = (event) => {
          try { const message = JSON.parse(event.data); if (['ready', 'changed'].includes(message.type)) { connected = true; window.dispatchEvent(new Event('dtr:changed')); } } catch { /* Ignore malformed notifications. */ }
        };
        socket.onclose = () => { connected = false; retry(); };
        socket.onerror = () => socket.close();
      } catch { retry(); }
    };
    const retry = () => { if (!stopped) { timer = setTimeout(connect, delay); delay = Math.min(delay * 2, 30000); } };
    const visibility = () => { if (document.visibilityState === 'visible') { window.dispatchEvent(new Event('dtr:changed')); void apiRequest('/api/auth/me').catch(() => {}); } };
    if (config.ws_url) void connect();
    void poll();
    const pollTimer = setInterval(() => { void poll(); }, 10000);
    document.addEventListener('visibilitychange', visibility);
    return () => { stopped = true; clearTimeout(timer); clearInterval(pollTimer); socket?.close(); document.removeEventListener('visibilitychange', visibility); };
  }, [userId, config.ws_url, session.user?.rol]);
  return <AuthContext.Provider value={{ ...session, config, reload, setUser }}>{children}</AuthContext.Provider>;
}
// oxlint-disable-next-line react/only-export-components -- The hook is the public interface of this context module.
export const useAuth = () => useContext(AuthContext);

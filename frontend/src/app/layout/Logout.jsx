import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiRequest } from '../helpers/cuentasApi';
import { useAuth } from '../helpers/AuthContext';
function Logout() {
  const navigate = useNavigate(); const { setUser } = useAuth(); const [error, setError] = useState('');
  useEffect(() => {
    let active = true;
    apiRequest('/api/auth/logout', { method: 'POST' }).then(() => { if (active) { setUser(null); navigate('/login', { replace: true }); } }).catch(() => { if (active) setError('No se pudo cerrar la sesión. Reintenta.'); });
    return () => { active = false; };
  }, [navigate, setUser]);
  return <p className="p-8" role="status">{error || 'Cerrando sesión…'}{error && <button className="ml-3 underline" onClick={() => window.location.reload()}>Reintentar</button>}</p>;
}
export default Logout;

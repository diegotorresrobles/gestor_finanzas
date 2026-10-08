import { Navigate, Outlet } from "react-router-dom"
import { useAuth } from './AuthContext';

function RouteProtection({redirectTo = '/login', role}) {
  const { user, loading, error, reload } = useAuth();
  if (loading) return <p className="p-8" role="status">Verificando sesión…</p>;
  if (error) return <div className="p-8" role="alert">{error} <button type="button" onClick={reload} className="underline">Reintentar</button></div>;

  if (!user) {
    return <Navigate to={redirectTo} replace />
  }
  if (role && user.rol !== role) return <Navigate to={user.rol === 'admin' ? '/admin' : '/'} replace />;

  return <Outlet />
}



export default RouteProtection

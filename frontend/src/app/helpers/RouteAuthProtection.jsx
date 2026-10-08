import { Navigate, Outlet } from "react-router-dom"
import { useAuth } from './AuthContext';

function RouteAuthProtection({redirectTo = '/'}) {
  const { user, loading } = useAuth();
  if (loading) return <p className="p-8" role="status">Verificando sesión…</p>;

  if (user) {
    return <Navigate to={user.rol === 'admin' ? '/admin' : redirectTo} replace />
  }

  return <Outlet />
}



export default RouteAuthProtection

import { NavLink } from "react-router-dom";
import { useAuth } from '../helpers/AuthContext';

function Sidebar({ isOpen, onClose }) {
  const { user, config } = useAuth();
  return (
    <aside
      id="dashboard-sidebar"
      aria-label="Navegación principal"
      className={`${isOpen ? 'flex' : 'hidden'} fixed top-0 left-0 z-50 h-dvh w-64 max-w-[85vw] flex-col overflow-hidden border-r border-gray-300 bg-gray-50 text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 lg:flex`}
    >
      <div className="flex shrink-0 items-center justify-between gap-2 border-b border-gray-300 p-5 dark:border-gray-600">
        <NavLink to={user?.rol === 'admin' ? '/admin' : '/'} onClick={onClose} className="flex items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-blue-700 dark:focus-visible:outline-blue-400">
          <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-700 text-sm font-bold text-white">{config.logo ? <img src={config.logo} alt="" className="size-10 rounded-xl object-contain" /> : 'GF'}</span>
          <span className="text-sm font-semibold">{config.name}</span>
        </NavLink>
        <button type="button" onClick={onClose} aria-label="Cerrar menú" className="cursor-pointer rounded-md p-1 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-blue-700 dark:hover:bg-gray-700 dark:focus-visible:outline-blue-400 lg:hidden">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
            <path d="m6 6 12 12M6 18 18 6" />
          </svg>
        </button>
      </div>
      <nav className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4">
        {user?.rol === 'user' && <>
        <NavLink to="/" end onClick={onClose} className="mb-2 block rounded-lg px-3 py-3 text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-700">Resumen</NavLink>
        <p className="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Mi actividad</p>
        <NavLink
          to="/cuentas"
          onClick={onClose}
          className={({ isActive }) => `flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-blue-700 dark:focus-visible:outline-blue-400 ${isActive ? 'bg-blue-700 text-gray-50' : 'text-gray-600 hover:bg-gray-200 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200'}`}
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="3" />
            <path d="M3 9h18M7 15h3" />
          </svg>
          Cuentas
        </NavLink>
        <NavLink to="/transacciones" onClick={onClose} className={({ isActive }) => `mt-2 flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-blue-700 dark:focus-visible:outline-blue-400 ${isActive ? 'bg-blue-700 text-gray-50' : 'text-gray-600 hover:bg-gray-200 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200'}`}>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 7h16m-4-4 4 4-4 4M20 17H4m4-4-4 4 4 4" /></svg>
          Transacciones
        </NavLink>
        </>}
        {user?.rol === 'admin' && <NavLink to="/admin" onClick={onClose} className="block rounded-lg bg-blue-700 px-3 py-3 text-sm text-white">Administración</NavLink>}
        <NavLink to="/account" onClick={onClose} className="mt-2 block rounded-lg px-3 py-3 text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-700">Mi cuenta</NavLink>
      </nav>
      <div className="shrink-0 border-t border-gray-300 p-4 dark:border-gray-600">
        <NavLink to="/logout" onClick={onClose} className="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-200 hover:text-gray-800 focus-visible:outline-2 focus-visible:outline-blue-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 dark:focus-visible:outline-blue-400">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M10 12h11m-4-4 4 4-4 4" />
          </svg>
          Cerrar sesión
        </NavLink>
      </div>
    </aside>
  );
}

export default Sidebar;

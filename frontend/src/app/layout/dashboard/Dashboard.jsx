import { useEffect, useRef, useState } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from '../../elements/Sidebar'
import { useAuth } from '../../helpers/AuthContext';

function Dashboard() {
  const { config } = useAuth();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const menuRef = useRef(null);

  useEffect(() => {
    if (!sidebarOpen) return;

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        setSidebarOpen(false);
        menuRef.current?.focus();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [sidebarOpen]);

  return (
    <div className="flex h-dvh w-full flex-col overflow-hidden bg-white text-gray-800 dark:bg-gray-900 dark:text-gray-200">
      <header className="flex shrink-0 items-center gap-3 border-b border-gray-300 bg-gray-50 px-4 py-4 dark:border-gray-600 dark:bg-gray-800 lg:hidden">
        <button
          ref={menuRef}
          type="button"
          aria-label="Abrir menú de navegación"
          aria-expanded={sidebarOpen}
          aria-controls="dashboard-sidebar"
          onClick={() => setSidebarOpen(true)}
          className="cursor-pointer rounded-md p-2 hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-blue-700 dark:hover:bg-gray-700 dark:focus-visible:outline-blue-400"
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <span className="font-semibold">{config.name}</span>
      </header>
      {sidebarOpen && (
        <button
          type="button"
          aria-label="Cerrar menú de navegación"
          onClick={() => { setSidebarOpen(false); menuRef.current?.focus(); }}
          className="fixed inset-0 z-40 bg-black/30 backdrop-blur-xs dark:bg-black/50 lg:hidden"
        />
      )}
      <Sidebar
        isOpen={sidebarOpen}
        onClose={() => { setSidebarOpen(false); menuRef.current?.focus(); }}
      />
      <main className="min-h-0 min-w-0 flex-1 overflow-auto overscroll-contain p-5 sm:p-8 lg:ml-64 lg:p-10">
        <Outlet />
      </main>
    </div>
  )
}

export default Dashboard

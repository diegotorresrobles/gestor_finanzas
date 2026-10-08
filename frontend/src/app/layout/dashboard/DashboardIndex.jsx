import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import CuentaCard from "../../elements/CuentaCard";
import TransaccionModal from "../../elements/TransaccionModal";
import TransaccionesTable from '../../elements/TransaccionesTable';
import useFinanceData from '../../helpers/useFinanceData';
import useCuentas from "../../helpers/useCuentas";
import { formatBalance } from "../../helpers/cuentasApi";

function DashboardIndex() {
  const { cuentas, tipoNombre, loading, error, refresh } = useCuentas();
  const recent = useFinanceData(['/api/transacciones/recientes']);
  const [selected, setSelected] = useState(null);
  const navigate = useNavigate();
  const balance = cuentas.reduce((total, cuenta) => total + Math.round(Number(cuenta.balance) * 100), 0) / 100;
  return <section aria-labelledby="dashboard-title">
    <header className="flex flex-wrap items-center justify-between gap-4">
      <div><h1 id="dashboard-title" className="text-3xl font-semibold tracking-tight">Resumen</h1><p className="mt-2 text-gray-500 dark:text-gray-400">El balance de tus cuentas, en un solo lugar.</p></div>
      <Link to="/cuentas" className="rounded-md bg-blue-700 px-5 py-2.5 text-gray-50 hover:ring-4 hover:ring-blue-700/50 focus-visible:outline-2 focus-visible:outline-blue-400">Administrar cuentas</Link>
    </header>
    <div className="mt-8 rounded-xl border border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-800">
      <p className="text-sm text-gray-500 dark:text-gray-400">Balance general</p>
      <p className="mt-2 break-words text-4xl font-semibold tabular-nums">{loading || error ? '—' : formatBalance(balance)}</p>
      <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">{loading ? 'Cargando cuentas…' : `${cuentas.length} ${cuentas.length === 1 ? 'cuenta registrada' : 'cuentas registradas'}`}</p>
    </div>
    {error && <div role="alert" className="mt-5 text-red-600 dark:text-red-400"><p>{error}</p><button type="button" onClick={() => refresh()} className="mt-2 cursor-pointer underline">Reintentar</button></div>}
    {loading ? <p role="status" className="mt-8 text-gray-500 dark:text-gray-400">Cargando tus cuentas…</p> : !error && cuentas.length === 0 ? <div className="mt-8 rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-600"><h2 className="text-lg font-semibold">Agrega tu primera cuenta</h2><p className="mt-2 text-gray-500 dark:text-gray-400">Tus cuentas y sus balances aparecerán aquí.</p><Link to="/cuentas" className="mt-4 inline-block rounded text-blue-700 underline dark:text-blue-400">Ir a Cuentas</Link></div> : <div className="mt-8 flex gap-5 overflow-x-auto pb-4 [&>article]:w-80 [&>article]:shrink-0">{cuentas.map((cuenta) => <CuentaCard key={cuenta.id} cuenta={cuenta} tipo={tipoNombre(cuenta.tipo_cuenta_id)} onView={(cuenta) => navigate(`/cuentas/${cuenta.id}`)} onAddMovimiento={setSelected} />)}</div>}
    <div className="mt-8">{recent.error && <p role="alert" className="mb-4 text-red-600 dark:text-red-400">{recent.error}</p>}<TransaccionesTable title="Últimas 5 transacciones" data={(recent.data[0] ?? []).slice(0, 5)} loading={recent.loading} actions={<Link to="/transacciones" className="text-blue-700 underline dark:text-blue-400">Ver todas</Link>} /></div>
    {selected && <TransaccionModal cuentaId={selected.id} onClose={() => setSelected(null)} onSaved={() => { setSelected(null); void refresh(); }} />}
  </section>;
}

export default DashboardIndex;

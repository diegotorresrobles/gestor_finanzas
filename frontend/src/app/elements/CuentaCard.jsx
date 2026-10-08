import Button from "./Button";
import { accountColor, creditStatus, formatBalance } from "../helpers/cuentasApi";

function CuentaCard({ cuenta, tipo, onView, onAddMovimiento }) {
  return <article className="flex min-w-0 flex-col overflow-hidden rounded-xl border border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800">
    <div className="h-1.5" style={{ backgroundColor: accountColor(cuenta.color) }} />
    <div className="flex flex-1 flex-col p-5">
      <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{tipo}</p>
      <h2 className="mt-2 break-words text-lg font-semibold">{cuenta.nombre}</h2>
      <p className="mt-6 text-sm text-gray-500 dark:text-gray-400">Balance actual</p>
      <p className="mt-1 break-words text-3xl font-semibold tabular-nums">{formatBalance(cuenta.balance)}</p>
      {creditStatus(cuenta.balance, tipo) && <p className={`mt-2 text-sm font-medium ${Number(cuenta.balance) < 0 ? 'text-red-700 dark:text-red-400' : 'text-green-700 dark:text-green-400'}`}>{creditStatus(cuenta.balance, tipo)}</p>}
      {cuenta.limite_credito != null && <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Límite: {formatBalance(cuenta.limite_credito)} · Disponible: {formatBalance(Number(cuenta.limite_credito) + Number(cuenta.balance))}</p>}
      <div className="mt-auto flex flex-wrap gap-3 pt-6"><Button variant="primary" label="Ver información" action={() => onView(cuenta)} /><Button variant="secondary" label="Agregar movimiento" disabled={!onAddMovimiento} action={() => onAddMovimiento?.(cuenta)} /></div>
      {!onAddMovimiento && <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">El registro de movimientos estará disponible próximamente.</p>}
    </div>
  </article>;
}

export default CuentaCard;

import { Link } from 'react-router-dom';
import Table from './Table';
import DeleteTransaccion from './DeleteTransaccion';
import { formatAccountDate, formatBalance, transactionLabel } from '../helpers/cuentasApi';

function TransaccionesTable({ data, loading = false, actions, title = 'Transacciones', accountId = null }) {
  const columns = [
    { key: 'fecha', label: 'Fecha', render: (value) => <span className="whitespace-nowrap">{formatAccountDate(value)}</span> },
    { key: 'tipo', label: 'Tipo', render: (value) => <span className={`whitespace-nowrap rounded-full px-2 py-1 text-xs font-medium ${value === 'ingreso' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : value === 'gasto' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'}`}>{transactionLabel(value)}</span> },
    { key: 'cuenta_nombre', label: 'Cuenta', render: (value, row) => <span className="block min-w-32">{value}{!row.cuenta_id && ' (eliminada)'}{row.tipo === 'transferencia' && <span className="block text-xs text-gray-500 dark:text-gray-400">→ {row.cuenta_destino_nombre}{!row.cuenta_destino_id && ' (eliminada)'}</span>}</span> },
    { key: 'monto', label: 'Monto', align: 'right', render: (value, row) => {
      const positive = row.tipo === 'ingreso' || (row.tipo === 'transferencia' && accountId && Number(row.cuenta_destino_id) === Number(accountId));
      return <span className={`whitespace-nowrap tabular-nums ${positive ? 'text-green-700 dark:text-green-400' : row.tipo === 'gasto' || accountId ? 'text-red-700 dark:text-red-400' : ''}`}>{row.tipo === 'transferencia' && !accountId ? '' : positive ? '+' : '-'}{formatBalance(value)}</span>;
    } },
    { key: 'categoria', label: 'Categoría' },
    { key: 'descripcion', label: 'Descripción', render: (value) => <span className="block min-w-32 max-w-xs break-words">{value || '—'}</span> },
    { key: 'actions', label: 'Acciones', render: (_, row) => <div className="flex items-center gap-3"><Link to={`/transacciones/${row.id}`} className="whitespace-nowrap rounded text-blue-700 underline dark:text-blue-400">Ver / editar</Link><DeleteTransaccion id={row.id} /></div> },
  ];
  return <Table title={title} description={`${data.length} ${data.length === 1 ? 'transacción' : 'transacciones'}`} columns={columns} data={data} loading={loading} actions={actions} emptyMessage="Todavía no hay transacciones registradas." />;
}

export default TransaccionesTable;

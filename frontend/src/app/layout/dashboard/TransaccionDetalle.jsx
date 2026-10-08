import { useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import Button from '../../elements/Button';
import DeleteTransaccion from '../../elements/DeleteTransaccion';
import TransaccionForm from '../../elements/TransaccionForm';
import useFinanceData from '../../helpers/useFinanceData';
import { useTransactionForm } from '../../helpers/useTransactionForm';
import { formatAccountDate, formatBalance, transactionLabel } from '../../helpers/cuentasApi';

function Editor({ transaction, cuentas, categorias, onSaved }) {
  const navigate = useNavigate();
  const archived = !transaction.cuenta_id || (transaction.tipo === 'transferencia' && !transaction.cuenta_destino_id);
  const [editing, setEditing] = useState(false);
  const state = useTransactionForm({ tipo: transaction.tipo, expected_version: transaction.version, cuenta_id: String(transaction.cuenta_id), cuenta_destino_id: transaction.cuenta_destino_id ? String(transaction.cuenta_destino_id) : '', categoria_id: String(transaction.categoria_id), monto: transaction.monto, descripcion: transaction.descripcion ?? '', fecha: transaction.fecha ?? '' }, onSaved, transaction.id);
  if (editing) return <form onSubmit={state.submit} className="mt-8 max-w-2xl space-y-5 rounded-xl border border-gray-300 bg-gray-50 p-5 dark:border-gray-600 dark:bg-gray-800">
    <h2 className="text-xl font-semibold">Editar transacción</h2>
    <p className="text-sm text-gray-500 dark:text-gray-400">Al guardar, los saldos se actualizarán según los nuevos datos.</p>
    {state.error && <p role="alert" className="text-red-600 dark:text-red-400">{state.error}</p>}
    <TransaccionForm form={state.form} onChange={state.change} errors={state.errors} cuentas={cuentas} categorias={categorias} disabled={state.busy} />
    <div className="flex flex-wrap gap-3"><Button type="submit" label={state.busy ? 'Guardando…' : 'Guardar cambios'} variant="primary" disabled={state.busy} /><Button label="Cancelar" variant="secondary" disabled={state.busy} action={() => { state.reset(); setEditing(false); }} /></div>
  </form>;
  const fields = [['Tipo', transactionLabel(transaction.tipo)], ['Monto', formatBalance(transaction.monto)], ['Fecha', formatAccountDate(transaction.fecha)], ['Categoría', transaction.categoria], ['Descripción', transaction.descripcion || '—'], ['Registrada', formatAccountDate(transaction.created_at)], ['Actualizada', formatAccountDate(transaction.updated_at)]];
  return <div className="mt-8 max-w-2xl rounded-xl border border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-800">
    <dl className="space-y-5">
      {fields.map(([label, value]) => <div key={label} className="flex flex-wrap justify-between gap-3"><dt className="text-gray-500 dark:text-gray-400">{label}</dt><dd className="max-w-full break-words font-medium">{value}</dd></div>)}
      <div className="flex flex-wrap justify-between gap-3"><dt className="text-gray-500 dark:text-gray-400">{transaction.tipo === 'transferencia' ? 'Cuenta origen' : 'Cuenta'}</dt><dd>{transaction.cuenta_id ? <Link to={`/cuentas/${transaction.cuenta_id}`} className="text-blue-700 underline dark:text-blue-400">{transaction.cuenta_nombre}</Link> : `${transaction.cuenta_nombre} (eliminada)`}</dd></div>
      {transaction.tipo === 'transferencia' && <div className="flex flex-wrap justify-between gap-3"><dt className="text-gray-500 dark:text-gray-400">Cuenta destino</dt><dd>{transaction.cuenta_destino_id ? <Link to={`/cuentas/${transaction.cuenta_destino_id}`} className="text-blue-700 underline dark:text-blue-400">{transaction.cuenta_destino_nombre}</Link> : `${transaction.cuenta_destino_nombre} (eliminada)`}</dd></div>}
    </dl>
    {archived && <p className="mt-6 text-sm text-gray-500 dark:text-gray-400">Una cuenta de este movimiento fue eliminada. El historial se conserva para tus análisis y ya no se puede editar.</p>}
    <div className="mt-6 flex gap-3"><Button label="Editar transacción" variant="primary" disabled={archived} action={() => { state.reset(); setEditing(true); }} /><DeleteTransaccion id={transaction.id} onDeleted={() => navigate('/transacciones', { replace: true })} /></div>
  </div>;
}

function TransaccionDetalle() {
  const { id } = useParams();
  const { data, loading, error, refresh } = useFinanceData([`/api/transacciones/${id}`, '/api/cuentas', '/api/transacciones/categorias']);
  const [notice, setNotice] = useState('');
  return <section>
    <Link to="/transacciones" className="text-sm text-blue-700 underline dark:text-blue-400">← Transacciones</Link>
    <h1 className="mt-4 text-3xl font-semibold">Transacción #{id}</h1>
    {notice && <p role="status" className="mt-4 text-green-700 dark:text-green-400">{notice}</p>}
    {loading ? <p role="status" className="mt-6">Cargando transacción…</p> : error ? <div role="alert" className="mt-6 text-red-600 dark:text-red-400"><p>{error}</p><button type="button" onClick={refresh} className="underline">Reintentar</button></div> : data[0] && <Editor key={id} transaction={data[0]} cuentas={data[1]} categorias={data[2]} onSaved={() => { setNotice('Transacción actualizada correctamente.'); void refresh(); }} />}
  </section>;
}

export default TransaccionDetalle;

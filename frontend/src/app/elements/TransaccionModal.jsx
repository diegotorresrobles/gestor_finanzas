import { Link } from 'react-router-dom';
import Modal from './Modal';
import TransaccionForm from './TransaccionForm';
import useFinanceData from '../helpers/useFinanceData';
import { localDate, useTransactionForm } from '../helpers/useTransactionForm';

function CreateDialog({ cuentas, categorias, cuentaId, onClose, onSaved }) {
  const state = useTransactionForm({ tipo: 'gasto', cuenta_id: cuentaId ? String(cuentaId) : '', cuenta_destino_id: '', categoria_id: String(categorias[0]?.id ?? ''), monto: '', descripcion: '', fecha: localDate() }, onSaved);
  return <Modal isOpen title="Agregar transacción" onClose={() => { if (!state.locked.current) onClose(); }} onSubmit={state.submit} disableClose={state.busy} actions={[{ label: 'Cancelar', disabled: state.busy }, { label: state.busy ? 'Guardando…' : 'Guardar transacción', variant: 'primary', type: 'submit', disabled: state.busy || !cuentas.length || !categorias.length }]}>
    {state.error && <p role="alert" className="mb-4 text-red-600 dark:text-red-400">{state.error}</p>}
    {cuentas.length ? <TransaccionForm form={state.form} onChange={state.change} errors={state.errors} cuentas={cuentas} categorias={categorias} disabled={state.busy} /> : <p>Primero <Link to="/cuentas" className="text-blue-700 underline dark:text-blue-400">agrega una cuenta</Link> para registrar transacciones.</p>}
  </Modal>;
}

function TransaccionModal({ cuentaId = null, onClose, onSaved }) {
  const { data, loading, error, refresh } = useFinanceData(['/api/cuentas', '/api/transacciones/categorias']);
  if (loading || error) return <Modal isOpen title="Agregar transacción" onClose={onClose} actions={[{ label: 'Cerrar' }]}>{error ? <div role="alert"><p className="text-red-600 dark:text-red-400">{error}</p><button type="button" onClick={refresh} className="mt-3 cursor-pointer underline">Reintentar</button></div> : <p role="status">Cargando cuentas y categorías…</p>}</Modal>;
  return <CreateDialog cuentas={data[0]} categorias={data[1]} cuentaId={cuentaId} onClose={onClose} onSaved={onSaved} />;
}

export default TransaccionModal;

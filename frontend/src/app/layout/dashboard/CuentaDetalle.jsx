import { useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Button from '../../elements/Button';
import CuentaForm from '../../elements/CuentaForm';
import CuentaInfo from '../../elements/CuentaInfo';
import TransaccionesTable from '../../elements/TransaccionesTable';
import TransaccionModal from '../../elements/TransaccionModal';
import useFinanceData from '../../helpers/useFinanceData';
import { cuentasRequest } from '../../helpers/cuentasApi';

function AccountEditor({ cuenta, tipos, onSaved, handleError }) {
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({ nombre: cuenta.nombre, tipo_cuenta_id: String(cuenta.tipo_cuenta_id), balance: cuenta.balance, limite_credito: cuenta.limite_credito ?? '', expected_balance: cuenta.balance, color: `#${cuenta.color}` });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const lock = useRef(false);
  const save = async (event) => {
    event.preventDefault();
    if (lock.current) return;
    lock.current = true; setBusy(true); setErrors({}); setError('');
    try { await cuentasRequest(`/${cuenta.id}`, { method: 'PUT', body: form }); onSaved(); }
    catch (error) { setErrors(error.errors ?? {}); setError(handleError(error)); }
    finally { lock.current = false; setBusy(false); }
  };
  const change = (event) => { setForm((previous) => ({ ...previous, [event.target.name]: event.target.value })); setErrors({}); setError(''); };
  return <div className="mt-8 max-w-2xl rounded-xl border border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-800">
    {editing ? <form onSubmit={save} className="space-y-5">
      <h2 className="text-xl font-semibold">Editar cuenta</h2>
      {error && <p role="alert" className="text-red-600 dark:text-red-400">{error}</p>}
      <CuentaForm form={form} onChange={change} errors={errors} tipos={tipos} disabled={busy} balanceLabel="Balance actual" />
      <div className="flex flex-wrap gap-3"><Button type="submit" label={busy ? 'Guardando…' : 'Guardar cambios'} variant="primary" disabled={busy} /><Button label="Cancelar" variant="secondary" disabled={busy} action={() => { setForm({ nombre: cuenta.nombre, tipo_cuenta_id: String(cuenta.tipo_cuenta_id), balance: cuenta.balance, limite_credito: cuenta.limite_credito ?? '', expected_balance: cuenta.balance, color: `#${cuenta.color}` }); setErrors({}); setError(''); setEditing(false); }} /></div>
    </form> : <><CuentaInfo cuenta={cuenta} tipo={cuenta.tipo_cuenta} /><div className="mt-6"><Button label="Editar cuenta" variant="primary" action={() => { setForm({ nombre: cuenta.nombre, tipo_cuenta_id: String(cuenta.tipo_cuenta_id), balance: cuenta.balance, expected_balance: cuenta.balance, limite_credito: cuenta.limite_credito ?? '', color: `#${cuenta.color}` }); setEditing(true); }} /></div></>}
  </div>;
}

function CuentaDetalle() {
  const { id } = useParams();
  const { data, loading, error, refresh, handleError } = useFinanceData([`/api/cuentas/${id}`, '/api/cuentas/tipos']);
  const [notice, setNotice] = useState('');
  const [open, setOpen] = useState(false);
  const cuenta = data[0];
  return <section>
    <Link to="/cuentas" className="text-sm text-blue-700 underline dark:text-blue-400">← Cuentas</Link>
    <h1 className="mt-4 break-words text-3xl font-semibold">{cuenta?.nombre ?? 'Información de la cuenta'}</h1>
    {notice && <p role="status" className="mt-4 text-green-700 dark:text-green-400">{notice}</p>}
    {loading ? <p role="status" className="mt-6">Cargando cuenta…</p> : error ? <div role="alert" className="mt-6 text-red-600 dark:text-red-400"><p>{error}</p><button type="button" onClick={refresh} className="underline">Reintentar</button></div> : cuenta && <>
      <AccountEditor key={id} cuenta={cuenta} tipos={data[1]} handleError={handleError} onSaved={() => { setNotice('Cuenta actualizada correctamente.'); void refresh(); }} />
      <div className="mt-8"><TransaccionesTable data={cuenta.transacciones} accountId={cuenta.id} title="Movimientos de la cuenta" actions={<Button variant="primary" label="Agregar transacción" action={() => setOpen(true)} />} /></div>
    </>}
    {open && <TransaccionModal cuentaId={id} onClose={() => setOpen(false)} onSaved={() => { setOpen(false); setNotice('Transacción agregada correctamente.'); void refresh(); }} />}
  </section>;
}

export default CuentaDetalle;

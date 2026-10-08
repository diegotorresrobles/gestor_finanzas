import { useRef, useState } from "react";
import { Link } from "react-router-dom";
import Button from "../../elements/Button";
import Modal from "../../elements/Modal";
import Table from "../../elements/Table";
import CuentaForm from "../../elements/CuentaForm";
import useCuentas from "../../helpers/useCuentas";
import { accountColor, creditStatus, cuentasRequest, formatAccountDate, formatBalance } from "../../helpers/cuentasApi";

const initialForm = { nombre: '', tipo_cuenta_id: '', balance: '0', limite_credito: '', color: '#1d4ed8' };

function Cuentas() {
  const { cuentas, setCuentas, tipos, tipoNombre, loading, error, refresh, handleError } = useCuentas();
  const [modal, setModal] = useState(null);
  const [form, setForm] = useState(initialForm);
  const [errors, setErrors] = useState({});
  const [modalError, setModalError] = useState('');
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const submitting = useRef(false);

  const openModal = (kind, cuenta = null) => { setForm(initialForm); setErrors({}); setModalError(''); setModal({ kind, cuenta }); };
  const closeModal = () => { if (!submitting.current) setModal(null); };
  const changeField = (event) => {
    const { name, value } = event.target;
    setForm((previous) => ({ ...previous, [name]: value }));
    setErrors((previous) => ({ ...previous, [name]: undefined }));
    setModalError('');
  };

  const createCuenta = async (event) => {
    event.preventDefault();
    if (submitting.current) return;
    submitting.current = true;
    setBusy(true); setModalError(''); setErrors({});
    try {
      const cuenta = await cuentasRequest('', { method: 'POST', body: { ...form, nombre: form.nombre.trim(), color: form.color.slice(1) } });
      setCuentas((previous) => [cuenta, ...previous]);
      setModal(null); setNotice('Cuenta agregada correctamente.');
      void refresh();
    } catch (error) {
      setErrors(error.errors ?? {}); setModalError(handleError(error));
    } finally { submitting.current = false; setBusy(false); }
  };

  const deleteCuenta = async () => {
    if (submitting.current) return;
    submitting.current = true;
    setBusy(true); setModalError('');
    try {
      await cuentasRequest('', { method: 'DELETE', body: { id: modal.cuenta.id } });
      setCuentas((previous) => previous.filter((cuenta) => cuenta.id !== modal.cuenta.id));
      setModal(null); setNotice('Cuenta eliminada correctamente.');
    } catch (error) { setModalError(error.errors?.cuenta ?? handleError(error)); }
    finally { submitting.current = false; setBusy(false); }
  };

  const columns = [
    { key: 'nombre', label: 'Nombre', render: (value) => <span className="block min-w-32 max-w-xs break-words font-medium">{value}</span> },
    { key: 'tipo_cuenta_id', label: 'Tipo', render: (value) => tipoNombre(value) },
    { key: 'balance', label: 'Balance', align: 'right', render: (value, cuenta) => <span className="whitespace-nowrap tabular-nums">{formatBalance(value)}{creditStatus(value, tipoNombre(cuenta.tipo_cuenta_id)) && <span className={`block text-xs ${Number(value) < 0 ? 'text-red-700 dark:text-red-400' : 'text-green-700 dark:text-green-400'}`}>{creditStatus(value, tipoNombre(cuenta.tipo_cuenta_id))}</span>}</span> },
    { key: 'color', label: 'Color', render: (value) => <span className="flex items-center gap-2 whitespace-nowrap"><span aria-hidden="true" className="size-4 rounded-full border border-gray-300 dark:border-gray-600" style={{ backgroundColor: accountColor(value) }} />{accountColor(value).toUpperCase()}</span> },
    { key: 'created_at', label: 'Creada', render: (value) => <span className="whitespace-nowrap">{formatAccountDate(value)}</span> },
    { key: 'actions', label: 'Acciones', align: 'right', render: (_, cuenta) => <div className="flex justify-end gap-3"><Link to={`/cuentas/${cuenta.id}`} aria-label={`Ver información de ${cuenta.nombre}`} className="whitespace-nowrap rounded px-2 py-1 text-blue-700 hover:bg-blue-700/10 focus-visible:outline-2 dark:text-blue-400">Ver / editar</Link><button type="button" onClick={() => openModal('delete', cuenta)} aria-label={`Eliminar ${cuenta.nombre}`} className="cursor-pointer rounded px-2 py-1 text-red-700 hover:bg-red-700/10 focus-visible:outline-2 dark:text-red-400">Eliminar</button></div> },
  ];

  return <section aria-labelledby="cuentas-title" className="min-w-0">
    <h1 id="cuentas-title" className="text-3xl font-semibold tracking-tight">Cuentas</h1>
    <p className="mt-2 text-gray-500 dark:text-gray-400">Consulta y administra tus cuentas.</p>
    {notice && <p role="status" className="mt-5 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{notice}</p>}
    {error && <div role="alert" className="mt-5 flex flex-wrap items-center gap-3 rounded-lg bg-red-50 p-3 text-red-700 dark:bg-red-900/20 dark:text-red-400"><p>{error}</p><button type="button" onClick={() => refresh()} className="cursor-pointer underline">Reintentar</button></div>}
    <div className="mt-8"><Table title="Mis cuentas" description={`${cuentas.length} ${cuentas.length === 1 ? 'cuenta' : 'cuentas'}`} columns={columns} data={cuentas} loading={loading} emptyMessage={error ? 'No se pudo cargar la información.' : 'Todavía no tienes cuentas. Agrega la primera para empezar.'} actions={<Button variant="primary" label="Agregar cuenta" disabled={tipos.length === 0} action={() => openModal('create')} />} /></div>
    {modal && <Modal isOpen title={modal.kind === 'create' ? 'Agregar cuenta' : 'Eliminar cuenta'} onClose={closeModal} onSubmit={modal.kind === 'create' ? createCuenta : undefined} disableClose={busy} actions={[
      { label: 'Cancelar', disabled: busy },
      modal.kind === 'create' ? { label: busy ? 'Guardando…' : 'Guardar cuenta', variant: 'primary', type: 'submit', disabled: busy } : { label: busy ? 'Eliminando…' : 'Eliminar cuenta', variant: 'danger', action: deleteCuenta, disabled: busy },
    ]}>
      {modalError && <p role="alert" className="mb-4 text-sm text-red-600 dark:text-red-400">{modalError}</p>}
      {modal.kind === 'create' ? <CuentaForm form={form} onChange={changeField} errors={errors} tipos={tipos} disabled={busy} /> : <p className="break-words">¿Quieres eliminar la cuenta <strong>{modal.cuenta.nombre}</strong>? Esta acción no se puede deshacer. Tus transacciones se conservarán en el historial y los saldos de tus otras cuentas no cambiarán.</p>}
    </Modal>}
  </section>;
}

export default Cuentas;

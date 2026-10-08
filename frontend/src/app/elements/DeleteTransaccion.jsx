import { useState } from 'react';
import Button from './Button';
import Modal from './Modal';
import { apiRequest } from '../helpers/cuentasApi';
export default function DeleteTransaccion({ id, onDeleted }) {
  const [open, setOpen] = useState(false), [busy, setBusy] = useState(false), [error, setError] = useState('');
  const remove = async () => {
    if (busy) return; setBusy(true); setError('');
    try { await apiRequest(`/api/transacciones/${id}`, { method: 'DELETE' }); setOpen(false); onDeleted?.(); }
    catch (e) { setError(Object.values(e.errors ?? {})[0] || e.message); } finally { setBusy(false); }
  };
  return <><Button label="Eliminar" variant="danger" action={() => { setError(''); setOpen(true); }} />{open && <Modal isOpen title="Eliminar transacción" disableClose={busy} onClose={() => setOpen(false)} actions={[{ label: 'Cancelar', disabled: busy }, { label: busy ? 'Eliminando…' : 'Eliminar transacción', variant: 'danger', disabled: busy, action: remove }]}><p>Se revertirá el movimiento: un gasto devolverá saldo, un ingreso lo descontará y una transferencia revertirá ambos lados. Solo se ajustarán las cuentas que todavía existan. Esta acción no se puede deshacer.</p>{error && <p role="alert" className="mt-4 text-red-600 dark:text-red-400">{error}</p>}</Modal>}</>;
}

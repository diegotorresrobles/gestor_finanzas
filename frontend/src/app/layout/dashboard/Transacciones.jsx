import { useState } from 'react';
import Button from '../../elements/Button';
import TransaccionesTable from '../../elements/TransaccionesTable';
import TransaccionModal from '../../elements/TransaccionModal';
import useFinanceData from '../../helpers/useFinanceData';

function Transacciones() {
  const { data, loading, error, refresh } = useFinanceData(['/api/transacciones']);
  const [open, setOpen] = useState(false);
  const [notice, setNotice] = useState('');
  return <section>
    <h1 className="text-3xl font-semibold">Transacciones</h1>
    <p className="mt-2 text-gray-500 dark:text-gray-400">Tus gastos, ingresos y transferencias internas.</p>
    {notice && <p role="status" className="mt-5 text-green-700 dark:text-green-400">{notice}</p>}
    {error && <div role="alert" className="mt-5 text-red-600 dark:text-red-400"><p>{error}</p><button type="button" onClick={refresh} className="cursor-pointer underline">Reintentar</button></div>}
    <div className="mt-8"><TransaccionesTable data={data[0] ?? []} loading={loading} actions={<Button label="Agregar transacción" variant="primary" action={() => setOpen(true)} />} /></div>
    {open && <TransaccionModal onClose={() => setOpen(false)} onSaved={() => { setOpen(false); setNotice('Transacción agregada correctamente.'); void refresh(); }} />}
  </section>;
}

export default Transacciones;

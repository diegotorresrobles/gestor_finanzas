import Input from './Input';

function TransaccionForm({ form, onChange, errors, cuentas, categorias, disabled = false }) {
  return <div className="space-y-5">
    <Input type="select" name="tipo" label="Tipo de transacción" value={form.tipo} onChange={onChange} error={errors.tipo} options={[{ value: 'gasto', label: 'Gasto' }, { value: 'ingreso', label: 'Ingreso' }, { value: 'transferencia', label: 'Transferencia interna' }]} required disabled={disabled} />
    <Input type="select" name="cuenta_id" label={form.tipo === 'transferencia' ? 'Cuenta origen' : 'Cuenta'} value={form.cuenta_id} onChange={onChange} error={errors.cuenta_id} options={cuentas.map((cuenta) => ({ value: cuenta.id, label: cuenta.nombre }))} required disabled={disabled} />
    {form.tipo === 'transferencia' && <Input type="select" name="cuenta_destino_id" label="Cuenta destino" value={form.cuenta_destino_id} onChange={onChange} error={errors.cuenta_destino_id} options={cuentas.filter((cuenta) => String(cuenta.id) !== String(form.cuenta_id)).map((cuenta) => ({ value: cuenta.id, label: cuenta.nombre }))} required disabled={disabled} />}
    <Input type="number" name="monto" label="Monto" value={form.monto} onChange={onChange} error={errors.monto} min="0.01" max="9999999999.99" step="0.01" required disabled={disabled} />
    <Input type="select" name="categoria_id" label="Categoría" value={form.categoria_id} onChange={onChange} error={errors.categoria_id} options={categorias.map((categoria) => ({ value: categoria.id, label: categoria.categoria }))} required disabled={disabled} />
    <Input type="date" name="fecha" label="Fecha" value={form.fecha} onChange={onChange} error={errors.fecha} required disabled={disabled} />
    <Input name="descripcion" label="Descripción" value={form.descripcion} onChange={onChange} error={errors.descripcion} maxLength={2000} disabled={disabled} />
    {form.tipo === 'transferencia' && <p className="text-sm text-gray-500 dark:text-gray-400">El monto se descontará de la cuenta origen y se sumará a la cuenta destino.</p>}
  </div>;
}

export default TransaccionForm;

import Input from "./Input";

function CuentaForm({ form, onChange, errors, tipos, disabled, balanceLabel = 'Balance inicial' }) {
  return <div className="space-y-5">
    <Input name="nombre" label="Nombre de la cuenta" value={form.nombre} onChange={onChange} error={errors.nombre} maxLength={255} required disabled={disabled} />
    <Input type="select" name="tipo_cuenta_id" label="Tipo de cuenta" value={form.tipo_cuenta_id} onChange={onChange} error={errors.tipo_cuenta_id} options={tipos.map((tipo) => ({ value: tipo.id, label: tipo.tipo }))} required disabled={disabled} />
    <Input type="number" name="balance" label={balanceLabel} value={form.balance} onChange={onChange} error={errors.balance} min="-9999999999.99" max="9999999999.99" step="0.01" required disabled={disabled} />
    {tipos.find((tipo) => String(tipo.id) === String(form.tipo_cuenta_id))?.tipo.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().includes('credito') && <Input type="number" name="limite_credito" label="Límite de crédito" value={form.limite_credito ?? ''} onChange={onChange} error={errors.limite_credito} min="0" max="9999999999.99" step="0.01" required disabled={disabled} />}
    <Input type="color" name="color" label="Color de la cuenta" value={form.color} onChange={onChange} error={errors.color} required disabled={disabled} />
  </div>;
}

export default CuentaForm;

import { accountColor, creditStatus, formatAccountDate, formatBalance } from "../helpers/cuentasApi";

function CuentaInfo({ cuenta, tipo }) {
  const fields = [['Nombre', cuenta.nombre], ['Tipo', tipo], ['Balance', formatBalance(cuenta.balance)], ['Fecha de creación', formatAccountDate(cuenta.created_at)], ['Última actualización', formatAccountDate(cuenta.updated_at)]];
  const status = creditStatus(cuenta.balance, tipo);
  if (status) fields.splice(3, 0, ['Estado del crédito', status]);
  if (cuenta.limite_credito != null) fields.splice(3, 0, ['Límite de crédito', formatBalance(cuenta.limite_credito)], ['Crédito disponible', formatBalance(Number(cuenta.limite_credito) + Number(cuenta.balance))]);
  return <div>
    <div className="mb-5 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"><span className="size-4 rounded-full border border-gray-300 dark:border-gray-600" style={{ backgroundColor: accountColor(cuenta.color) }} />Color {accountColor(cuenta.color).toUpperCase()}</div>
    <dl className="space-y-4">{fields.map(([label, value]) => <div key={label} className="flex flex-wrap justify-between gap-2"><dt className="text-gray-500 dark:text-gray-400">{label}</dt><dd className="break-words font-medium">{value}</dd></div>)}</dl>
  </div>;
}

export default CuentaInfo;

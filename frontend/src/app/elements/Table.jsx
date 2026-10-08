import { useId } from "react";

function Table({ title, description, columns, data = [], rowKey = 'id', actions, loading = false, emptyMessage = 'No hay registros.' }) {
  const titleId = useId();
  return (
    <div className="overflow-hidden rounded-xl border border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800">
      <header className="flex flex-wrap items-center justify-between gap-4 border-b border-gray-300 p-5 dark:border-gray-600">
        <div><h2 id={titleId} className="text-lg font-semibold">{title}</h2>{description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>}</div>
        {actions}
      </header>
      <div className="overflow-x-auto">
        <table aria-labelledby={titleId} aria-busy={loading} className="w-full text-left text-sm">
          <thead className="bg-gray-200/60 text-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
            <tr>{columns.map((column) => <th key={column.key} scope="col" className={`whitespace-nowrap px-5 py-3 font-medium ${column.align === 'right' ? 'text-right' : ''}`}>{column.label}</th>)}</tr>
          </thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
            {loading ? <tr><td colSpan={columns.length} className="p-10 text-center text-gray-500 dark:text-gray-400" role="status">Cargando…</td></tr> : data.length === 0 ? <tr><td colSpan={columns.length} className="p-10 text-center text-gray-500 dark:text-gray-400">{emptyMessage}</td></tr> : data.map((row) => <tr key={typeof rowKey === 'function' ? rowKey(row) : row[rowKey]} className="hover:bg-gray-100 dark:hover:bg-gray-700/40">{columns.map((column) => <td key={column.key} className={`px-5 py-4 ${column.align === 'right' ? 'text-right' : ''}`}>{column.render ? column.render(row[column.key], row) : (row[column.key] ?? '—')}</td>)}</tr>)}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default Table;

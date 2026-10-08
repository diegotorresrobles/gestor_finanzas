import { useId } from 'react';
function Input({ type = 'text', name, label, onChange, disabled = false, error, value, options = [], id, ...props }) {
  const generatedId = useId();
  const fieldId = id ?? `${name}-${generatedId}`;
  const className = `block w-full rounded-sm bg-white p-2.5 outline-1 -outline-offset-1 outline-gray-300 transition-colors focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 disabled:cursor-not-allowed disabled:bg-gray-200 dark:bg-gray-900 dark:outline-gray-600 dark:disabled:bg-gray-700 ${type === 'color' ? 'h-12' : ''}`;
  const attributes = { ...props, name, id: fieldId, onChange, disabled, value: value ?? '', className, 'aria-invalid': Boolean(error), 'aria-describedby': error ? `${fieldId}-error` : undefined };
  return (
    <div>
      <label htmlFor={fieldId} className="mb-2 block cursor-pointer">{label}</label>
      {type === 'select' ? <select {...attributes}><option value="">Selecciona una opción</option>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select> : <input {...attributes} type={type} />}
      {error && <p id={`${fieldId}-error`} className="mt-1 text-sm text-red-600 dark:text-red-400">{error}</p>}
    </div>
  );
}

export default Input;



function Button({
  type = 'button',
  variant,
  disabled = false,
  btnRef = null,
  value = '',
  label,
  action
}) {
  const buttonTypes = {
    primary: 'bg-blue-700 hover:ring-blue-700/50 focus:ring-blue-700/50',
    secondary: 'bg-gray-600 hover:ring-gray-600/50 focus:ring-gray-600/50',
    danger: 'bg-red-700 hover:ring-red-700/50 focus:ring-red-700/50'
  }

  return (
    <button
      key={value}
      ref={btnRef}
      type={type}
      disabled={disabled}
      className={`cursor-pointer text-gray-50 px-5 py-2.5 rounded-md ring-4 ring-transparent transition-shadow outline-0 disabled:bg-gray-500 hover:disabled:ring-gray-500/50 hover:disabled:bg-gray-500 focus:disabled:bg-gray-500 ${buttonTypes[variant] ?? ''}`}
      onClick={() => action?.(value)}

    >
      {label}
    </button>
  )
}

export default Button

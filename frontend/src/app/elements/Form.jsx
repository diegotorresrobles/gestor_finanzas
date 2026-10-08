import { useNavigate } from "react-router-dom"
import Button from "./Button"
import Input from "./Input"


function Form({
  action = '',
  method = 'GET',
  onSubmit,
  classNameMod,
  title,
  inputs = [],
  submitLabel,
  actions = [],
  data = {}
}) {
  const navigate = useNavigate();

  return (
    <form action={action} method={method} onSubmit={onSubmit} className={`max-w-[95%] ${classNameMod} rounded-lg formulario-glass p-5 bg-gray-50 dark:bg-gray-800`}>
      <h2 className="text-center text-3xl font-sec">{title}</h2>
      <div className="py-10 flex flex-col gap-5">
        {inputs.map(i => (
          <Input 
            key={i.name}
            type={i.type}
            name={i.name}
            label={i.label}
            onChange={i.onChange}
            error={i.error}
            value={i.value}
            disabled={i.disabled}
          />
        ))}
      </div>
      <div className="text-right">
        <Button
          variant='primary'
          type='submit'
          label={submitLabel}
          disabled={data.isSubmit}
        />
      </div>
      <div className="mt-5">
        {actions.map(a => (
          <button 
            key={a.url}
            onClick={() => {navigate(a.url)}}
            className="dark:text-gray-500 block mb-2.5 last:mb-0 cursor-pointer"
            type="button"
          >
            {a.label}
          </button>
        ))}
      </div>
    </form>
  )
}

export default Form
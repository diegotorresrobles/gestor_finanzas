import { useState } from "react"
import { useAuth } from '../helpers/AuthContext';
import { apiRequest } from '../helpers/cuentasApi';
import { useNavigate, useLocation } from "react-router-dom";
import Form from "../elements/Form";


function Login() {
  const [form, setForm] = useState({
    correo: '',
    password: ''
  });
  const [errors, setErrors] = useState({});
  const [isSubmit, setIsSubmit] = useState(false);
  const { setUser } = useAuth();

  const navigate = useNavigate();
  const location = useLocation();

  const handleInputChange = (e) => {
    const {name, value} = e.target
    setForm(prev => ({
      ...prev,
      [name]: value
    }));
    setErrors(prev => ({
      ...prev,
      [name]: undefined
    }));
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmit(true);

    try {
      const data = await apiRequest('/api/auth/login', { method: 'POST', body: form });
      setForm({});
      e.target.reset();
      setErrors({});
      setUser(data.user);
      navigate(data.user.rol === 'admin' ? '/admin' : '/');
    } catch (error) {
      setErrors(error.errors && Object.keys(error.errors).length ? error.errors : { correo: error.message });
      setForm((previous) => ({ ...previous, password: '' }));
    } finally {
      setIsSubmit(false);
    }
  }

  return (
    <>
      <div className="w-full min-h-dvh flex flex-col items-center justify-center gap-5 p-5">
        {location.state?.message && <p role="status" className="max-w-sm text-center text-green-700 dark:text-green-400">{location.state.message}</p>}
        <Form
          title='Inicia Sesión'
          classNameMod='w-sm'
          onSubmit={handleSubmit}
          method='POST'
          inputs={[
            {
              name: 'correo', 
              type: 'email', 
              label: 'Correo', 
              onChange: handleInputChange, 
              value: form.correo,
              error: errors.correo
            },
            {
              name: 'password', 
              type: 'password', 
              label: 'Contraseña', 
              onChange: handleInputChange, 
              value: form.password,
              error: errors.password
            }
          ]}
          submitLabel='Inicia Sesión'
          actions={[
            {
              label: '¿No tienes cuenta? Crea una',
              url: '/register'
            },
            {
              label: 'Inicio',
              url: '/'
            }
          ]}
          data={{
            isSubmit: isSubmit
          }}
        />
      </div>
    </>
  )
}

export default Login

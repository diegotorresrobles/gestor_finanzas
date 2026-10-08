import { useState } from "react"
import Modal from "../elements/Modal";
import { useNavigate } from "react-router-dom";
import Form from "../elements/Form";
import { apiRequest } from '../helpers/cuentasApi';


function Register() {
  const [form, setForm] = useState({
    nombre: '',
    apellido: '',
    correo: '',
    password: ''
  });
  const [errors, setErrors] = useState({});
  const [isSubmit, setIsSubmit] = useState(false);
  const [openModal, setOpenModal] = useState();

  const navigate = useNavigate();

  const handleInputChange = (e) => {
    const {name, value} = e.target
    setForm(prev => ({
      ...prev,
      [name]: value
    }));
    setErrors(prev => ({
      ...prev,
      [name]: undefined
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmit(true);

    try {
      await apiRequest('/api/users', { method: 'POST', body: form, retry: false });
      setForm({ nombre: '', apellido: '', correo: '', password: '' });
      e.target.reset();
      setErrors({});
      setOpenModal(true);
    } catch (error) {
      setErrors(error.errors && Object.keys(error.errors).length ? error.errors : { correo: error.message });
    } finally {
      setIsSubmit(false);
    }
  }
  const handleCloseModal = (value) => {
    setOpenModal(false);
    if (value === true) {
      navigate("/login");
    }
  }

  return (
    <>
      {openModal && (
        <Modal
          isOpen={openModal}
          type='success'
          title='Cuenta creada'
          actions={[
            {label: 'Ok', value: true, variant: 'primary'}
          ]}
          onClose={handleCloseModal}
        >
          Se han enviado las instrucciones para activar tu cuenta a tu correo
        </Modal>
      )}
      <div className="w-full max-h-dvh h-dvh flex items-center justify-center">
        <Form
          title='Registrarse'
          classNameMod='w-sm'
          onSubmit={handleSubmit}
          method='POST'
          inputs={[
            {
              name: 'nombre', 
              label: 'Nombre', 
              onChange: handleInputChange, 
              value: form.nombre,
              error: errors.nombre
            },
            {
              name: 'apellido', 
              label: 'Apellido(s)', 
              onChange: handleInputChange, 
              value: form.apellido,
              error: errors.apellido
            },
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
          submitLabel='Registrarse'
          actions={[
            {
              label: '¿Ya tienes cuenta? Inicia sesión',
              url: '/login'
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

export default Register

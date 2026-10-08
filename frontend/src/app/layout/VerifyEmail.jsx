import { useNavigate, useParams } from "react-router-dom"
import Form from "../elements/Form"
import { useState } from "react";
import Modal from "../elements/Modal";
import { apiRequest } from '../helpers/cuentasApi';


function VerifyEmail() {
  const { email, token } = useParams();
  const [isSubmit, setIsSubmit] = useState(false);
  const [openModal, setOpenModal] = useState(false);
  const [errors, setErrors] = useState({});
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmit(true);

    try {
      await apiRequest('/api/users/verify', { method: 'POST', body: { correo: email, token }, retry: false });
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
    } else {
      navigate("/");
    }
  }
  return (
    <>
      {openModal && (
        <Modal
          isOpen={openModal}
          type='success'
          title='Correo verificado'
          actions={[
            {label: 'Ok', value: false, variant: 'secondary'},
            {label: 'Iniciar sesión', value: true, variant: 'primary'}
          ]}
          onClose={handleCloseModal}
        >
          Ya puedes usar tu cuenta
        </Modal>
      )}
      <div className="w-full max-h-dvh h-dvh flex items-center justify-center">
        <Form 
          method="POST"
          title='Verificar correo'
          submitLabel='Verificar correo'
          onSubmit={handleSubmit}
          classNameMod='w-sm'
          inputs={[
            {type: 'email', name: 'correo', label: 'Correo', value: email, disabled: true, error: errors.correo},
            {name: 'token', label: 'Token', value: token, disabled: true, error: errors.token}
          ]}
          actions={[
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

export default VerifyEmail

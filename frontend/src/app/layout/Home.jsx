import { NavLink } from "react-router-dom"
import Button from "../elements/Button"


function Home() {
  return (
    <div className="w-full max-h-dvh h-dvh items-center justify-center flex gap-5">
      <NavLink to="/login" end>
        <Button
          key=''
          variant='primary'
          label='Iniciar Sesión'
        />
      </NavLink>
      <NavLink to="/register" end>
        <Button
          key=''
          variant='primary'
          label='Registrate'
        />
      </NavLink>
    </div>
  )
}

export default Home
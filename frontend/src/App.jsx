import { BrowserRouter, Routes, Route } from "react-router-dom"

import './styles/style.css'
import Login from "./app/layout/Login";
import Logout from "./app/layout/Logout";
import Register from "./app/layout/Register";
import VerifyEmail from "./app/layout/VerifyEmail";
import Dashboard from "./app/layout/dashboard/Dashboard";
import DashboardIndex from "./app/layout/dashboard/DashboardIndex";
import Cuentas from "./app/layout/dashboard/Cuentas";
import CuentaDetalle from "./app/layout/dashboard/CuentaDetalle";
import Transacciones from "./app/layout/dashboard/Transacciones";
import TransaccionDetalle from "./app/layout/dashboard/TransaccionDetalle";
import RouteAuthProtection from "./app/helpers/RouteAuthProtection";
import RouteProtection from "./app/helpers/RouteProtection";
import { AuthProvider } from './app/helpers/AuthContext';
import Account from './app/layout/dashboard/Account';
import Admin from './app/layout/dashboard/Admin';
import ConfirmEmailChange from './app/layout/ConfirmEmailChange';

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
      <Routes>
        <Route path="/account/email/confirm" element={<ConfirmEmailChange />} />
        <Route path="/logout" element={<Logout />} />
        <Route element={<RouteAuthProtection />} >
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/verify-email/t/:token/e/:email" element={<VerifyEmail />} />
        </Route>
        <Route element={<RouteProtection />} >
          <Route element={<Dashboard />} >
            <Route path='/account' element={<Account />} />
          </Route>
        </Route>
        <Route element={<RouteProtection role="admin" />}>
          <Route element={<Dashboard />}><Route path='/admin' element={<Admin />} /></Route>
        </Route>
        <Route element={<RouteProtection role="user" />} >
          <Route element={<Dashboard />} >
            <Route path='/' element={<DashboardIndex />} />
            <Route path='/cuentas' element={<Cuentas />} />
            <Route path='/cuentas/:id' element={<CuentaDetalle />} />
            <Route path='/transacciones' element={<Transacciones />} />
            <Route path='/transacciones/:id' element={<TransaccionDetalle />} />
          </Route>
        </Route>
      </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App

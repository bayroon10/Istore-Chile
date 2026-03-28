import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'

import Tienda from './pages/Tienda'
import Login from './pages/login' 
import Inventario from './pages/Inventario'
import Dashboard from './pages/Dashboard'
import Pedidos from './pages/Pedidos'
import AdminLayout from './layouts/AdminLayout'
import MiCuenta from './pages/MiCuenta';

// ==========================================
// 🛡️ EL NUEVO GUARDIA DE SEGURIDAD REAL
// ==========================================
// ==========================================
// 🛡️ EL GUARDIA DE SEGURIDAD (SOLO ADMINS)
// ==========================================
function RutaProtegida({ children }) {
  const token = localStorage.getItem('token_istore');
  const rol = localStorage.getItem('rol_istore'); // El guardia busca el Gafete VIP

  // Si tiene token Y ADEMÁS su rol es 'admin', lo dejamos pasar al panel.
  if (token && rol === 'admin') {
    return children; 
  } 
  
  // Si no es admin (o no está logueado), lo mandamos a su cuenta normal o al login.
  return <Navigate to="/mi-cuenta" />; 
}

export default function App() {
  return (
    <Router>
      <Routes>
        {/* RUTAS PÚBLICAS */}
        <Route path="/" element={<Tienda />} />
        <Route path="/tienda" element={<Tienda />} />  {/* Agregamos esta por si acaso */}  
        <Route path="/mi-cuenta" element={<MiCuenta />} />  
<Route path="/acceso-secreto-bairon" element={<Login />} />        
        {/* RUTAS PRIVADAS (ADMIN) */}
        <Route path="/admin" element={
          <RutaProtegida>
            <AdminLayout />
          </RutaProtegida>
        }>
          <Route index element={<Dashboard />} />
          <Route path="inventario" element={<Inventario />} />
          <Route path="pedidos" element={<Pedidos />} />
        </Route>
      </Routes>
    </Router>
  )
}
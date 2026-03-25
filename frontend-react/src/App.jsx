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
function RutaProtegida({ children }) {
  const token = localStorage.getItem('token_istore');
  // Si tiene el token de Sanctum, lo dejamos pasar. Si no, lo mandamos al Login.
  if (token) {
    return children; 
  } 
  return <Navigate to="/login" />; 
}

export default function App() {
  return (
    <Router>
      <Routes>
        {/* RUTAS PÚBLICAS */}
        <Route path="/" element={<Tienda />} />
        <Route path="/tienda" element={<Tienda />} />  {/* Agregamos esta por si acaso */}  
        <Route path="/mi-cuenta" element={<MiCuenta />} />  
        <Route path="/login" element={<Login />} /> {/* 🌟 RUTA DEL LOGIN */}
        
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
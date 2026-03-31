import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { useAuth } from '../contexts/AuthContext';
import { useCart } from '../contexts/CartContext';
import api from '../lib/api';

export default function MiCuenta() {
  const navigate = useNavigate();
  const { user, isAuthenticated, login, register, logout, loading: authLoading } = useAuth();
  const { refreshCart } = useCart();
  
  const [historial, setHistorial] = useState([]);
  const [modo, setModo] = useState('login');
  const [formulario, setFormulario] = useState({ name: '', email: '', password: '' });
  const [loadingOrders, setLoadingOrders] = useState(false);

  // Cargar historial de compras cuando hay usuario
  useEffect(() => {
    if (isAuthenticated) {
      setLoadingOrders(true);
      api.get('/orders')
        .then(data => {
          setHistorial(data.data || []);
        })
        .catch(err => {
          console.error('Error cargando historial:', err);
          setHistorial([]);
        })
        .finally(() => setLoadingOrders(false));
    }
  }, [isAuthenticated]);

  const manejarSubmit = async (e) => {
    e.preventDefault();
    
    try {
      if (modo === 'login') {
        await login(formulario.email, formulario.password);
      } else {
        await register(formulario.name, formulario.email, formulario.password);
      }
      
      Swal.fire({ 
        title: '¡Bienvenido!', 
        text: `Hola ${formulario.name || formulario.email}`, 
        icon: 'success', 
        timer: 1500, 
        showConfirmButton: false 
      });
      
      // Sincronizar carrito post-login
      refreshCart();
    } catch (err) {
      Swal.fire('Error', err.message || 'Revisa tus datos e intenta de nuevo.', 'error');
    }
  };

  const cerrarSesion = () => {
    logout();
    setHistorial([]);
  };

  // Colores de estado
  const statusColors = {
    pending: { bg: '#fff3cd', text: '#856404' },
    paid: { bg: '#d1ecf1', text: '#0c5460' },
    processing: { bg: '#fff3cd', text: '#856404' },
    shipped: { bg: '#d4edda', text: '#155724' },
    delivered: { bg: '#34c759', text: 'white' },
    cancelled: { bg: '#f8d7da', text: '#721c24' },
  };

  // =========================================================
  // VISTA 1: PANEL DEL CLIENTE (autenticado)
  // =========================================================
  if (isAuthenticated && user) {
    return (
      <div style={{ padding: '40px', maxWidth: '1000px', margin: '0 auto', minHeight: '100vh', fontFamily: 'system-ui' }}>
        
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '40px', background: 'white', padding: '30px', borderRadius: '24px', boxShadow: '0 10px 30px rgba(0,0,0,0.05)' }}>
          <div>
            <h1 style={{ margin: '0 0 5px 0', fontSize: '32px', color: '#1d1d1f' }}>Hola, {user.name} 👋</h1>
            <p style={{ margin: 0, color: '#86868b' }}>{user.email}</p>
          </div>
          <div style={{ display: 'flex', gap: '15px' }}>
            <button onClick={() => navigate('/tienda')} style={{ background: '#f5f5f7', color: '#1d1d1f', border: 'none', padding: '12px 20px', borderRadius: '12px', fontWeight: 'bold', cursor: 'pointer' }}>Ir a la Tienda</button>
            <button onClick={cerrarSesion} style={{ background: '#ff3b30', color: 'white', border: 'none', padding: '12px 20px', borderRadius: '12px', fontWeight: 'bold', cursor: 'pointer' }}>Cerrar Sesión</button>
          </div>
        </div>

        {/* Historial */}
        <h2 style={{ fontSize: '24px', color: '#1d1d1f', marginBottom: '20px' }}>📦 Historial de Compras ({historial.length})</h2>
        
        {loadingOrders ? (
          <div style={{ textAlign: 'center', padding: '60px', background: 'white', borderRadius: '24px', color: '#86868b' }}>
            Cargando tus compras...
          </div>
        ) : historial.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '60px', background: 'white', borderRadius: '24px', color: '#86868b' }}>
            Aún no has realizado ninguna compra con esta cuenta.
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            {historial.map(order => {
              const colors = statusColors[order.status] || statusColors.pending;
              return (
                <div key={order.id} style={{ background: 'white', padding: '25px', borderRadius: '20px', boxShadow: '0 5px 15px rgba(0,0,0,0.03)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
                    <div>
                      <p style={{ margin: '0 0 5px 0', fontWeight: 'bold', color: '#1d1d1f', fontSize: '18px' }}>
                        Pedido {order.order_number}
                      </p>
                      <p style={{ margin: 0, color: '#86868b', fontSize: '14px' }}>
                        {new Date(order.created_at).toLocaleDateString('es-CL', { year: 'numeric', month: 'long', day: 'numeric' })}
                      </p>
                      <p style={{ margin: '5px 0 0 0', color: '#86868b', fontSize: '14px' }}>
                        Envío: {order.shipping?.method}
                      </p>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <h3 style={{ margin: '0 0 5px 0', color: '#1d1d1f', fontSize: '24px' }}>
                        ${order.total?.toLocaleString()}
                      </h3>
                      <span style={{ 
                        display: 'inline-block', 
                        background: colors.bg, 
                        color: colors.text, 
                        padding: '5px 12px', 
                        borderRadius: '20px', 
                        fontSize: '12px', 
                        fontWeight: 'bold' 
                      }}>
                        {order.status_label}
                      </span>
                    </div>
                  </div>

                  {/* Items de la orden */}
                  {order.items && order.items.length > 0 && (
                    <div style={{ borderTop: '1px solid #f0f0f0', paddingTop: '15px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                      {order.items.map(item => (
                        <div key={item.id} style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                          {item.product_image && (
                            <img src={item.product_image} alt={item.product_name} style={{ width: '40px', height: '40px', objectFit: 'contain', borderRadius: '8px', background: '#f5f5f7' }} />
                          )}
                          <span style={{ flex: 1, fontSize: '14px', color: '#1d1d1f' }}>{item.product_name}</span>
                          <span style={{ fontSize: '13px', color: '#86868b' }}>x{item.quantity}</span>
                          <span style={{ fontWeight: 'bold', fontSize: '14px', color: '#1d1d1f' }}>${item.subtotal?.toLocaleString()}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>
    );
  }

  // =========================================================
  // VISTA 2: FORMULARIO DE LOGIN / REGISTRO
  // =========================================================
  return (
    <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh', background: '#f5f5f7', fontFamily: 'system-ui' }}>
      <div style={{ background: 'white', padding: '40px', borderRadius: '30px', width: '100%', maxWidth: '400px', boxShadow: '0 25px 50px rgba(0,0,0,0.1)' }}>
        
        <div style={{ textAlign: 'center', marginBottom: '30px' }}>
          <h1 style={{ margin: 0, fontSize: '28px', color: '#1d1d1f' }}> iStore ID</h1>
          <p style={{ color: '#86868b', marginTop: '5px' }}>{modo === 'login' ? 'Inicia sesión para ver tus compras.' : 'Crea tu cuenta para comprar más rápido.'}</p>
        </div>

        <div style={{ display: 'flex', background: '#f5f5f7', borderRadius: '14px', padding: '5px', marginBottom: '25px' }}>
          <button onClick={() => setModo('login')} style={{ flex: 1, padding: '10px', border: 'none', background: modo === 'login' ? 'white' : 'transparent', borderRadius: '10px', fontWeight: 'bold', cursor: 'pointer', boxShadow: modo === 'login' ? '0 2px 5px rgba(0,0,0,0.1)' : 'none', color: '#1d1d1f' }}>Ingresar</button>
          <button onClick={() => setModo('registro')} style={{ flex: 1, padding: '10px', border: 'none', background: modo === 'registro' ? 'white' : 'transparent', borderRadius: '10px', fontWeight: 'bold', cursor: 'pointer', boxShadow: modo === 'registro' ? '0 2px 5px rgba(0,0,0,0.1)' : 'none', color: '#1d1d1f' }}>Registrarme</button>
        </div>

        <form onSubmit={manejarSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
          {modo === 'registro' && (
            <input type="text" placeholder="Nombre completo" required value={formulario.name} onChange={e => setFormulario({...formulario, name: e.target.value})} style={{ padding: '16px', borderRadius: '14px', border: '1px solid #e5e5ea', background: 'white', fontSize: '15px', outline: 'none' }} />
          )}
          <input type="email" placeholder="Correo electrónico" required value={formulario.email} onChange={e => setFormulario({...formulario, email: e.target.value})} style={{ padding: '16px', borderRadius: '14px', border: '1px solid #e5e5ea', background: 'white', fontSize: '15px', outline: 'none' }} />
          <input type="password" placeholder="Contraseña" required minLength="6" value={formulario.password} onChange={e => setFormulario({...formulario, password: e.target.value})} style={{ padding: '16px', borderRadius: '14px', border: '1px solid #e5e5ea', background: 'white', fontSize: '15px', outline: 'none' }} />
          
          <button type="submit" style={{ background: '#0071e3', color: 'white', border: 'none', padding: '16px', borderRadius: '14px', fontWeight: 'bold', cursor: 'pointer', marginTop: '10px', fontSize: '16px', boxShadow: '0 4px 15px rgba(0,113,227,0.3)' }}>
            {modo === 'login' ? 'Iniciar Sesión' : 'Crear Cuenta'}
          </button>
        </form>
        
        <div style={{ textAlign: 'center', marginTop: '20px' }}>
          <button onClick={() => navigate('/tienda')} style={{ background: 'transparent', border: 'none', color: '#0071e3', cursor: 'pointer', fontWeight: 'bold' }}>← Volver a la Tienda</button>
        </div>
      </div>
    </div>
  );
}
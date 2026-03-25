import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';

export default function MiCuenta() {
  const navigate = useNavigate();
  // Diferenciamos el token del cliente del token del administrador
  const [token, setToken] = useState(localStorage.getItem('cliente_token'));
  const [usuario, setUsuario] = useState(null);
  const [historial, setHistorial] = useState([]);
  
  const [modo, setModo] = useState('login'); // 'login' o 'registro'
  const [formulario, setFormulario] = useState({ name: '', email: '', password: '' });

  // Si hay token, cargamos el perfil y las compras automáticamente
  useEffect(() => {
    if (token) {
      fetch('http://127.0.0.1:8000/api/cliente/perfil', {
        headers: { 'Authorization': `Bearer ${token}` }
      })
      .then(res => res.json())
      .then(data => {
        if(data.user) {
          setUsuario(data.user);
          setHistorial(data.historial_compras || []);
        } else {
          cerrarSesion();
        }
      })
      .catch(() => cerrarSesion());
    }
  }, [token]);

  const manejarSubmit = async (e) => {
    e.preventDefault();
    const endpoint = modo === 'login' ? 'login' : 'registro';
    
    try {
      const response = await fetch(`http://127.0.0.1:8000/api/cliente/${endpoint}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formulario)
      });
      
      const data = await response.json();

      if (response.ok) {
        localStorage.setItem('cliente_token', data.token);
        setToken(data.token);
        Swal.fire({ title: '¡Bienvenido!', text: `Hola ${data.user.name}`, icon: 'success', timer: 1500, showConfirmButton: false });
      } else {
        Swal.fire('Error', data.error || 'Revisa tus datos e intenta de nuevo.', 'error');
      }
    } catch (error) {
      Swal.fire('Error', 'No se pudo conectar al servidor.', 'error');
    }
  };

  const cerrarSesion = () => {
    localStorage.removeItem('cliente_token');
    setToken(null);
    setUsuario(null);
    setHistorial([]);
  };

  // =========================================================
  // VISTA 1: SI EL CLIENTE YA INICIÓ SESIÓN (PANEL VIP)
  // =========================================================
  if (token && usuario) {
    return (
      <div style={{ padding: '40px', maxWidth: '1000px', margin: '0 auto', minHeight: '100vh', fontFamily: 'system-ui' }}>
        
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '40px', background: 'white', padding: '30px', borderRadius: '24px', boxShadow: '0 10px 30px rgba(0,0,0,0.05)' }}>
          <div>
            <h1 style={{ margin: '0 0 5px 0', fontSize: '32px', color: '#1d1d1f' }}>Hola, {usuario.name} 👋</h1>
            <p style={{ margin: 0, color: '#86868b' }}>{usuario.email}</p>
          </div>
          <div style={{ display: 'flex', gap: '15px' }}>
            <button onClick={() => navigate('/tienda')} style={{ background: '#f5f5f7', color: '#1d1d1f', border: 'none', padding: '12px 20px', borderRadius: '12px', fontWeight: 'bold', cursor: 'pointer' }}>Ir a la Tienda</button>
            <button onClick={cerrarSesion} style={{ background: '#ff3b30', color: 'white', border: 'none', padding: '12px 20px', borderRadius: '12px', fontWeight: 'bold', cursor: 'pointer' }}>Cerrar Sesión</button>
          </div>
        </div>

        <h2 style={{ fontSize: '24px', color: '#1d1d1f', marginBottom: '20px' }}>📦 Historial de Compras ({historial.length})</h2>
        
        {historial.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '60px', background: 'white', borderRadius: '24px', color: '#86868b' }}>
            Aún no has realizado ninguna compra con esta cuenta.
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            {historial.map(pedido => (
              <div key={pedido.id} style={{ background: 'white', padding: '25px', borderRadius: '20px', boxShadow: '0 5px 15px rgba(0,0,0,0.03)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <p style={{ margin: '0 0 5px 0', fontWeight: 'bold', color: '#1d1d1f', fontSize: '18px' }}>Pedido #{pedido.id}</p>
                  <p style={{ margin: 0, color: '#86868b', fontSize: '14px' }}>Fecha: {new Date(pedido.created_at).toLocaleDateString()}</p>
                  <p style={{ margin: '5px 0 0 0', color: '#86868b', fontSize: '14px' }}>Envío: {pedido.metodo_envio}</p>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <h3 style={{ margin: '0 0 5px 0', color: '#1d1d1f', fontSize: '24px' }}>${parseInt(pedido.total).toLocaleString()}</h3>
                  <span style={{ display: 'inline-block', background: pedido.estado === 'Pendiente' ? '#fff500' : '#34c759', color: pedido.estado === 'Pendiente' ? '#1d1d1f' : 'white', padding: '5px 12px', borderRadius: '20px', fontSize: '12px', fontWeight: 'bold' }}>
                    {pedido.estado || 'Procesando'}
                  </span>
                </div>
              </div>
            ))}
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
          <h1 style={{ margin: 0, fontSize: '28px', color: '#1d1d1f' }}> iStore ID</h1>
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
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
    const cargarHistorial = async () => {
      setLoadingOrders(true);
      try {
        const data = await api.get('/orders');
        setHistorial(data.data || []);
      } catch (err) {
        setHistorial([]);
      } finally {
        setLoadingOrders(false);
      }
    };

    if (isAuthenticated) {
      cargarHistorial();
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
        text: `Acceso concedido para ${formulario.name || formulario.email}`, 
        icon: 'success', 
        timer: 1500, 
        showConfirmButton: false,
        background: '#000',
        color: '#fff'
      });
      
      refreshCart();
    } catch (err) {
      Swal.fire({
        title: 'Error',
        text: err.message || 'Revisa tus datos e intenta de nuevo.',
        icon: 'error',
        background: '#000',
        color: '#fff'
      });
    }
  };

  const cerrarSesion = () => {
    logout();
    setHistorial([]);
  };

  // Colores de estado (Modernizados para Dark Theme)
  const statusColors = {
    pending: { bg: 'bg-yellow-500/20', text: 'text-yellow-500', border: 'border-yellow-500/30' },
    paid: { bg: 'bg-urban-blue/20', text: 'text-urban-blue', border: 'border-urban-blue/30' },
    processing: { bg: 'bg-orange-500/20', text: 'text-orange-500', border: 'border-orange-500/30' },
    shipped: { bg: 'bg-purple-500/20', text: 'text-purple-500', border: 'border-purple-500/30' },
    delivered: { bg: 'bg-green-500/20', text: 'text-green-500', border: 'border-green-500/30' },
    cancelled: { bg: 'bg-red-500/20', text: 'text-red-500', border: 'border-red-500/30' },
  };

  // =========================================================
  // VISTA 1: PANEL DEL CLIENTE (autenticado)
  // =========================================================
  if (isAuthenticated && user) {
    return (
      <div className="min-h-screen bg-pitch-black p-6 md:p-12 font-sans relative overflow-hidden">
        
        {/* Glows de fondo */}
        <div className="absolute top-0 left-0 w-full h-full pointer-events-none">
            <div className="absolute top-[-10%] left-[-10%] w-[600px] h-[600px] bg-urban-blue/10 rounded-full blur-[150px]"></div>
            <div className="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-urban-blue/5 rounded-full blur-[120px]"></div>
        </div>

        <div className="max-w-5xl mx-auto relative z-10">
          
          {/* Header Dashboard */}
          <div className="glass-dark p-8 md:p-12 rounded-[32px] border border-white/10 flex flex-col md:flex-row justify-between items-center gap-8 mb-12 shadow-2xl">
            <div className="flex items-center gap-6 text-center md:text-left">
              <div className="w-20 h-20 rounded-2xl bg-urban-blue flex items-center justify-center text-4xl shadow-neon-blue">
                {user.name?.charAt(0).toUpperCase()}
              </div>
              <div>
                <h1 className="text-3xl md:text-4xl font-black text-white mb-2 uppercase tracking-tight">Hola, {user.name}</h1>
                <p className="text-urban-blue font-bold tracking-widest text-sm uppercase">{user.email}</p>
              </div>
            </div>
            
            <div className="flex flex-wrap justify-center gap-4">
              <button 
                onClick={() => navigate('/tienda')} 
                className="px-6 py-3 rounded-xl bg-white/5 border border-white/10 text-white font-bold hover:bg-white/10 transition-all">
                Ir a la Tienda
              </button>
              <button 
                onClick={cerrarSesion} 
                className="px-6 py-3 rounded-xl bg-red-500/20 border border-red-500/50 text-red-500 font-bold hover:bg-red-500/30 transition-all">
                Cerrar Sesión
              </button>
            </div>
          </div>

          {/* Historial Section */}
          <div className="mb-8 flex items-center justify-between">
            <h2 className="text-2xl font-black text-white uppercase tracking-tighter flex items-center gap-3">
               <span className="text-urban-blue">📦</span> Mis Pedidos
               <span className="bg-urban-blue/10 text-urban-blue text-xs px-3 py-1 rounded-full border border-urban-blue/20 ml-2">
                 {historial.length}
               </span>
            </h2>
          </div>
          
          {loadingOrders ? (
            <div className="glass-dark rounded-[24px] border border-white/5 p-20 text-center text-gray-500 animate-pulse text-xl font-bold uppercase tracking-widest">
              Analizando historial...
            </div>
          ) : historial.length === 0 ? (
            <div className="glass-dark rounded-[24px] border border-white/5 p-20 text-center relative overflow-hidden">
                <div className="text-6xl mb-6 opacity-30 grayscale">🛒</div>
                <p className="text-gray-400 text-lg font-medium mb-8">Tu inventario personal está vacío.</p>
                <button 
                  onClick={() => navigate('/tienda')}
                  className="bg-urban-blue text-white px-10 py-4 rounded-2xl font-black shadow-neon-blue hover:scale-105 transition-all">
                  INICIAR COMPRAS ➔
                </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-6">
              {historial.map(order => {
                const colors = statusColors[order.status] || statusColors.pending;
                return (
                  <div key={order.id} className="glass-dark rounded-[24px] border border-white/10 p-6 md:p-8 hover:border-urban-blue/30 transition-all duration-500 group shadow-lg">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
                      <div>
                        <div className="flex items-center gap-3 mb-2">
                          <span className={`px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border ${colors.bg} ${colors.text} ${colors.border}`}>
                            {order.status_label}
                          </span>
                          <span className="text-gray-500 text-xs font-bold font-mono">#{order.order_number}</span>
                        </div>
                        <p className="text-gray-400 text-sm font-medium">
                          {new Date(order.created_at).toLocaleDateString('es-CL', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                        </p>
                      </div>
                      
                      <div className="text-left md:text-right">
                        <p className="text-3xl font-black text-white tracking-tighter group-hover:text-urban-blue transition-colors">
                            ${order.total?.toLocaleString()}
                        </p>
                        <p className="text-gray-500 text-xs font-bold uppercase tracking-widest mt-1">
                            Envío: {order.shipping?.method || 'N/A'}
                        </p>
                      </div>
                    </div>

                    {/* Detalle de Productos */}
                    <div className="space-y-4 pt-6 border-t border-white/5">
                      {order.items?.map(item => (
                        <div key={item.id} className="flex items-center gap-4 bg-white/5 p-3 rounded-2xl border border-transparent hover:border-white/10 transition-all">
                          <div className="w-14 h-14 bg-black rounded-xl p-2 flex items-center justify-center border border-white/5">
                            {item.product_image ? (
                              <img src={item.product_image} alt={item.product_name} className="w-full h-full object-contain" />
                            ) : (
                              <span className="text-xl"></span>
                            )}
                          </div>
                          <div className="flex-1">
                            <h4 className="text-white text-sm font-bold truncate max-w-[200px] md:max-w-md">{item.product_name}</h4>
                            <p className="text-gray-500 text-xs font-bold">CANTIDAD: {item.quantity}</p>
                          </div>
                          <div className="text-right">
                            <p className="text-white text-sm font-black font-mono">${item.subtotal?.toLocaleString()}</p>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    );
  }

  // =========================================================
  // VISTA 2: FORMULARIO DE LOGIN / REGISTRO
  // =========================================================
  return (
    <div className="min-h-screen bg-pitch-black flex justify-center items-center p-6 relative overflow-hidden font-sans">
      
      {/* Background FX */}
      <div className="absolute top-[-20%] left-[-10%] w-[800px] h-[800px] bg-urban-blue/10 rounded-full blur-[150px] pointer-events-none"></div>
      <div className="absolute bottom-[-20%] right-[-10%] w-[600px] h-[600px] bg-urban-blue/5 rounded-full blur-[120px] pointer-events-none"></div>

      <div className="glass-dark p-8 md:p-12 rounded-[40px] w-full max-w-md shadow-2xl backdrop-blur-3xl border border-white/10 relative z-10">
        
        <div className="text-center mb-10">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-urban-blue/10 border border-urban-blue/30 mb-8 shadow-neon-blue">
            <span className="text-4xl text-urban-blue font-black"></span>
          </div>
          <h1 className="text-3xl font-black text-white tracking-tighter mb-2 uppercase">iStore <span className="text-urban-blue">ID</span></h1>
          <p className="text-gray-400 font-medium">{modo === 'login' ? 'Protocolo de acceso personal' : 'Registro de nuevo cliente'}</p>
        </div>

        {/* Switcher */}
        <div className="bg-black/50 p-1.5 rounded-2xl flex gap-2 mb-10 border border-white/5">
          <button 
            onClick={() => setModo('login')} 
            className={`flex-1 py-3 px-4 rounded-xl text-sm font-black uppercase tracking-widest transition-all ${modo === 'login' ? 'bg-urban-blue text-white shadow-neon-blue' : 'text-gray-500 hover:text-gray-300'}`}>
            Ingresar
          </button>
          <button 
            onClick={() => setModo('registro')} 
            className={`flex-1 py-3 px-4 rounded-xl text-sm font-black uppercase tracking-widest transition-all ${modo === 'registro' ? 'bg-urban-blue text-white shadow-neon-blue' : 'text-gray-500 hover:text-gray-300'}`}>
            Registrar
          </button>
        </div>

        <form onSubmit={manejarSubmit} className="space-y-5">
          {modo === 'registro' && (
            <div className="group">
              <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-2 mb-1.5 block group-focus-within:text-urban-blue transition-colors">Nombre Real</label>
              <input 
                type="text" 
                placeholder="EJ: Bairon Doe" 
                required 
                value={formulario.name} 
                onChange={e => setFormulario({...formulario, name: e.target.value})} 
                className="w-full p-4 rounded-2xl bg-black/40 border border-white/5 text-white placeholder:text-gray-700 focus:border-urban-blue/50 focus:ring-4 focus:ring-urban-blue/10 outline-none transition-all"
              />
            </div>
          )}
          
          <div className="group">
            <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-2 mb-1.5 block group-focus-within:text-urban-blue transition-colors">Identidad Electrónica</label>
            <input 
              type="email" 
              placeholder="correo@istore.cl" 
              required 
              value={formulario.email} 
              onChange={e => setFormulario({...formulario, email: e.target.value})} 
              className="w-full p-4 rounded-2xl bg-black/40 border border-white/5 text-white placeholder:text-gray-700 focus:border-urban-blue/50 focus:ring-4 focus:ring-urban-blue/10 outline-none transition-all"
            />
          </div>

          <div className="group">
            <label className="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-2 mb-1.5 block group-focus-within:text-urban-blue transition-colors">Código de Seguridad</label>
            <input 
              type="password" 
              placeholder="••••••••" 
              required 
              minLength="6" 
              value={formulario.password} 
              onChange={e => setFormulario({...formulario, password: e.target.value})} 
              className="w-full p-4 rounded-2xl bg-black/40 border border-white/5 text-white placeholder:text-gray-700 focus:border-urban-blue/50 focus:ring-4 focus:ring-urban-blue/10 outline-none transition-all"
            />
          </div>
          
          <button 
            type="submit" 
            className="w-full bg-urban-blue text-white py-4 rounded-2xl font-black text-lg hover:scale-[1.02] active:scale-95 transition-all shadow-neon-blue mt-4 uppercase tracking-widest">
            {modo === 'login' ? 'Conectar' : 'Crear Perfil'}
          </button>
        </form>
        
        <div className="mt-10 text-center">
          <button 
            onClick={() => navigate('/tienda')} 
            className="text-gray-500 hover:text-urban-blue font-bold text-xs uppercase tracking-widest transition-colors flex items-center justify-center gap-2 mx-auto">
            <span>←</span> Volver al Catálogo
          </button>
        </div>
      </div>
    </div>
  );
}
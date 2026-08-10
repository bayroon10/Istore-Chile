import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { Package } from 'lucide-react';
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

  // Colores semánticos de estado (design system Stitch)
  const statusColors = {
    pending:    'bg-amber-500/15 text-amber-400 border-amber-500/25',
    pendiente:  'bg-amber-500/15 text-amber-400 border-amber-500/25',
    paid:       'bg-emerald-500/15 text-emerald-400 border-emerald-500/25',
    pagado:     'bg-emerald-500/15 text-emerald-400 border-emerald-500/25',
    processing: 'bg-amber-500/15 text-amber-400 border-amber-500/25',
    shipped:    'bg-blue-500/15 text-blue-400 border-blue-500/25',
    enviado:    'bg-blue-500/15 text-blue-400 border-blue-500/25',
    delivered:  'bg-purple-500/15 text-purple-400 border-purple-500/25',
    entregado:  'bg-purple-500/15 text-purple-400 border-purple-500/25',
    cancelled:  'bg-red-500/15 text-red-400 border-red-500/25',
    cancelado:  'bg-red-500/15 text-red-400 border-red-500/25',
  };

  // =========================================================
  // VISTA 1: PANEL DEL CLIENTE (autenticado)
  // =========================================================
  if (isAuthenticated && user) {
    return (
      <div className="min-h-screen bg-pitch-black p-6 md:p-12 font-sans">
        <div className="max-w-4xl mx-auto space-y-8">

          {/* ── PROFILE HEADER ─────────────────────────────── */}
          <div className="glass-dark rounded-[2.5rem] p-8 relative overflow-hidden">
            {/* Blob decorativo estático */}
            <div className="absolute -top-20 -right-20 w-[300px] h-[300px] bg-blue-500/5 blur-[80px] rounded-full pointer-events-none" />

            <div className="relative flex flex-col md:flex-row justify-between items-center md:items-start gap-8">
              {/* Avatar + info */}
              <div className="flex items-center gap-6">
                <div
                  className="w-20 h-20 rounded-2xl flex items-center justify-center text-4xl font-black text-white shrink-0"
                  style={{
                    background: 'linear-gradient(135deg, #3b82f6, #22d3ee)',
                    boxShadow: '0 0 0 2px rgba(59,130,246,0.3), 0 0 0 4px #000'
                  }}
                >
                  {user.name?.charAt(0).toUpperCase()}
                </div>
                <div>
                  <h1 className="text-4xl md:text-5xl font-black text-white tracking-tighter leading-none mb-2">
                    {user.name}
                  </h1>
                  <p className="text-blue-400 text-sm uppercase tracking-[0.15em] font-bold">{user.email}</p>
                </div>
              </div>

              {/* Actions */}
              <div className="flex flex-wrap gap-3 shrink-0">
                <button
                  onClick={() => navigate('/tienda')}
                  className="px-5 py-2.5 rounded-2xl glass border border-white/10 text-white text-sm font-black uppercase tracking-widest hover:border-blue-500/30 transition-all"
                >
                  ← Tienda
                </button>
                <button
                  onClick={cerrarSesion}
                  className="px-5 py-2.5 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm font-black uppercase tracking-widest hover:bg-red-500/20 transition-all"
                >
                  Cerrar Sesión
                </button>
              </div>
            </div>
          </div>

          {/* ── MIS PEDIDOS ────────────────────────────────── */}
          <div className="flex items-center justify-between">
            <h2 className="text-2xl font-black text-white uppercase tracking-tighter flex items-center gap-3">
              Mis Pedidos
              <span className="bg-blue-500/10 text-blue-400 text-xs px-3 py-1 rounded-full border border-blue-500/20">
                {historial.length}
              </span>
            </h2>
          </div>

          {loadingOrders ? (
            <div className="glass-dark rounded-[2rem] p-16 text-center">
              <div className="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4" />
              <p className="text-gray-400 text-xs font-black uppercase tracking-widest">Cargando pedidos...</p>
            </div>
          ) : historial.length === 0 ? (
            <div className="glass-dark rounded-[2rem] p-20 text-center">
              <Package size={48} className="text-gray-700 mb-4 mx-auto" />
              <p className="text-gray-400 font-bold mb-8">Aún no realizas ningún pedido</p>
              <a
                href="/"
                className="inline-block px-8 py-4 rounded-2xl bg-blue-500 text-white font-black uppercase text-sm tracking-widest hover:shadow-neon-blue transition-all"
              >
                Ir al Catálogo →
              </a>
            </div>
          ) : (
            <div className="space-y-6">
              {historial.map(order => {
                const badge = statusColors[order.status] || statusColors.pending;
                return (
                  <div key={order.id} className="glass-dark rounded-[2rem] p-6 space-y-4 hover:border-blue-500/20 transition-all duration-300">
                    {/* Order header */}
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                      <div className="space-y-1">
                        <span className="font-mono text-xs text-gray-400">#{order.order_number}</span>
                        <div className="flex items-center gap-3">
                          <span className={`px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border ${badge}`}>
                            {order.status_label}
                          </span>
                          <span className="text-gray-400 text-xs">
                            {new Date(order.created_at).toLocaleDateString('es-CL', { year: 'numeric', month: 'short', day: 'numeric' })}
                          </span>
                        </div>
                      </div>
                      <p className="text-2xl font-black text-white tracking-tighter">
                        ${Number(order.total).toLocaleString()}
                      </p>
                    </div>

                    {/* Items */}
                    {order.items?.length > 0 && (
                      <div className="space-y-3 pt-4 border-t border-white/5">
                        {order.items.map(item => {
                          const imgSrc = item.product_image && !item.product_image.includes('via.placeholder.com')
                            ? item.product_image
                            : `https://placehold.co/40x40/0a0a0a/3b82f6?text=${encodeURIComponent((item.product_name || 'i').slice(0,2))}`;
                          const imgFallback = 'https://placehold.co/40x40/000/3b82f6?text=i';
                          return (
                            <div key={item.id} className="flex items-center gap-4">
                              <div className="w-10 h-10 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center shrink-0 overflow-hidden">
                                <img
                                  src={imgSrc}
                                  onError={e => { if (e.target.src !== imgFallback) e.target.src = imgFallback; }}
                                  alt={item.product_name}
                                  className="w-full h-full object-contain"
                                />
                              </div>
                              <div className="flex-1 min-w-0">
                                <p className="text-white text-sm font-bold truncate">{item.product_name}</p>
                                <p className="text-gray-400 text-xs">× {item.quantity}</p>
                              </div>
                              <p className="text-white text-sm font-black font-mono shrink-0">
                                ${Number(item.subtotal).toLocaleString()}
                              </p>
                            </div>
                          );
                        })}
                      </div>
                    )}
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
            className={`flex-1 py-3 px-4 rounded-xl text-sm font-black uppercase tracking-widest transition-all ${modo === 'login' ? 'bg-urban-blue text-white shadow-neon-blue' : 'text-gray-400 hover:text-gray-300'}`}>
            Ingresar
          </button>
          <button 
            onClick={() => setModo('registro')} 
            className={`flex-1 py-3 px-4 rounded-xl text-sm font-black uppercase tracking-widest transition-all ${modo === 'registro' ? 'bg-urban-blue text-white shadow-neon-blue' : 'text-gray-400 hover:text-gray-300'}`}>
            Registrar
          </button>
        </div>

        <form onSubmit={manejarSubmit} className="space-y-5">
          {modo === 'registro' && (
            <div className="group">
              <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-2 mb-1.5 block group-focus-within:text-blue-400 transition-colors">Nombre Real</label>
              <input
                type="text"
                placeholder="EJ: Bairon Doe"
                required
                value={formulario.name}
                onChange={e => setFormulario({...formulario, name: e.target.value})}
                className="w-full h-14 bg-white/5 border border-white/10 rounded-2xl px-5 text-white placeholder:text-gray-700 focus:border-blue-500/50 outline-none transition-all"
              />
            </div>
          )}

          <div className="group">
            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-2 mb-1.5 block group-focus-within:text-blue-400 transition-colors">Identidad Electrónica</label>
            <input
              type="email"
              placeholder="correo@istore.cl"
              required
              value={formulario.email}
              onChange={e => setFormulario({...formulario, email: e.target.value})}
              className="w-full h-14 bg-white/5 border border-white/10 rounded-2xl px-5 text-white placeholder:text-gray-700 focus:border-blue-500/50 outline-none transition-all"
            />
          </div>

          <div className="group">
            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-2 mb-1.5 block group-focus-within:text-blue-400 transition-colors">Código de Seguridad</label>
            <input
              type="password"
              placeholder="••••••••"
              required
              minLength="6"
              value={formulario.password}
              onChange={e => setFormulario({...formulario, password: e.target.value})}
              className="w-full h-14 bg-white/5 border border-white/10 rounded-2xl px-5 text-white placeholder:text-gray-700 focus:border-blue-500/50 outline-none transition-all"
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
            className="text-gray-400 hover:text-urban-blue font-bold text-xs uppercase tracking-widest transition-colors flex items-center justify-center gap-2 mx-auto">
            <span>←</span> Volver al Catálogo
          </button>
        </div>
      </div>
    </div>
  );
}
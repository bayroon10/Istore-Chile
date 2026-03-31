import { useState, useEffect } from 'react'
import Swal from 'sweetalert2'
import CheckoutForm from '../components/CheckoutForm';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import api from '../lib/api';
import Footer from '../components/Footer';

// 🌟 Stripe
import { loadStripe } from '@stripe/stripe-js';
import { Elements } from '@stripe/react-stripe-js';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);

export default function Tienda() {
  const [productos, setProductos] = useState([]);
  const [verCarrito, setVerCarrito] = useState(false);
  const [busqueda, setBusqueda] = useState('');
  const [categoriaActiva, setCategoriaActiva] = useState('Todas');
  const [mostrarCheckout, setMostrarCheckout] = useState(false);

  // Carrito del backend vía CartContext
  const { items, totalItems, totalPrice, addItem, updateQuantity, removeItem, refreshCart } = useCart();
  const { isAuthenticated } = useAuth();

  // Cargar productos desde la API
  useEffect(() => {
    api.get('/products')
      .then(data => {
        setProductos(data.data || []);
      })
      .catch(() => {}); // Erradicación de logs de depuración
  }, []);

  const agregarAlCarrito = async (producto) => {
    const result = await addItem(producto.id);
    if (!result.success) {
      Swal.fire({
        title: 'Error',
        text: result.error,
        icon: 'warning',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#0071e3'
      });
    } else {
      // Toast moderno
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        background: '#1d1d1f',
        color: '#fff',
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      });
      Toast.fire({
        icon: 'success',
        title: `${producto.name} añadido`
      });
    }
  };

  const categoriasUnicas = ['Todas', ...new Set(productos.map(p => p.category?.name).filter(Boolean))];
  const productosFiltrados = productos.filter(p => {
    const nombre = (p.name || '').toLowerCase();
    const categoria = p.category?.name || '';
    return nombre.includes(busqueda.toLowerCase()) && (categoriaActiva === 'Todas' || categoria === categoriaActiva);
  });

  return (
    <div className="bg-pitch-black min-h-screen text-white font-sans selection:bg-urban-blue/30 selection:text-urban-blue">

      {/* 💳 MODAL DE CHECKOUT (GLASSMORPHISM) */}
      {mostrarCheckout && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 backdrop-blur-xl bg-black/60 transition-all duration-300">
          <div className="glass-dark w-full max-w-md p-8 rounded-[2.5rem] shadow-neon-glow border border-white/10 animate-in fade-in zoom-in duration-300">
            <div className="text-center mb-8">
              <h2 className="text-3xl font-black text-white mb-2 leading-tight">Checkout Seguro</h2>
              <p className="text-gray-400 text-sm">Completa tu orden con tecnología de encriptación Stripe.</p>
            </div>

            <Elements stripe={stripePromise}>
              <CheckoutForm
                total={totalPrice}
                cerrarModal={() => setMostrarCheckout(false)}
                onSuccess={() => {
                  refreshCart();
                  setMostrarCheckout(false);
                }}
              />
            </Elements>
          </div>
        </div>
      )}

      {/* 🛍️ SIDE-CART DRAWER (PINTA TECH-WEAR) */}
      <div className={`fixed inset-y-0 right-0 z-[150] w-full max-w-sm glass-dark border-l border-white/10 shadow-[-20px_0_50px_rgba(0,0,0,0.5)] transform transition-transform duration-500 ease-out p-6 flex flex-col ${verCarrito ? 'translate-x-0' : 'translate-x-full'}`}>
        <div className="flex items-center justify-between mb-8 border-b border-white/10 pb-4">
          <h3 className="text-2xl font-black">Tu Bolsa <span className="text-urban-blue">({totalItems})</span></h3>
          <button onClick={() => setVerCarrito(false)} className="p-2 hover:bg-white/5 rounded-full transition-colors text-gray-400 hover:text-white">✕</button>
        </div>

        <div className="flex-1 overflow-y-auto pr-2 space-y-6">
          {items.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-40 text-gray-500 italic">
              <span className="text-4xl mb-4">🛒</span>
              Tu bolsa está vacía.
            </div>
          ) : (
            items.map((item) => (
              <div key={item.id} className="flex items-center gap-4 group">
                <div className="w-20 h-20 bg-space-grey rounded-2xl flex items-center justify-center border border-white/5 group-hover:border-urban-blue/30 transition-all">
                  <img src={item.product_image || "https://images.unsplash.com/photo-1606841837044-8848419615a1?q=80&w=200"} alt="" className="w-16 h-16 object-contain" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-bold text-sm truncate">{item.product_name}</p>
                  <div className="flex items-center gap-3 mt-2">
                    <button onClick={() => updateQuantity(item.product_id, item.quantity - 1)} className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all">-</button>
                    <span className="text-sm font-black">{item.quantity}</span>
                    <button onClick={() => updateQuantity(item.product_id, item.quantity + 1)} className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all">+</button>
                    <button onClick={() => removeItem(item.product_id)} className="ml-auto text-xs font-bold text-red-500/80 hover:text-red-500 transition-colors uppercase tracking-widest">Eliminar</button>
                  </div>
                </div>
                <div className="text-right font-black text-urban-blue">${item.subtotal.toLocaleString()}</div>
              </div>
            ))
          )}
        </div>

        <div className="mt-8 pt-6 border-t border-white/10">
          <div className="flex justify-between items-center mb-6 px-2">
            <span className="text-gray-400 font-bold uppercase tracking-tight">Gran Total</span>
            <span className="text-3xl font-black text-white">${totalPrice.toLocaleString()}</span>
          </div>
          <button
            disabled={items.length === 0}
            onClick={() => {
              if (!isAuthenticated) {
                Swal.fire({ 
                  title: 'Inicia Sesión', 
                  text: 'Debes estar autenticado para comprar.', 
                  icon: 'info', 
                  background: '#000',
                  color: '#fff',
                  confirmButtonColor: '#0071e3' 
                }).then(() => window.location.href = '/mi-cuenta');
                return;
              }
              setVerCarrito(false);
              setMostrarCheckout(true);
            }}
            className="w-full py-5 rounded-[1.2rem] bg-urban-blue text-white font-black text-lg shadow-neon-blue hover:shadow-neon-glow transition-all duration-300 disabled:opacity-50 disabled:grayscale"
          >
            PAGAR AHORA ➔
          </button>
        </div>
      </div>

      {/* 🚀 NAVBAR URBANO */}
      <nav className="fixed top-0 left-0 right-0 z-[100] px-10 py-6 flex items-center justify-between pointer-events-none">
        <div className="pointer-events-auto">
          {/* El logo ya está en la Navbar global de App.jsx, aquí podemos poner el indicador del carrito */}
        </div>
        <div className="pointer-events-auto flex items-center gap-4">
          <button
            onClick={() => setVerCarrito(true)}
            className="glass-dark px-6 py-3 rounded-full flex items-center gap-3 group transition-all duration-300 hover:border-urban-blue/50 hover:shadow-neon-blue"
          >
            <span className="text-xl">🛍️</span>
            <span className="font-black text-sm tracking-widest uppercase">Carrito</span>
            <span className="bg-urban-blue text-white text-[10px] w-5 h-5 flex items-center justify-center rounded-full font-black animate-pulse">{totalItems}</span>
          </button>
        </div>
      </nav>

      {/* 🌃 CONTENIDO PRINCIPAL */}
      <div className="max-w-7xl mx-auto px-6 lg:px-10 space-y-24">

        {/* ⚡ HERO BANNER SPECTACULAR */}
        <section className="relative h-[80vh] min-h-[500px] flex items-center justify-center overflow-hidden rounded-[3rem] mt-4">
          <div className="absolute inset-0 z-0">
            <img src="https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?q=80&w=2070" className="w-full h-full object-cover grayscale brightness-[0.3]" alt="" />
            <div className="absolute inset-0 bg-gradient-to-t from-pitch-black via-transparent to-transparent"></div>
            <div className="absolute inset-0 bg-gradient-to-r from-pitch-black/80 via-transparent to-pitch-black/80"></div>
          </div>

          <div className="relative z-10 text-center px-4 max-w-3xl animate-in fade-in slide-in-from-bottom-10 duration-1000">
            <span className="inline-block px-4 py-1 rounded-full glass border border-urban-blue/30 text-urban-blue text-xs font-black tracking-[0.3em] uppercase mb-6 shadow-neon-blue">
              Nueva Colección 2026
            </span>
            <h1 className="text-6xl md:text-8xl font-black text-white leading-none mb-6 tracking-tighter">
              TECH-WEAR <span className="text-transparent bg-clip-text bg-gradient-to-r from-urban-blue to-cyan-400">PRO.</span>
            </h1>
            <p className="text-gray-400 text-lg md:text-xl font-medium mb-10 max-w-xl mx-auto">
              Equipamiento táctico para tu ecosistema digital. Diseñado para los que no se detienen.
            </p>
            <button onClick={() => {
              const el = document.getElementById('catalogo');
              el?.scrollIntoView({ behavior: 'smooth' });
            }} className="px-10 py-5 bg-urban-blue rounded-[1.2rem] text-white font-black uppercase text-sm tracking-[0.2em] shadow-neon-blue hover:shadow-neon-glow hover:-translate-y-1 transition-all duration-300">
              Explorar Catálogo
            </button>
          </div>
        </section>

        {/* 🛡️ BENEFICIOS SECTION */}
        <section className="grid grid-cols-1 md:grid-cols-3 gap-8 pb-12 border-b border-white/5">
          {[
            { icon: '🚀', title: 'Envío Flash', desc: 'Entrega prioritaria en menos de 24h.' },
            { icon: '🤖', title: 'Soporte con IA', desc: 'Santi resuelve tus dudas al instante.' },
            { icon: '🔒', title: 'Pago Encriptado', desc: 'Transacciones 100% seguras vía Stripe.' }
          ].map((b, i) => (
            <div key={i} className="glass-dark p-8 rounded-[2rem] flex flex-col items-center text-center group transition-smooth hover:border-urban-blue/20">
              <span className="text-5xl mb-4 group-hover:scale-110 transition-transform duration-500">{b.icon}</span>
              <h4 className="text-xl font-black mb-2">{b.title}</h4>
              <p className="text-gray-400 text-sm leading-relaxed">{b.desc}</p>
            </div>
          ))}
        </section>

        {/* 🔍 FILTROS URBANO */}
        <div id="catalogo" className="flex flex-col md:flex-row gap-6 items-center justify-between pt-12">
          <div className="w-full md:max-w-md relative group">
            <input
              type="text"
              placeholder="Busca tu equipamiento..."
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
              className="w-full h-14 glass-dark pl-14 pr-6 rounded-2xl outline-none border border-white/5 focus:border-urban-blue/50 focus:shadow-neon-blue transition-all duration-300"
            />
            <span className="absolute left-6 top-1/2 -translate-y-1/2 text-xl opacity-50 group-focus-within:opacity-100 transition-opacity">🔍</span>
          </div>

          <div className="flex gap-2 overflow-x-auto w-full md:w-auto pb-4 md:pb-0 no-scrollbar">
            {categoriasUnicas.map(cat => (
              <button
                key={cat}
                onClick={() => setCategoriaActiva(cat)}
                className={`px-6 py-3 rounded-full text-sm font-black transition-smooth whitespace-nowrap border ${categoriaActiva === cat ? 'bg-urban-blue text-white border-urban-blue shadow-neon-blue' : 'glass border-white/5 text-gray-500 hover:text-white'}`}
              >
                {cat.toUpperCase()}
              </button>
            ))}
          </div>
        </div>

        {/* 📦 GRID DE PRODUCTOS (LUXURY) */}
        <div className="pb-24">
          <div className="flex items-end justify-between mb-10">
            <h2 className="text-4xl font-black tracking-tighter">EL PRÓXIMO NIVEL <span className="text-gray-600">({productosFiltrados.length})</span></h2>
          </div>

          {productosFiltrados.length === 0 ? (
            <div className="text-center py-20 glass rounded-[3rem] border-dashed border-white/10 text-gray-500 text-xl italic font-medium">
              No se han encontrado resultados en este cuadrante. 🛰️
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
              {productosFiltrados.map(p => (
                <div
                  key={p.id}
                  className="glass-dark group relative rounded-[2.5rem] p-8 flex flex-col h-[460px] transition-smooth hover:-translate-y-2 hover:shadow-neon-glow hover:border-urban-blue/20"
                >
                  <div className="flex justify-between items-start mb-4">
                    <span className="text-[10px] font-black tracking-[0.2em] text-urban-blue px-3 py-1 rounded-full glass border border-urban-blue/20 uppercase">
                      {p.category?.name || 'GENERIC'}
                    </span>
                    <span className="text-xl font-black tracking-tighter">$ {p.price.toLocaleString()}</span>
                  </div>

                  <div className="flex-1 flex items-center justify-center p-4 relative group-hover:scale-110 transition-transform duration-700">
                    <div className="absolute inset-0 bg-urban-blue/5 rounded-full blur-[60px] opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <img
                      src={p.primary_image_url || "https://images.unsplash.com/photo-1606841837044-8848419615a1?q=80&w=800"}
                      alt=""
                      className="max-h-full max-w-full drop-shadow-[0_20px_50px_rgba(0,0,0,0.5)]"
                    />
                  </div>

                  <div className="mt-6">
                    <h3 className="text-xl font-black text-white leading-tight mb-6 line-clamp-2">{p.name}</h3>
                    <button
                      onClick={() => agregarAlCarrito(p)}
                      className="w-full py-4 rounded-2xl bg-white text-black font-black uppercase text-xs tracking-widest hover:bg-urban-blue hover:text-white transition-all duration-300"
                    >
                      Añadir a la Bolsa
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

      </div>

      <Footer />
    </div>
  )
}
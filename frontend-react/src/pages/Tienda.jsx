import { useState, useEffect } from 'react'
import Swal from 'sweetalert2' 
import CheckoutForm from '../components/CheckoutForm';
import Chatbot from '../components/Chatbot';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import api from '../lib/api';

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
  const { items, totalItems, totalPrice, addItem, updateQuantity, removeItem, clearCart, refreshCart } = useCart();
  const { isAuthenticated } = useAuth();

  // Cargar productos desde la API
  useEffect(() => {
    api.get('/products')
      .then(data => {
        // La API ahora retorna un objeto con "data" (ProductResource::collection)
        setProductos(data.data || []);
      })
      .catch(err => console.error('Error cargando productos:', err));
  }, []);

  const agregarAlCarrito = async (producto) => {
    const result = await addItem(producto.id);
    if (!result.success) {
      Swal.fire('Error', result.error, 'warning');
    } else {
      Swal.fire({ 
        title: '¡Agregado!', 
        text: `${producto.name} se añadió a tu bolsa.`,
        icon: 'success', 
        timer: 1200, 
        showConfirmButton: false 
      });
    }
  };

  // Lógica de filtrado
  const categoriasUnicas = ['Todas', ...new Set(productos.map(p => p.category?.name).filter(Boolean))];
  const productosFiltrados = productos.filter(p => {
    const nombre = (p.name || '').toLowerCase();
    const categoria = p.category?.name || '';
    const coincideBusqueda = nombre.includes(busqueda.toLowerCase());
    const coincideCategoria = categoriaActiva === 'Todas' || categoria === categoriaActiva;
    return coincideBusqueda && coincideCategoria;
  });

  return (
    <div style={{ width: '100%', position: 'relative', background: '#f5f5f7', minHeight: '100vh' }}>
      
      {/* MODAL DE CHECKOUT */}
      {mostrarCheckout && (
        <div style={{ position: 'fixed', top: 0, left: 0, width: '100%', height: '100%', background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(10px)', zIndex: 999, display: 'flex', justifyContent: 'center', alignItems: 'center', animation: 'fadeIn 0.3s' }}>
          <div style={{ background: 'white', padding: '40px', borderRadius: '30px', width: '90%', maxWidth: '450px', boxShadow: '0 25px 50px rgba(0,0,0,0.3)' }}>
            <div style={{ textAlign: 'center', marginBottom: '25px' }}>
              <h2 style={{ marginTop: 0, fontSize: '26px', color: '#1d1d1f', marginBottom: '5px' }}>Finalizar Compra</h2>
              <p style={{ color: '#86868b', margin: 0, fontSize: '15px' }}>Ingresa tus datos y método de pago seguro.</p>
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

      {/* NAVBAR */}
      <nav style={{ background: 'rgba(255,255,255,0.8)', backdropFilter: 'blur(10px)', padding: '15px 40px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', position: 'sticky', top: 0, zIndex: 10, borderBottom: '1px solid #ddd' }}>
        <h2 style={{ margin: 0, color: '#1d1d1f', fontSize: '24px' }}> iStore</h2>
        <div style={{ display: 'flex', gap: '15px' }}>
          <button onClick={() => window.location.href='/mi-cuenta'} style={{ cursor: 'pointer', fontWeight: 'bold', background: '#f5f5f7', color: '#1d1d1f', border: 'none', padding: '10px 20px', borderRadius: '30px', transition: '0.2s' }}>
            👤 Mi Cuenta
          </button>
          <div onClick={() => setVerCarrito(!verCarrito)} style={{ cursor: 'pointer', fontWeight: 'bold', background: '#0071e3', color: 'white', padding: '10px 20px', borderRadius: '30px', transition: 'transform 0.2s', boxShadow: '0 4px 10px rgba(0,113,227,0.3)' }}>
            🛒 Carrito ({totalItems})
          </div>
        </div>
      </nav>

      {/* MINI CARRITO FLOTANTE */}
      {verCarrito && (
        <div style={{ position: 'absolute', top: '75px', right: '40px', background: 'white', border: '1px solid #ddd', padding: '25px', borderRadius: '20px', boxShadow: '0 20px 40px rgba(0,0,0,0.1)', zIndex: 20, width: '380px', animation: 'fadeIn 0.3s' }}>
          <h3 style={{ margin: '0 0 20px 0', fontSize: '20px', color: '#1d1d1f' }}>Tu Bolsa de Compras</h3>
          
          {items.length === 0 ? (
            <p style={{ color: '#888', textAlign: 'center', padding: '20px 0' }}>Tu bolsa está vacía.</p>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '15px', maxHeight: '400px', overflowY: 'auto', paddingRight: '10px' }}>
              {items.map((item) => (
                <div key={item.id} style={{ display: 'flex', alignItems: 'center', gap: '15px', paddingBottom: '15px', borderBottom: '1px solid #eee' }}>
                  <img 
                    src={item.product_image || "https://images.unsplash.com/photo-1606841837044-8848419615a1?q=80&w=200"} 
                    alt={item.product_name} 
                    style={{ width: '60px', height: '60px', objectFit: 'contain', borderRadius: '10px', background: '#f5f5f7' }} 
                  />
                  <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '2px' }}>
                    <span style={{ fontWeight: 'bold', color: '#1d1d1f', fontSize: '14px' }}>{item.product_name}</span>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '4px' }}>
                      <button 
                        onClick={() => updateQuantity(item.product_id, item.quantity - 1)}
                        style={{ width: '24px', height: '24px', borderRadius: '50%', border: '1px solid #ddd', background: '#f5f5f7', cursor: 'pointer', fontSize: '14px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                      >−</button>
                      <span style={{ color: '#1d1d1f', fontSize: '14px', fontWeight: 'bold' }}>{item.quantity}</span>
                      <button 
                        onClick={() => updateQuantity(item.product_id, item.quantity + 1)}
                        style={{ width: '24px', height: '24px', borderRadius: '50%', border: '1px solid #ddd', background: '#f5f5f7', cursor: 'pointer', fontSize: '14px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                      >+</button>
                      <button 
                        onClick={() => removeItem(item.product_id)}
                        style={{ marginLeft: 'auto', background: 'none', border: 'none', color: '#ff3b30', cursor: 'pointer', fontSize: '13px', fontWeight: 'bold' }}
                      >Quitar</button>
                    </div>
                  </div>
                  <div style={{ textAlign: 'right', fontWeight: 'bold', color: '#1d1d1f', fontSize: '15px' }}>
                    ${item.subtotal.toLocaleString()}
                  </div>
                </div>
              ))}
            </div>
          )}
          
          <div style={{ background: '#f5f5f7', padding: '15px', borderRadius: '15px', marginTop: '20px', display: 'flex', justifyContent: 'space-between', fontWeight: 'bold', fontSize: '18px' }}>
            <span>Total:</span>
            <span style={{ color: '#1d1d1f' }}>${totalPrice.toLocaleString()}</span>
          </div>
          
          <button 
            onClick={() => {
              if (items.length === 0) {
                Swal.fire('Bolsa vacía', 'Agrega productos para continuar.', 'info');
                return;
              }
              if (!isAuthenticated) {
                Swal.fire({
                  title: 'Inicia Sesión',
                  text: 'Debes iniciar sesión para completar tu compra.',
                  icon: 'info',
                  confirmButtonColor: '#0071e3',
                  confirmButtonText: 'Ir a Mi Cuenta',
                }).then(() => window.location.href = '/mi-cuenta');
                return;
              }
              setVerCarrito(false); 
              setMostrarCheckout(true);
            }} 
            style={{ background: '#0071e3', color: 'white', border: 'none', padding: '15px', width: '100%', borderRadius: '12px', marginTop: '20px', fontWeight: 'bold', cursor: 'pointer', fontSize: '16px', transition: '0.2s', boxShadow: '0 4px 10px rgba(0,113,227,0.3)' }}>
            Pagar Compra ➔
          </button>
        </div>
      )}

      {/* CONTENIDO PRINCIPAL */}
      <div style={{ padding: '40px', maxWidth: '1200px', margin: '0 auto' }}>
        <div style={{ background: '#000', color: '#fff', padding: '80px 40px', borderRadius: '30px', textAlign: 'center', marginBottom: '40px', backgroundImage: 'url("https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?q=80&w=2070")', backgroundSize: 'cover', backgroundPosition: 'center', boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
          <div style={{ background: 'rgba(0,0,0,0.5)', display: 'inline-block', padding: '30px 50px', borderRadius: '24px', backdropFilter: 'blur(5px)' }}>
            <h1 style={{ fontSize: '3.5rem', margin: '0 0 10px 0', letterSpacing: '-1px' }}>Pro. Más allá.</h1>
            <p style={{ fontSize: '1.3rem', color: '#f5f5f7', margin: '0 0 20px 0' }}>Accesorios diseñados para tu ecosistema.</p>
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', marginBottom: '40px', background: 'white', padding: '20px', borderRadius: '20px', boxShadow: '0 4px 15px rgba(0,0,0,0.03)' }}>
          <input type="text" placeholder="🔍 Busca fundas, cargadores, audífonos..." value={busqueda} onChange={(e) => setBusqueda(e.target.value)} style={{ width: '100%', padding: '15px 20px', borderRadius: '12px', border: '1px solid #ddd', fontSize: '16px', outline: 'none', boxSizing: 'border-box' }} />
          <div style={{ display: 'flex', gap: '10px', overflowX: 'auto', paddingBottom: '5px' }}>
            {categoriasUnicas.map(cat => (
              <button key={cat} onClick={() => setCategoriaActiva(cat)} style={{ background: categoriaActiva === cat ? '#1d1d1f' : '#f5f5f7', color: categoriaActiva === cat ? 'white' : '#1d1d1f', border: 'none', padding: '10px 20px', borderRadius: '20px', fontWeight: 'bold', cursor: 'pointer', whiteSpace: 'nowrap', transition: '0.2s' }}>{cat}</button>
            ))}
          </div>
        </div>

        <h2 style={{ fontSize: '28px', marginBottom: '20px', color: '#1d1d1f' }}>Catálogo <span style={{ color: '#86868b' }}>({productosFiltrados.length} resultados)</span></h2>
        
        {productosFiltrados.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '50px', color: '#86868b', fontSize: '18px' }}>No encontramos productos para tu búsqueda. 😢</div>
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '25px' }}>
            {productosFiltrados.map(p => {
              const nombre = p.name;
              const precio = p.price;
              const categoria = p.category?.name;
              const imagen = p.primary_image_url || "https://images.unsplash.com/photo-1606841837044-8848419615a1?q=80&w=800";
              
              return (
                <div key={p.id} style={{ background: 'white', borderRadius: '24px', padding: '25px', position: 'relative', overflow: 'hidden', display: 'flex', flexDirection: 'column', justifyContent: 'space-between', boxShadow: '0 10px 20px rgba(0,0,0,0.04)', transition: 'transform 0.3s' }}>
                  <div style={{ zIndex: 2 }}>
                    <p style={{ margin: 0, color: '#0071e3', fontSize: '12px', fontWeight: 'bold', textTransform: 'uppercase', letterSpacing: '1px' }}>{categoria}</p>
                    <h3 style={{ margin: '10px 0', fontSize: '22px', color: '#1d1d1f' }}>{nombre}</h3>
                    <p style={{ fontWeight: 'bold', fontSize: '20px', color: '#1d1d1f' }}>${typeof precio === 'number' ? precio.toLocaleString() : precio}</p>
                  </div>
                  
                  <img src={imagen} alt="Producto" style={{ width: '100%', height: '200px', objectFit: 'contain', margin: '20px 0', zIndex: 1 }} />
                  
                  <button onClick={() => agregarAlCarrito(p)} style={{ zIndex: 2, background: '#f5f5f7', color: '#0071e3', border: 'none', padding: '12px', borderRadius: '15px', cursor: 'pointer', fontWeight: 'bold', fontSize: '16px', transition: '0.2s' }}>Añadir a la bolsa</button>
                </div>
              );
            })}
          </div>
        )}
      </div>
      <Chatbot />
    </div>
  )
}
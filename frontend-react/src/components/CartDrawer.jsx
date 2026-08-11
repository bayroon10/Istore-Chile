import { useState } from 'react';
import Swal from 'sweetalert2';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';

export default function CartDrawer({ onCheckoutStart }) {
  const { verCarrito, cerrarCarrito, items, totalItems, totalPrice, updateQuantity, removeItem } = useCart();
  const { isAuthenticated } = useAuth();

  // Estado para bloquear peticiones concurrentes por ítem individual
  const [updatingItems, setUpdatingItems] = useState({});

  const handleUpdateQuantity = async (productId, newQuantity) => {
    if (updatingItems[productId]) return; // Prevenir peticiones concurrentes en vuelo
    setUpdatingItems(prev => ({ ...prev, [productId]: true }));
    try {
      await updateQuantity(productId, newQuantity);
    } finally {
      setUpdatingItems(prev => ({ ...prev, [productId]: false }));
    }
  };

  const handleRemoveItem = async (productId) => {
    if (updatingItems[productId]) return; // Prevenir peticiones concurrentes en vuelo
    setUpdatingItems(prev => ({ ...prev, [productId]: true }));
    try {
      await removeItem(productId);
    } finally {
      setUpdatingItems(prev => ({ ...prev, [productId]: false }));
    }
  };

  return (
    <div
      className={`fixed inset-y-0 right-0 z-[150] w-full max-w-sm glass-dark border-l border-white/10 shadow-[-20px_0_50px_rgba(0,0,0,0.5)] transform transition-transform duration-500 ease-out p-6 flex flex-col ${
        verCarrito ? 'translate-x-0' : 'translate-x-full'
      }`}
    >
      {/* Header */}
      <div className="flex items-center justify-between mb-8 border-b border-white/10 pb-4">
        <h3 className="text-2xl font-black">
          Tu Bolsa <span className="text-urban-blue">({totalItems})</span>
        </h3>
        <button
          onClick={cerrarCarrito}
          aria-label="Cerrar bolsa de compras"
          className="p-2 hover:bg-white/5 rounded-full transition-colors text-gray-400 hover:text-white cursor-pointer"
        >
          ✕
        </button>
      </div>

      {/* Item list */}
      <div className="flex-1 overflow-y-auto pr-2 space-y-6">
        {items.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-52 text-center space-y-4">
            <span className="text-5xl mb-2">🛒</span>
            <p className="text-gray-400 font-bold text-sm">Tu bolsa está vacía.</p>
            <button
              onClick={() => {
                cerrarCarrito();
                const el = document.getElementById('catalogo');
                if (el) {
                  el.scrollIntoView({ behavior: 'smooth' });
                } else {
                  window.location.href = '/#catalogo';
                }
              }}
              className="px-6 py-3 rounded-full bg-urban-blue/10 border border-urban-blue/30 text-urban-blue text-xs font-black uppercase tracking-widest hover:bg-urban-blue hover:text-white transition-all cursor-pointer"
            >
              Explorar Equipamiento →
            </button>
          </div>
        ) : (
          items.map((item) => {
            const isItemUpdating = !!updatingItems[item.product_id];
            return (
              <div
                key={item.id}
                className={`flex items-center gap-4 group transition-all duration-300 ${
                  isItemUpdating ? 'opacity-40 pointer-events-none animate-pulse' : ''
                }`}
              >
                <div className="w-20 h-20 bg-space-grey rounded-2xl flex items-center justify-center border border-white/5 group-hover:border-urban-blue/30 transition-all shrink-0">
                  <img
                    src={
                      item.product_image && !item.product_image.includes('via.placeholder.com')
                        ? item.product_image
                        : `https://placehold.co/80x80/0a0a0a/3b82f6?text=${encodeURIComponent((item.product_name || 'i').slice(0, 2))}`
                    }
                    alt={item.product_name || 'Producto'}
                    loading="lazy"
                    onError={(e) => {
                      e.target.src = 'https://placehold.co/80x80/000/3b82f6?text=i';
                    }}
                    className="w-16 h-16 object-contain"
                  />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-bold text-sm truncate">{item.product_name}</p>
                  <div className="flex items-center gap-3 mt-2">
                    <button
                      disabled={isItemUpdating}
                      onClick={() => handleUpdateQuantity(item.product_id, item.quantity - 1)}
                      aria-label={`Disminuir cantidad de ${item.product_name}`}
                      className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                      -
                    </button>
                    <span className="text-sm font-black">{item.quantity}</span>
                    <button
                      disabled={isItemUpdating}
                      onClick={() => handleUpdateQuantity(item.product_id, item.quantity + 1)}
                      aria-label={`Aumentar cantidad de ${item.product_name}`}
                      className="w-6 h-6 rounded-full glass border border-white/10 flex items-center justify-center hover:bg-urban-blue/20 transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                      +
                    </button>
                    <button
                      disabled={isItemUpdating}
                      onClick={() => handleRemoveItem(item.product_id)}
                      className="ml-auto text-xs font-bold text-red-500/80 hover:text-red-500 transition-colors uppercase tracking-widest cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                      Eliminar
                    </button>
                  </div>
                </div>
                <div className="text-right font-black text-urban-blue shrink-0">${item.subtotal.toLocaleString()}</div>
              </div>
            );
          })
        )}
      </div>

      {/* Footer / Total & Checkout button */}
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
                confirmButtonColor: '#0071e3',
              }).then(() => (window.location.href = '/mi-cuenta'));
              return;
            }
            cerrarCarrito();
            if (onCheckoutStart) {
              onCheckoutStart();
            } else {
              window.location.href = '/tienda?checkout=true';
            }
          }}
          className="w-full py-5 rounded-[1.2rem] bg-urban-blue text-white font-black text-lg shadow-neon-blue hover:shadow-neon-glow transition-all duration-300 disabled:opacity-50 disabled:grayscale cursor-pointer"
        >
          PAGAR AHORA ➔
        </button>
      </div>
    </div>
  );
}

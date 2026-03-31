import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../lib/api';
import { useAuth } from './AuthContext';

const CartContext = createContext(null);

export function CartProvider({ children }) {
  const { user, token, isAuthenticated } = useAuth();
  const [cart, setCart] = useState(null);
  const [loading, setLoading] = useState(false);

  // -------------------------------------------------------
  // Cargar el carrito al montar o cuando cambia el usuario
  // -------------------------------------------------------
  const fetchCart = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api.get('/cart');
      setCart(data.data);
    } catch (err) {
      console.error('Error cargando carrito:', err);
      setCart({ items: [], total_items: 0, total_price: 0 });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchCart();
  }, [fetchCart, user]); // Recargar cuando cambia el usuario (login/logout)

  // -------------------------------------------------------
  // Sync del carrito guest → user (post-login)
  // -------------------------------------------------------
  useEffect(() => {
    if (isAuthenticated) {
      const sessionId = localStorage.getItem('istore_session_id');
      if (sessionId) {
        api.post('/cart/sync', { session_id: sessionId })
          .then(data => setCart(data.data))
          .catch(() => {}); // Si falla, no bloqueamos nada
      }
    }
  }, [isAuthenticated]);

  // -------------------------------------------------------
  // Acciones del carrito
  // -------------------------------------------------------
  const addItem = useCallback(async (productId, quantity = 1) => {
    try {
      const data = await api.post('/cart/items', { product_id: productId, quantity });
      setCart(data.data);
      return { success: true };
    } catch (err) {
      return { success: false, error: err.message };
    }
  }, []);

  const updateQuantity = useCallback(async (productId, quantity) => {
    try {
      const data = await api.put(`/cart/items/${productId}`, { quantity });
      setCart(data.data);
      return { success: true };
    } catch (err) {
      return { success: false, error: err.message };
    }
  }, []);

  const removeItem = useCallback(async (productId) => {
    try {
      const data = await api.delete(`/cart/items/${productId}`);
      setCart(data.data);
      return { success: true };
    } catch (err) {
      return { success: false, error: err.message };
    }
  }, []);

  const clearCart = useCallback(async () => {
    try {
      const data = await api.delete('/cart');
      setCart(data.data);
      return { success: true };
    } catch (err) {
      return { success: false, error: err.message };
    }
  }, []);

  // -------------------------------------------------------
  // Valores expuestos
  // -------------------------------------------------------
  const value = {
    cart,
    loading,
    items: cart?.items || [],
    totalItems: cart?.total_items || 0,
    totalPrice: cart?.total_price || 0,

    // Acciones
    addItem,
    updateQuantity,
    removeItem,
    clearCart,
    refreshCart: fetchCart,
  };

  return (
    <CartContext.Provider value={value}>
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart debe usarse dentro de un CartProvider');
  }
  return context;
}

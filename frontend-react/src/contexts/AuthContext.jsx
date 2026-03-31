import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(
    () => localStorage.getItem('token_istore') || localStorage.getItem('cliente_token')
  );
  const [loading, setLoading] = useState(true);

  // Al montarse, si hay token, cargamos el perfil
  useEffect(() => {
    if (token) {
      api.get('/cliente/perfil')
        .then(data => {
          setUser(data.user);
        })
        .catch(() => {
          // Token expirado o inválido → limpiamos
          logout();
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, [token]);

  const login = useCallback(async (email, password) => {
    const data = await api.post('/cliente/login', { email, password });
    const newToken = data.token;

    // Guardar el token unificado
    localStorage.setItem('token_istore', newToken);
    localStorage.removeItem('cliente_token'); // Limpiar legacy key
    setToken(newToken);
    setUser(data.user);

    return data;
  }, []);

  const adminLogin = useCallback(async (email, password) => {
    const data = await api.post('/login', { email, password });
    const newToken = data.token;

    localStorage.setItem('token_istore', newToken);
    localStorage.setItem('role_istore', data.role);
    localStorage.setItem('usuario_istore', data.usuario);
    setToken(newToken);

    // Cargar perfil completo
    try {
      const perfil = await api.get('/cliente/perfil');
      setUser(perfil.user);
    } catch {
      // Si falla, al menos tenemos datos del login
      setUser({ name: data.usuario, role: data.role });
    }

    return data;
  }, []);

  const register = useCallback(async (name, email, password) => {
    const data = await api.post('/cliente/registro', { name, email, password });
    const newToken = data.token;

    localStorage.setItem('token_istore', newToken);
    localStorage.removeItem('cliente_token');
    setToken(newToken);
    setUser(data.user);

    return data;
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('token_istore');
    localStorage.removeItem('cliente_token');
    localStorage.removeItem('role_istore');
    localStorage.removeItem('usuario_istore');
    setToken(null);
    setUser(null);
  }, []);

  const value = {
    user,
    token,
    loading,
    isAuthenticated: !!token && !!user,
    isAdmin: user?.role === 'admin',
    login,
    adminLogin,
    register,
    logout,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe usarse dentro de un AuthProvider');
  }
  return context;
}

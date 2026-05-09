import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(
    () => localStorage.getItem('token_istore') || localStorage.getItem('cliente_token')
  );
  const [loading, setLoading] = useState(true);

  const logout = useCallback(() => {
    // Intentar invalidar el token en el servidor de manera asíncrona
    api.post('/logout').catch(() => {});

    localStorage.removeItem('token_istore');
    localStorage.removeItem('cliente_token');
    localStorage.removeItem('role_istore');
    localStorage.removeItem('usuario_istore');
    localStorage.removeItem('istore_logged_in');
    setToken(null);
    setUser(null);
  }, []);

  // Al montarse, si hay token o el indicador de inicio de sesión por cookie, cargamos el perfil
  useEffect(() => {
    const hasToken = !!token;
    const hasLoggedInFlag = localStorage.getItem('istore_logged_in') === 'true';

    if (hasToken || hasLoggedInFlag) {
      api.get('/cliente/perfil')
        .then(data => {
          setUser(data.user);
          // Si el backend retornó token (fallback híbrido), lo guardamos
          if (data.token) {
            setToken(data.token);
            localStorage.setItem('token_istore', data.token);
          }
        })
        .catch(() => {
          // Token expirado o cookie inválida → limpiamos
          logout();
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, [token, logout]);

  // Escucha el evento global 'auth:expired' disparado por api.js
  // cuando el backend responde 401 en una request autenticada.
  useEffect(() => {
    const handleAuthExpired = () => {
      logout();
    };
    window.addEventListener('auth:expired', handleAuthExpired);
    return () => window.removeEventListener('auth:expired', handleAuthExpired);
  }, [logout]);

  const login = useCallback(async (email, password) => {
    const data = await api.post('/cliente/login', { email, password });
    const newToken = data.token;

    // Guardar el token unificado en localStorage como fallback
    if (newToken) {
      localStorage.setItem('token_istore', newToken);
      setToken(newToken);
    }
    localStorage.removeItem('cliente_token'); // Limpiar legacy key
    localStorage.setItem('istore_logged_in', 'true');
    setUser(data.user);

    return data;
  }, []);

  const adminLogin = useCallback(async (email, password) => {
    const data = await api.post('/login', { email, password });
    const newToken = data.token;

    if (newToken) {
      localStorage.setItem('token_istore', newToken);
      setToken(newToken);
    }
    localStorage.setItem('istore_logged_in', 'true');
    localStorage.setItem('role_istore', data.role);
    localStorage.setItem('usuario_istore', data.usuario);

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

    if (newToken) {
      localStorage.setItem('token_istore', newToken);
      setToken(newToken);
    }
    localStorage.removeItem('cliente_token');
    localStorage.setItem('istore_logged_in', 'true');
    setUser(data.user);

    return data;
  }, []);



  const value = {
    user,
    token,
    loading,
    isAuthenticated: (!!token || localStorage.getItem('istore_logged_in') === 'true') && !!user,
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

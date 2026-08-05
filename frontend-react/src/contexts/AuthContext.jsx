import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../lib/api';

const AuthContext = createContext(null);

function rememberLegacyToken(token) {
  if (token) {
    localStorage.setItem('token_istore', token);
    localStorage.removeItem('cliente_token');
  }
}

function clearLegacyAuthentication() {
  localStorage.removeItem('token_istore');
  localStorage.removeItem('cliente_token');
  localStorage.removeItem('role_istore');
  localStorage.removeItem('usuario_istore');
  localStorage.removeItem('istore_logged_in');
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const clearAuthentication = useCallback(() => {
    clearLegacyAuthentication();
    setUser(null);
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post('/logout');
    } finally {
      clearAuthentication();
    }
  }, [clearAuthentication]);


  useEffect(() => {
    let mounted = true;

    api.get('/cliente/perfil')
      .then((data) => {
        if (mounted) {
          setUser(data.user);
        }
      })
      .catch(() => {
        if (mounted) {
          setUser(null);
        }
      })
      .finally(() => {
        if (mounted) {
          setLoading(false);
        }
      });

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    window.addEventListener('auth:expired', clearAuthentication);
    return () => window.removeEventListener('auth:expired', clearAuthentication);
  }, [clearAuthentication]);

  const login = useCallback(async (email, password) => {
    const data = await api.post('/cliente/login', { email, password });

    rememberLegacyToken(data.token);
    localStorage.removeItem('istore_session_id');
    setUser(data.user);

    return data;
  }, []);

  const adminLogin = useCallback(async (email, password) => {
    const data = await api.post('/login', { email, password });

    rememberLegacyToken(data.token);
    localStorage.removeItem('istore_session_id');

    try {
      const profile = await api.get('/cliente/perfil');
      setUser(profile.user);
    } catch {
      setUser({ name: data.usuario, role: data.role });
    }

    return data;
  }, []);

  const register = useCallback(async (name, email, password) => {
    const data = await api.post('/cliente/registro', { name, email, password });

    rememberLegacyToken(data.token);
    localStorage.removeItem('istore_session_id');
    setUser(data.user);

    return data;
  }, []);

  const value = {
    user,
    token: api.getToken(),
    loading,
    isAuthenticated: Boolean(user),
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

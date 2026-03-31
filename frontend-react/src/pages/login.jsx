import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { useAuth } from '../contexts/AuthContext';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const navigate = useNavigate();
  const { adminLogin } = useAuth();

  const handleLogin = async (e) => {
    e.preventDefault();
    
    try {
      const data = await adminLogin(email, password);
      
      Swal.fire({ 
        title: `¡Bienvenido ${data.usuario}!`, 
        icon: 'success', 
        timer: 1500, 
        showConfirmButton: false,
        background: '#000',
        color: '#fff'
      });
      
      if (data.role === 'admin') {
        navigate('/admin');
      } else {
        navigate('/mi-cuenta');
      }
    } catch (err) {
      Swal.fire({
        title: 'Acceso Denegado', 
        text: err.message || 'Credenciales incorrectas', 
        icon: 'error',
        background: '#000',
        color: '#fff'
      });
    }
  };

  return (
    <div className="min-h-screen bg-pitch-black flex justify-center items-center font-sans p-4 relative overflow-hidden">
      
      {/* 🔮 EFECTOS DE FONDO (GLOWS) */}
      <div className="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-urban-blue/10 rounded-full blur-[120px] pointer-events-none"></div>
      <div className="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-urban-blue/5 rounded-full blur-[100px] pointer-events-none"></div>

      <div className="glass-dark p-10 rounded-[32px] w-full max-w-md shadow-2xl backdrop-blur-2xl border border-white/10 relative z-10">
        
        <div className="text-center mb-10">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-urban-blue/10 border border-urban-blue/30 mb-6 shadow-neon-blue">
            <span className="text-3xl text-urban-blue font-black"></span>
          </div>
          <h1 className="text-white text-3xl font-black tracking-tight mb-2 uppercase"> iStore <span className="text-urban-blue">Admin</span></h1>
          <p className="text-gray-400 font-medium">Protocolo de acceso restringido</p>
        </div>

        <form onSubmit={handleLogin} className="flex flex-col gap-6">
          <div className="group">
             <label className="text-xs font-bold text-gray-500 uppercase tracking-widest ml-1 mb-2 block group-focus-within:text-urban-blue transition-colors">Identidad Digital</label>
             <input 
              type="email" 
              placeholder="correo@istore.cl" 
              required 
              value={email} 
              onChange={e => setEmail(e.target.value)} 
              className="w-full p-4 rounded-2xl border border-white/5 bg-black/40 text-white placeholder:text-gray-600 focus:border-urban-blue/50 focus:ring-4 focus:ring-urban-blue/10 outline-none transition-all duration-300" 
            />
          </div>
          
          <div className="group">
            <label className="text-xs font-bold text-gray-500 uppercase tracking-widest ml-1 mb-2 block group-focus-within:text-urban-blue transition-colors">Código de Acceso</label>
            <input 
              type="password" 
              placeholder="••••••••" 
              required 
              value={password} 
              onChange={e => setPassword(e.target.value)} 
              className="w-full p-4 rounded-2xl border border-white/5 bg-black/40 text-white placeholder:text-gray-600 focus:border-urban-blue/50 focus:ring-4 focus:ring-urban-blue/10 outline-none transition-all duration-300" 
            />
          </div>

          <button 
            type="submit" 
            className="w-full bg-urban-blue text-white py-4 rounded-2xl font-black text-lg hover:scale-[1.02] active:scale-95 transition-all duration-300 shadow-neon-blue flex items-center justify-center gap-2 group mt-4">
            INICIAR SESIÓN 
            <span className="group-hover:translate-x-1 transition-transform">➔</span>
          </button>
        </form>

        <div className="mt-8 pt-8 border-t border-white/5 text-center">
            <p className="text-gray-600 text-xs font-bold uppercase tracking-widest">SISTEMA INTEGRADO ISTORE PRO v3.1</p>
        </div>
      </div>
    </div>
  );
}
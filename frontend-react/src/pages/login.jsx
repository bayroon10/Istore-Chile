import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const navigate = useNavigate();

  const handleLogin = async (e) => {
    e.preventDefault();
    
    try {
      const response = await fetch('https://istore-backend-nxvt.onrender.com/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      const data = await response.json();

      if (response.ok) {
        // 🌟 ¡ÉXITO! Guardamos la pulsera VIP (Token) en el navegador
        localStorage.setItem('token_istore', data.token);
        localStorage.setItem('usuario_istore', data.usuario);
        
        Swal.fire({ title: `¡Bienvenido ${data.usuario}!`, icon: 'success', timer: 1500, showConfirmButton: false });
        navigate('/admin'); // Lo mandamos pa' adentro
      } else {
        Swal.fire('Acceso Denegado', data.error || 'Credenciales incorrectas', 'error');
      }
    } catch (error) {
      Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
  };

  return (
    <div style={{ minHeight: '100vh', background: '#000', display: 'flex', justifyContent: 'center', alignItems: 'center', fontFamily: 'sans-serif' }}>
      <div style={{ background: '#1d1d1f', padding: '50px', borderRadius: '24px', width: '100%', maxWidth: '400px', boxShadow: '0 20px 40px rgba(0,0,0,0.5)', textAlign: 'center' }}>
        
        <h1 style={{ color: 'white', margin: '0 0 10px 0', fontSize: '32px' }}> iStore Admin</h1>
        <p style={{ color: '#86868b', marginBottom: '40px' }}>Ingresa tus credenciales para continuar</p>

        <form onSubmit={handleLogin} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
          <input 
            type="email" 
            placeholder="Correo electrónico" 
            required 
            value={email} 
            onChange={e => setEmail(e.target.value)} 
            style={{ padding: '15px', borderRadius: '12px', border: '1px solid #333', background: '#000', color: 'white', fontSize: '16px', outline: 'none' }} 
          />
          <input 
            type="password" 
            placeholder="Contraseña" 
            required 
            value={password} 
            onChange={e => setPassword(e.target.value)} 
            style={{ padding: '15px', borderRadius: '12px', border: '1px solid #333', background: '#000', color: 'white', fontSize: '16px', outline: 'none' }} 
          />
          <button 
            type="submit" 
            style={{ background: '#0071e3', color: 'white', border: 'none', padding: '15px', borderRadius: '12px', fontWeight: 'bold', fontSize: '16px', cursor: 'pointer', marginTop: '10px' }}>
            Iniciar Sesión ➔
          </button>
        </form>

      </div>
    </div>
  );
}
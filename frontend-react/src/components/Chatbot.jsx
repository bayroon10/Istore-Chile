import { useState } from 'react';

export default function Chatbot() {
  const [abierto, setAbierto] = useState(false);
  const [mensajes, setMensajes] = useState([{ texto: '¡Hola! Soy el asistente virtual de iStore . ¿Qué andas buscando hoy?', emisor: 'bot' }]);
  const [input, setInput] = useState('');
  const [cargando, setCargando] = useState(false);

  const enviarMensaje = async (e) => {
    e.preventDefault();
    if (!input.trim()) return;

    const nuevoMensaje = { texto: input, emisor: 'user' };
    setMensajes([...mensajes, nuevoMensaje]);
    setInput('');
    setCargando(true);

    try {
      const response = await fetch('http://127.0.0.1:8000/api/chatbot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mensaje: input })
      });
      const data = await response.json();
      setMensajes(prev => [...prev, { texto: data.respuesta, emisor: 'bot' }]);
    } catch (error) {
      setMensajes(prev => [...prev, { texto: 'Error de conexión 🔌', emisor: 'bot' }]);
    }
    setCargando(false);
  };

  return (
    <div style={{ position: 'fixed', bottom: '20px', right: '20px', zIndex: 9999 }}>
      
      {/* LA VENTANA DEL CHAT */}
      {abierto && (
        <div style={{ background: 'white', width: '350px', height: '450px', borderRadius: '20px', boxShadow: '0 20px 40px rgba(0,0,0,0.2)', display: 'flex', flexDirection: 'column', overflow: 'hidden', marginBottom: '15px', border: '1px solid #e5e5ea', animation: 'fadeIn 0.3s' }}>
          
          <div style={{ background: '#1d1d1f', color: 'white', padding: '15px', fontWeight: 'bold', display: 'flex', justifyContent: 'space-between' }}>
            <span> iStore Bot</span>
            <button onClick={() => setAbierto(false)} style={{ background: 'transparent', border: 'none', color: 'white', cursor: 'pointer', fontSize: '16px' }}>✖</button>
          </div>

          <div style={{ flex: 1, padding: '15px', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: '10px', background: '#f5f5f7' }}>
            {mensajes.map((msg, i) => (
              <div key={i} style={{ alignSelf: msg.emisor === 'user' ? 'flex-end' : 'flex-start', background: msg.emisor === 'user' ? '#0071e3' : '#e5e5ea', color: msg.emisor === 'user' ? 'white' : '#1d1d1f', padding: '10px 15px', borderRadius: '15px', maxWidth: '80%', fontSize: '14px' }}>
                {msg.texto}
              </div>
            ))}
            {cargando && <div style={{ alignSelf: 'flex-start', background: '#e5e5ea', padding: '10px 15px', borderRadius: '15px', fontSize: '14px' }}>Escribiendo... ✍️</div>}
          </div>

          <form onSubmit={enviarMensaje} style={{ display: 'flex', borderTop: '1px solid #e5e5ea', padding: '10px', background: 'white' }}>
            <input type="text" placeholder="Pregunta por un producto..." value={input} onChange={e => setInput(e.target.value)} style={{ flex: 1, border: 'none', outline: 'none', padding: '10px', fontSize: '14px' }} />
            <button type="submit" disabled={cargando} style={{ background: '#0071e3', color: 'white', border: 'none', padding: '10px 15px', borderRadius: '10px', cursor: 'pointer', fontWeight: 'bold' }}>Enviar</button>
          </form>

        </div>
      )}

      {/* EL BOTÓN FLOTANTE */}
      <button onClick={() => setAbierto(!abierto)} style={{ width: '60px', height: '60px', borderRadius: '30px', background: '#1d1d1f', color: 'white', border: 'none', cursor: 'pointer', boxShadow: '0 10px 20px rgba(0,0,0,0.2)', fontSize: '24px', display: 'flex', justifyContent: 'center', alignItems: 'center', float: 'right', transition: 'transform 0.2s' }}>
        💬
      </button>

    </div>
  );
}
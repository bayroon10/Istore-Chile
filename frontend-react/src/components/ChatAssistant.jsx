import { useState, useRef, useEffect } from 'react';
import api from '../lib/api';

export default function ChatAssistant() {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([
    { role: 'bot', text: '¡Hola! 🇨🇱 Soy Santi de iStore. ¿En qué puedo ayudarte hoy?' }
  ]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const scrollRef = useRef(null);

  useEffect(() => {
    if (scrollRef.current) {
        scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages, loading]);

  const handleSend = async (e) => {
    e.preventDefault();
    if (!input.trim() || loading) return;

    const userMsg = input.trim();
    setMessages(prev => [...prev, { role: 'user', text: userMsg }]);
    setInput('');
    setLoading(true);

    try {
      const response = await api.post('/chatbot', { message: userMsg });
      const botReply = response.reply || '¡Chuta! No pude procesar eso. ¿Me dices de nuevo?';
      setMessages(prev => [...prev, { role: 'bot', text: botReply }]);
    } catch (err) {
      setMessages(prev => [...prev, { role: 'bot', text: 'Perdona, pero tengo mala conexión ahora. Inténtalo más tarde.' }]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed bottom-10 right-10 z-[200] font-sans">
      
      {/* 🎈 EL ORBE DE ENERGÍA (BURBUJA FLOTANTE) */}
      {!isOpen && (
        <button 
          onClick={() => setIsOpen(true)}
          className="relative w-16 h-16 rounded-full bg-urban-blue flex items-center justify-center text-3xl shadow-neon-glow animate-pulse hover:scale-110 transition-all duration-300 group"
        >
          <div className="absolute inset-0 bg-urban-blue rounded-full blur-[20px] opacity-40 group-hover:opacity-80 transition-opacity"></div>
          <span className="relative z-10 drop-shadow-[0_0_10px_rgba(255,255,255,0.5)]">🤖</span>
        </button>
      )}

      {/* 💬 VENTANA DE CHAT (URBAN DARK) */}
      {isOpen && (
        <div className="glass-dark w-[90vw] max-w-[380px] h-[550px] rounded-[2.5rem] shadow-neon-glow flex flex-col overflow-hidden border border-white/10 animate-in slide-in-from-bottom-5 fade-in duration-300">
          
          {/* HEADER URBANO */}
          <div className="bg-carbon-grey/50 p-6 flex justify-between items-center border-b border-white/5 backdrop-blur-xl">
            <div className="flex items-center gap-4">
                <div className="relative">
                    <div className="w-10 h-10 bg-urban-blue rounded-full flex items-center justify-center text-xl shadow-neon-blue">🤖</div>
                    <div className="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 rounded-full border-2 border-carbon-grey animate-pulse"></div>
                </div>
                <div>
                    <h4 className="text-sm font-black text-white tracking-widest uppercase">Santi <span className="text-urban-blue"></span></h4>
                    <p className="text-[10px] text-gray-500 font-bold uppercase tracking-tight">Especialista iStore</p>
                </div>
            </div>
            <button onClick={() => setIsOpen(false)} className="text-gray-400 hover:text-white transition-colors text-xl">✕</button>
          </div>

          {/* MENSAJES (STYLE APP) */}
          <div ref={scrollRef} className="flex-1 p-6 overflow-y-auto space-y-4 no-scrollbar">
            {messages.map((msg, i) => (
              <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                <div className={`p-4 text-sm font-medium leading-relaxed max-w-[85%] ${msg.role === 'user' 
                  ? 'bg-urban-blue text-white rounded-[1.2rem] rounded-tr-none shadow-neon-blue' 
                  : 'bg-space-grey text-gray-200 rounded-[1.2rem] rounded-tl-none border border-white/5'}`}>
                  {msg.text}
                </div>
              </div>
            ))}
            {loading && (
              <div className="flex justify-start">
                  <div className="bg-space-grey/30 px-4 py-2 rounded-full text-[10px] font-black text-urban-blue tracking-widest uppercase flex items-center gap-2">
                     <span className="w-1 h-1 bg-urban-blue rounded-full animate-bounce"></span>
                     <span className="w-1 h-1 bg-urban-blue rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                     <span className="w-1 h-1 bg-urban-blue rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                     Pensando...
                  </div>
              </div>
            )}
          </div>

          {/* INPUT TÁCTICO */}
          <form onSubmit={handleSend} className="p-6 bg-black/20 border-t border-white/5">
            <div className="relative flex items-center">
                <input 
                    type="text" 
                    placeholder="Escribe un mensaje..." 
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    className="w-full h-12 bg-carbon-grey border border-white/5 pl-6 pr-14 rounded-full text-sm text-white outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all"
                />
                <button 
                    type="submit" 
                    disabled={loading || !input.trim()}
                    className="absolute right-2 w-8 h-8 rounded-full bg-urban-blue text-white flex items-center justify-center text-xs shadow-neon-blue hover:shadow-neon-glow transition-all disabled:opacity-50"
                >
                    ➤
                </button>
            </div>
          </form>

        </div>
      )}

    </div>
  );
}

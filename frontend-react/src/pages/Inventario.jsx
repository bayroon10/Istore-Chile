import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';
import api from '../lib/api';
import { Package, Pencil, Trash2, Download, Plus, X, Image } from 'lucide-react';

export default function Inventario() {
  const [productos, setProductos] = useState([]);
  const [categorias, setCategorias] = useState([]);
  const [formulario, setFormulario] = useState({ name: '', category_id: '', price: '', stock: '', description: '' });
  const [imagenActual, setImagenActual] = useState(null);
  const [editandoId, setEditandoId] = useState(null);
  const [loading, setLoading] = useState(true);

  const cargarDatos = async () => {
    setLoading(true);
    try {
      const [resProd, resCat] = await Promise.all([
        api.get('/products'),
        api.get('/categories')
      ]);
      setProductos(resProd.data || []);
      setCategorias(resCat.data || []);
    } catch (err) {
      Swal.fire({
        title: 'Error',
        text: 'No se pudieron cargar los datos del inventario',
        icon: 'error',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#0071e3'
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { cargarDatos() }, []);

  const guardarProducto = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('name', formulario.name);
    formData.append('category_id', formulario.category_id);
    formData.append('price', formulario.price);
    formData.append('stock', formulario.stock);
    formData.append('description', formulario.description);
    
    if (imagenActual) { 
      formData.append('image', imagenActual); 
    }
    
    if (editandoId) { 
      formData.append('_method', 'PUT'); 
    }

    const url = editandoId ? `/products/${editandoId}` : '/products';

    try {
      // Usamos fetch directo para Multipart/Form-Data con el helper de token
      const token = api.getToken();
      const response = await fetch(`${api.API_BASE}${url}`, {
        method: 'POST',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        },
        body: formData,
      });

      const data = await response.json();

      if (response.ok) {
        Swal.fire({
          title: '¡Éxito!',
          text: 'Producto guardado correctamente',
          icon: 'success',
          background: '#000',
          color: '#fff',
          confirmButtonColor: '#0071e3'
        });
        resetFormulario();
        cargarDatos();
      } else {
        Swal.fire({
          title: 'Error',
          text: data.message || 'No se pudo guardar el producto',
          icon: 'error',
          background: '#000',
          color: '#fff',
          confirmButtonColor: '#0071e3'
        });
      }
    } catch (error) {
      Swal.fire({
        title: 'Error de Conexión',
        text: 'No se pudo contactar al servidor',
        icon: 'error',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#0071e3'
      });
    }
  };

  const resetFormulario = () => {
    setFormulario({ name: '', category_id: '', price: '', stock: '', description: '' });
    setImagenActual(null);
    setEditandoId(null);
    if (document.getElementById('fileInput')) {
      document.getElementById('fileInput').value = "";
    }
  }

  const eliminarProducto = (id) => {
    Swal.fire({
      title: '¿Estás seguro?', 
      text: "¡No podrás revertir esto!", 
      icon: 'warning',
      showCancelButton: true, 
      confirmButtonColor: '#ff3b30', 
      cancelButtonColor: '#333', 
      confirmButtonText: 'Sí, borrar', 
      cancelButtonText: 'Cancelar',
      background: '#000',
      color: '#fff'
    }).then(async (result) => {
      if (result.isConfirmed) {
        try {
          await api.delete(`/products/${id}`);
          Swal.fire({
            title: '¡Borrado!',
            text: 'El producto ha sido eliminado.',
            icon: 'success',
            background: '#000',
            color: '#fff',
            confirmButtonColor: '#0071e3'
          });
          cargarDatos();
        } catch (err) {
          Swal.fire({
            title: 'Error',
            text: err.message,
            icon: 'error',
            background: '#000',
            color: '#fff',
            confirmButtonColor: '#0071e3'
          });
        }
      }
    });
  };

  const editarProducto = (p) => {
    setFormulario({ 
      name: p.name, 
      category_id: p.category?.id || '', 
      price: p.price, 
      stock: p.stock,
      description: p.description || ''
    });
    setEditandoId(p.id);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const descargarExcel = async () => {
    try {
      const response = await fetch(`${api.API_BASE}/reports/products`, {
        method: 'GET',
        headers: { 
          'Authorization': `Bearer ${api.getToken()}`
        }
      });

      if (!response.ok) throw new Error('Error al generar el reporte');

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'Inventario_iStore.xlsx');
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      
      Swal.fire({
        title: '¡Listo!',
        text: 'El Excel se descargó correctamente',
        icon: 'success',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#0071e3'
      });
    } catch (error) {
      Swal.fire({
        title: 'Error',
        text: 'No se pudo descargar el Excel',
        icon: 'error',
        background: '#000',
        color: '#fff',
        confirmButtonColor: '#0071e3'
      });
    }
  };

  if (loading) {
    return (
      <div className="space-y-8 animate-pulse">
        <div className="flex justify-between items-center">
          <div className="space-y-2">
            <div className="h-8 w-48 bg-white/5 rounded-lg"></div>
            <div className="h-4 w-64 bg-white/5 rounded-lg"></div>
          </div>
          <div className="h-12 w-40 bg-white/5 rounded-xl"></div>
        </div>
        <div className="h-48 w-full bg-white/5 rounded-[2rem]"></div>
        <div className="space-y-4">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="h-20 w-full bg-white/5 rounded-[1.5rem]"></div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      {/* HEADER DE INVENTARIO */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h2 className="text-3xl font-black text-white uppercase tracking-tighter flex items-center gap-3">
            <Package className="text-urban-blue" size={32} /> Inventario
          </h2>
          <p className="text-gray-400 font-bold uppercase tracking-widest text-[10px] mt-1">
            Gestiona los productos del marketplace.
          </p>
        </div>
        
        <button 
          onClick={descargarExcel}
          className="px-6 py-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-black uppercase text-xs tracking-widest hover:bg-emerald-500/20 hover:text-emerald-300 hover:shadow-[0_0_20px_rgba(16,185,129,0.15)] transition-all flex items-center gap-2 cursor-pointer"
        >
          <Download size={16} />
          Descargar Excel
        </button>
      </div>

      {/* FORMULARIO EDITAR/CREAR */}
      <div className="glass-dark rounded-[2rem] p-8 border border-white/10 shadow-2xl relative overflow-hidden">
        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-6 flex items-center gap-2">
          {editandoId ? <Pencil size={12} className="text-amber-400" /> : <Plus size={12} className="text-urban-blue" />}
          {editandoId ? 'Editar Producto' : 'Crear Nuevo Producto'}
        </p>

        <form onSubmit={guardarProducto} className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
          {/* Nombre */}
          <div className="relative">
            <input 
              type="text" 
              placeholder="Nombre del Producto" 
              required 
              value={formulario.name} 
              onChange={e => setFormulario({...formulario, name: e.target.value})} 
              className="w-full h-14 bg-white/5 border border-white/10 rounded-[1.2rem] px-5 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all"
            />
          </div>

          {/* Categoría */}
          <div className="relative">
            <select 
              required 
              value={formulario.category_id} 
              onChange={e => setFormulario({...formulario, category_id: e.target.value})} 
              className="w-full h-14 bg-white/5 border border-white/10 rounded-[1.2rem] px-5 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all appearance-none cursor-pointer"
            >
              <option value="" className="bg-pitch-black text-gray-400">Categoría</option>
              {categorias.map(cat => (
                <option key={cat.id} value={cat.id} className="bg-pitch-black text-white">{cat.name}</option>
              ))}
            </select>
            <span className="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">▼</span>
          </div>

          {/* Precio */}
          <div className="relative">
            <input 
              type="number" 
              placeholder="Precio ($)" 
              required 
              value={formulario.price} 
              onChange={e => setFormulario({...formulario, price: e.target.value})} 
              className="w-full h-14 bg-white/5 border border-white/10 rounded-[1.2rem] px-5 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all"
            />
          </div>

          {/* Stock */}
          <div className="relative">
            <input 
              type="number" 
              placeholder="Stock" 
              required 
              value={formulario.stock} 
              onChange={e => setFormulario({...formulario, stock: e.target.value})} 
              className="w-full h-14 bg-white/5 border border-white/10 rounded-[1.2rem] px-5 text-white text-sm outline-none focus:border-urban-blue/50 focus:shadow-neon-blue transition-all"
            />
          </div>

          {/* Subir Imagen */}
          <div className="col-span-1 md:col-span-2 lg:col-span-3 flex items-center gap-4 bg-white/5 border border-dashed border-white/10 rounded-[1.2rem] px-5 h-14 relative group hover:border-white/20 transition-all overflow-hidden">
            <Image size={18} className="text-gray-400 group-hover:text-urban-blue transition-colors" />
            <span className="text-gray-400 text-sm font-semibold truncate">
              {imagenActual ? imagenActual.name : "Subir Imagen del Producto"}
            </span>
            <input 
              type="file" 
              id="fileInput" 
              onChange={e => setImagenActual(e.target.files[0])} 
              className="absolute inset-0 opacity-0 cursor-pointer"
            />
          </div>

          {/* Botones */}
          <div className="col-span-1 flex gap-3 h-14">
            {editandoId && (
              <button 
                type="button" 
                onClick={resetFormulario} 
                className="flex-1 rounded-[1.2rem] bg-white/5 border border-white/10 text-gray-400 font-bold hover:bg-white/10 hover:text-white transition-all text-sm flex items-center justify-center gap-1 cursor-pointer"
              >
                <X size={16} /> Cancelar
              </button>
            )}
            <button 
              type="submit" 
              className={`${editandoId ? 'flex-[1.5]' : 'w-full'} h-full rounded-[1.2rem] bg-urban-blue text-white font-black uppercase text-xs tracking-widest shadow-neon-blue hover:shadow-neon-glow hover:scale-[1.02] active:scale-100 transition-all flex items-center justify-center gap-2 cursor-pointer`}
            >
              {editandoId ? <Pencil size={14} /> : <Plus size={14} />}
              {editandoId ? 'Actualizar' : 'Guardar'}
            </button>
          </div>
        </form>
      </div>

      {/* LISTA DE PRODUCTOS */}
      <div className="glass-dark rounded-[2.5rem] border border-white/10 overflow-hidden shadow-2xl">
        {/* Header de la lista (oculto en mobile) */}
        <div className="hidden md:flex items-center justify-between px-8 py-5 bg-white/5 border-b border-white/10 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">
          <div className="flex-1 flex items-center gap-4">
            <span className="w-14 text-center">Imagen</span>
            <span>Producto</span>
          </div>
          <div className="w-32 text-left">Precio</div>
          <div className="w-24 text-left">Stock</div>
          <div className="w-48 text-center">Acciones</div>
        </div>

        {/* Filas */}
        <div className="divide-y divide-white/5">
          {productos.map(p => (
            <div key={p.id} className="flex flex-col md:flex-row md:items-center justify-between px-8 py-5 hover:bg-white/[0.02] transition-colors gap-4">
              {/* Información */}
              <div className="flex-1 flex items-center gap-5">
                {/* Imagen del Producto */}
                <div className="w-14 h-14 rounded-2xl bg-white flex items-center justify-center p-1.5 border border-white/10 overflow-hidden shrink-0 shadow-inner">
                  <img 
                    src={p.primary_image_url && p.primary_image_url.trim() !== '' ? p.primary_image_url : 'https://placehold.co/100x100/000000/FFFFFF/png?text='} 
                    alt={p.name} 
                    onError={(e) => {
                      const fallback = 'https://placehold.co/100x100/000000/FFFFFF/png?text=';
                      if (e.target.src !== fallback) {
                        e.target.src = fallback;
                      }
                    }}
                    className="max-h-full max-w-full object-contain"
                    loading="lazy"
                  />
                </div>

                {/* Textos del Producto */}
                <div className="truncate">
                  <h4 className="text-white font-bold text-base tracking-tight hover:text-urban-blue transition-colors truncate">{p.name}</h4>
                  <p className="text-gray-400 font-bold uppercase tracking-wider text-[10px] mt-0.5">{p.category?.name || "Sin Categoría"}</p>
                </div>
              </div>

              {/* Precio */}
              <div className="w-full md:w-32 flex md:block items-center justify-between">
                <span className="md:hidden text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Precio</span>
                <span className="text-white font-black tracking-tight text-lg">${p.price.toLocaleString()}</span>
              </div>

              {/* Stock */}
              <div className="w-full md:w-24 flex md:block items-center justify-between">
                <span className="md:hidden text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Stock</span>
                <span className={`font-bold text-sm px-3 py-1 rounded-full ${p.stock <= 3 ? 'bg-red-500/10 text-red-400 border border-red-500/20' : 'bg-white/5 text-gray-300 border border-white/5'}`}>
                  {p.stock} {p.stock === 1 ? 'unidad' : 'unidades'}
                </span>
              </div>

              {/* Acciones */}
              <div className="w-full md:w-48 flex items-center justify-end gap-3 pt-3 md:pt-0 border-t md:border-t-0 border-white/5 md:justify-center">
                <button 
                  onClick={() => editarProducto(p)}
                  className="flex-1 md:flex-none px-4 py-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 font-bold text-xs uppercase tracking-widest hover:bg-amber-500/20 hover:text-amber-300 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                >
                  <Pencil size={12} />
                  Editar
                </button>
                <button 
                  onClick={() => eliminarProducto(p.id)}
                  className="flex-1 md:flex-none px-4 py-2.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 font-bold text-xs uppercase tracking-widest hover:bg-red-500/20 hover:text-red-300 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                >
                  <Trash2 size={12} />
                  Borrar
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
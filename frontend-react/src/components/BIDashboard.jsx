import React from 'react';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { AlertTriangle, Smartphone, TrendingUp, DollarSign } from 'lucide-react';

const dataVentas = [
  { dia: 'Lun', ingresos: 120000 },
  { dia: 'Mar', ingresos: 85000 },
  { dia: 'Mie', ingresos: 210000 },
  { dia: 'Jue', ingresos: 45000 },
  { dia: 'Vie', ingresos: 320000 },
  { dia: 'Sab', ingresos: 450000 },
  { dia: 'Dom', ingresos: 290000 },
];

const dataStock = [
  { nombre: 'Cargador 20W (Alt)', stock: 8, nivel: 'Crítico' },
  { nombre: 'Audífonos i12 (Alt)', stock: 5, nivel: 'Crítico' },
  { nombre: 'iPhone 11 64GB', stock: 3, nivel: 'Crítico' },
  { nombre: 'Cable Lightning', stock: 45, nivel: 'Óptimo' },
  { nombre: 'Funda Silicona', stock: 12, nivel: 'Atención' },
];

export default function BIDashboard() {
  return (
    <div style={{ padding: '24px', backgroundColor: '#f9fafb', minHeight: '100vh', fontFamily: 'system-ui' }}>
      <div style={{ marginBottom: '32px' }}>
        <h1 style={{ fontSize: '28px', fontWeight: 'bold', color: '#1f2937', margin: 0 }}>Panel de Control Comercial</h1>
        <p style={{ color: '#6b7280', margin: '4px 0 0 0' }}>Istore-Chile | Gestión de Accesorios y Equipos</p>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '24px', marginBottom: '32px' }}>
        <div style={{ backgroundColor: 'white', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <p style={{ color: '#6b7280', fontSize: '14px', margin: 0 }}>Ingresos Semanales</p>
              <h3 style={{ fontSize: '24px', fontWeight: 'bold', color: '#1f2937', margin: '8px 0 0 0' }}>$1,520,000</h3>
            </div>
            <DollarSign color="#10b981" size={32} />
          </div>
        </div>
        <div style={{ backgroundColor: 'white', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <p style={{ color: '#6b7280', fontSize: '14px', margin: 0 }}>Riesgos de Quiebre</p>
              <h3 style={{ fontSize: '24px', fontWeight: 'bold', color: '#ef4444', margin: '8px 0 0 0' }}>3 Productos</h3>
            </div>
            <AlertTriangle color="#ef4444" size={32} />
          </div>
        </div>
        <div style={{ backgroundColor: 'white', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <p style={{ color: '#6b7280', fontSize: '14px', margin: 0 }}>Equipos Vendidos</p>
              <h3 style={{ fontSize: '24px', fontWeight: 'bold', color: '#1f2937', margin: '8px 0 0 0' }}>14</h3>
            </div>
            <Smartphone color="#3b82f6" size={32} />
          </div>
        </div>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(500px, 1fr))', gap: '24px' }}>
        <div style={{ backgroundColor: 'white', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
          <h2 style={{ fontSize: '18px', fontWeight: '600', marginBottom: '16px' }}>Curva de Ingresos (Últimos 7 días)</h2>
          <div style={{ height: '300px', width: '100%' }}>
            <ResponsiveContainer>
              <LineChart data={dataVentas}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="dia" />
                <YAxis />
                <Tooltip formatter={(value) => `$${value}`} />
                <Line type="monotone" dataKey="ingresos" stroke="#3b82f6" strokeWidth={3} dot={{ r: 4 }} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>
        <div style={{ backgroundColor: 'white', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
          <h2 style={{ fontSize: '18px', fontWeight: '600', marginBottom: '16px' }}>Accesorios con Stock Crítico</h2>
          <div style={{ height: '300px', width: '100%' }}>
            <ResponsiveContainer>
              <BarChart data={dataStock.filter(d => d.stock <= 15)} layout="vertical" margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" />
                <YAxis dataKey="nombre" type="category" width={150} />
                <Tooltip />
                <Bar dataKey="stock" fill="#ef4444" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  );
}

import { useState, useEffect, useRef } from 'react';

/**
 * useCountUp
 * Anima un número de 0 al valor objetivo en `duration` ms
 * usando requestAnimationFrame con easing exponencial.
 *
 * Uso:
 *   const displayValue = useCountUp(1520000, 1200);
 *   <span>{displayValue.toLocaleString()}</span>
 *
 * @param {number} targetValue  Valor final a alcanzar
 * @param {number} duration     Duración en ms (default: 1200)
 * @param {boolean} start       Si es false, no inicia la animación (p.ej., antes de entrar al viewport)
 * @returns {number}            Valor animado actual
 */
export function useCountUp(targetValue, duration = 1200, start = true) {
  const [current, setCurrent] = useState(0);
  const rafRef = useRef(null);
  const startTimeRef = useRef(null);

  useEffect(() => {
    if (!start || typeof targetValue !== 'number') return;

    // Cancel any existing animation
    if (rafRef.current) cancelAnimationFrame(rafRef.current);
    startTimeRef.current = null;
    setCurrent(0);

    const animate = (timestamp) => {
      if (!startTimeRef.current) startTimeRef.current = timestamp;

      const elapsed = timestamp - startTimeRef.current;
      const progress = Math.min(elapsed / duration, 1);

      // Exponential ease-out: 1 - (1 - progress)^3
      const eased = 1 - Math.pow(1 - progress, 3);
      const value = Math.round(eased * targetValue);

      setCurrent(value);

      if (progress < 1) {
        rafRef.current = requestAnimationFrame(animate);
      } else {
        setCurrent(targetValue);
      }
    };

    rafRef.current = requestAnimationFrame(animate);

    return () => {
      if (rafRef.current) cancelAnimationFrame(rafRef.current);
    };
  }, [targetValue, duration, start]);

  return current;
}

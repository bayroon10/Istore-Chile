import { useEffect, useRef } from 'react';

/**
 * useStaggerReveal
 * Observa un contenedor y agrega la clase 'revealed' a cada elemento hijo
 * que tenga el atributo data-stagger, con un delay escalonado de `staggerMs` ms.
 *
 * Uso:
 *   const gridRef = useStaggerReveal();
 *   <div ref={gridRef}>
 *     <div data-stagger>…</div>
 *     <div data-stagger>…</div>
 *   </div>
 *
 * CSS requerido (ya incluido en index.css):
 *   [data-stagger] { opacity: 0; transform: translateY(20px); transition: opacity .4s, transform .4s; }
 *   [data-stagger].revealed { opacity: 1; transform: translateY(0); }
 */
export function useStaggerReveal(staggerMs = 60, threshold = 0.1) {
  const containerRef = useRef(null);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    const items = Array.from(container.querySelectorAll('[data-stagger]'));
    if (items.length === 0) return;

    // Reset all items before observing
    items.forEach((el) => {
      el.classList.remove('revealed');
      el.style.transitionDelay = '';
    });

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const visibleItems = Array.from(container.querySelectorAll('[data-stagger]'));
            visibleItems.forEach((el, index) => {
              el.style.transitionDelay = `${index * staggerMs}ms`;
              el.classList.add('revealed');
            });
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold }
    );

    observer.observe(container);

    return () => observer.disconnect();
  }, [staggerMs, threshold]);

  return containerRef;
}

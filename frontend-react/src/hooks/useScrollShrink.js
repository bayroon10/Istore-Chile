import { useState, useEffect } from 'react';

/**
 * Returns true once the user has scrolled past 60px.
 * Used to shrink the floating navbar on scroll.
 */
const useScrollShrink = () => {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const handler = () => setScrolled(window.scrollY > 60);
    window.addEventListener('scroll', handler, { passive: true });
    return () => window.removeEventListener('scroll', handler);
  }, []);

  return scrolled;
};

export default useScrollShrink;

import { useEffect } from 'react';
export default function useRealtime(refresh) {
  useEffect(() => {
    let timer;
    const changed = () => { clearTimeout(timer); timer = setTimeout(() => { void refresh(); }, 150); };
    window.addEventListener('dtr:changed', changed);
    return () => { clearTimeout(timer); window.removeEventListener('dtr:changed', changed); };
  }, [refresh]);
}

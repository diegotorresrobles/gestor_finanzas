import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiRequest } from './cuentasApi';
import useRealtime from './useRealtime';

function useFinanceData(paths) {
  const key = JSON.stringify(paths);
  const [resource, setResource] = useState({ key, data: [], loading: true, error: '' });
  const navigate = useNavigate();
  const handleError = useCallback((error) => {
    if (error.status === 401) navigate('/login', { replace: true });
    return Object.values(error.errors ?? {})[0] || error.message || 'No se pudo conectar con el servidor';
  }, [navigate]);
  const load = useCallback(async (signal) => {
    try {
      const values = await Promise.all(JSON.parse(key).map((path) => apiRequest(path, { signal })));
      if (!signal?.aborted) setResource({ key, data: values, loading: false, error: '' });
    } catch (error) {
      if (!signal?.aborted) {
        const message = handleError(error);
        setResource((previous) => ({ key, data: previous.key === key ? previous.data : [], loading: false, error: message }));
      }
    }
  }, [key, handleError]);
  const refresh = useCallback(() => { setResource((previous) => ({ ...previous, loading: true, error: '' })); return load(); }, [load]);
  useEffect(() => {
    const abort = new AbortController();
    // oxlint-disable-next-line react/set-state-in-effect -- The data updates happen only after awaited requests.
    load(abort.signal);
    return () => abort.abort();
  }, [load]);
  useRealtime(load);
  const current = resource.key === key ? resource : { data: [], loading: true, error: '' };
  return { ...current, refresh, handleError };
}

export default useFinanceData;

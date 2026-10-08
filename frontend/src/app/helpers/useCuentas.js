import { useCallback, useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { cuentasRequest } from "./cuentasApi";
import useRealtime from './useRealtime';

function useCuentas() {
  const [cuentas, setCuentas] = useState([]);
  const [tipos, setTipos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleError = useCallback((error) => {
    if (error.status === 401) navigate('/login', { replace: true });
    return error.message || 'No se pudo conectar con el servidor';
  }, [navigate]);

  const load = useCallback(async (signal) => {
    try {
      const [accounts, types] = await Promise.all([cuentasRequest('', { signal }), cuentasRequest('/tipos', { signal })]);
      if (signal?.aborted) return;
      setCuentas(accounts);
      setTipos(types);
    } catch (error) {
      if (!signal?.aborted) setError(handleError(error));
    } finally {
      if (!signal?.aborted) setLoading(false);
    }
  }, [handleError]);

  const refresh = useCallback(() => {
    setLoading(true);
    setError('');
    return load();
  }, [load]);
  useRealtime(load);

  useEffect(() => {
    const controller = new AbortController();
    // oxlint-disable-next-line react/set-state-in-effect -- load updates state only after the awaited network requests.
    load(controller.signal);
    return () => controller.abort();
  }, [load]);

  const tipoNombre = (id) => tipos.find((tipo) => Number(tipo.id) === Number(id))?.tipo ?? `Tipo ${id}`;
  return { cuentas, setCuentas, tipos, tipoNombre, loading, error, refresh, handleError };
}

export default useCuentas;

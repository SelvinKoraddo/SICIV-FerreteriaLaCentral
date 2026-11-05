import axios from 'axios';

/** Evento global emitido cuando el servidor responde que la sesión ya no es válida. */
export const SESSION_EXPIRED_EVENT = 'siciv:session-expired';

/** Rutas donde un 401 es parte del flujo normal y no indica una sesión caducada. */
const AUTH_ROUTES = ['/api/login', '/api/me'];

const api = axios.create({
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    withCredentials: true,
});

/**
 * Al expirar la sesión (401 fuera de las rutas de autenticación) se notifica a la
 * aplicación para que regrese a la pantalla de inicio de sesión. Se usa un evento del
 * DOM para no acoplar el cliente HTTP con el estado de Vue.
 */
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const url = error.config?.url ?? '';

        if (status === 401 && !AUTH_ROUTES.some((route) => url.endsWith(route))) {
            window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
        }

        return Promise.reject(error);
    },
);

export default api;

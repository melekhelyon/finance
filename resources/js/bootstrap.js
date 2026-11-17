import axios from 'axios';
import router from './router';
import { invalidateData, setSession, session } from './live';

window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;
axios.interceptors.request.use(config => {
    const token = localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});
axios.interceptors.response.use(
    async response => {
        const method = response.config.method?.toLowerCase();
        if (
            ['post', 'put', 'patch', 'delete'].includes(method) &&
            !['/api/login', '/api/register', '/api/logout', '/api/admin/backup'].includes(response.config.url)
        ) {
            await invalidateData();
        }
        return response;
    },
    error => {
        if (
            [401, 419].includes(error.response?.status) &&
            !['/api/login', '/api/register'].includes(error.config?.url)
        ) {
            if (error.config?.headers?.Authorization !== `Bearer ${session.token}`) return Promise.reject(error);
            setSession(null);
            router.push({ name: 'app.login' });
        }
        return Promise.reject(error);
    }
);

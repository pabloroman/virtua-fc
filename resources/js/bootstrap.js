import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 419) {
            window.location.href = error.response.data?.redirect || '/login';
        }
        return Promise.reject(error);
    }
);

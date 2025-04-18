import axios from 'axios';
window.axios = axios;

window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-CSRF-TOKEN'] = `${webData?.csrfToken}`;
window.axios.defaults.headers.common['X-Requested-With'] = 'application/json';

// Add a request interceptor
axios.interceptors.request.use(
  function (config) {
    config.headers['Authorization'] = `Bearer ${webData?.sessionToken}`;

    return config;
  },
  function (error) {
    // Do something with request error
    return Promise.reject(error);
  }
);

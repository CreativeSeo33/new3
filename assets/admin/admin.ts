import { createApp } from 'vue';
import App from './App.vue';
import { router } from '@admin/router/index';
import './styles.css';

createApp(App).use(router).mount('#admin-app');

// ai:bootstrap area=admin uses=router,store
import { createApp } from 'vue';
import App from './App.vue';
import { router } from '@admin/router/index';
import './styles.css';

// Import Stimulus
import '../bootstrap.js';

createApp(App).use(router).mount('#admin-app');

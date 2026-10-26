import { createApp } from 'vue';
import App from './App.vue';
import router from './router/index.js';

// Token desain DESIGN.md — CSS dipakai ulang apa adanya, tanpa perubahan.
import '../css/variables.css';
import '../css/base.css';
import '../css/components.css';
import '../css/dashboard.css';
import '../css/print.css';

createApp(App).use(router).mount('#app');

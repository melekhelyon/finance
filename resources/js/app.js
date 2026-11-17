import './bootstrap';
import '../css/app.css';
import '../css/responsive.css';
import '../css/interactions.css';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { startLiveUpdates } from './live';

startLiveUpdates();

createApp(App).use(router).mount('#app');

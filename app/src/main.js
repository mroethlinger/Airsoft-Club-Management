import { createApp } from 'vue';
import App from './App.vue';

// Mounted by the [acm_app] shortcode (phase 3).
const el = document.getElementById('acm-app');
if (el) {
  createApp(App).mount(el);
}

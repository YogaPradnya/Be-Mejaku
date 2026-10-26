<template>
  <div v-if="open" class="modal-backdrop active" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">Masuk Dashboard</h3>
          <div class="font-caption" style="color: #71717a;">Gunakan akun admin backend (ENV: ADMIN_EMAIL)</div>
        </div>
        <button class="modal-close-btn" @click="emit('close')">&times;</button>
      </div>
      <form @submit.prevent="doLogin">
        <div class="form-group">
          <label class="form-label" for="login-email">Email</label>
          <input id="login-email" v-model="form.email" type="email" class="form-input" required placeholder="admin@mejaku.local" autocomplete="username">
        </div>
        <div class="form-group">
          <label class="form-label" for="login-password">Password</label>
          <input id="login-password" v-model="form.password" type="password" class="form-input" required placeholder="••••••••" autocomplete="current-password">
        </div>
        <div class="form-group">
          <label class="form-label" for="login-base">API Base URL</label>
          <input id="login-base" v-model="form.base" type="text" class="form-input font-mono" style="font-size: 12px;" placeholder="http://localhost:8000/api">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">Masuk</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { api, setBaseUrl, setToken, baseUrl } from '../api/client.js';
import { refreshApi, session } from '../store/session.js';
import { notify, notifySuccess } from '../store/alert.js';

defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['close', 'logged-in']);

const form = reactive({ email: '', password: '', base: baseUrl() });
const busy = ref(false);

async function doLogin() {
  busy.value = true;
  try {
    if (form.base.trim()) setBaseUrl(form.base.trim());
    const res = await api.login(form.email.trim(), form.password);
    setToken(res.data.token);
    form.password = '';
    session.loggedIn = true;
    await refreshApi();
    notifySuccess('Masuk berhasil. Selamat datang kembali.');
    emit('logged-in');
    emit('close');
  } catch (e) {
    notify(e.data?.message || e.message || 'Login gagal. Periksa email, password, dan API base URL.');
  } finally {
    busy.value = false;
  }
}
</script>

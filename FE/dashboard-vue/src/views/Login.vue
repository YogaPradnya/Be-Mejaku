<template>
  <div class="login-page">
    <div class="login-card card">
      <div class="login-brand">
        <div class="brand-badge">M</div>
        <div>
          <h1 class="brand-heading">Merchants</h1>
          <p class="brand-caption">Store System &amp; Dashboard</p>
        </div>
      </div>

      <div class="login-header">
        <h2 class="login-title">Masuk Akun</h2>
        <p class="login-desc">Silakan masukkan email dan password admin Anda untuk melanjutkan.</p>
      </div>

      <form class="login-form" @submit.prevent="handleLogin">
        <div class="form-group">
          <label class="form-label" for="login-email">Email</label>
          <input
            id="login-email"
            v-model="form.email"
            type="email"
            class="form-input"
            required
            placeholder="admin@mejaku.local"
            autocomplete="username"
            :disabled="busy"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="login-password">Password</label>
          <input
            id="login-password"
            v-model="form.password"
            type="password"
            class="form-input"
            required
            placeholder="••••••••"
            autocomplete="current-password"
            :disabled="busy"
          >
        </div>

        <button type="submit" class="btn btn-primary login-btn" :disabled="busy">
          {{ busy ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { api, setToken, isLoggedIn } from '../api/client.js';
import { refreshApi, session, fetchStore } from '../store/session.js';
import { notify, notifySuccess } from '../store/alert.js';

const router = useRouter();
const route = useRoute();

const form = reactive({
  email: '',
  password: ''
});

const busy = ref(false);

onMounted(async () => {
  if (isLoggedIn()) {
    try {
      await api.me();
      router.replace('/overview');
    } catch (e) {
      // Token basi/invalid — bersihkan agar form login tampil.
      setToken('');
      session.loggedIn = false;
    }
  }
});

async function handleLogin() {
  if (!form.email || !form.password) {
    notify('Harap isi email dan password.');
    return;
  }

  busy.value = true;
  try {
    const res = await api.login(form.email.trim(), form.password);
    setToken(res.data.token);
    form.password = '';
    session.loggedIn = true;
    await Promise.all([refreshApi(), fetchStore()]);
    notifySuccess('Masuk berhasil. Selamat datang kembali.');

    const target = route.query.redirect || '/overview';
    router.push(target);
  } catch (e) {
    notify(e.data?.message || e.message || 'Login gagal. Periksa kembali email dan password Anda.');
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.login-page {
  width: 100%;
  min-height: 100vh;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--color-surface-soft, #fafafa);
  padding: var(--spacing-xl, 24px) var(--spacing-md, 16px);
  box-sizing: border-box;
}

.login-card {
  width: 100%;
  max-width: 420px;
  margin: auto;
  background: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #e4e4e7);
  border-radius: var(--radius-lg, 12px);
  padding: 32px 28px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.login-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
}

.brand-badge {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background-color: #09090b;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 18px;
  letter-spacing: -0.5px;
}

.brand-heading {
  font-size: 16px;
  font-weight: 700;
  line-height: 1.2;
  margin: 0;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.brand-caption {
  font-size: 11px;
  color: #71717a;
  margin: 0;
}

.login-header {
  margin-bottom: 24px;
}

.login-title {
  font-size: 20px;
  font-weight: 600;
  color: #09090b;
  margin: 0 0 6px 0;
}

.login-desc {
  font-size: 13px;
  color: #71717a;
  margin: 0;
  line-height: 1.5;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.login-btn {
  width: 100%;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 500;
  margin-top: 8px;
}

@media (max-width: 480px) {
  .login-page {
    padding: var(--spacing-md, 16px) var(--spacing-sm, 12px);
  }

  .login-card {
    padding: 24px 18px;
    border-radius: var(--radius-md, 8px);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
  }

  .login-title {
    font-size: 18px;
  }

  .login-btn {
    height: 42px;
    font-size: 14px;
  }
}
</style>

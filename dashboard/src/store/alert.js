/** Popup notifikasi global (error/sukses) — satu mekanisme untuk semua view. */
import { reactive } from 'vue';

let seq = 0;

export const alerts = reactive({ items: [] });

export function notify(message, type = 'error', timeout = 6000) {
  const id = ++seq;
  alerts.items.push({ id, message: String(message || 'Terjadi kesalahan.'), type });
  setTimeout(() => dismiss(id), timeout);
  return id;
}

export function notifySuccess(message, timeout = 4000) {
  return notify(message, 'success', timeout);
}

export function dismiss(id) {
  const i = alerts.items.findIndex((a) => a.id === id);
  if (i >= 0) alerts.items.splice(i, 1);
}

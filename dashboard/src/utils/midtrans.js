/**
 * Helper Snap.js Midtrans yang dipakai halaman customer.
 * Script dimuat dinamis mengikuti environment dari backend.
 */

function snapScriptUrl(isProduction) {
  return isProduction
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js';
}

export function ensureSnapLoaded(clientKey, isProduction) {
  if (typeof window.snap !== 'undefined' && window.snap.pay) return Promise.resolve();
  return new Promise((resolve, reject) => {
    const existing = document.querySelector('script[data-midtrans-snap]');
    if (existing) {
      existing.addEventListener('load', resolve, { once: true });
      existing.addEventListener('error', () => reject(new Error('Gagal memuat Snap.js Midtrans.')), { once: true });
      return;
    }
    const s = document.createElement('script');
    s.src = snapScriptUrl(isProduction);
    s.setAttribute('data-client-key', clientKey || '');
    s.setAttribute('data-midtrans-snap', '1');
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('Gagal memuat Snap.js Midtrans. Periksa koneksi / adblock.'));
    document.head.appendChild(s);
  });
}

export function payWithSnapToken(snapToken, callbacks = {}) {
  if (!window.snap || !window.snap.pay) throw new Error('Snap.js belum termuat.');
  window.snap.pay(snapToken, {
    onSuccess: callbacks.onSuccess || (() => {}),
    onPending: callbacks.onPending || (() => {}),
    onError: callbacks.onError || (() => {}),
    onClose: callbacks.onClose || (() => {})
  });
}

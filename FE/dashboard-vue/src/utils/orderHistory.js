/**
 * orderHistory.js - Manajemen riwayat pesanan customer di perangkat lokal.
 */

const STORAGE_KEY = 'mejaku_customer_order_history';

export function getCustomerOrders() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const list = JSON.parse(raw);
    return Array.isArray(list) ? list : [];
  } catch (e) {
    return [];
  }
}

export function saveCustomerOrder(orderData) {
  if (!orderData || !orderData.order_number) return;
  try {
    const list = getCustomerOrders();
    const existingIndex = list.findIndex((o) => o.order_number === orderData.order_number);

    const itemsSummary = (orderData.items || []).map((it) => {
      const name = it.menu_item?.name || it.name || 'Menu';
      const qty = it.quantity || it.qty || 1;
      return `${name} (${qty}x)`;
    }).join(', ');

    const entry = {
      order_number: orderData.order_number,
      table_code: orderData.table?.code || orderData.table_code || '-',
      total: Number(orderData.total || 0),
      created_at: orderData.created_at || new Date().toISOString(),
      items_count: (orderData.items || []).length,
      items_summary: itemsSummary,
      payment_status: orderData.payment_status || 'pending',
      preparation_status: orderData.preparation_status || 'received',
      raw_items: (orderData.items || []).map((it) => ({
        menu_item: {
          id: it.menu_item?.id || it.menu_item_id,
          name: it.menu_item?.name || it.name || 'Menu'
        },
        portion: it.portion ? { name: it.portion.name } : null
      }))
    };

    if (existingIndex >= 0) {
      list[existingIndex] = { ...list[existingIndex], ...entry };
    } else {
      list.unshift(entry);
    }

    // Simpan maksimal 30 riwayat pesanan terbaru
    const trimmed = list.slice(0, 30);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
  } catch (e) {
    // Abaikan error jika storage penuh
  }
}

export function updateCustomerOrderStatus(orderNumber, statusData = {}) {
  if (!orderNumber) return;
  try {
    const list = getCustomerOrders();
    const target = list.find((o) => o.order_number === orderNumber);
    if (target) {
      if (statusData.payment_status) target.payment_status = statusData.payment_status;
      if (statusData.preparation_status) target.preparation_status = statusData.preparation_status;
      if (statusData.total) target.total = Number(statusData.total);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    }
  } catch (e) {
    // Abaikan error
  }
}

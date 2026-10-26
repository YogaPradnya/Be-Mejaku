export function rupiah(amount) {
  return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
}

export const PREP_LABEL = {
  received: 'Diterima',
  preparing: 'Disiapkan',
  ready: 'Siap',
  completed: 'Selesai'
};

export const PAY_LABEL = {
  pending: 'Belum Bayar',
  checking: 'Dicek',
  paid: 'Lunas',
  failed: 'Gagal',
  expired: 'Kedaluarsa',
  cancelled: 'Dibatalkan'
};

export const PREP_NEXT = {
  received: { to: 'preparing', label: 'Mulai Siapkan' },
  preparing: { to: 'ready', label: 'Siap Disajikan' },
  ready: { to: 'completed', label: 'Selesaikan' }
};

export function formatDateTime(iso) {
  if (!iso) return '-';
  return new Date(iso).toLocaleString('id-ID');
}

/** Samakan bentuk order API backend menjadi baris tabel dashboard. */
export function adaptApiOrder(o) {
  const table = o.table || {};
  const items = (o.items || []).map((it) => {
    const parts = [it.menu_item ? it.menu_item.name : 'Item'];
    if (it.portion) parts.push(`Porsi ${it.portion.name}`);
    if (it.spice_level) parts.push(it.spice_level.name);
    (it.addons || []).forEach((a) => parts.push(`+ ${a.name}`));
    return { name: parts.join(' • '), qty: it.quantity, price: it.unit_price };
  });
  return {
    source: 'api',
    id: o.order_number,
    date: formatDateTime(o.created_at),
    customer: o.notes || '-',
    tableCode: table.code || '-',
    tableFloor: table.floor || '',
    items,
    itemsSummary: items.map((i) => `${i.name} (${i.qty}x)`).join(', '),
    total: o.total,
    prepStatus: o.preparation_status,
    payStatus: o.payment_status,
    method: o.payment && o.payment.method ? o.payment.method : '-',
    cost: { subtotal: o.subtotal, serviceFee: o.service_fee, discount: o.discount || 0, tax: o.tax, total: o.total }
  };
}

/** Bentuk detail untuk OrderDetailModal dari order API penuh atau lokal. */
export function buildOrderDetail(data, source) {
  if (source === 'api') {
    const t = data.table || {};
    const stageMap = { received: 0, preparing: 1, ready: 2, completed: 3 };
    return {
      id: data.order_number,
      tableLabel: `Meja ${t.code || '-'}${t.floor ? ' • ' + t.floor : ''}`,
      prepKey: data.preparation_status,
      payKey: data.payment_status,
      prepLabel: PREP_LABEL[data.preparation_status] || data.preparation_status,
      payLabel: PAY_LABEL[data.payment_status] || data.payment_status,
      stage: stageMap[data.preparation_status] ?? 0,
      method: (data.payment && data.payment.method) || '-',
      items: (data.items || []).map((it) => {
        const det = [];
        if (it.portion) det.push(`Porsi ${it.portion.name} (+${Number(it.portion.surcharge || 0).toLocaleString('id-ID')})`);
        if (it.spice_level) det.push(it.spice_level.name);
        (it.addons || []).forEach((a) => det.push(`+ ${a.name}`));
        return { name: it.menu_item ? it.menu_item.name : 'Item', detail: det.join(' • '), qty: it.quantity, price: it.unit_price };
      }),
      cost: { subtotal: data.subtotal, serviceFee: data.service_fee, discount: data.discount || 0, tax: data.tax, total: data.total }
    };
  }
  const stageMap = { MENUNGGU: 0, DIPROSES: 1, LUNAS: 2, SELESAI: 3 };
  const prepMap = { MENUNGGU: 'received', DIPROSES: 'preparing', LUNAS: 'ready', SELESAI: 'completed' };
  return {
    id: data.id,
    tableLabel: 'Meja -',
    prepKey: prepMap[data.status] || 'received',
    payKey: data.status === 'LUNAS' || data.status === 'SELESAI' ? 'paid' : 'pending',
    prepLabel: PREP_LABEL[prepMap[data.status]] || data.status,
    payLabel: data.status === 'LUNAS' || data.status === 'SELESAI' ? 'Lunas' : 'Belum Bayar',
    stage: stageMap[data.status] ?? 0,
    method: data.method || '-',
    items: (data.items || []).map((i) => ({ name: i.name, detail: '', qty: i.qty, price: i.price })),
    cost: { subtotal: data.total, serviceFee: 0, discount: 0, tax: 0, total: data.total }
  };
}
/** Samakan baris tabel menjadi order siap-struk. */
export function toReceiptOrder(row) {
  if (row.source === 'api') {
    const c = row.cost || {};
    return {
      id: row.id,
      date: row.date,
      customer: `Meja ${row.tableCode}`,
      method: row.method,
      items: row.items.map((i) => ({ name: i.name, qty: i.qty, price: i.price })),
      subtotal: c.subtotal ?? row.total,
      serviceFee: c.serviceFee ?? 0,
      discount: c.discount ?? 0,
      tax: c.tax ?? 0,
      total: c.total ?? row.total,
      paidAmount: row.payStatus === 'paid' ? (c.total ?? row.total) : 0,
      changeAmount: 0
    };
  }
  return row.raw;
}

const mongoose = require('mongoose');

const orderItemSchema = new mongoose.Schema(
  {
    menu_item_id: { type: mongoose.Schema.Types.ObjectId, ref: 'MenuItem', required: true },
    name: { type: String, required: true },
    quantity: { type: Number, required: true, min: 1 },
    portion: { type: String, default: null },
    addons: { type: [String], default: [] },
    unit_price: { type: Number, required: true, min: 0 },
    line_total: { type: Number, required: true, min: 0 },
  },
  { _id: false }
);

// Split-billing: satu order boleh dibayar gabungan beberapa metode.
// Contoh: cash Rp20.000 + QRIS sisanya. Jumlah payments wajib pas dengan total.
const paymentPartSchema = new mongoose.Schema(
  {
    method: { type: String, enum: ['cash', 'qris', 'transfer'], required: true },
    amount: { type: Number, required: true, min: 1 },
    status: { type: String, enum: ['pending', 'paid', 'failed'], default: 'pending' },
    paid_at: { type: Date, default: null },
  },
  { _id: false, timestamps: { createdAt: true, updatedAt: false } }
);

const orderSchema = new mongoose.Schema(
  {
    order_number: { type: String, required: true, unique: true },
    table_code: { type: String, required: true, uppercase: true, trim: true },
    notes: { type: String, default: '' },
    promo_code: { type: String, default: null },
    items: { type: [orderItemSchema], required: true },
    subtotal: { type: Number, required: true, min: 0 },
    service_fee: { type: Number, required: true, min: 0 },
    tax: { type: Number, required: true, min: 0 },
    discount: { type: Number, default: 0, min: 0 },
    total: { type: Number, required: true, min: 0 },
    payments: { type: [paymentPartSchema], default: [] },
    payment_status: { type: String, enum: ['pending', 'paid', 'failed', 'expired', 'cancelled'], default: 'pending' },
    preparation_status: {
      type: String,
      enum: ['received', 'preparing', 'ready', 'completed'],
      default: 'received',
    },
  },
  { timestamps: true }
);

orderSchema.methods.refreshPaymentStatus = function () {
  const paidSum = this.payments.filter((p) => p.status === 'paid').reduce((a, p) => a + p.amount, 0);
  if (paidSum >= this.total && this.total > 0) {
    this.payment_status = 'paid';
    if (this.preparation_status === 'received') this.preparation_status = 'preparing';
  }
  return paidSum;
};

module.exports = mongoose.model('Order', orderSchema);

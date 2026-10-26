const mongoose = require('mongoose');

const promoSchema = new mongoose.Schema(
  {
    code: { type: String, required: true, unique: true, uppercase: true, trim: true },
    type: { type: String, enum: ['percentage', 'fixed'], required: true },
    value: { type: Number, required: true, min: 1 },
    min_order: { type: Number, default: 0, min: 0 },
    max_discount: { type: Number, default: null, min: 0 },
    is_active: { type: Boolean, default: true },
  },
  { timestamps: true }
);

promoSchema.methods.discountFor = function (subtotal) {
  if (!this.is_active || subtotal < (this.min_order || 0)) return 0;
  let d = this.type === 'percentage' ? Math.round((subtotal * this.value) / 100) : this.value;
  if (this.max_discount != null) d = Math.min(d, this.max_discount);
  return Math.min(d, subtotal);
};

module.exports = mongoose.model('Promo', promoSchema);

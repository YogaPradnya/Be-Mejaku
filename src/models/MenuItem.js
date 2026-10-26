const mongoose = require('mongoose');

// Satu merchant (Warung). Foto diambil dari URL Cloudinary hasil upload dashboard.
const menuItemSchema = new mongoose.Schema(
  {
    name: { type: String, required: true, maxlength: 150 },
    slug: { type: String, required: true, unique: true },
    description: { type: String, default: '' },
    base_price: { type: Number, required: true, min: 0 },
    stock: { type: Number, default: 0, min: 0 },
    image: { type: String, default: null },
    has_spice_option: { type: Boolean, default: true },
    portions: [{ name: { type: String, required: true }, surcharge: { type: Number, default: 0, min: 0 } }],
    addons: [{ name: { type: String, required: true }, price: { type: Number, default: 0, min: 0 } }],
    rating: { type: Number, default: 0 },
    rating_count: { type: Number, default: 0 },
    is_available: { type: Boolean, default: true },
  },
  { timestamps: true }
);

module.exports = mongoose.model('MenuItem', menuItemSchema);

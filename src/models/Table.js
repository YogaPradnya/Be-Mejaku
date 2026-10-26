const mongoose = require('mongoose');

const tableSchema = new mongoose.Schema(
  {
    code: { type: String, required: true, unique: true, uppercase: true, trim: true },
    floor: { type: String, default: '' },
    is_active: { type: Boolean, default: true },
  },
  { timestamps: true }
);

module.exports = mongoose.model('Table', tableSchema);

const mongoose = require('mongoose');

async function connectDB(uri) {
  const mongoUri = uri || process.env.MONGODB_URI || 'mongodb://127.0.0.1:27017/mejaku';
  mongoose.set('strictQuery', true);
  await mongoose.connect(mongoUri);
  return mongoose.connection;
}

module.exports = { connectDB };

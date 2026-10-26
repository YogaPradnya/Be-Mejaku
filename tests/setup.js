const mongoose = require('mongoose');
const { MongoMemoryServer } = require('mongodb-memory-server');
const { buildApp } = require('../src/app');

let mongo;

beforeAll(async () => {
  mongo = await MongoMemoryServer.create();
  await mongoose.connect(mongo.getUri());
  process.env.JWT_SECRET = 'test-secret';
  process.env.SERVICE_FEE = '3000';
  process.env.TAX_RATE = '0.10';
});

afterAll(async () => {
  await mongoose.disconnect();
  if (mongo) await mongo.stop();
});

global.testApp = buildApp();

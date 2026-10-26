const express = require('express');
const fs = require('fs');
const path = require('path');
const YAML = require('yaml');
const swaggerUi = require('swagger-ui-express');

const { connectDB } = require('./config/db');

function buildApp() {
  const app = express();
  app.use(express.json());

  // Health tanpa DB agar mudah dicek monitoring.
  app.get('/api/v1/health', (req, res) => {
    const states = ['disconnected', 'connected', 'connecting', 'disconnecting'];
    res.json({ success: true, data: { status: 'ok', db: states[require('mongoose').connection.readyState] || 'unknown' } });
  });

  // Dokumentasi Swagger / OpenAPI 3.0 — Gambar 6-3 SRS/SDS.
  const swaggerDoc = YAML.parse(fs.readFileSync(path.join(__dirname, '..', 'swagger.yaml'), 'utf8'));
  app.use('/api/docs', swaggerUi.serve, swaggerUi.setup(swaggerDoc, { customSiteTitle: 'MejaKu API Docs' }));
  app.get('/api/docs.json', (req, res) => res.json(swaggerDoc));

  app.use('/api/v1/auth', require('./routes/auth'));
  app.use('/api/v1/vendor/menu', require('./routes/menu'));
  app.use('/api/v1/tables', require('./routes/tables'));
  app.use('/api/v1/orders', require('./routes/orders'));
  app.use('/api/v1/orders/:orderNumber/payments', require('./routes/payments'));
  app.use('/api/v1/promos', require('./routes/promos'));

  // 404 JSON untuk path tak dikenal.
  app.use((req, res) => res.status(404).json({ success: false, message: 'Endpoint tidak ditemukan.' }));

  return app;
}

module.exports = { buildApp, connectDB };

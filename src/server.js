'use strict';

const { openDb } = require('./db');
const { createApp } = require('./app');

const port = Number(process.env.PORT) || 3000;
const db = openDb();
const server = createApp(db);

server.listen(port, () => {
  console.log(`Maxcollection running at http://localhost:${port}`);
});

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var secrets_1 = require("../secrets");
var mysql = require("mysql");
exports.pool = mysql.createPool({
    connectionLimit: secrets_1.secrets.mysql.poolSize,
    host: secrets_1.secrets.mysql.host,
    user: secrets_1.secrets.mysql.user,
    password: secrets_1.secrets.mysql.password,
    database: secrets_1.secrets.mysql.schema,
    port: secrets_1.secrets.mysql.port,
});

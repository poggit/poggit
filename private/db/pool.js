"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var secrets_1 = require("../secrets");
var mysql = require("mysql");
exports.pool = mysql.createPool({
    connectionLimit: secrets_1.SECRETS.mysql.poolSize,
    host: secrets_1.SECRETS.mysql.host,
    user: secrets_1.SECRETS.mysql.user,
    password: secrets_1.SECRETS.mysql.password,
    database: secrets_1.SECRETS.mysql.schema,
    port: secrets_1.SECRETS.mysql.port,
});

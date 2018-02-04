"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var fs = require("fs");
var path = require("path");
exports.secrets = JSON.parse(fs.readFileSync(path.join(__dirname, "..", "secret", "secrets.json")).toString("utf8"));

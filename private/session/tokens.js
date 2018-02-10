"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var crypto = require("crypto");
exports.LENGTH_AJAX = 5000;
exports.LENGTH_FLOW = 30 * 1000;
exports.LENGTH_USER_INPUT = 60 * 60 * 1000;
var tokens = {};
function generateToken(length, onError, consumer) {
    crypto.randomBytes(8, function (err, buffer) {
        if (err) {
            onError(err);
        }
        else {
            var token = buffer.toString("hex");
            tokens[token] = Date.now() + length;
            consumer(token);
        }
    });
}
exports.generateToken = generateToken;
function consumeToken(token) {
    if (tokens[token] !== undefined && tokens[token] >= Date.now()) {
        delete tokens[token];
        return true;
    }
    return false;
}
exports.consumeToken = consumeToken;
function cleanTokens() {
    var now = Date.now();
    for (var token in tokens) {
        if (tokens.hasOwnProperty(token) && tokens[token] < now) {
            delete tokens[token];
        }
    }
}
exports.cleanTokens = cleanTokens;

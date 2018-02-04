"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var tokens_1 = require("./tokens");
function persistLocAjax(req, res, next) {
    req.session.persistLoc = req.requireString("path");
    tokens_1.generateToken(tokens_1.LENGTH_FLOW, next, function (token) {
        res.ajaxSuccess({ state: token });
    });
}
exports.persistLocAjax = persistLocAjax;

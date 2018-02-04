"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
function logoutAjax(req, res, next) {
    req.session.auth = null;
    res.ajaxSuccess({});
}
exports.logoutAjax = logoutAjax;

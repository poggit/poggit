"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var db_1 = require("../db");
function keepOnlineAjax(req, res, next) {
    if (req.session.auth !== null) {
        db_1.db.insert("INSERT INTO user_ips (uid, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE time = CURRENT_TIMESTAMP", [req.session.auth.uid, req.realIp], function () { return undefined; });
    }
    db_1.db.select("SELECT KeepOnline(?, ?) onlineCount", [req.realIp, req.session.auth !== null ? req.session.auth.uid : 0], function (result) {
        res.ajaxSuccess(result[0].onlineCount);
    }, next);
}
exports.keepOnlineAjax = keepOnlineAjax;

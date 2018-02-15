"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var secrets_1 = require("../secrets");
var dbUtils;
(function (dbUtils) {
    function createReportError(eh) {
        var trace = secrets_1.SECRETS.meta.debug ? new Error().stack : "";
        return function (err) {
            console.error("Error " + err.code + " executing query: " + err.message);
            console.error("  at: " + err.sql);
            console.error("Stack trace:");
            console.trace(trace);
            eh(err);
        };
    }
    dbUtils.createReportError = createReportError;
    function logQuery(query, args) {
        console.debug("Executing MySQL query: ", query.replace(/[\n\r\t ]+/g, " ").trim(), "|", JSON.stringify(args));
    }
    dbUtils.logQuery = logQuery;
    function qm(count) {
        return new Array(count).fill("?").join(",");
    }
    dbUtils.qm = qm;
})(dbUtils = exports.dbUtils || (exports.dbUtils = {}));

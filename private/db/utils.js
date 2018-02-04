"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var dbUtils;
(function (dbUtils) {
    dbUtils.reportError = function (err) {
        console.error("Error " + err.code + " executing query: " + err.message);
        console.error("Error at: '" + err.sql + "'");
    };
    function logQuery(query, args) {
        console.debug("Executing MySQL query: ", query.replace(/[\n\r\t ]+/g, " ").trim(), "|", JSON.stringify(args));
    }
    dbUtils.logQuery = logQuery;
    function qm(count) {
        return new Array(count).fill("?").join(",");
    }
    dbUtils.qm = qm;
})(dbUtils = exports.dbUtils || (exports.dbUtils = {}));

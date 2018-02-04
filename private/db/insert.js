"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var util_1 = require("../util/util");
var secrets_1 = require("../secrets");
var utils_1 = require("./utils");
var pool_1 = require("./pool");
var dbInsert;
(function (dbInsert) {
    var logQuery = utils_1.dbUtils.logQuery;
    var qm = utils_1.dbUtils.qm;
    var reportError = utils_1.dbUtils.reportError;
    function insert_dup(table, staticFields, updateFields, onError, onInsert) {
        if (onInsert === void 0) { onInsert = function () { return undefined; }; }
        var mergedFields = Object.assign({}, staticFields, updateFields);
        insert("INSERT INTO `" + table + "`\n\t\t\t(" + Object.keys(mergedFields).map(function (col) { return "`" + col + "`"; }).join(",") + ")\n\t\t\tVALUES (" + qm(util_1.util.sizeOfObject(mergedFields)) + ")\n\t\t\tON DUPLICATE KEY UPDATE " + Object.keys(updateFields).map(function (col) { return "`" + col + "` = ?"; }).join(","), Object.values(mergedFields).concat(Object.values(updateFields)), onError, onInsert);
    }
    dbInsert.insert_dup = insert_dup;
    function insert(query, args, onError, onInsert) {
        if (onInsert === void 0) { onInsert = function () { return undefined; }; }
        logQuery(query, args);
        pool_1.pool.query({
            sql: query,
            timeout: secrets_1.secrets.mysql.timeout,
            values: args,
        }, function (err, results) {
            if (err) {
                reportError(err);
                onError(err);
            }
            else {
                onInsert(results.insertId);
            }
        });
    }
    dbInsert.insert = insert;
})(dbInsert = exports.dbInsert || (exports.dbInsert = {}));

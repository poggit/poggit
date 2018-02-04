"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var utils_1 = require("./utils");
var secrets_1 = require("../secrets");
var pool_1 = require("./pool");
var dbDelete;
(function (dbDelete) {
    var reportError = utils_1.dbUtils.reportError;
    var logQuery = utils_1.dbUtils.logQuery;
    function del(table, where, whereArgs, onError) {
        var query = "DELETE FROM `" + table + "` WHERE " + where;
        logQuery(query, Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs());
        pool_1.pool.query({
            sql: query,
            values: Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs(),
            timeout: secrets_1.secrets.mysql.timeout,
        }, function (err) {
            if (err) {
                reportError(err);
                onError(err);
            }
        });
    }
    dbDelete.del = del;
})(dbDelete = exports.dbDelete || (exports.dbDelete = {}));

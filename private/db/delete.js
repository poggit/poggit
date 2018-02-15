"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var utils_1 = require("./utils");
var secrets_1 = require("../secrets");
var pool_1 = require("./pool");
var dbDelete;
(function (dbDelete) {
    var logQuery = utils_1.dbUtils.logQuery;
    var createReportError = utils_1.dbUtils.createReportError;
    function del(table, where, whereArgs, onError) {
        var query = "DELETE FROM `" + table + "` WHERE " + where;
        logQuery(query, Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs());
        onError = createReportError(onError);
        pool_1.pool.query({
            sql: query,
            values: Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs(),
            timeout: secrets_1.SECRETS.mysql.timeout,
        }, function (err) {
            if (err) {
                onError(err);
            }
        });
    }
    dbDelete.del = del;
})(dbDelete = exports.dbDelete || (exports.dbDelete = {}));

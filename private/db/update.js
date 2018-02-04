"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var utils_1 = require("./utils");
var pool_1 = require("./pool");
var secrets_1 = require("../secrets");
var dbUpdate;
(function (dbUpdate) {
    var logQuery = utils_1.dbUtils.logQuery;
    var reportError = utils_1.dbUtils.reportError;
    function update(table, set, where, whereArgs, onError, onUpdated) {
        if (onUpdated === void 0) { onUpdated = function () { return undefined; }; }
        var query = "UPDATE `" + table + "`\n\t\t\t\tSET " + Object.keys(set).map(function (column) { return "`" + column + "` = ?"; }).join(",") + "\n\t\t\t\tWHERE " + where;
        var args = Object.values(set).concat(Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs());
        logQuery(query, args);
        pool_1.pool.query({
            sql: query,
            values: args,
            timeout: secrets_1.secrets.mysql.timeout,
        }, function (err, results) {
            if (err) {
                reportError(err);
                onError(err);
            }
            else {
                onUpdated(results.changedRows);
            }
        });
    }
    dbUpdate.update = update;
})(dbUpdate = exports.dbUpdate || (exports.dbUpdate = {}));

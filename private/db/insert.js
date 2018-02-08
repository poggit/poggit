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
    function insert_dup(table, rows, onError, onInsert) {
        if (onInsert === void 0) { onInsert = function () { return undefined; }; }
        if (rows.length === 0) {
            onInsert(0);
            return;
        }
        var columns = Object.keys(rows[0].mergedFields);
        insert("INSERT INTO `" + table + "`\n\t\t\t(" + columns.map(function (col) { return "`" + col + "`"; }).join(",") + ")\n\t\t\tVALUES " + rows.map(function (row) { return row.getQuery(); }).join(",") + "\n\t\t\tON DUPLICATE KEY UPDATE " + columns.map(function (col) { return "VALUES(`" + col + "`)"; }).join(","), util_1.util.flattenArray(rows.map(function (row) { return row.getArgs(); })), onError, onInsert);
    }
    dbInsert.insert_dup = insert_dup;
    var InsertRow = (function () {
        function InsertRow(staticFields, updateFields) {
            this.staticFields = staticFields;
            this.updateFields = updateFields;
            this.mergedFields = Object.assign({}, staticFields, updateFields);
        }
        InsertRow.prototype.getQuery = function () {
            return "(" + qm(util_1.util.sizeOfObject(this.mergedFields)) + ")";
        };
        InsertRow.prototype.getArgs = function () {
            return Object.values(this.mergedFields);
        };
        return InsertRow;
    }());
    dbInsert.InsertRow = InsertRow;
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

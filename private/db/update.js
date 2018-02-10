"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var utils_1 = require("./utils");
var pool_1 = require("./pool");
var secrets_1 = require("../secrets");
var select_1 = require("./select");
var dbUpdate;
(function (dbUpdate) {
    var logQuery = utils_1.dbUtils.logQuery;
    var reportError = utils_1.dbUtils.reportError;
    var ListWhereClause = select_1.dbSelect.ListWhereClause;
    function update(table, set, where, whereArgs, onError, onUpdated) {
        if (onUpdated === void 0) { onUpdated = function () { return undefined; }; }
        var query = "UPDATE `" + table + "` SET " + Object.keys(set).map(function (column) { return "`" + column + "` = " + (set[column] instanceof CaseValue ? set[column].getArgs() : "?"); }).join(",") + " WHERE " + where;
        var args = [];
        var values = Object.values(set);
        for (var i = 0; i < values.length; ++i) {
            if (values[i] instanceof CaseValue) {
                args.push(values[i].getArgs());
            }
            else {
                args.push(values[i]);
            }
        }
        args = args.concat(Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs());
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
    function update_bulk(table, by, rows, where, whereArgs, onError, onUpdated) {
        if (onUpdated === void 0) { onUpdated = function () { return undefined; }; }
        var set = {};
        for (var key in rows) {
            if (!rows.hasOwnProperty(key)) {
                continue;
            }
            var row = rows[key];
            for (var column in row) {
                if (!row.hasOwnProperty(column)) {
                    continue;
                }
                if (set[column] === undefined) {
                    set[column] = new CaseValue(by);
                }
                set[column].map[key] = row[column];
            }
        }
        var list = new ListWhereClause(by, Object.keys(rows));
        where = "(" + where.toString() + ") AND " + list;
        whereArgs = (Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs()).concat(list.getArgs());
        update(table, set, where, whereArgs, onError, onUpdated);
    }
    dbUpdate.update_bulk = update_bulk;
    var CaseValue = (function () {
        function CaseValue(by, map) {
            if (map === void 0) { map = {}; }
            this.by = by;
            this.map = map;
        }
        CaseValue.prototype.getQuery = function () {
            var output = "CASE ";
            for (var when in this.map) {
                output += "WHEN " + this.by + " = ? THEN ? ";
            }
            return output;
        };
        CaseValue.prototype.getArgs = function () {
            var args = [];
            for (var when in this.map) {
                args.push(when);
                args.push(this.map[when]);
            }
            return args;
        };
        return CaseValue;
    }());
    dbUpdate.CaseValue = CaseValue;
})(dbUpdate = exports.dbUpdate || (exports.dbUpdate = {}));

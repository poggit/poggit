"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var secrets_1 = require("../secrets");
var utils_1 = require("./utils");
var pool_1 = require("./pool");
var dbSelect;
(function (dbSelect) {
    var logQuery = utils_1.dbUtils.logQuery;
    var reportError = utils_1.dbUtils.reportError;
    var qm = utils_1.dbUtils.qm;
    var SelectQuery = (function () {
        function SelectQuery() {
            this.fieldArgs = [];
            this.joins = [];
            this.joinArgs = [];
            this.whereArgs = [];
            this.havingArgs = [];
            this.orderDesc = false;
            this.orderArgs = [];
        }
        SelectQuery.prototype.createQuery = function () {
            var select_expr = [];
            for (var key in this.fields) {
                if (this.fields.hasOwnProperty(key)) {
                    select_expr.push("(" + this.fields[key] + ") AS `" + key + "`");
                }
            }
            return "SELECT " + select_expr.join(",") + " FROM `" + this.from + "`\n\t\t\t" + this.joins.map(function (join) { return join.toString(); }).join(" ") + "\n\t\t\tWHERE " + (Array.isArray(this.where) ? this.where.map(function (c) { return c.toString(); }).join(" ") : this.where) + "\n\t\t\t" + (this.group ? "GROUP BY " + this.group : "") + "\n\t\t\t" + (this.having ? "HAVING " + this.having : "") + "\n\t\t\t" + (this.order ? "ORDER BY " + this.order + " " + (this.orderDesc ? "DESC" : "ASC") : "") + "\n\t\t\t" + (this.limit ? "LIMIT " + this.limit : "");
        };
        SelectQuery.prototype.createArgs = function () {
            if (!(Array.isArray(this.whereArgs))) {
                return this.whereArgs.getArgs();
            }
            var whereArgs = [];
            for (var i in this.whereArgs) {
                var arg = this.whereArgs[i];
                if (Array.isArray(arg)) {
                    whereArgs = whereArgs.concat(arg);
                }
                else if (typeof arg === "object" && !(arg instanceof Date) && !(arg instanceof Buffer) && arg !== null) {
                    whereArgs = whereArgs.concat(arg.getArgs());
                }
                else {
                    whereArgs.push(arg);
                }
            }
            return this.fieldArgs
                .concat(this.joinArgs)
                .concat(whereArgs)
                .concat(Array.isArray(this.havingArgs) ? this.havingArgs : this.havingArgs.getArgs())
                .concat(this.orderArgs);
        };
        SelectQuery.prototype.execute = function (onSelect, onError) {
            select(this.createQuery(), this.createArgs(), onSelect, onError);
        };
        return SelectQuery;
    }());
    dbSelect.SelectQuery = SelectQuery;
    var ListWhereClause = (function () {
        function ListWhereClause(field, literalList) {
            this.field = field;
            this.literalList = literalList;
        }
        ListWhereClause.prototype.toString = function () {
            return this.literalList.length !== 0 ? "(" + this.field + " IN (" + qm(this.literalList.length) + "))" : "0";
        };
        ListWhereClause.prototype.getArgs = function () {
            return this.literalList;
        };
        return ListWhereClause;
    }());
    dbSelect.ListWhereClause = ListWhereClause;
    var Join = (function () {
        function Join(type, table, on) {
            this.type = "";
            this.type = type;
            this.table = table;
            this.on = on;
        }
        Join.prototype.toString = function () {
            return this.type + " JOIN `" + this.table + "` ON " + this.on;
        };
        Join.INNER_ON = function (motherTable, motherColumn, satelliteTable, satelliteColumn) {
            if (satelliteColumn === void 0) { satelliteColumn = motherColumn; }
            return Join.INNER(motherTable, "`" + motherTable + "`.`" + motherColumn + "` = `" + satelliteTable + "`.`" + satelliteColumn + "`");
        };
        Join.INNER = function (table, on) {
            return new Join("INNER", table, on);
        };
        Join.LEFT_ON = function (motherTable, motherColumn, satelliteTable, satelliteColumn) {
            if (satelliteColumn === void 0) { satelliteColumn = motherColumn; }
            return Join.LEFT(motherTable, "`" + motherTable + "`.`" + motherColumn + "` = `" + satelliteTable + "`.`" + satelliteColumn + "`");
        };
        Join.LEFT = function (table, on) {
            return new Join("LEFT", table, on);
        };
        Join.RIGHT_ON = function (motherTable, motherColumn, satelliteTable, satelliteColumn) {
            if (satelliteColumn === void 0) { satelliteColumn = motherColumn; }
            return Join.RIGHT(motherTable, "`" + motherTable + "`.`" + motherColumn + "` = `" + satelliteTable + "`.`" + satelliteColumn + "`");
        };
        Join.RIGHT = function (table, on) {
            return new Join("RIGHT", table, on);
        };
        Join.OUTER_ON = function (motherTable, motherColumn, satelliteTable, satelliteColumn) {
            if (satelliteColumn === void 0) { satelliteColumn = motherColumn; }
            return Join.OUTER(motherTable, "`" + motherTable + "`.`" + motherColumn + "` = `" + satelliteTable + "`.`" + satelliteColumn + "`");
        };
        Join.OUTER = function (table, on) {
            return new Join("OUTER", table, on);
        };
        return Join;
    }());
    dbSelect.Join = Join;
    function select(query, args, onSelect, onError) {
        logQuery(query, args);
        pool_1.pool.query({
            sql: query,
            timeout: secrets_1.secrets.mysql.timeout,
            values: args,
            typeCast: (function (field, next) {
                if (field.type === "BIT" && field.length === 1) {
                    return field.string() === "\u0001";
                }
                return next();
            }),
        }, function (err, results) {
            if (err) {
                reportError(err);
                onError(err);
            }
            else {
                onSelect(results);
            }
        });
    }
    dbSelect.select = select;
})(dbSelect = exports.dbSelect || (exports.dbSelect = {}));

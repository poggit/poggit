"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var secrets_1 = require("../secrets");
var utils_1 = require("./utils");
var pool_1 = require("./pool");
var dbSelect;
(function (dbSelect) {
    var logQuery = utils_1.dbUtils.logQuery;
    var qm = utils_1.dbUtils.qm;
    var createReportError = utils_1.dbUtils.createReportError;
    var SelectQuery = (function () {
        function SelectQuery() {
            this.fieldArgs = [];
            this.joins = [];
            this.joinArgs = [];
            this.whereArgs = [];
            this.havingArgs = [];
            this.orderArgs = [];
        }
        SelectQuery.prototype.createQuery = function () {
            var select_expr = [];
            for (var key in this.fields) {
                if (this.fields.hasOwnProperty(key)) {
                    select_expr.push("(" + this.fields[key] + ") AS `" + key + "`");
                }
            }
            return "SELECT " + select_expr.join(",") + " FROM `" + this.from + "` " +
                (this.joins.map(function (join) { return join.toString(); }).join(" ") + " WHERE ") +
                (Array.isArray(this.where) ? this.where.map(function (c) { return c.toString(); }).join(" ") : this.where) +
                (this.group ? " GROUP BY " + this.group : "") +
                (this.having ? " HAVING " + this.having : "") +
                (this.order ? " ORDER BY " + this.order : "") +
                (this.limit ? " LIMIT " + this.limit : "");
        };
        SelectQuery.prototype.createArgs = function () {
            if (!(Array.isArray(this.whereArgs))) {
                return this.whereArgs.getArgs();
            }
            var whereArgs = [];
            for (var _i = 0, _a = this.whereArgs; _i < _a.length; _i++) {
                var arg = _a[_i];
                if (Array.isArray(arg)) {
                    whereArgs = whereArgs.concat(arg);
                }
                else {
                    if (typeof arg === "object" && !(arg instanceof Date) && !(arg instanceof Buffer) && arg !== null) {
                        whereArgs = whereArgs.concat(arg.getArgs());
                    }
                    else {
                        whereArgs.push(arg);
                    }
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
        function Join(type, table, on, alias) {
            if (alias === void 0) { alias = table; }
            this.type = "";
            this.type = type;
            this.table = table;
            this.alias = alias;
            this.on = on;
        }
        Join.prototype.toString = function () {
            return this.type + " JOIN `" + this.table + "` `" + this.alias + "` ON " + this.on;
        };
        Join.ON = function (type, motherTable, motherColumn, satelliteTable, satelliteColumn, motherTableAlias) {
            if (satelliteColumn === void 0) { satelliteColumn = motherColumn; }
            if (motherTableAlias === void 0) { motherTableAlias = motherTable; }
            return new Join(type, motherTable, "`" + motherTableAlias + "`.`" + motherColumn + "` = `" + satelliteTable + "`.`" + satelliteColumn + "`", motherTableAlias);
        };
        return Join;
    }());
    dbSelect.Join = Join;
    function select(query, args, onSelect, onError) {
        logQuery(query, args);
        onError = createReportError(onError);
        pool_1.pool.query({
            sql: query,
            timeout: secrets_1.SECRETS.mysql.timeout,
            values: args,
            typeCast: (function (field, next) {
                if (field.type === "BIT" && field.length === 1) {
                    return field.string() === "\u0001";
                }
                return next();
            }),
        }, function (err, results) {
            if (err) {
                onError(err);
            }
            else {
                onSelect(results);
            }
        });
    }
    dbSelect.select = select;
})(dbSelect = exports.dbSelect || (exports.dbSelect = {}));

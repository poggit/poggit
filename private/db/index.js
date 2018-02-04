"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var select_1 = require("./select");
var update_1 = require("./update");
var delete_1 = require("./delete");
var insert_1 = require("./insert");
var db;
(function (db) {
    db.SelectQuery = select_1.dbSelect.SelectQuery;
    db.ListWhereClause = select_1.dbSelect.ListWhereClause;
    db.Join = select_1.dbSelect.Join;
    db.select = select_1.dbSelect.select;
    db.insert_dup = insert_1.dbInsert.insert_dup;
    db.insert = insert_1.dbInsert.insert;
    db.update = update_1.dbUpdate.update;
    db.del = delete_1.dbDelete.del;
})(db = exports.db || (exports.db = {}));

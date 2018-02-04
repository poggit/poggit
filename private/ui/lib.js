"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var date_format = require("dateformat");
function date(date) {
    return date_format(date, "mmm d yyyy");
}
exports.date = date;

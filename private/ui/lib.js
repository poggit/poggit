"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var date_format = require("dateformat");
function isoDate(date) {
    return date_format("isoDateTime");
}
exports.isoDate = isoDate;
function date(date) {
    return "<span title=\"" + date_format(date, "HH:MM:ss") + "\">" + date_format(date, "mmm d yyyy") + "</span>";
}
exports.date = date;
function quantity(quantity, singular, plural) {
    if (plural === void 0) { plural = singular + "s"; }
    return quantity === 0 ? "no " + plural : quantity + " " + (quantity > 1 ? plural : singular);
}
exports.quantity = quantity;
function average(array, n) {
    if (n === void 0) { n = NaN; }
    if (array.length === 0) {
        return NaN;
    }
    return array.reduce(function (a, b) { return a + b; }, 0) / array.length;
}
exports.average = average;
var componentTerms_1 = require("./componentTerms");
exports.SECTION_CI = componentTerms_1.SECTION_CI;
exports.SECTION_RELEASE = componentTerms_1.SECTION_RELEASE;

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var util;
(function (util) {
    function sizeOfObject(object) {
        var i = 0;
        for (var k in object) {
            if (object.hasOwnProperty(k)) {
                ++i;
            }
        }
        return i;
    }
    util.sizeOfObject = sizeOfObject;
    function waitAll(forAll, eventually) {
        var left = forAll.length;
        for (var i = 0; i < forAll.length; ++i) {
            forAll[i](function () {
                if (--left === 0) {
                    eventually();
                }
            });
        }
    }
    util.waitAll = waitAll;
})(util = exports.util || (exports.util = {}));

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
    function flattenArray(arrays) {
        return (_a = []).concat.apply(_a, arrays);
        var _a;
    }
    util.flattenArray = flattenArray;
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
    function gatherAll(forAll, eventually) {
        var left = forAll.length;
        var values = Array(forAll.length);
        var _loop_1 = function (i) {
            forAll[i](function (value) {
                values[i] = value;
                if (--left === 0) {
                    eventually.apply(void 0, values);
                }
            });
        };
        for (var i = 0; i < forAll.length; ++i) {
            _loop_1(i);
        }
    }
    util.gatherAll = gatherAll;
})(util = exports.util || (exports.util = {}));
exports.nop = function () {
};

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var ResFile_class_1 = require("../res/ResFile.class");
var secrets_1 = require("../secrets");
var jsQueue = (function () {
    function jsQueue() {
        this.files = [];
    }
    jsQueue.prototype.add = function (dir, internal, name, ext, min) {
        if (min === void 0) { min = !secrets_1.secrets.meta.debug; }
        this.files.push(new ResFile_class_1.ResFile(dir, internal, name, ext, min));
        return "";
    };
    jsQueue.prototype.flush = function (salt) {
        return this.files.map(function (file) { return file.html(salt); }).join();
    };
    return jsQueue;
}());
exports.jsQueue = jsQueue;

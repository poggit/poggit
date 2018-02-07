"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var version_1 = require("../version");
var fs = require("fs");
var path = require("path");
var config_1 = require("../consts/config");
var fileSizeCache = {};
var ResFile = (function () {
    function ResFile(dir, internal, name, ext, min) {
        this.dir = dir;
        this.internal = internal;
        this.name = name;
        this.ext = ext;
        this.min = min;
        var file = "" + this.name + (this.min ? ".min" : "") + "." + this.ext;
        this.pathName = "/" + this.dir + "/" + file;
        this.file = path.join(version_1.POGGIT.INSTALL_ROOT, internal, file);
    }
    ResFile.prototype.getPathName = function () {
        return this.pathName;
    };
    ResFile.prototype.getFile = function () {
        return this.file;
    };
    ResFile.prototype.html = function (salt) {
        var cache;
        if (fileSizeCache[this.getFile()] === undefined) {
            cache = fileSizeCache[this.getFile()]
                = fs.statSync(this.getFile()).size > config_1.Config.MAX_INLINE_SIZE ? null : fs.readFileSync(this.getFile()).toString();
        }
        else {
            cache = fileSizeCache[this.getFile()];
        }
        if (this.ext === "js") {
            return cache === null ?
                "<script src='" + this.getPathName() + "/" + (this.name.replace(".", "Dot").replace("/", "Slash") + salt) + "'></script>" :
                "<script>" + cache + "</script>";
        }
        if (this.ext === "css") {
            return cache === null ?
                "<link rel=\"stylesheet\" type=\"text/css\" href=\"" + this.getPathName() + "/" + salt + "\"/>" :
                "<style>" + cache + "</style>";
        }
        return "never";
    };
    return ResFile;
}());
exports.ResFile = ResFile;

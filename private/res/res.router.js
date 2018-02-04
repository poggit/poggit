"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var path_1 = require("path");
var version_1 = require("../version");
var fs = require("fs");
function res(dir) {
    var res = express_1.Router();
    res.get("*", function (req, res, next) {
        var pieces = req.path.substr(1).split("/");
        if (pieces.length) {
            if (pieces[pieces.length - 1].indexOf(".") === -1) {
                pieces.pop();
            }
            var target_1 = path_1.join(version_1.POGGIT.INSTALL_ROOT, dir, pieces.join("/"));
            if (path_1.relative(path_1.join(version_1.POGGIT.INSTALL_ROOT), target_1).replace("\\", "/").indexOf(dir + "/") !== 0) {
                res.status(403).set("Content-Type", "text/plain").send("This file is not accessible by you.");
                return;
            }
            fs.access(target_1, fs.constants.R_OK, function (err) {
                if (err) {
                    res.status(404).set("Content-Type", "text/plain").send("This file does not exist.");
                }
                else {
                    res.sendFile(target_1, {
                        maxAge: 604800000,
                    }, function (err) { return next(err); });
                }
            });
        }
    });
    return res;
}
exports.res = res;

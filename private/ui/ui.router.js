"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var PageInfo_class_1 = require("./PageInfo.class");
var version_1 = require("../version");
var secrets_1 = require("../secrets");
var rand = require("randomstring");
var home_ui_1 = require("./home.ui");
var jsQueue_class_1 = require("./jsQueue.class");
var ResFile_1 = require("../res/ResFile");
exports.ui = express_1.Router();
exports.ui.use("/", function (req, res, next) {
    var pageInfo = res.locals.pageInfo
        = new PageInfo_class_1.PageInfo("https://poggit.pmmp.io" + req.path, secrets_1.secrets.meta.debug ? rand.generate({
            length: 5,
            charset: "alphanumeric",
            readable: true,
        }) : version_1.POGGIT.GIT_COMMIT);
    res.locals.sessionData = req.session.toSessionData();
    res.locals.js = new jsQueue_class_1.jsQueue;
    res.locals.css = function (file) {
        return new ResFile_1.ResFile("res", "res", file, "css", !secrets_1.secrets.meta.debug).html(pageInfo.resSalt);
    };
    next();
});
exports.ui.get("/", home_ui_1.home_ui);

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var PageInfo_class_1 = require("./PageInfo.class");
var version_1 = require("../version");
var secrets_1 = require("../secrets");
var rand = require("randomstring");
var home_ui_1 = require("./home.ui");
var jsQueue_class_1 = require("./jsQueue.class");
var ResFile_class_1 = require("../res/ResFile.class");
var list_router_1 = require("./release/list/list.router");
var details_router_1 = require("./release/details/details.router");
exports.ui = express_1.Router();
exports.ui.use("/", function (req, res, next) {
    var pageInfo = res.locals.pageInfo
        = new PageInfo_class_1.PageInfo("https://poggit.pmmp.io" + req.path, secrets_1.SECRETS.meta.debug ? rand.generate({
            length: 5,
            charset: "alphanumeric",
            readable: true,
        }) : version_1.POGGIT.GIT_COMMIT);
    res.locals.sessionData = req.session.toSessionData();
    res.locals.js = new jsQueue_class_1.jsQueue;
    res.locals.css = function (file) {
        return new ResFile_class_1.ResFile("res", "res", file, "css", !secrets_1.SECRETS.meta.debug).html(pageInfo.resSalt);
    };
    next();
});
exports.ui.get("/", home_ui_1.home_ui);
exports.ui.use("/plugins", list_router_1.list_ui);
exports.ui.use("/pi", list_router_1.list_ui);
exports.ui.use("/p", details_router_1.details_ui);
exports.ui.use("/plugin", details_router_1.details_ui);

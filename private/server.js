"use strict";
var express = require("express");
var path = require("path");
var logger = require("morgan");
var body_parser = require("body-parser");
var cookie_parser = require("cookie-parser");
var serve_favicon = require("serve-favicon");
var compression = require("compression");
var ui_lib = require("./ui/lib");
var ui_router_1 = require("./ui/ui.router");
var secrets_1 = require("./secrets");
var cookies_app_1 = require("./session/cookies.app");
var res_router_1 = require("./res/res.router");
var people_1 = require("./lib/people");
var release_1 = require("./lib/release");
var version_1 = require("./version");
var csrf_router_1 = require("./session/csrf.router");
var tokens_1 = require("./session/tokens");
var authFlow_router_1 = require("./session/auth/authFlow.router");
var app = express();
app.set("views", path.join(__dirname, "..", "views"));
app.set("view engine", "pug");
app.use(logger("dev"));
app.use(body_parser.json());
app.use(body_parser.urlencoded({ extended: false }));
app.use(cookie_parser());
app.use(compression({}));
app.use(function (req, res, next) {
    res.set("X-Powered-By", "Express/4, Poggit/" + version_1.POGGIT.VERSION);
    req.realIp = req.headers["cf-connecting-ip"] || req.connection.remoteAddress;
    next();
});
app.use(serve_favicon(path.join(__dirname, "..", "res", "poggit.png")));
app.use("/res", res_router_1.res("res"));
app.use("/js", res_router_1.res("legacy"));
app.use("/ts", res_router_1.res("public"));
app.use(cookies_app_1.auth);
app.use("/gamma.flow", authFlow_router_1.authFlow);
app.use("/csrf", csrf_router_1.csrf);
app.use(ui_router_1.ui);
setInterval(tokens_1.cleanTokens, 10000);
setInterval(cookies_app_1.cleanSessions, 10000);
app.locals.PoggitConsts = {
    AdminLevel: people_1.people.AdminLevel,
    Staff: people_1.people.StaffList,
    Release: release_1.Release,
    Debug: secrets_1.secrets.meta.debug,
    App: {
        ClientId: secrets_1.secrets.app.clientId,
        AppId: secrets_1.secrets.app.id,
        AppName: secrets_1.secrets.app.urlName,
    },
};
app.locals.secrets = secrets_1.secrets;
app.locals.POGGIT = version_1.POGGIT;
app.locals.lib = ui_lib;
module.exports = app;

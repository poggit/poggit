"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var tokens_1 = require("./tokens");
var keepOnline_ajax_1 = require("./keepOnline.ajax");
var persistLoc_ajax_1 = require("./persistLoc.ajax");
var logoutAjax_ajax_1 = require("./logoutAjax.ajax");
var body_parser = require("body-parser");
exports.csrf = express_1.Router();
exports.csrf.post("/", function (req, res, next) {
    tokens_1.generateToken(tokens_1.LENGTH_AJAX, next, function (token) {
        res.status(201).set("content-type", "text/plain").send(token);
    });
});
exports.csrf.use(body_parser.json());
exports.csrf.use(function (req, res, next) {
    if (req.headers["x-poggit-csrf"] === undefined) {
        res.status(401).set("content-type", "text/plain").send("Missing x-poggit-csrf header");
        return;
    }
    var token = req.headers["x-poggit-csrf"];
    if (typeof token !== "string" || !tokens_1.consumeToken(token)) {
        res.status(401).set("content-type", "text/plain").send("CSRF token is invalid or has expired");
        return;
    }
    req.requireBoolean = function (name) {
        var ret = req.body[name];
        if (typeof ret !== "boolean") {
            throw "Missing boolean parameter \"" + name + "\"";
        }
        return ret;
    };
    req.requireNumber = function (name) {
        var ret = req.body[name];
        if (typeof ret !== "number") {
            throw "Missing number parameter \"" + name + "\"";
        }
        return ret;
    };
    req.requireString = function (name) {
        var ret = req.body[name];
        if (typeof ret !== "string") {
            throw new AjaxError("Missing string parameter \"" + name + "\"");
        }
        return ret;
    };
    res.ajaxSuccess = function (data) {
        if (data === void 0) { data = {}; }
        res.status(200).set("content-type", "application/json").send(JSON.stringify({
            success: true,
            data: data,
        }));
    };
    res.ajaxError = function (message) {
        res.status(200).set("content-type", "application/json").send(JSON.stringify({
            success: false,
            data: message,
        }));
    };
    next();
});
var AjaxError = (function () {
    function AjaxError(message) {
        this.message = message;
    }
    return AjaxError;
}());
exports.AjaxError = AjaxError;
function ajaxDelegate(path, delegate) {
    exports.csrf.post("/" + path, function (req, res, next) {
        try {
            delegate(req, res, next);
        }
        catch (thrown) {
            if (thrown instanceof AjaxError) {
                res.ajaxError(thrown.message);
            }
            else {
                throw thrown;
            }
        }
    });
}
ajaxDelegate("session/online", keepOnline_ajax_1.keepOnlineAjax);
ajaxDelegate("login/persistLoc", persistLoc_ajax_1.persistLocAjax);
ajaxDelegate("login/logout", logoutAjax_ajax_1.logoutAjax);

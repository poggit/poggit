"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var crypto = require("crypto");
var Session_class_1 = require("./Session.class");
exports.SESSION_DURATION = 2 * 60 * 60 * 1000;
exports.sessions = {};
exports.auth = function (req, res, next) {
    var cookie;
    if (req.cookies["PoggitSess"] !== undefined) {
        cookie = req.cookies["PoggitSess"];
        if (exports.sessions[cookie] !== undefined) {
            exports.sessions[cookie].refresh(exports.SESSION_DURATION);
            execute();
            return;
        }
    }
    crypto.randomBytes(16, function (err, buf) {
        if (err) {
            next(err);
            return;
        }
        res.cookie("PoggitSess", cookie = buf.toString("hex"), {
            httpOnly: true,
            sameSite: false,
            secure: true,
            maxAge: exports.SESSION_DURATION,
        });
        exports.sessions[cookie] = new Session_class_1.Session(exports.SESSION_DURATION);
        execute();
    });
    function execute() {
        req.session = exports.sessions[cookie];
        if (req.session === null) {
            next(new Error("req.session is null"));
        }
        next();
    }
};
function cleanSessions() {
    for (var cookie in exports.sessions) {
        if (exports.sessions[cookie].expired()) {
            delete exports.sessions[cookie];
        }
    }
}
exports.cleanSessions = cleanSessions;

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var tokens_1 = require("../tokens");
var request = require("request");
var secrets_1 = require("../../secrets");
var query_string = require("querystring");
var Authentication_class_1 = require("./Authentication.class");
var gh_1 = require("../../gh");
exports.authFlow = express_1.Router();
exports.authFlow.get("/auth", function (req, res, next) {
    if (!req.query.code || !req.query.state) {
        res.redirect("/");
        return;
    }
    var code = req.query.code;
    var state = req.query.state;
    if (!tokens_1.consumeToken(state)) {
        res.status(401).render("error", {
            error: new Error("Please enable cookies. If you did not click the \"Login with GitHub\" button on Poggit, take caution -- you may have been redirected from a phishing site."),
        });
        return;
    }
    request.post("https://github.com/login/oauth/access_token", {
        form: {
            client_id: secrets_1.secrets.app.clientId,
            client_secret: secrets_1.secrets.app.clientSecret,
            code: code,
            state: state,
        },
    }, function (error, response, body) {
        if (error) {
            next(error);
            return;
        }
        var token = query_string.parse(body).access_token;
        gh_1.gh.me(token, function (user) {
            console.log("Login: " + user.login);
            req.session.auth = new Authentication_class_1.Authentication(user.id, user.login, token);
            res.redirect(req.session.persistLoc || "/");
        }, next);
        res.cookie("gamma_logged_in", "true", {
            httpOnly: false,
            expires: new Date(Date.now() + 86400e+3 * 10000),
            secure: true,
        });
    });
});

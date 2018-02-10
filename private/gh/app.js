"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var version_1 = require("../version");
var path_1 = require("path");
var fs = require("fs");
var jwt = require("jsonwebtoken");
var secrets_1 = require("../secrets");
var index_1 = require("./index");
var ghApp;
(function (ghApp) {
    var PRIVATE_KEY_PATH = path_1.join(version_1.POGGIT.INSTALL_ROOT, "secret", "app.pem");
    var jwtCache;
    function getJwt(consumer, onError) {
        var now = Math.floor(Date.now() / 1000);
        if (jwtCache !== undefined && now + 5 < jwtCache.exp) {
            consumer(jwtCache.value);
            return;
        }
        fs.readFile(PRIVATE_KEY_PATH, "utf8", function (err, pem) {
            jwt.sign({
                iat: now,
                exp: now + 600,
                iss: secrets_1.secrets.app.id,
            }, pem, { algorithm: "RS256" }, function (err, token) {
                if (err) {
                    onError(err);
                }
                else {
                    consumer(token);
                }
            });
        });
    }
    ghApp.getJwt = getJwt;
    function asInstallation(installId, consumer, onError) {
        getJwt(function (token) {
            index_1.gh.post(token, "installations/" + installId + "/access_tokens", {}, function (result) {
                consumer(result.token);
            }, onError);
        }, onError);
    }
    ghApp.asInstallation = asInstallation;
})(ghApp = exports.ghApp || (exports.ghApp = {}));

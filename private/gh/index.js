"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var request = require("request");
var gh;
(function (gh) {
    function me(token, handler, error) {
        get(token, "user", handler, error);
    }
    gh.me = me;
    function get(token, path, handle, onError) {
        request.get("https://api.github.com/" + path, {
            headers: {
                authorization: "bearer " + token,
                accept: [
                    "application/vnd.github.v3+json",
                    "application/vnd.github.mercy-preview+json",
                    "application/vnd.github.machine-man-preview+json",
                    "application/vnd.github.cloak-preview+json",
                    "application/vnd.github.jean-grey-preview+json",
                ].join(","),
                "user-agent": "Poggit/2.0-gamma"
            },
            timeout: 10000,
        }, function (error, response, body) {
            if (error) {
                onError(error);
            }
            else {
                handle(JSON.parse(body));
            }
        });
    }
})(gh = exports.gh || (exports.gh = {}));

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var request = require("request");
var webhooks_1 = require("./webhooks");
var gh;
(function (gh) {
    gh.wh = webhooks_1.ghWebhooks;
    var pathRepo = function (repoIdentifier) { return typeof repoIdentifier === "number" ?
        "repositories/" + repoIdentifier : "repos/" + repoIdentifier.owner + "/" + repoIdentifier.name; };
    var hashRepo = function (repoIdentifier) { return typeof repoIdentifier === "number" ?
        "" + repoIdentifier : repoIdentifier.owner + "/" + repoIdentifier.name; };
    var permissionCache = {};
    function me(token, handler, error) {
        get(token, "user", handler, error);
    }
    gh.me = me;
    function repo(uid, token, repo, handler, error) {
        get(token, pathRepo(repo), function (repo) {
            if (repo.permissions !== undefined) {
                permissionCache[uid + ":" + repo.id] = permissionCache[uid + ":" + repo.full_name] = {
                    updated: new Date(),
                    value: repo.permissions,
                };
            }
            handler(repo);
        }, error);
    }
    gh.repo = repo;
    function testPermission(uid, token, repoId, permission, consumer, error) {
        var hash = uid + ":" + hashRepo(repoId);
        if (permissionCache[hash] !== undefined && new Date().getTime() - permissionCache[hash].updated.getTime() < 3600e+3) {
            consumer(permissionCache[hash].value[permission]);
        }
        repo(uid, token, repoId, function () { return consumer(permissionCache[uid + ":" + hashRepo(repoId)].value[permission]); }, error);
    }
    gh.testPermission = testPermission;
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

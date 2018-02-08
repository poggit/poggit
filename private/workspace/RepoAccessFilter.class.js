"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var gh_1 = require("../gh");
var RepoAccessFilter = (function () {
    function RepoAccessFilter(repoId, permission) {
        if (permission === void 0) { permission = "admin"; }
        this.repoId = repoId;
        this.permission = permission;
    }
    RepoAccessFilter.prototype.allow = function (req, res, next, error) {
        var _this = this;
        var auth = req.session.auth;
        if (auth === null) {
            error(new Error("You need to login with GitHub to view this resource."));
        }
        else {
            gh_1.gh.testPermission(auth.uid, auth.token, this.repoId, this.permission, function (success) {
                if (success) {
                    next();
                }
                else {
                    error(new Error("You need " + _this.permission + " access to repo #" + _this.repoId + " to view this resource.\nFor security reasons, we are not going to reveal the name of this repo. Make sure you have logged in to the correct account. You are currently logged in as @" + auth.name + "."));
                }
            }, error);
        }
    };
    return RepoAccessFilter;
}());
exports.RepoAccessFilter = RepoAccessFilter;

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var index_1 = require("../util/index");
var ThumbnailRelease_class_1 = require("../release/ThumbnailRelease.class");
var config_1 = require("../consts/config");
function home_ui(req, res, next) {
    res.locals.pageInfo.title = "Poggit";
    res.locals.index = {
        recentReleases: [],
    };
    index_1.util.waitAll([
        function (complete) {
            ThumbnailRelease_class_1.ThumbnailRelease.fromConstraint(function (query) {
                query.where = "state >= ?";
                query.whereArgs = [config_1.Config.MIN_PUBLIC_RELEASE_STATE];
                query.order = "releases.updateTime";
                query.orderDesc = true;
                query.limit = 10;
            }, function (releases) {
                res.locals.index.recentReleases = releases;
                complete();
            }, function (error) { return next(error); });
        },
    ], function () { return res.render(req.session.auth !== null ? "index/member" : "index/guest"); });
}
exports.home_ui = home_ui;

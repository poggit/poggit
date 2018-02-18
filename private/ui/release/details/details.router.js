"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var release_1 = require("../../../consts/release");
var DetailedRelease_class_1 = require("../../../release/DetailedRelease.class");
var config_1 = require("../../../consts/config");
var ReleasePerm_class_1 = require("./ReleasePerm.class");
var ThumbnailRelease_class_1 = require("../../../release/ThumbnailRelease.class");
var api_1 = require("../../../pm/api");
exports.details_ui = express_1.Router();
exports.details_ui.get("/", function (req, res, next) {
    if (req.query.releaseId !== undefined) {
        var releaseId = parseInt(req.query.releaseId);
        if (releaseId >= 1) {
            specificReleaseId(req, res, next, releaseId);
            return;
        }
    }
    res.redirect("/plugins");
});
exports.details_ui.get("/id/:releaseId(\\d+)", function (req, res, next) {
    specificReleaseId(req, res, next, parseInt(req.params.releaseId));
});
function specificReleaseId(req, res, next, releaseId) {
    DetailedRelease_class_1.DetailedRelease.fromConstraint(function (query) {
        query.where = "releases.releaseId = ?";
        query.whereArgs = [releaseId];
    }, function (releases) {
        if (releases.length === 0) {
            res.redirect("/plugins?error=" + encodeURIComponent("The releaseId " + releaseId + " does not exist"));
            return;
        }
        var release = releases[0];
        if (!release_1.Release.canAccessState(req.session.getAdminLevel(), release.state)) {
            res.redirect("/plugins?error=" + encodeURIComponent(release.state === release_1.Release.State.Rejected ? "This release has been rejected" : "This release is not accessible to you yet"));
            return;
        }
    }, next);
}
exports.details_ui.get("/:name(" + release_1.Release.NAME_PATTERN + ")", function (req, res, next) {
    var name = req.params.name;
    var preRelease = req.query.pre !== "off";
    DetailedRelease_class_1.DetailedRelease.fromConstraint(function (query) {
        query.where = "releases.name = ?";
        query.whereArgs = [name];
        query.order = "releases.releaseId DESC";
    }, function (releases) {
        var highestNewer = null;
        var bestRelease = null;
        for (var i = 0; i < releases.length; ++i) {
            if (releases[i].state >= config_1.Config.MIN_PUBLIC_RELEASE_STATE) {
                bestRelease = releases[i];
                break;
            }
            if (release_1.Release.canAccessState(req.session.getAdminLevel(), releases[i].state) &&
                (highestNewer === null || highestNewer.state < releases[i].state)) {
                highestNewer = {
                    releaseId: releases[i].releaseId,
                    version: releases[i].version,
                    state: releases[i].state,
                };
            }
        }
        if (bestRelease === null) {
            res.redirect("/plugins?term=" + encodeURIComponent(name) + "&error=" + encodeURIComponent("The plugin " + name + " does not exist, or is not visible to you."));
            return;
        }
        if (highestNewer !== null && highestNewer.state < bestRelease.state) {
            bestRelease.lowerStateAlt = highestNewer;
        }
        displayPlugin(req, res, next, bestRelease);
    }, next);
});
exports.details_ui.get("/:name(" + release_1.Release.NAME_PATTERN + ")/:version(" + release_1.Release.VERSION_PATTERN + ")", function (req, res, next) {
    var name = req.params.name;
    var version = req.params.version;
    DetailedRelease_class_1.DetailedRelease.fromConstraint(function (query) {
        query.where = "releases.name = ? AND releases.version = ?";
        query.whereArgs = [name, version];
        query.order = "releases.releaseId DESC";
    }, function (releases) {
        if (releases.length === 0) {
            res.redirect("/plugins?term=" + encodeURIComponent(name));
            return;
        }
        if (releases.length === 1) {
            displayPlugin(req, res, next, releases[0]);
            return;
        }
    }, next);
});
function displayPlugin(req, res, next, release) {
    var releasePerm = new ReleasePerm_class_1.ReleasePerm(req.session, release);
    ThumbnailRelease_class_1.ThumbnailRelease.fromConstraint(function (query) {
        query.where = "releases.projectId = ?";
        query.whereArgs = [release.build.projectId];
        query.order = "releases.releaseId DESC";
    }, function (previous) {
        res.locals.pageInfo.title = release.name + " v" + release.version;
        res.locals.pageInfo.description = release.name + " - " + release.shortDesc;
        (_a = res.locals.pageInfo.keywords).push.apply(_a, release.keywords);
        var filtered = previous.filter(function (r) { return release_1.Release.canAccessState(req.session.getAdminLevel(), r.state); });
        var earliest = filtered.reduceRight(function (a, b) { return a.getTime() < b.approveDate.getTime() ? a : b.approveDate; }, release.approveDate);
        res.render("release/details", {
            release: release,
            access: releasePerm,
            previousReleases: filtered,
            publishDate: earliest,
            pmApis: api_1.POCKETMINE_APIS,
        });
        var _a;
    }, next);
}

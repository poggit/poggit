"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var crypto_1 = require("crypto");
var secrets_1 = require("../secrets");
var body_parser = require("body-parser");
var db_1 = require("../db");
var gh_1 = require("../gh");
var workspace_1 = require("../workspace");
var WebhookExecutor_class_1 = require("./WebhookExecutor.class");
var RepoAccessFilter_class_1 = require("../workspace/RepoAccessFilter.class");
exports.webhookRouter = express_1.Router();
exports.webhookRouter.use(body_parser.json({
    verify: function (req, res, buf) {
        var inputSignature = req.headers["x-hub-signature"];
        var hmac = crypto_1.createHmac("sha1", secrets_1.secrets.app.webhookSecret);
        hmac.update(buf);
        var hash = hmac.digest("hex");
        if ("sha1=" + hash !== inputSignature) {
            throw new Error("Invalid signature!");
        }
    },
}));
exports.webhookRouter.post("/", function (req, res, next) {
    var delivery = req.headers["x-github-delivery"];
    var event = req.headers["x-github-event"];
    if (event === "ping") {
        res.send("pong");
        return;
    }
    var accessFilters = [];
    var payload = req.body;
    if (gh_1.gh.wh.isRepoPayload(payload)) {
        var repo = payload.repository;
        accessFilters.push(new RepoAccessFilter_class_1.RepoAccessFilter(repo.id, "admin"));
    }
    else if (gh_1.gh.wh.isOrgPayload(payload)) {
        var org = payload.organization;
    }
    workspace_1.resources.create("log", "text/plain", "poggit.webhook.log", 86400e+3 * 7, accessFilters, function (resourceId, file) {
        db_1.db.insert("INSERT INTO webhook_executions (deliveryId, logRsr) VALUES (?, ?)", [delivery, resourceId], next, function () {
            WebhookExecutor_class_1.WebhookExecutor.create(event, file, payload, function () {
            }).start();
            res.status(202).set("Content-Type", "text/plain").send("Started");
        });
    }, next);
});

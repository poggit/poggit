"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var express_1 = require("express");
var crypto_1 = require("crypto");
var secrets_1 = require("../secrets");
var body_parser = require("body-parser");
var db_1 = require("../db");
var gh_1 = require("../gh");
var workspace_1 = require("../workspace");
var RepoAccessFilter_class_1 = require("../workspace/RepoAccessFilter.class");
var CreateWebhookExecutor_class_1 = require("./CreateWebhookExecutor.class");
var RepositoryWebhookExecutor_class_1 = require("./RepositoryWebhookExecutor.class");
var PullRequestWebhookExecutor_class_1 = require("./PullRequestWebhookExecutor.class");
var PushWebhookExecutor_class_1 = require("./PushWebhookExecutor.class");
var InstallationWebhookExecutor_class_1 = require("./InstallationWebhookExecutor.class");
var InstallationRepositoriesWebhookExecutor_class_1 = require("./InstallationRepositoriesWebhookExecutor.class");
var fs_1 = require("fs");
exports.webhookRouter = express_1.Router();
exports.webhookRouter.use(body_parser.json({
    verify: function (req, res, buf) {
        var inputSignature = req.headers["x-hub-signature"];
        var hmac = crypto_1.createHmac("sha1", secrets_1.SECRETS.app.webhookSecret);
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
        var stream = fs_1.createWriteStream(file);
        stream.on("open", function () {
            db_1.db.insert("INSERT INTO webhook_executions (deliveryId, logRsr) VALUES (?, ?)", [delivery, resourceId], next, function () {
                var exec = createWebhookExecutor(event, stream, payload, function () {
                });
                if (exec === null) {
                    res.status(415).send("Unsupported event " + event);
                    return;
                }
                exec.start();
                res.status(202).set("Content-Type", "text/plain").send("Started");
            });
        });
    }, next);
});
function createWebhookExecutor(event, logFile, payload, onComplete) {
    if (event === "ping") {
        return null;
    }
    switch (event) {
        case "installation":
            return new InstallationWebhookExecutor_class_1.InstallationWebhookExecutor(logFile, payload, onComplete);
        case "installation_repositories":
            return new InstallationRepositoriesWebhookExecutor_class_1.InstallationRepositoriesWebhookExecutor(logFile, payload, onComplete);
        case "repository":
            return new RepositoryWebhookExecutor_class_1.RepositoryWebhookExecutor(logFile, payload, onComplete);
        case "push":
            return new PushWebhookExecutor_class_1.PushWebhookExecutor(logFile, payload, onComplete);
        case "pull_request":
            return new PullRequestWebhookExecutor_class_1.PullRequestWebhookExecutor(logFile, payload, onComplete);
        case "create":
            return new CreateWebhookExecutor_class_1.CreateWebhookExecutor(logFile, payload, onComplete);
    }
    return null;
}

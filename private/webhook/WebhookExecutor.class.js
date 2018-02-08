"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var fs_1 = require("fs");
var InstallationWebhookExecutor_class_1 = require("./InstallationWebhookExecutor.class");
var InstallationRepositoriesWebhookExecutor_class_1 = require("./InstallationRepositoriesWebhookExecutor.class");
var RepositoryWebhookExecutor_class_1 = require("./RepositoryWebhookExecutor.class");
var PushWebhookExecutor_class_1 = require("./PushWebhookExecutor.class");
var PullRequestWebhookExecutor_class_1 = require("./PullRequestWebhookExecutor.class");
var CreateWebhookExecutor_class_1 = require("./CreateWebhookExecutor.class");
var WebhookExecutor = (function () {
    function WebhookExecutor(logFile, payload, onComplete) {
        this._onComplete = onComplete;
        this.stream = fs_1.createWriteStream(logFile);
        this.payload = payload;
    }
    WebhookExecutor.create = function (event, logFile, payload, onComplete) {
        if (event === "ping") {
            throw new Error("Cannot create webhook executor for ping event");
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
        throw new TypeError("Unsupported event \"" + event + "\"");
    };
    WebhookExecutor.prototype.log = function (message) {
        this.stream.write(message + "\n");
    };
    WebhookExecutor.prototype.onComplete = function () {
        this._onComplete();
    };
    WebhookExecutor.prototype.start = function () {
        this.run();
    };
    WebhookExecutor.prototype.onError = function (error) {
        this.log("Error: " + error);
        this._onComplete();
    };
    return WebhookExecutor;
}());
exports.WebhookExecutor = WebhookExecutor;

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var WebhookExecutor = (function () {
    function WebhookExecutor(logFile, payload, onComplete) {
        this._onComplete = onComplete;
        this.stream = logFile;
        this.payload = payload;
    }
    WebhookExecutor.prototype.log = function (message) {
        this.stream.write(message + "\n");
    };
    WebhookExecutor.prototype.onComplete = function () {
        this.stream.end();
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

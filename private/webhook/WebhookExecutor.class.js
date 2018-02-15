"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var util_1 = require("../util");
var WebhookExecutor = (function () {
    function WebhookExecutor(logFile, payload, onComplete) {
        this._onComplete = onComplete;
        this.stream = logFile;
        this.payload = payload;
    }
    WebhookExecutor.prototype.log = function (message) {
        this.stream.write(message + "\n");
    };
    WebhookExecutor.prototype.start = function () {
        var _this = this;
        console.info("Handling event: " + this.constructor.toString());
        util_1.util.waitAll(this.getTasks().map(function (ep) { return ep2sp(ep, _this.onError); }), this.onComplete);
    };
    WebhookExecutor.prototype.onComplete = function () {
        this.stream.end();
        this._onComplete();
    };
    WebhookExecutor.prototype.onError = function (error) {
        this.log("Error: " + error);
        this._onComplete();
    };
    return WebhookExecutor;
}());
exports.WebhookExecutor = WebhookExecutor;
function ep2sp(ep, eh) {
    return function (onComplete) {
        ep(onComplete, function (err) {
            eh(err);
            onComplete();
        });
    };
}

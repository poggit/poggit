"use strict";
var __extends = (this && this.__extends) || (function () {
    var extendStatics = Object.setPrototypeOf ||
        ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
        function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
var WebhookExecutor_class_1 = require("./WebhookExecutor.class");
var InstallationWebhookExecutor = (function (_super) {
    __extends(InstallationWebhookExecutor, _super);
    function InstallationWebhookExecutor() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    InstallationWebhookExecutor.prototype.getTasks = function () {
        return [];
    };
    return InstallationWebhookExecutor;
}(WebhookExecutor_class_1.WebhookExecutor));
exports.InstallationWebhookExecutor = InstallationWebhookExecutor;

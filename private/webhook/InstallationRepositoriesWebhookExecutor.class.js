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
var db_1 = require("../db");
var InstallationRepositoriesWebhookExecutor = (function (_super) {
    __extends(InstallationRepositoriesWebhookExecutor, _super);
    function InstallationRepositoriesWebhookExecutor() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    InstallationRepositoriesWebhookExecutor.prototype.run = function () {
        var _this = this;
        var rows = {};
        this.payload.repositories_removed.forEach(function (repo) {
            rows[repo.id] = { build: false };
        });
        db_1.db.update_bulk("repos", "repoId", rows, "1", [], this.onError, function () { return _this.onComplete; });
    };
    return InstallationRepositoriesWebhookExecutor;
}(WebhookExecutor_class_1.WebhookExecutor));
exports.InstallationRepositoriesWebhookExecutor = InstallationRepositoriesWebhookExecutor;

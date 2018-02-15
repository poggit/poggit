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
var gh_1 = require("../gh");
var db_1 = require("../db");
var app = gh_1.gh.app;
var InstallationRepositoriesWebhookExecutor = (function (_super) {
    __extends(InstallationRepositoriesWebhookExecutor, _super);
    function InstallationRepositoriesWebhookExecutor() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    InstallationRepositoriesWebhookExecutor.prototype.getTasks = function () {
        var _this = this;
        return [
            function (onComplete, onError) {
                app.asInstallation(_this.payload.installation.id, function (token) {
                    gh_1.gh.graphql.repoData(token, _this.payload.repositories_added
                        .map(function (repo) { return ({ owner: _this.payload.installation.account.login, name: repo.name }); }), "databaseId isPrivate", function (repos) {
                        var rows = repos.map(function (repo) {
                            return new db_1.db.InsertRow({ repoId: repo.databaseId }, {
                                owner: repo._repo.owner,
                                name: repo._repo.name,
                                private: repo.isPrivate,
                                build: true,
                                installation: _this.payload.installation.id,
                            });
                        });
                        db_1.db.insert_dup("repos", rows, onError, onComplete);
                    }, onError);
                }, onError);
            },
            function (onComplete, onError) {
                var rows = {};
                for (var _i = 0, _a = _this.payload.repositories_removed; _i < _a.length; _i++) {
                    var repo = _a[_i];
                    rows[repo.id] = { build: false };
                }
                db_1.db.update_bulk("repos", "repoId", rows, "1", [], onError, function () { return onComplete; });
            },
        ];
    };
    return InstallationRepositoriesWebhookExecutor;
}(WebhookExecutor_class_1.WebhookExecutor));
exports.InstallationRepositoriesWebhookExecutor = InstallationRepositoriesWebhookExecutor;

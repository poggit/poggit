<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace poggit;

use poggit\account\ConfirmLogoutModule;
use poggit\account\GitHubLoginCallbackModule;
use poggit\account\KeepOnlineAjax;
use poggit\account\LoginModule;
use poggit\account\LogoutAjax;
use poggit\account\PersistLoginLocAjax;
use poggit\account\SettingsAjax;
use poggit\account\SettingsModule;
use poggit\account\SuAjax;
use poggit\ci\api\AbsoluteBuildIdModule;
use poggit\ci\api\BuildBadgeModule;
use poggit\ci\api\BuildDataRequestAjax;
use poggit\ci\api\BuildInfoModule;
use poggit\ci\api\BuildShieldModule;
use poggit\ci\api\DynamicBuildHistoryAjax;
use poggit\ci\api\FqnListChildrenApi;
use poggit\ci\api\GetVirionModule;
use poggit\ci\api\ProjectListAjax;
use poggit\ci\api\ProjectSubToggleAjax;
use poggit\ci\api\ReadmeBadgerAjax;
use poggit\ci\api\ResendLastPushAjax;
use poggit\ci\api\ScanRepoProjectsAjax;
use poggit\ci\api\SearchBuildAjax;
use poggit\ci\api\ToggleRepoAjax;
use poggit\ci\ui\BuildModule;
use poggit\ci\ui\fqn\FqnListModule;
use poggit\ci\ui\fqn\FqnViewModule;
use poggit\ci\ui\VirionListModule;
use poggit\debug\AddResourceModule;
use poggit\debug\AddResourceReceive;
use poggit\debug\EvalModule;
use poggit\errdoc\InternalErrorPage;
use poggit\help\HideTosModule;
use poggit\help\PmApiListModule;
use poggit\help\TosModule;
use poggit\home\HomeModule;
use poggit\home\SessionBumpNotifAjax;
use poggit\japi\ApiModule;
use poggit\module\CsrfModule;
use poggit\module\GitHubApiProxyAjax;
use poggit\module\ProxyLinkModule;
use poggit\module\ResModule;
use poggit\module\RobotsTxtModule;
use poggit\module\SupportProxyModule;
use poggit\release\details\ReleaseDetailsModule;
use poggit\release\details\ReleaseFlowModule;
use poggit\release\details\ReleaseGetModule;
use poggit\release\details\ReleaseIdRedirectModule;
use poggit\release\details\ReleaseShieldModule;
use poggit\release\details\ReleaseStateChangeAjax;
use poggit\release\details\ReleaseVoteAjax;
use poggit\release\details\review\ReleaseAssignAjax;
use poggit\release\details\review\ReviewAdminAjax;
use poggit\release\details\review\ReviewQueueModule;
use poggit\release\details\review\ReviewReplyAjax;
use poggit\release\index\ReleaseListJsonModule;
use poggit\release\index\ReleaseListModule;
use poggit\release\submit\GetReleaseVersionsAjax;
use poggit\release\submit\NewSubmitAjax;
use poggit\release\submit\SubmitFormAjax;
use poggit\release\submit\SubmitModule;
use poggit\release\submit\ValidateReleaseNameAjax;
use poggit\release\submit\ValidateReleaseVersionAjax;
use poggit\resource\ResourceGetModule;
use poggit\webhook\GitHubWebhookModule;

register_module("api", ApiModule::class);
register_module("csrf", CsrfModule::class);
register_module("login", LoginModule::class);
register_module("persistLoc", PersistLoginLocAjax::class);
register_module("webhooks.gh.app", GitHubLoginCallbackModule::class);
register_module("login.su", SuAjax::class);
register_module("logout", LogoutAjax::class);
register_module("logout.confirm", ConfirmLogoutModule::class);
register_module("proxy.api.gh", GitHubApiProxyAjax::class);
register_module("settings", SettingsModule::class);
register_module("opt.toggle", SettingsAjax::class);
register_module("session.online", KeepOnlineAjax::class);
register_module("home", HomeModule::class);
register_module("session.bumpNotif", SessionBumpNotifAjax::class);
register_module("build", BuildModule::class);
register_module("b", BuildModule::class);
register_module("ci", BuildModule::class);
register_module("dev", BuildModule::class);
register_module("build.scanRepoProjects", ScanRepoProjectsAjax::class);
register_module("ajax.toggleRepo", ToggleRepoAjax::class);
register_module("search.ajax", SearchBuildAjax::class);
register_module("ci.project.toggleSub", ProjectSubToggleAjax::class);
register_module("v", VirionListModule::class);
register_module("babs", AbsoluteBuildIdModule::class);
//register_module("get.pmmp", GetPmmpModule::class);
//register_module("get.pmmp.sha1", GetPmmpModule::class);
//register_module("get.pmmp.md5", GetPmmpModule::class);
register_module("ci.project.list", ProjectListAjax::class);
register_module("build.history.new", DynamicBuildHistoryAjax::class);
register_module("ci.build.request", BuildDataRequestAjax::class);
register_module("ci.badge", BuildBadgeModule::class);
register_module("ci.shield", BuildShieldModule::class);
register_module("fqn.txt", FqnListModule::class);
register_module("fqn.yml", FqnListModule::class);
register_module("fqn", FqnViewModule::class);
register_module("fqn.api", FqnListChildrenApi::class);
register_module("ci.info", BuildInfoModule::class);
register_module("v.dl", GetVirionModule::class);
register_module("ci.webhookTest", ResendLastPushAjax::class);
register_module("ci.badge.readme", ReadmeBadgerAjax::class);
register_module("submit", SubmitModule::class);
register_module("update", SubmitModule::class);
register_module("edit", SubmitModule::class);
register_module("submit.form", SubmitFormAjax::class);
register_module("release.submit.validate.name", ValidateReleaseNameAjax::class);
register_module("release.submit.validate.version", ValidateReleaseVersionAjax::class);
register_module("submit.deps.getversions", GetReleaseVersionsAjax::class);
register_module("submit.new.ajax", NewSubmitAjax::class);
register_module("plugins", ReleaseListModule::class);
register_module("pi", ReleaseListModule::class);
register_module("index", ReleaseListModule::class);
register_module("releases.json", ReleaseListJsonModule::class);
register_module("plugins.json", ReleaseListJsonModule::class);
register_module("releases.min.json", ReleaseListJsonModule::class);
register_module("plugins.min.json", ReleaseListJsonModule::class);
register_module("releases.list", ReleaseListJsonModule::class);
register_module("plugins.list", ReleaseListJsonModule::class);
register_module("release", ReleaseDetailsModule::class);
register_module("rel", ReleaseDetailsModule::class);
register_module("plugin", ReleaseDetailsModule::class);
register_module("p", ReleaseDetailsModule::class);
register_module("rid", ReleaseIdRedirectModule::class);
register_module("get", ReleaseGetModule::class);
register_module("get.md5", ReleaseGetModule::class);
register_module("get.sha1", ReleaseGetModule::class);
register_module("release.statechange", ReleaseStateChangeAjax::class);
register_module("release.vote", ReleaseVoteAjax::class);
register_module("release.flow", ReleaseFlowModule::class);
register_module("shield.dl", ReleaseShieldModule::class);
register_module("shield.download", ReleaseShieldModule::class);
register_module("shield.downloads", ReleaseShieldModule::class);
register_module("shield.dl.total", ReleaseShieldModule::class);
register_module("shield.download.total", ReleaseShieldModule::class);
register_module("shield.downloads.total", ReleaseShieldModule::class);
register_module("shield.state", ReleaseShieldModule::class);
register_module("shield.approve", ReleaseShieldModule::class);
register_module("shield.approved", ReleaseShieldModule::class);
register_module("shield.api", ReleaseShieldModule::class);
register_module("shield.spoon", ReleaseShieldModule::class);
register_module("review", ReviewQueueModule::class);
register_module("review.admin", ReviewAdminAjax::class);
register_module("review.reply", ReviewReplyAjax::class);
register_module("review.assign", ReleaseAssignAjax::class);
register_module("tos", TosModule::class);
register_module("hideTos", HideTosModule::class);

foreach(["", ".json", ".yml", ".xml"] as $type) {
    foreach($type === ".yml" ? [""] : ["", ".min"] as $min) {
        foreach(["", ".full"] as $full) {
            register_module("pmapis{$full}{$min}{$type}", PmApiListModule::class);
        }
    }
}

register_module("robots.txt", RobotsTxtModule::class);
register_module("support", SupportProxyModule::class);
foreach(ProxyLinkModule::getNames() as $name) {
    register_module($name, ProxyLinkModule::class);
}

register_module("res", ResModule::class);
register_module("js", ResModule::class);
register_module("r", ResourceGetModule::class);
register_module("r.md5", ResourceGetModule::class);
register_module("r.sha1", ResourceGetModule::class);
register_module("webhooks.gh.repo", GitHubWebhookModule::class);
register_module("500ise.template", InternalErrorPage::class);

register_module("addResource", AddResourceModule::class, true);
register_module("addResource.recv", AddResourceReceive::class, true);
register_module("eval", EvalModule::class, true);

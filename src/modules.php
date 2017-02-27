<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

use poggit\account\GitHubLoginCallbackModule;
use poggit\account\LoginModule;
use poggit\account\LogoutAjax;
use poggit\account\PersistLoginLocAjax;
use poggit\account\SettingsAjax;
use poggit\account\SettingsModule;
use poggit\account\SuAjax;
use poggit\ci\api\AbsoluteBuildIdModule;
use poggit\ci\api\BuildImageModule;
use poggit\ci\api\GetPmmpModule;
use poggit\ci\api\LoadBuildHistoryAjax;
use poggit\ci\api\ReadmeBadgerAjax;
use poggit\ci\api\ScanRepoProjectsAjax;
use poggit\ci\api\SearchBuildAjax;
use poggit\ci\api\ToggleRepoAjax;
use poggit\ci\ui\BuildModule;
use poggit\ci\ui\fqn\FqnListModule;
use poggit\debug\AddResourceModule;
use poggit\debug\AddResourceReceive;
use poggit\help\HelpModule;
use poggit\help\HideTosModule;
use poggit\help\LintsHelpModule;
use poggit\help\PrivateResourceHelpModule;
use poggit\help\ReleaseSubmitHelpModule;
use poggit\help\TosModule;
use poggit\home\NewHomeModule;
use poggit\japi\ApiModule;
use poggit\module\CsrfModule;
use poggit\module\GitHubApiProxyAjax;
use poggit\module\JsModule;
use poggit\module\ProxyLinkModule;
use poggit\module\ResModule;
use poggit\module\RobotsTxtModule;
use poggit\release\details\ProjectReleasesModule;
use poggit\release\index\ReleaseListModule;
use poggit\release\review\ReleaseManagement;
use poggit\release\review\ReviewListModule;
use poggit\release\review\ReviewManagement;
use poggit\release\submit\PluginSubmitAjax;
use poggit\release\submit\RelSubValidateAjax;
use poggit\release\submit\SubmitPluginModule;
use poggit\resource\ResourceGetModule;
use poggit\webhook\NewGitHubRepoWebhookModule;

registerModule(CsrfModule::class);
registerModule(LogoutAjax::class);
registerModule(SuAjax::class);
registerModule(PersistLoginLocAjax::class);
registerModule(GitHubApiProxyAjax::class);

registerModule(NewHomeModule::class);
registerModule(LoginModule::class);
registerModule(SettingsModule::class);
registerModule(SettingsAjax::class);

registerModule(ApiModule::class);

registerModule(BuildModule::class);
registerModule(AbsoluteBuildIdModule::class);
registerModule(GetPmmpModule::class);
registerModule(BuildImageModule::class);
registerModule(FqnListModule::class);
registerModule(ScanRepoProjectsAjax::class);
registerModule(SearchBuildAjax::class);
registerModule(ReleaseManagement::class);
registerModule(ReviewManagement::class);
registerModule(ToggleRepoAjax::class);
registerModule(RelSubValidateAjax::class);
registerModule(LoadBuildHistoryAjax::class);
registerModule(ReadmeBadgerAjax::class);

registerModule(ReleaseListModule::class);
registerModule(ProjectReleasesModule::class);
registerModule(ReviewListModule::class);

registerModule(SubmitPluginModule::class);
registerModule(PluginSubmitAjax::class);
//registerModule(dep_PluginSubmitCallbackModule::class);

registerModule(HelpModule::class);
registerModule(PrivateResourceHelpModule::class);
registerModule(LintsHelpModule::class);
registerModule(ReleaseSubmitHelpModule::class);
registerModule(TosModule::class);
registerModule(HideTosModule::class);

registerModule(RobotsTxtModule::class);
registerModule(ProxyLinkModule::class);
registerModule(ResModule::class);
registerModule(JsModule::class);

registerModule(GitHubLoginCallbackModule::class);
registerModule(NewGitHubRepoWebhookModule::class);

registerModule(ResourceGetModule::class);

if(Poggit::isDebug()) {
    registerModule(AddResourceModule::class);
    registerModule(AddResourceReceive::class);
}

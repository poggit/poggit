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
use poggit\ci\api\BuildBadgeModule;
use poggit\ci\api\BuildDataRequestAjax;
use poggit\ci\api\BuildInfoModule;
use poggit\ci\api\BuildShieldModule;
use poggit\ci\api\DynamicBuildHistoryAjax;
use poggit\ci\api\GetPmmpModule;
use poggit\ci\api\GetVirionModule;
use poggit\ci\api\ProjectSubToggleAjax;
use poggit\ci\api\ReadmeBadgerAjax;
use poggit\ci\api\ResendLastPushAjax;
use poggit\ci\api\ScanRepoProjectsAjax;
use poggit\ci\api\SearchBuildAjax;
use poggit\ci\api\ToggleRepoAjax;
use poggit\ci\ui\BuildModule;
use poggit\ci\ui\fqn\FqnListModule;
use poggit\ci\ui\VirionListModule;
use poggit\debug\AddResourceModule;
use poggit\debug\AddResourceReceive;
use poggit\help\CreditsModule;
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
use poggit\release\details\ReleaseDetailsModule;
use poggit\release\details\ReleaseGetModule;
use poggit\release\details\ReleaseStateChangeAjax;
use poggit\release\details\ReleaseVoteAjax;
use poggit\release\details\review\ReviewAdminAjax;
use poggit\release\details\review\ReviewQueueModule;
use poggit\release\details\review\ReviewReplyAjax;
use poggit\release\index\ReleaseListJsonModule;
use poggit\release\index\ReleaseListModule;
use poggit\release\submit\GetReleaseVersionsAjax;
use poggit\release\submit\NewSubmitAjax;
use poggit\release\submit\SubmitModule;
use poggit\release\submit\ValidateReleaseNameAjax;
use poggit\release\submit\ValidateReleaseVersionAjax;
use poggit\resource\ResourceGetModule;
use poggit\webhook\GitHubWebhookModule;


// api
registerModule(ApiModule::class);
registerModule(CsrfModule::class);

// account
registerModule(LoginModule::class);
registerModule(PersistLoginLocAjax::class);
registerModule(GitHubLoginCallbackModule::class);
registerModule(SuAjax::class);
registerModule(LogoutAjax::class);
registerModule(GitHubApiProxyAjax::class);
registerModule(SettingsModule::class);
registerModule(SettingsAjax::class);

// home
registerModule(HomeModule::class);
registerModule(SessionBumpNotifAjax::class);

// ci: display
registerModule(BuildModule::class);
registerModule(ScanRepoProjectsAjax::class);
registerModule(ToggleRepoAjax::class);
registerModule(SearchBuildAjax::class);
registerModule(ProjectSubToggleAjax::class);
registerModule(VirionListModule::class);
// ci: browser redirects
registerModule(AbsoluteBuildIdModule::class);
registerModule(GetPmmpModule::class);
// ci: external api (non HTML)
registerModule(DynamicBuildHistoryAjax::class);
registerModule(BuildDataRequestAjax::class);
registerModule(BuildBadgeModule::class);
registerModule(BuildShieldModule::class);
registerModule(FqnListModule::class);
registerModule(BuildInfoModule::class);
registerModule(GetVirionModule::class);
// ci: misc
registerModule(ResendLastPushAjax::class);
registerModule(ReadmeBadgerAjax::class);

// release: submit
registerModule(SubmitModule::class);
registerModule(ValidateReleaseNameAjax::class);
registerModule(ValidateReleaseVersionAjax::class);
registerModule(GetReleaseVersionsAjax::class);
registerModule(NewSubmitAjax::class);
// release: index
registerModule(ReleaseListModule::class);
registerModule(ReleaseListJsonModule::class);
// release: details
registerModule(ReleaseDetailsModule::class);
registerModule(ReleaseGetModule::class);
registerModule(ReleaseStateChangeAjax::class);
registerModule(ReleaseVoteAjax::class);
// release: review
registerModule(ReviewQueueModule::class);
registerModule(ReviewAdminAjax::class);
registerModule(ReviewReplyAjax::class);

// help pages
registerModule(TosModule::class);
registerModule(HideTosModule::class);
registerModule(PmApiListModule::class);

// misc
registerModule(RobotsTxtModule::class);
registerModule(ProxyLinkModule::class);
registerModule(ResModule::class);
registerModule(ResourceGetModule::class);
registerModule(GitHubWebhookModule::class);

if(Meta::isDebug()) {
    registerModule(AddResourceModule::class);
    registerModule(AddResourceReceive::class);
}

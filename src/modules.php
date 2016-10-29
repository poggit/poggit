<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

use poggit\debug\AddResourceModule;
use poggit\debug\AddResourceReceive;
use poggit\module\ajax\CsrfModule;
use poggit\module\ajax\GitHubApiProxyAjax;
use poggit\module\ajax\LogoutAjax;
use poggit\module\ajax\PersistLocAjax;
use poggit\module\ajax\ToggleRepoAjax;
use poggit\module\build\AbsoluteBuildIdModule;
use poggit\module\build\BuildImageModule;
use poggit\module\build\BuildModule;
use poggit\module\build\LoadBuildHistoryAjax;
use poggit\module\help\HideTosModule;
use poggit\module\help\PrivateResourceHelpModule;
use poggit\module\help\TosModule;
use poggit\module\home\LoadHomeReposModule;
use poggit\module\home\NewHomeModule;
use poggit\module\ProxyLinkModule;
use poggit\module\releases\index\ReleaseListModule;
use poggit\module\releases\project\ProjectReleasesModule;
use poggit\module\releases\submit\SubmitPluginModule;
use poggit\module\res\JsModule;
use poggit\module\res\ResModule;
use poggit\module\resource\ResourceGetModule;
use poggit\module\webhooks\GitHubLoginModule;
use poggit\module\webhooks\repo\NewGitHubRepoWebhookModule;

registerModule(NewHomeModule::class);

registerModule(BuildModule::class);
registerModule(AbsoluteBuildIdModule::class);
registerModule(BuildImageModule::class);

registerModule(ReleaseListModule::class);
registerModule(ProjectReleasesModule::class);
registerModule(SubmitPluginModule::class);

registerModule(PrivateResourceHelpModule::class);
registerModule(TosModule::class);
registerModule(HideTosModule::class);

registerModule(ProxyLinkModule::class);
registerModule(ResModule::class);
registerModule(JsModule::class);

registerModule(GitHubLoginModule::class);
registerModule(NewGitHubRepoWebhookModule::class);

registerModule(ResourceGetModule::class);

registerModule(CsrfModule::class);
registerModule(LogoutAjax::class);
registerModule(PersistLocAjax::class);
registerModule(LoadHomeReposModule::class);
registerModule(ToggleRepoAjax::class);
registerModule(LoadBuildHistoryAjax::class);
registerModule(GitHubApiProxyAjax::class);

if(Poggit::isDebug()) {
    registerModule(AddResourceModule::class);
    registerModule(AddResourceReceive::class);
}

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

use poggit\page\ajax\GitHubApiProxyAjax;
use poggit\page\ajax\LoadBuildHistoryAjax;
use poggit\page\ajax\LogoutAjax;
use poggit\page\ajax\PersistLocAjax;
use poggit\page\ajax\ToggleRepoAjax;
use poggit\page\build\AbsoluteIdBuildPage;
use poggit\page\build\BuildPage;
use poggit\page\CsrfPage;
use poggit\page\help\PrivateResourceHelpPage;
use poggit\page\home\HomePage;
use poggit\page\res\JsPage;
use poggit\page\res\ResPage;
use poggit\page\resource\ResourceGetPage;
use poggit\page\webhooks\GitHubAppCallback;
use poggit\page\webhooks\GitHubRepoWebhook;

registerModule(HomePage::class);
registerModule(BuildPage::class);
registerModule(AbsoluteIdBuildPage::class);

registerModule(PrivateResourceHelpPage::class);

registerModule(ResPage::class);
registerModule(JsPage::class);

registerModule(GitHubAppCallback::class);
registerModule(GitHubRepoWebhook::class);

registerModule(ResourceGetPage::class);

registerModule(CsrfPage::class);
registerModule(LogoutAjax::class);
registerModule(PersistLocAjax::class);
registerModule(ToggleRepoAjax::class);
registerModule(LoadBuildHistoryAjax::class);
registerModule(GitHubApiProxyAjax::class);

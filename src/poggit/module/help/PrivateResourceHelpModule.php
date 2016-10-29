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

namespace poggit\module\help;

use poggit\module\Module;
use poggit\output\OutputManager;
use poggit\Poggit;

class PrivateResourceHelpModule extends Module {
    public function getName() : string {
        return "help.resource.private";
    }

    public function output() {
        $minifier = OutputManager::startMinifyHtml();
        ?>
        <html>
        <head>
            <?php $this->headIncludes("Poggit Help: Private Resources", "Help information about downloading private resources in Poggit") ?>
            <title>Private Resources | Help | Poggit</title>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1 class="topic">Private resources</h1>
            <p>Some resources, such as builds from private repositories, are <em>private</em>, i.e. they can only be
                accessed by <em>authorized users</em>.</p>
            <p>Users are authorized by providing any <a href="https://github.com/settings/tokens">
                    GitHub access tokens</a> from any users with certain access to concerned repository. For example, if
                you want to download a build for a project from a private repository, you must provide an access token
                that has <em>pull</em> access to that repository.</p>
            <p>If you are downloading the plugin from a browser where you are logged in to Poggit, you only have to add
                <code>?cookie</code> behind the URL, for example, <code>/r/123?cookie</code>. Poggit will use the access
                token from the GitHub application authorization as the access token. For example, if you are downloading
                a plugin build, using <code>?cookie</code>, you can only download builds from repos of organizations
                that you have authorized to Poggit.</p>
            <p>This does not work when you are downloading resources using <code>wget</code> or <code>curl</code>, which
                cannot "login" into Poggit. For these cases, you need to manually provide GitHub access tokens. You can
                generate new access tokens at <a href="https://github.com/settings/tokens">your settings</a>. The
                <code>repo</code> scope is required so that Poggit knows that you have access (at least <code>pull
                </code> access) to the concerned private repo. Poggit does not intentionally store these access tokens
                directly, so it is safe to revoke these tokens as long as the request to Poggit has completed.</p>
            <p>There are three ways to pass the access token to Poggit, namely <code>GET</code> parameters,
                <code>POST</code> parameters and <code>Authorization</code> headers.</p>
            <p>To pass the access token through a <code>GET</code> parameter, simply add the parameter
                <code>access_token</code> to the URL. Example URL:
            <pre class="code"><span class="domain"></span><?= Poggit::getRootPath()
                ?>r/123?access_token=0000000000000000000000000000000000000000</pre>
            <p>The <code>GET</code> method is however very discouraged, because there may be logs of access tokens at
                places that they should not stay at. Instead, use <code>POST</code> fields. You can provide them in
                <code>curl</code> or <code>wget</code> like this:</p>
            <pre class="code">
                curl -d "access_token=0000000000000000000000000000000000000000" <span class="domain"></span><?=
                Poggit::getRootPath() ?>r/123
                wget --post-data="access_token=0000000000000000000000000000000000000000" <span class="domain"></span><?=
                Poggit::getRootPath() ?>r/123
            </pre>
            <p>However, the best method is to use the <code>Authorization</code> header. You can use it like this:</p>
            <pre class="code">
                curl -H "Authorization: 0000000000000000000000000000000000000000" <span class="domain"></span><?=
                Poggit::getRootPath() ?>r/123
                wget --header="Authorization: 0000000000000000000000000000000000000000" <span
                    class="domain"></span><?= Poggit::getRootPath() ?>r/123
            </pre>
            <p>For compatibility with OAuth requests, you can insert any words before the token in Authorization
                headers.</p>
        </div>
        </body>
        </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}

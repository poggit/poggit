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

namespace poggit\account;

use poggit\module\HtmlModule;
use poggit\module\Module;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHub;
use function array_map;
use function explode;
use function json_encode;

class LoginModule extends HtmlModule {
    public function output() {
        $session = Session::getInstance();
        $enabled = ["repo", "read:org"];
        if($loggedIn = $session->isLoggedIn()) {
            GitHub::ghApiGet("", $session->getAccessToken());
            $headers = Curl::parseHeaders();
            if(isset($headers["X-OAuth-Scopes"])) {
                $enabled = array_map("trim", explode(",", $headers["X-OAuth-Scopes"]));
            }
        }
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title><?= $loggedIn ? "Authorize more GitHub scopes" : "Login with GitHub" ?> | Poggit</title>
          <?php $this->headIncludes("Login") ?>
        <script>var hasScopes = <?= json_encode($enabled) ?>;</script>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body" class="scopes-wrapper">
        <div class="scopes-heading">
          <h1><?= $loggedIn ? "Authorize more GitHub scopes" : "Login with GitHub" ?></h1>
          <p>Poggit requests your GitHub authorization for the following scopes. You can disable some of them if
            you
            find them unnecessary. They can be enabled in the future if you want to enable more features.</p>
        </div>
        <div class="table-responsive">
          <table class="info-table table">
            <tr>
              <th><input type="checkbox" id="checkAll"></th>
              <th>Name</th>
              <th>What Poggit can do with this scope</th>
              <th>Why Poggit needs this scope</th>
              <th>Should I uncheck this scope?</th>
            </tr>
            <tr>
              <td><input type="checkbox" class="authScope" data-scope="write:repo_hook"></td>
              <td><code class="code">write:repo_hook</code></td>
              <td>View and create webhooks in your repos</td>
              <td>When you enable Poggit-CI for a repo, Poggit will create webhooks in the repo so that GitHub
                will notify Poggit for events like pull requests and commit pushes.
              </td>
              <td>Unless you are not using Poggit-CI at all, you have to enable this.<br/>
                Covered by <code class="code">repo</code> for all repos, or
                <code class="code">public_repo</code> for public repos
              </td>
            </tr>
            <tr>
              <td><input type="checkbox" class="authScope" data-scope="repo:status"></td>
              <td><code class="code">repo:status</code></td>
              <td>Create <a href="https://github.com/blog/1227-commit-status-api">commit statuses</a> for commits
                in your repo, as well as commits in pull requests to your repo
              </td>
              <td>When Poggit-CI builds a project, it also executes quick code quality scans called "lint" on your
                project. It will then send the result as commit statuses to GitHub. If the project cannot be
                built, or very severe problems are detected, an "error" or "failure" status will be sent. Pull
                requests with these statuses will have a warning.<br/>
                Poggit will only create statuses starting with <code class="code">poggit-ci/</code>.
              </td>
              <td>You are strongly recommended to authorize Poggit with this scope, unless you don't use Poggit-CI
                at all.<br/>
                Covered by <code class="code">repo</code> for all repos, or
                <code class="code">public_repo</code> for public repos
              </td>
            </tr>
            <tr>
              <td><input type="checkbox" class="authScope" data-scope="public_repo"></td>
              <td><code class="code">public_repo</code></td>
              <td>Write access to all your public repos, along with write access to their webhooks, commit
                statuses and other stuff.
              </td>
              <td>Poggit uses the write access to create the .poggit.yml file. This is not mandatory.</td>
              <td>If you disable this scope, you have to edit .poggit.yml manually.<br/>
                Covered by <code class="code">repo</code></td>
            </tr>
            <tr>
              <td><input type="checkbox" class="authScope" data-scope="repo"></td>
              <td><code class="code">repo</code></td>
              <td>Read and write access to all your public <em>and private</em> repos, along with write access to
                their webhooks, commit statuses and other stuff.
              </td>
              <td>If you want to manage your private repos through Poggit-CI, you must enable this scope, or
                Poggit will think that you do not have permission to see your private repos and display &quot;Not
                Found&quot; or &quot;Access denied&quot; when you try to access them.
              </td>
              <td>You must enable this scope so that Poggit knows that you are a member of a private repo.</td>
            </tr>
            <tr>
              <td><input type="checkbox" class="authScope" data-scope="read:org"/></td>
              <td><code class="code">read:org</code></td>
              <td>See what organizations you are in, including private membership</td>
              <td>This will allow Poggit to list all the organizations you are in, including those that your
                membership is private. Poggit can still know about the organizations in which your membership is
                public.
              </td>
              <td>You do not need to enable this scope if you are public in all organizations, or if you do not
                need to enable/disable Poggit-CI for repos in organizations you have private membership in.
              </td>
            </tr>
            <tr>
              <td><input type="checkbox" class="authScope" data-scope="user:email" checked disabled></td>
              <td><code class="code">user:email</code></td>
              <td>Permission to view your primary email address on GitHub</td>
              <td>Poggit will use this email address to contact you. <em>We will not send regular ads to this email
                  address without your explicit consent.</em> The mails will be sent explicitly by a human from
                <code>poggitbot@gmail.com</code> (or from poggit.pmmp.io in the future), and your address will not be
                visible to other recipients.
              </td>
              <td>You must enable this scope to login with Poggit.</td>
            </tr>
          </table>
        </div>
        <div class="scopes-info">
          <p><span class="action" id="submitScopes">Login with GitHub with these authorizations</span></p>
          <p class="remark">Note: When Poggit-CI builds a project, it will access the repo using authorization from
            the user who enabled Poggit-CI for the repo. You do not need to enable some scopes if you are not
            planning to enable Poggit-CI yourself.<br/>
            <strong>However</strong>, if the repo is private, you must enable the scope for private repos, or at
            least, the scope for writing commit statuses, so that Poggit knows you are a member of that repo and
            have at least read access to it.
          </p>
        </div>
      </div>
      <?php
      $this->bodyFooter();
      Module::queueJs("authorize");
      $this->flushJsList();
      ?>
      </body>
      </html>
        <?php
    }
}

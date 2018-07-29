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

namespace poggit\ci\cause;

use poggit\account\Session;
use poggit\Mbd;
use poggit\Meta;
use poggit\utils\internet\GitHub;
use function htmlspecialchars;
use function substr;

class V2PullRequestBuildCause extends V2BuildCause {
    /** @var int */
    public $repoId;
    /** @var int */
    public $prNumber;
    /** @var string */
    public $commit;

    public function echoHtml() {
        $token = Session::getInstance()->getAccessToken(true);
        $repo = GitHub::ghApiGet("repositories/$this->repoId", $token);
        $pr = GitHub::ghApiGet("repositories/$this->repoId/pulls/$this->prNumber", $token);
        $commit = GitHub::ghApiGet("repositories/$this->repoId/commits/$this->commit", $token);
        if($commit->committer === null) {
            $commit->committer = (object) ["login" => $commit->commit->committer->name, "name" => $commit->commit->committer->name, "avatar_url" => Meta::root() . "defavt"];
        }
        if($commit->author === null) {
            $commit->author = (object) ["login" => $commit->commit->author->name, "name" => $commit->commit->author->name, "avatar_url" => Meta::root() . "defavt"];
        }
        ?>
      <p>Triggered by commit
        <code class="code"><?= substr($this->commit, 0, 7) ?></code> <?php Mbd::ghLink($commit->html_url) ?>
        by
          <?php
          Mbd::displayUser($commit->author);
          if($commit->author->login !== $commit->committer->login) {
              echo " with ";
              Mbd::displayUser($commit->committer);
          }
          ?>
        in <span class="hover-title" title="<?= Mbd::esq($pr->title) ?>">
                pull request #<?= $this->prNumber ?><?php Mbd::ghLink($pr->html_url) ?></span>
        by <?php Mbd::displayUser($pr->user); ?>
        in <?php Mbd::displayRepo($repo->owner->login, $repo->name) ?>
      </p>
      <pre class="code"><?= htmlspecialchars($commit->commit->message) ?></pre>
        <?php
    }

    public function getCommitSha(): string {
        return $this->commit;
    }
}

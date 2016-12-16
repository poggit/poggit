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

namespace poggit\builder\cause;

use poggit\embed\EmbedUtils;
use poggit\utils\internet\CurlUtils;
use poggit\utils\SessionUtils;

class V2PushBuildCause extends V2BuildCause {
    /** @var int */
    public $repoId;
    /** @var string */
    public $commit;

    public function echoHtml() {
        $token = SessionUtils::getInstance()->getAccessToken();
        $repo = CurlUtils::ghApiGet("repositories/$this->repoId", $token);
        $commit = CurlUtils::ghApiGet("repositories/$this->repoId/commits/$this->commit", $token);
        ?>
        <p>Triggered by commit
            <code class="code"><?= substr($this->commit, 0, 7) ?></code> <?php EmbedUtils::ghLink($commit->html_url) ?>
            by
            <?php
            EmbedUtils::displayUser($commit->author);
            if($commit->author->login !== $commit->committer->login) {
                echo " with ";
                EmbedUtils::displayUser($commit->committer);
            }
            ?>
            in <?php EmbedUtils::displayRepo($repo->owner->login, $repo->name, $repo->owner->avatar_url); ?>:
        </p>
        <!--        @formatter:off-->
        <pre class="code"><span class="time" data-timestamp="<?= strtotime($commit->commit->author->date) ?>"></span>
<?= $commit->commit->message ?></pre>
        <!--        @formatter:onl-->
        <?php
    }

    public function getCommitSha(): string {
        return $this->commit;
    }
}

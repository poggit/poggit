<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\module\webhooks\buildcause;

use poggit\module\build\BuildBuildPageVariant;
use poggit\Poggit;
use poggit\session\SessionUtils;

class CommitBuildCause extends BuildCause {
    public $repo;
    public $sha;

    public function setRepo(string $repoOwner, string $repoName) {
        $this->repo = new \stdClass();
        $this->repo->owner = $repoOwner;
        $this->repo->name = $repoName;
    }

    public function outputHtml() {
        $token = SessionUtils::getInstance()->getAccessToken();
        $commit = Poggit::ghApiGet("repos/{$this->repo->owner}/{$this->repo->name}/commits/$this->sha", $token);
        ?>
        <p>
            Commit <code class="code"><?= substr($this->sha, 0, 7) ?></code> <?php Poggit::ghLink($commit->html_url) ?>
            in <?= $this->repo->owner ?> <?php Poggit::ghLink("https://github.com/" . $this->repo->owner) ?>
            / <?= $this->repo->name ?>
            <?php Poggit::ghLink("https://github.com/" . $this->repo->owner . "/" . $this->repo->name) ?>:
        </p>
        <pre class="code">
            <?= str_replace(["\n", " "], ["\n", "&nbsp;"], htmlspecialchars($commit->commit->message)) ?>
        </pre>
        <h4>Modified project files in this commit:</h4>
        <ul>
            <?php
            foreach($commit->files as $file) {
                if(isset(BuildBuildPageVariant::$projectPath)) {
                    if(!Poggit::startsWith($file->filename, BuildBuildPageVariant::$projectPath) and
                        $file->filename !== ".poggit.yml" and $file->filename !== ".poggit/.poggit.yml"
                    ) {
                        continue;
                    }
                }
                echo "<li>" . htmlspecialchars($file->filename) . "</li>";
            }
            ?>
        </ul>
        <?php
    }
}

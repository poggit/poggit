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

namespace poggit\ci\ui;

use poggit\account\Session;
use poggit\Meta;
use poggit\utils\internet\GitHub;
use stdClass;
use function count;

class SelfBuildPage extends RepoListBuildPage {
    private $rawRepos;

    public function __construct() {
        if(!Session::getInstance()->isLoggedIn()) {
            throw new RecentBuildPage("", 200);
        }
        parent::__construct();
    }

    public function getTitle(): string {
        return "My Projects";
    }

    public function output() {
        ?>
      <div class="member-ci-wrapper">
        <div class="toggle-pane">
          <div class="toggle-repo-list">
            <p class="remark">Organization repos not showing up?<br/><a
                  href="<?= Meta::root() ?>orgperms">Check organization access on GitHub</a></p>
            <div id="toggle-orgs"></div>
            <div id="enableRepoBuilds">
              <div id="detailLoader"></div>
            </div>
          </div>
        </div>
        <div class="toggle-repo-pane">
          <div class="toggle-ajax-pane"></div>
            <?php
            if(count($this->repos) > 0) {
                $this->displayRepos($this->repos);
            } else { ?>
              <p>You don't have any projects built by Poggit-CI yet! Enable a repo in the repo list above/on the
                left, click the "off" button to enable the repo, and create a .poggit.yml according to the
                instructions. If you already have a .poggit.yml, push a commit that modifies .poggit.yml (e.g.
                add a new trailing line) to trigger Poggit-CI to build for the first time.</p>
            <?php } ?>
        </div>
      </div>
        <?php
    }

    /**
     * @return stdClass[]
     */
    protected function getRepos(): array {
        $session = Session::getInstance();
        $repos = GitHub::listMyRepos($session->getAccessToken(true), "default_branch: defaultBranchRef{name}");
        foreach($repos as &$repo) {
            $repo->projects = [];
        }
        unset($repo);
        $this->rawRepos = $repos;
        return $this->rawRepos;
    }

    protected function throwNoRepos() {
    }

    protected function throwNoProjects() {
    }
}

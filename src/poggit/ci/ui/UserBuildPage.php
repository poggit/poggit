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
use function strtolower;

class UserBuildPage extends RepoListBuildPage {
    /** @var string */
    private $user;

    public function __construct(string $user) {
        if($user === "recent") throw new RecentBuildPage("", 200);
        $this->user = $user;
        parent::__construct();
    }

    public function getTitle(): string {
        return "Projects of $this->user";
    }

    protected function getRepos(): array {
        $session = Session::getInstance();
        $repos = [];
        foreach(GitHub::listHisRepos($this->user, $session->getAccessToken(true),
            "id: databaseId " .
            "owner { login avatar_url: avatarUrl } " .
            "name " .
            "default_branch: defaultBranchRef{ name }") as $repo) {
            if(strtolower($repo->owner->login) === strtolower($this->user)) {
                $repo->projects = [];
                $repos[$repo->id] = $repo;
            }
        }
        return $repos;
    }

    protected function throwNoRepos() {
        $rp = Meta::root();
        throw new RecentBuildPage(<<<EOD
<p>This user does not exist or does not have any GitHub repos with Poggit-CI enabled.</p>
<p class="remark">Want to enable Poggit-CI for more repos you have admin access to? Go to
    <span class="action" onclick="window.location ='{$rp}ci';">Your Projects</span></p>
EOD
            , 404);
    }

    protected function throwNoProjects() {
        throw new RecentBuildPage(<<<EOD
<p>This user does not have any GitHub repos with Poggit CI enabled on Poggit.</p>
EOD
            , 404);
    }

    public function og() {
        echo "<meta property='profile:username' content='$this->user'/>";
        return "profile";
    }

    public function getMetaDescription(): string {
        return "Projects from $this->user built by Poggit";
    }

    public function output() {
        $this->displayRepos($this->repos);
    }
}

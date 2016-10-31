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

namespace poggit\module\build;

use poggit\Poggit;
use poggit\session\SessionUtils;

class SelfBuildPage extends RepoListBuildPage {
    private $rawRepos;

    public function __construct() {
        if(!SessionUtils::getInstance()->isLoggedIn()) {
            throw new RecentBuildPage;
        }
        parent::__construct();
    }

    public function getTitle() : string {
        return "My Projects";
    }

    public function output() {
        ?>
        <p><span onclick="$('html, body').animate({scrollTop: $('#toggle').offset().top}, 300); startToggleOrgs();"
                 class="action">Toggle Poggit-CI per repo</span></p>
        <p class="remark">Customize your projects by editing the <code>.poggit/.poggit.yml</code> in your project.</p>
        <hr>
        <?php parent::output(); ?>
        <script>
            <?php
            $enabledRepos = [];
                foreach($this->repos as $repo){
                    $enabledRepos[$repo->id] = [
                        "owner" => $repo->owner->login,
                        "name" => $repo->name,
                        "projectsCount" => count($repo->projects),
                        "id" => $repo->id
                    ];
                }
            ?>
            briefEnabledRepos = <?= json_encode($enabledRepos, JSON_UNESCAPED_SLASHES | JSON_BIGINT_AS_STRING) ?>;
        </script>
        <h2>Toggle Poggit-CI for repos <?php Poggit::displayAnchor("toggle") ?></h2>
        <div id="toggle-orgs">
            <span class="action" onclick="startToggleOrgs()">Toggle orgs</span>
        </div>
        <div id="enableRepoBuilds">

        </div>
        <?php
    }

    /**
     * @return \stdClass[]
     */
    protected function getRepos() : array {
        $this->rawRepos = $this->getReposByGhApi("user/repos?per_page=50", SessionUtils::getInstance()->getAccessToken());
        return $this->rawRepos;
    }

    protected function throwNoRepos() {
        $path = Poggit::getRootPath();
        throw new RecentBuildPage(<<<EOD
<p>You don't have any repos with Poggit CI enabled. Please visit
<a href="$path">Poggit homepage</a> to enable repos.</p>
EOD
        );
    }

    protected function throwNoProjects() {
        $path = Poggit::getRootPath();
        throw new RecentBuildPage(<<<EOD
<p>You don't have any repos with Poggit CI enabled. Please visit
<a href="$path">Poggit homepage</a> to enable repos.</p>
EOD
        );
    }
}

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

use poggit\module\VarPageModule;
use poggit\session\SessionUtils;

class BuildModule extends VarPageModule {

    public function getName(): string {
        return "build";
    }

    public function getAllNames(): array {
        return ["build", "b", "ci"];
    }

    protected function selectPage() {
        $parts = array_filter(explode("/", $this->getQuery()));
        if (count($parts) === 0) {
            throw new SelfBuildPage;
        } elseif (!preg_match('/([A-Za-z0-9\-])+/', $parts[0])) {
            throw new RecentBuildPage("Invalid name");
        } elseif (count($parts) === 1) {
            throw new UserBuildPage($parts[0]);
        } elseif (count($parts) === 2) {
            throw new RepoBuildPage($parts[0], $parts[1]);
        } elseif (count($parts) === 3) {
            throw new ProjectBuildPage($parts[0], $parts[1], $parts[2]);
        } else {
            throw new BuildBuildPage($parts[0], $parts[1], $parts[2], $parts[3]);
        }
    }

    protected function titleSuffix(): string {
        return " | Poggit CI";
    }

    public function moduleHeader() {
        ?>
        <div class="searchpane">
            <div class="resptablecol">
                <div class="resptable-cell"><input type="text" id="inputUser" placeholder="User/Org name" size="15"
                                                   style="margin: 2px;"></div>

                <div class="action disabled resptable-cell" id="gotoUser">User</div>
            </div>
            <div class="resptablecol">
                <div class="resptable-cell"><input type="text" id="inputRepo" placeholder="Repo" size="15"
                                                   style="margin: 2px;"></div>      
                <div class="action disabled resptable-cell" id="gotoRepo">Repo</div>
            </div>
            <div class="resptablecol">
                <div class="resptable-cell"><input type="text" id="inputProject" placeholder="Project" size="15"
                                                   style="margin: 2px;"></div>
                <div class="action disabled resptable-cell" id="gotoProject">Project</div>
            </div>
            <div class="resptablecol">
                <div class="resptable-lastcell">
                    <select id="inputBuildClass" style="margin: 2px;">
                        <option value="dev" selected>Dev build</option>
<!--                        <option value="beta">Beta build</option>-->
<!--                        <option value="rc">Release build</option>-->
                        <option value="pr">PR build</option>
                    </select>
                    <input type="text" id="inputBuild" placeholder="build" size="5"
                           style="margin: 2px;">
                </div>
                <div class="action disabled resptable-cell" id="gotoBuild">Build</div>  
            </div>
            <!-- TODO add babs link -->
            <div class="gotobuildbtn"><div id="gotoSelf" class="action"><div></div>
                        <?= SessionUtils::getInstance()->isLoggedIn() ? "Your Repos" : "Recent Builds" ?>
                </div>
            </div>
        </div>
        <?php
    }

}

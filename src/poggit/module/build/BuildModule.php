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
use poggit\utils\SessionUtils;

class BuildModule extends VarPageModule {

    private $parts;//lol

    public function getName(): string {
        return "build";
    }

    public function getAllNames(): array {
        return ["build", "b", "ci"];
    }

    protected function selectPage() {
        $parts = array_filter(explode("/", $this->getQuery()));
        $this->parts = count($parts);
        if(count($parts) === 0) {
            throw new SelfBuildPage;
        } elseif(!preg_match('/([A-Za-z0-9\-])+/', $parts[0])) {
            throw new RecentBuildPage("Invalid name");
        } elseif(count($parts) === 1) {
            throw new UserBuildPage($parts[0]);
        } elseif(count($parts) === 2) {
            throw new RepoBuildPage($parts[0], $parts[1]);
        } elseif(count($parts) === 3) {
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
            <div class="searchform">
                <div class="multisearch">
                            <div class="resptablecol">
                                <div class="resptable-cell"><input type="text" id="inputSearch" placeholder="Search All..." size="15"
                                                                   style="margin: 2px;"></div>
                                <div class="action resptable-cell" id="gotoUser">MultiSearch</div>
                            </div>    
                        </div>
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
            </div>
            <?php if(SessionUtils::getInstance()->isLoggedIn()) { ?>
            <div class="gotobuildbtns">
                <?php if($this->parts != 0) { ?>
                    <div>
                        <div id="gotoSelf" class="action">My Projects</div>
                    </div>
                <?php }
                if($this->parts != 1) { ?>
                    <div>
                        <div id="gotoRecent" class="action">Recent Builds</div>
                    </div>
                <?php } ?>
                <?php } else { ?>
                    <div class="recentbuildbutton">
                        <div id="gotoSelf" class="action">Recent Builds</div>
                    </div>
                <?php } ?>
                <!-- TODO add babs link -->
            </div>
        </div>
        <?php
    }

    protected function includeMoreJs() {
        $this->includeJs("build");
    }
}

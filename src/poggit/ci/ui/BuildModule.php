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
use poggit\ci\builder\ProjectBuilder;
use poggit\Meta;
use poggit\module\Module;
use poggit\module\VarPageModule;
use poggit\utils\lang\Lang;
use function array_shift;
use function count;
use function explode;
use function htmlspecialchars;
use function preg_match;
use function strtolower;

class BuildModule extends VarPageModule {
    const DISPLAY_NAME = "Dev";

    private $parts;

    protected function selectPage() {
        $parts = Lang::explodeNoEmpty("/", $this->getQuery());
        $this->parts = $parts;
        if(count($parts) === 0) {
            throw new SelfBuildPage;
        }
        if(!preg_match('/([A-Za-z0-9\-])+/', $parts[0])) {
            throw new RecentBuildPage("Invalid name", 400);
        }
        if(count($parts) === 1) {
            throw new UserBuildPage($parts[0]);
        }
        if(strtolower($parts[0]) === "pmmp" && strtolower($parts[1]) === "pocketmine-mp") {
            Meta::redirect("https://jenkins.pmmp.io/job/PocketMine-MP", true);
        }
        if(count($parts) === 2) {
            throw new RepoBuildPage($parts[0], $parts[1]);
        }
        throw new ProjectBuildPage($this, $parts[0], $parts[1], $parts[2]);
    }

    protected function titleSuffix(): string {
        return "";
    }

    public function moduleHeader() {
        ?>
      <div class="search-pane">
        <div class="search-form">
          <div class="search-header">
            <div class="multisearch">
              <div class="resptable-cell">
                <input type="text" id="inputSearch" placeholder="Search Projects" size="15"
                       style="margin: 2px;">
              </div>
              <div class="action resptable-cell" id="gotoSearch">MultiSearch</div>
            </div>
            <div class="resptable-col">
              <div class="resptable-cell">
                <input type="text" id="inputUser" placeholder="User/Org" size="15" style="margin: 2px;"
                       value="<?= htmlspecialchars($this->parts[0] ?? "") ?>"/>
              </div>
              <div class="action disabled resptable-cell" id="gotoUser">User</div>
            </div>
            <div class="resptable-col">
              <div class="resptable-cell">
                <input type="text" id="inputRepo" placeholder="Repo" size="15" style="margin: 2px;"
                       value="<?= htmlspecialchars($this->parts[1] ?? "") ?>"/>
              </div>
              <div class="action disabled resptable-cell" id="gotoRepo">Repo</div>
            </div>
            <div class="resptable-col">
              <div class="resptable-cell">
                <input type="text" id="inputProject" placeholder="Project" size="15" style="margin: 2px;"
                       value="<?= htmlspecialchars($this->parts[2] ?? "") ?>"/>
              </div>
              <div class="action disabled resptable-cell" id="gotoProject">Project</div>
            </div>
              <?php
              if(isset($this->parts[3])) {
                  $build = $this->parts[3];
                  $substrs = explode(":", $build);
                  $classIn = isset($substrs[1]) ? strtolower(array_shift($substrs)) : "dev";
                  $buildId = $substrs[0];
              } else {
                  $classIn = "dev";
                  $buildId = "";
              }
              ?>
            <div class="resptable-col">
              <div class="resptable-cell-last">
                <select id="inputBuildClass" class="inline-select">
                    <?php foreach(ProjectBuilder::$BUILD_CLASS_SID as $classId => $classSid) { ?>
                      <option value="<?= $classSid ?>" <?= $classSid === $classIn ? "selected" : "" ?>>
                          <?= htmlspecialchars(ProjectBuilder::$BUILD_CLASS_HUMAN[$classId]) ?>
                      </option>
                    <?php } ?>
                </select>
                <input type="text" id="inputBuild" placeholder="build" size="5" style="margin: 2px;"
                       value="<?= htmlspecialchars($buildId) ?>"/>
              </div>
              <div class="action disabled resptable-cell" id="gotoBuild">Build</div>
            </div>
          </div>
            <?php if(Session::getInstance()->isLoggedIn()) { ?>
          <div class="goto-build-buttons">
            <div>
              <div><a href="/cmds" class="action">Search command</a></div>
            </div>
            <div>
              <div id="gotoVirions" class="action">Virions</div>
            </div>
              <?php if(count($this->parts) !== 0) { ?>
                <div>
                  <div id="gotoAdmin" class="action">Add repo</div>
                </div>
                <div>
                  <div id="gotoSelf" class="action">My Projects</div>
                </div>
              <?php } ?>
              <?php if(count($this->parts) !== 1) { ?>
                <div>
                  <div id="gotoRecent" class="action">Recent Builds</div>
                </div>
              <?php } ?>
              <?php } else { ?>
                <div class="recent-build-button">
                  <div>
                    <div><a href="/cmds" class="action">Search command</a></div>
                  </div>
                  <div>
                    <div id="gotoRecent" class="action">Recent Builds</div>
                  </div>
                </div>
              <?php } ?>
            <!-- TODO add babs link -->
          </div>
        </div>
        <div id='search-results' hidden='true'></div>
      </div>
        <?php
    }

    public function moduleFooter() {
        Module::queueJs("build");
    }
}

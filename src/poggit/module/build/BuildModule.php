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

use poggit\output\OutputManager;
use poggit\module\Module;
use poggit\session\SessionUtils;

class BuildModule extends Module {
    /** @var BuildPageVariant */
    private $variant;

    public function getName() : string {
        return "build";
    }

    public function getAllNames() : array {
        return ["build", "b"];
    }

    public function output() {
        $parts = array_filter(explode("/", $this->getQuery()));
        try {
            if(count($parts) === 0) {
                $this->setVariant(new SelfBuildPageVariant());
            } elseif(!preg_match('/([A-Za-z0-9\-])+/', $parts[0])) {
                $this->setVariant(new RecentBuildPageVariant("Invalid name"));
            } elseif(count($parts) === 1) {
                $this->setVariant(new UserBuildPageVariant($parts[0]));
            } elseif(count($parts) === 2) {
                $this->setVariant(new RepoBuildPageVariant($parts[0], $parts[1]));
            } elseif(count($parts) === 3) {
                $this->setVariant(new ProjectBuildPageVariant($parts[0], $parts[1], $parts[2]));
            } else {
                $this->setVariant(new BuildBuildPageVariant($parts[0], $parts[1], $parts[2], $parts[3]));
            }
        } catch(AltVariantException $e) {
            // if an AltVariantException is thrown while instantiating an AltVariantException,
            // the inner AltVariantException will be thrown first
            // there being only one AltVariantException catch block, only the innermost block will be caught.
            $this->setVariant($e->getAlt());
        }
        $minifier = OutputManager::startMinifyHtml();
        ?>
        <html>
        <head>
            <?php $this->headIncludes() ?>
            <?php $this->includeJs("build") ?>
            <title><?= $this->variant->getTitle() ?> | Builds | Poggit</title>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <table>
                <tr>
                    <td>Builds for:</td>
                    <td>@<input type="text" id="inputUser" placeholder="User/Org name" size="15" autofocus
                                style="margin: 2px;"></td>
                    <td>/</td>
                    <td><input type="text" id="inputRepo" placeholder="Repo" size="15"
                               style="margin: 2px;"></td>
                    <td>/</td>
                    <td><input type="text" id="inputProject" placeholder="Project" size="15"
                               style="margin: 2px;"></td>
                    <td>/</td>
                    <td>
                        <select id="inputBuildClass" style="margin: 2px;">
                            <option value="dev" selected>Dev build</option>
                            <option value="beta">Beta build</option>
                            <option value="rc">Release build</option>
                        </select>
                        #<input type="text" id="inputBuild" placeholder="build" size="5"
                                style="margin: 2px;">
                    </td>
                </tr>
                <tr>
                    <td class="action" id="gotoSelf">
                        <?= SessionUtils::getInstance()->isLoggedIn() ? "your repos" : "Recent builds" ?></td>
                    <td class="action disabled" id="gotoUser">This user</td>
                    <td></td>
                    <td class="action disabled" id="gotoRepo">This repo</td>
                    <td></td>
                    <td class="action disabled" id="gotoProject">This project</td>
                    <td></td>
                    <td class="action disabled" id="gotoBuild">This build</td>
                </tr>
                <!-- TODO add babs link -->
            </table>
            <hr>
            <?php $this->variant->output() ?>
        </div>
        </body>
        </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }

    public function getVariant() : BuildPageVariant {
        return $this->variant;
    }

    public function setVariant(BuildPageVariant $variant) {
        $this->variant = $variant;
    }
}

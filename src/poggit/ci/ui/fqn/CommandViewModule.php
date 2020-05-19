<?php

/*
 * poggit
 *
 * Copyright (C) 2018 SOFe
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

declare(strict_types=1);

namespace poggit\ci\ui\fqn;

use poggit\Meta;
use poggit\ci\builder\ProjectBuilder;
use poggit\module\HtmlModule;
use poggit\utils\internet\Mysql;

class CommandViewModule extends HtmlModule {
    public function output() {
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Command search", "List of default commands in Poggit projects") ?>
        <title>Command search</title>
      </head>
      <body> <?php $this->bodyHeader() ?>
      <div id="body">
        <h1>Command search</h1>
        <form method="GET">
					<p>
            <input type="text" name="q" required/>
            <input type="submit" value="Search" class="action"/>
            <input type="checkbox" name="name" checked/> Name
          <!--<p>
            <input type="checkbox" name="description"/> Description
          </p>
          <p>
            <input type="checkbox" name="usage"/> Usage -->
          </p>
        </form>
          <?php $this->searchCommand(); ?>
      </div>
      <?php
      $this->bodyFooter();
      $this->flushJsList() ?>
      </body>
      </html>
        <?php
    }

    private function searchCommand() {
        if(!isset($_REQUEST["q"])) {
            return;
        }

        $where = [];
        $argTypes = "";
        $argValues = [];

        if($_REQUEST["name"] ?? null === "on") {
            $where[] = "known_commands.name = ?";
            $where[] = "known_aliases.name = ?";
            $argTypes .= "ss";
            $argValues[] = $_REQUEST["q"];
        }

        if(count($where) === 0) {
          return;
        }

				$where[] = "internal = ?";
				$argTypes .= "i";
				$argValues[] = ProjectBuilder::BUILD_CLASS_DEV;

        $where = implode(" OR ", $where);
        $results = Mysql::query("SELECT
          repos.owner owner,
          repos.name repo,
          projects.name project,
          known_commands.name cmd,
          MAX(known_commands.description) descr,
          MAX(known_commands.usage) `usage`
        FROM known_aliases
            INNER JOIN known_commands ON known_aliases.name = known_commands.name AND known_aliases.buildId = known_commands.buildId
            INNER JOIN builds ON builds.buildId = known_commands.buildId
            INNER JOIN projects ON projects.projectId = builds.projectId
            INNER JOIN repos ON repos.repoId = projects.repoId
        WHERE $where
        GROUP BY projects.projectId, known_commands.name
            ", $argTypes, ...$argValues);
        ?>
      <div class="brief-info-wrapper">
          <?php foreach($results as $result) { ?>
            <div class="brief-info">
              <h5>/<?= $result["cmd"] ?></h5>
							<p class="remark"><?= htmlspecialchars($result["usage"]) ?></p>
							<p class="remark"><?= htmlspecialchars($result["description"]) ?></p>
              <p class="remark">Plugin:
                <a href="<?= Meta::root() ?>ci/<?= $result["owner"] ?>/<?= $result["repo"] ?>/<?= $result["project"] ?>">
                    <?= $result["project"] ?>
                </a>
              </p>
            </div>
              <?php
          } ?>
      </div>
        <?php
    }
}

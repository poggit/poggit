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

declare(strict_types=1);

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\ci\builder\UserFriendlyException;
use poggit\ci\Virion;
use poggit\Meta;
use poggit\module\Module;
use poggit\utils\lang\Lang;
use function apache_request_headers;
use function count;
use function dechex;
use function end;
use function explode;
use function gmdate;
use function header;
use function http_response_code;
use function implode;
use function strtolower;

class GetVirionModule extends Module {
    public function output() {
        header("Content-Type: text/plain");
        $args = Lang::explodeNoEmpty("/", $this->getQuery());
        if(count($args) < 4) {
            http_response_code(400);
            echo implode("\r\n", [
                "Format:", /** @lang text */
                "/v.dl/repoId/<repoId>/<project>/<version>",
                "OR", /** @lang text */
                "/v.dl/<repoOwner>/<repoName>/<project>/<version>",
                "",
                "Optional arguments: branch", // TODO api
            ]);
            return;
        }
        $repoIdentifier = strtolower($args[0]) === "repoid" ? (int) $args[1] : "$args[0]/$args[1]";
        $project = $args[2];
        if($project === "~") {
            $project = $args[1];
        }
        $version = $args[3];
        $branch = $_REQUEST["branch"] ?? ":default";

        $token = Session::getInstance()->getAccessToken(null);
        $name = Session::getInstance()->getName(null);
        if(isset($_POST["token"])) {
            $token = $_POST["token"];
            $name = null;
        }
        if(isset(apache_request_headers()["Authorization"])) {
            $parts = explode(" ", $token);
            $token = end($parts);
            $name = null;
        }
        if($token === null) {
            $token = Meta::getSecret("app.defaultToken");
        }

        try {
            $virion = Virion::findVirion($repoIdentifier, $project, $version, function($apis) {
                return true; // TODO
            }, $token, $name ?? null, $branch);

            header("X-Poggit-Virion-Version: $virion->version");
            header("X-Poggit-Virion-BuildId: " . dechex((int)$virion->buildId));
            header("X-Poggit-Virion-BuildNumber: $virion->buildNumber");
            header("X-Poggit-Virion-BuildDate: " . gmdate('D, d M Y H:i:s T', $virion->created));

            Meta::redirect("r/" . $virion->resourceId . "/$project.phar");
        } catch(UserFriendlyException $e) {
            http_response_code(404);
            echo $e->getMessage();
        }
    }
}

<?php

/*
 *
 * poggit
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\ci\builder\UserFriendlyException;
use poggit\ci\Virion;
use poggit\Meta;
use poggit\module\Module;
use poggit\utils\lang\Lang;

class GetVirionModule extends Module {
    public function getName(): string {
        return "v.dl";
    }

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
        if(strtolower($args[0]) === "repoid") {
            $repoIdentifier = (int) $args[1];
        } else {
            $repoIdentifier = "$args[0]/$args[1]";
        }
        $project = $args[2];
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
            $virion = Virion::findVirion($repoIdentifier, $project, $version, function ($apis) {
                return true; // TODO
            }, $token, $name ?? null, $branch);

            header("X-Poggit-Virion-Version: $virion->version");
            header("X-Poggit-Virion-BuildId: " . dechex($virion->buildId));
            header("X-Poggit-Virion-BuildNumber: $virion->buildNumber");
            header("X-Poggit-Virion-BuildDate: " . gmdate('D, d M Y H:i:s T', $virion->created));

            Meta::redirect("r/" . $virion->resourceId . "/$project.phar");
        } catch(UserFriendlyException $e) {
            http_response_code(404);
            echo $e->getMessage();
        }
    }
}

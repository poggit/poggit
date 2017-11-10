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

namespace poggit\release\details;

use poggit\Meta;
use poggit\module\Module;
use const poggit\RES_DIR;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\OutputManager;

class ReleaseIconProxyModule extends Module {
    public function getName(): string {
        return "icon.proxy";
    }

    public function output() {
        $releaseId = $this->getQuery();
        if(!is_numeric($releaseId)) $this->errorBadRequest("Format: /icon.proxy/{releaseId}");
        $result = Mysql::query("SELECT icon FROM releases WHERE releaseId = ?", "i", $releaseId);
        if(count($result) === 0) $this->errorNotFound(true);
        $icon = $result[0]["icon"] ?? null;
        if(is_string($icon) && Lang::startsWith($icon, "https://raw.githubusercontent.com/")) {
            $data = Curl::curlGet($icon, "User-Agent: Poggit/" . Meta::VERSION);
            if(Curl::$lastCurlResponseCode < 400) {
                $headers = Curl::parseHeaders();
                if(isset($headers["content-type"])) header("Content-Type: " . $headers["content-type"]);
                header("Content-Length: " . strlen($data));
                header("Cache-Control: public, max-age=2592000");
                header("X-Image-Src: " . $icon);
                OutputManager::terminateAll();
                echo $data;
                return;
            }
        }
        $file = RES_DIR . "defaultPluginIcon2.png";
        header("Content-Type: image/png");
        header("Content-Length: " . filesize($file));
        header("Cache-Control: public, max-age=2592000");
        OutputManager::terminateAll();
        readfile($file);
        die;
    }
}

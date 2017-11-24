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

use Gajus\Dindent\Exception\RuntimeException;
use poggit\Config;
use poggit\Meta;
use poggit\module\Module;
use poggit\release\Release;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;

class ReleaseShieldModule extends Module {
    public function getName(): string {
        return "release-shield";
    }

    public function getAllNames(): array {
        return [
            "shield.dl",
            "shield.download",
            "shield.downloads",
            "shield.dl.total",
            "shield.download.total",
            "shield.downloads.total",
            "shield.state",
            "shield.approve",
            "shield.approved",
            "shield.api",
            "shield.spoon"
        ];
    }

    public function output() {
        switch(Meta::getModuleName()) {
            case "shield.dl":
            case "shield.download":
            case "shield.downloads":
                $parts = $this->downloads();
                break;
            case "shield.dl.total":
            case "shield.download.total":
            case "shield.downloads.total":
                $parts = $this->downloadsTotal();
                break;
            case "shield.state":
            case "shield.approve":
            case "shield.approved":
                $parts = $this->state();
                break;
            case "shield.api":
            case "shield.spoon":
                $parts = $this->spoon();
                break;
            default:
                $this->errorNotFound(true);
                return;
        }
        $parts[0] = str_replace(["+", "-", "_"], ["%20", "--", "__"], urlencode($parts[0]));
        $parts[1] = str_replace(["+", "-", "_"], ["%20", "--", "__"], urlencode($parts[1]));
        $url = "https://img.shields.io/badge/" . implode("-", $parts) . ".svg?style=" . ($_REQUEST["style"] ?? "flat");
        header("Content-Type: image/svg+xml;charset=utf-8");
        header("Cache-Control: no-cache");
        echo Curl::curlGet($url);
    }

    private function downloads(): array {
        $name = $this->getQuery();
        $result = Mysql::query("SELECT releaseId, version, dlCount FROM releases INNER JOIN resources ON releases.artifact = resources.resourceId WHERE name = ? AND state >= ? ORDER BY releaseId DESC LIMIT 1", "si", $name, Config::MIN_PUBLIC_RELEASE_STATE)[0] ?? null;
        if($result === null) $this->errorNotFound(true);
        return [
            "v" . $result["version"] . "@poggit", $result["dlCount"] . " downloads",
            self::filterValue((int) $result["dlCount"], [
                0 => "red",
                25 => "orange",
                100 => "yellow",
                250 => "yellowgreen",
                500 => "green",
                1000 => "brightgreen",
            ])
        ];
    }

    private function downloadsTotal(): array {
        $name = $this->getQuery();
        $result = Mysql::query("SELECT IFNULL(SUM(dlCount), -1) dlCount FROM releases INNER JOIN resources ON releases.artifact = resources.resourceId WHERE name = ? AND state >= ?", "si", $name, Config::MIN_PUBLIC_RELEASE_STATE)[0] ?? null;
        if($result === null || -1 === (int) $result["dlCount"]) $this->errorNotFound(true);
        return [
            "poggit", $result["dlCount"] . " downloads total",
            self::filterValue((int) $result["dlCount"], [
                0 => "red",
                25 => "orange",
                100 => "yellow",
                250 => "yellowgreen",
                500 => "green",
                1000 => "brightgreen",
            ])
        ];
    }

    private function state(): array {
        $name = $this->getQuery();
        $result = Mysql::query("SELECT releaseId, version, state FROM releases INNER JOIN resources ON releases.artifact = resources.resourceId WHERE name = ? AND state >= ? ORDER BY releaseId DESC LIMIT 1", "si", $name, Release::STATE_SUBMITTED)[0] ?? null;
        if($result === null) $this->errorNotFound(true);
        return [
            "v" . $result["version"] . " @poggit", Release::$STATE_ID_TO_HUMAN[$result["state"]],
            self::filterValue((int) $result["state"], [
                Release::STATE_SUBMITTED => "red",
                Release::STATE_CHECKED => "orange",
                Release::STATE_VOTED => "yellowgreen",
                Release::STATE_APPROVED => "green",
                Release::STATE_FEATURED => "brightgreen",
            ])
        ];
    }

    private function spoon() {
        $name = $this->getQuery();
        $result = Mysql::query("SELECT r.releaseId, r.version, MAX(till.id) till FROM releases r
            INNER JOIN release_spoons spoon ON r.releaseId = spoon.releaseId
            INNER JOIN known_spoons till ON spoon.till = till.name
            WHERE r.name = ? AND r.state >= ?
            GROUP BY r.releaseId ORDER BY r.releaseId DESC LIMIT 1", "si", $name, Release::STATE_CHECKED)[0] ?? null;
        if($result === null) $this->errorNotFound(true);
        $rkeys = array_flip($lkeys = array_keys(PocketMineApi::$VERSIONS));
        $id = (int) $result["till"];
        $api = $lkeys[$id];
        $color = "red";
        if($api === PocketMineApi::LATEST) {
            $color = "brightgreen";
        } elseif($rkeys[PocketMineApi::LATEST_COMPAT] < $id) {
            $color = "green";
        } elseif($rkeys[PocketMineApi::PROMOTED] < $id) {
            $color = "yellow";
            if($rkeys["3.0.0-ALPHA7"] <= $id){
                // hardcoded
                $color = "yellowgreen";
            }
        } elseif(strstr($api, ".", true) === strstr(PocketMineApi::PROMOTED_COMPAT, ".", true)) {
            $color = "orange";
        }
        return [
            "v" . $result["version"] . " @poggit", $api,
            $color
        ];
    }

    private static function filterValue(int $value, array $mapping) {
        krsort($mapping, SORT_NUMERIC);
        foreach($mapping as $min => $result) {
            if($value >= $min) {
                return $result;
            }
        }
        throw new RuntimeException("\$value lower than smallest key in \$mapping");
    }
}

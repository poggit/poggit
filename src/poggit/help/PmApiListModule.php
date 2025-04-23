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

namespace poggit\help;

use poggit\Meta;
use poggit\module\Module;
use poggit\utils\lang\Lang;
use poggit\utils\PocketMineApi;
use XMLWriter;
use function header;
use function json_encode;
use function strpos;
use function yaml_emit;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const YAML_LN_BREAK;
use const YAML_UTF8_ENCODING;

class PmApiListModule extends Module {
    public function output() {

        $min = strpos(Meta::getModuleName(), ".min") !== false;
        $full = strpos(Meta::getModuleName(), ".full") !== false;

        if(Lang::endsWith(Meta::getModuleName(), ".xml")) {
            $this->xmlResponse($full, $min);
            return;
        }

        $value = $full ? [
            "promoted" => PocketMineApi::$PROMOTED,
            "promotedCompat" => PocketMineApi::$PROMOTED_COMPAT,
            "latest" => PocketMineApi::$LATEST,
            "latestCompat" => PocketMineApi::$LATEST_COMPAT,
            "versions" => PocketMineApi::$VERSIONS,
        ] : PocketMineApi::$VERSIONS;
        if(Lang::endsWith(Meta::getModuleName(), ".yml")) {
            header("Content-Type: text/x-yaml; charset=utf-8");
            echo yaml_emit($value, YAML_UTF8_ENCODING, YAML_LN_BREAK);
            return;
        }
        header("Content-Type: application/json");
        echo json_encode($value, ($min ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function xmlResponse(bool $full, bool $min) {
        header("Content-Type: " . ($min ? "application/xml" : "text/xml"));
        $writer = new XMLWriter;
        $writer->openURI("php://output");
        if(!$min) {
            $writer->setIndent(true);
            $writer->setIndentString("  ");
        }
        $writer->startDocument();
        if(!$min) {
            $writer->writeComment(" Documentation: https://github.com/poggit/support/pmapis.md ");
        }
        $writer->startElement("pmapis");
        if($full) {
            $writer->writeAttribute("promoted", PocketMineApi::$PROMOTED);
            $writer->writeAttribute("promotedCompat", PocketMineApi::$PROMOTED_COMPAT);
            $writer->writeAttribute("latest", PocketMineApi::$LATEST);
            $writer->writeAttribute("latestCompat", PocketMineApi::$LATEST_COMPAT);
        }
        foreach(PocketMineApi::$VERSIONS as $name => $version) {
            $writer->startElement("api");
            $writer->writeAttribute("name", $name);
            $writer->writeAttribute("incompatible", $version["incompatible"] ? "true" : "false");
            $writer->endElement();
        }
        $writer->endElement();
        $writer->endDocument();
    }
}

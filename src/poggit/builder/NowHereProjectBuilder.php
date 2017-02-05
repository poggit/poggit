<?php

/*
 * Poggit + NOWHERE Plugin Workspace Framework
 *
 * Copyright (C) 2016-2017 Poggit and PEMapModder
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

namespace poggit\builder;

use Phar;
use poggit\builder\lint\BuildResult;
use poggit\module\webhooks\repo\WebhookProjectModel;
use poggit\utils\lang\LangUtils;

class NowHereProjectBuilder extends ProjectBuilder {
    public function getName(): string {
        return "nowhere";
    }

    public function getVersion(): string {
        return "2.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult {
        $result = new BuildResult();
        
        $info = json_decode($zipball->getContents($project->path . "nowhere.json")); // TODO check for existence
        $NAME = $info->name;
        $CLASS = $phar->getMetadata()["class"];
        $BUILD_NUMBER = $phar->getMetadata()["projectBuildNumber"];
        $VERSION = "{$info->version->major}.{$info->version->minor}-{$CLASS}#{$BUILD_NUMBER}";
        
        $permissions = [];
        if($zipball->isFile($project->path . "permissions.xml")) {
            $permissions = $this->parsePerms((new \SimpleXMLElement($zipball->getContents($project->path . "permissions.xml"))), [])["children"];
        }

        $phar->setStub('<?php require_once "phar://" . __FILE__ . "/entry/entry.php"; __HALT_COMPILER();');
        $phar->addFromString("plugin.yml", yaml_emit([
                    "name" => $NAME,
                    "author" => $info->author,
                    "authors" => $info->authors ?? [],
                    "main" => $info->main,
                    "api" => $info->api,
                    "depend" => $info->depend ?? [],
                    "softdepend" => $info->softdepend ?? [],
                    "loadbefore" => $info->loadbefore ?? [],
                    "description" => $info->description ?? "",
                    "website" => $info->website ?? "",
                    "prefix" => $info->prefix ?? $NAME,
                    "load" => $info->load ?? "POSTWORLD",
                    "version" => $VERSION,
                    "commands" => $info->commands ?? [],
                    "permissions" => $permissions,
                    "generated" => date(DATE_ISO8601)
]));
        $this->addDir($zipball, $phar, $project->path . "src/", "src/");
        $this->addDir($zipball, $phar, $project->path . "entry/", "entry/");
        $this->addDir($zipball, $phar, $project->path . "resources/", "resources/");
        // TODO lint
        return $result;
    }
    
    protected function addDir(RepoZipball $zipball, Phar $phar, string $from, string $localDir) {
        /** @type SplFileInfo $file */
        foreach($zipball->iterator("", true) as $file => $getCont) {
            if(substr($file, -1) === "/" or !LangUtils::startsWith($file, $from)) continue;
            $phar->addFromString($localDir . substr($file, strlen($from)), $getCont());
        }
    }

    protected function parsePerms(\SimpleXMLElement $element, array $parents){
        $prefix = "";
        foreach($parents as $parent){
            $prefix .= $parent . ".";
        }
        $description = (string) $element->attributes()->description;
        $default = (string) $element->attributes()->default;
        $children = [];
        foreach($element->children() as $childName => $child){
            $copy = $parents;
            $copy[] = $childName;
            $children[$prefix . $childName] = parsePerms($child, $copy);
        }
        return [
            "description" => $description,
            "default" => $default,
            "children" => $children,
        ];
    }
}

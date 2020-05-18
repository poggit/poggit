<?php

/*
 * Poggit + NOWHERE Plugin Workspace Framework
 *
 * Copyright (C) 2016-2018 Poggit and PEMapModder
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

namespace poggit\ci\builder;

use Phar;
use poggit\ci\lint\BuildResult;
use poggit\ci\lint\ManifestMissingBuildError;
use poggit\ci\RepoZipball;
use poggit\ci\Virion;
use poggit\Meta;
use poggit\utils\lang\Lang;
use poggit\webhook\WebhookProjectModel;
use SimpleXMLElement;
use SplFileInfo;
use function array_slice;
use function date;
use function explode;
use function implode;
use function json_decode;
use function strlen;
use function strtolower;
use function substr;
use const DATE_ATOM;

class NowHereProjectBuilder extends ProjectBuilder {
    public function getName(): string {
        return "nowhere";
    }

    public function getVersion(): string {
        return "2.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project, int $buildId, bool $isRepoPrivate): BuildResult {
        $this->project = $project;
        $this->tempFile = Meta::getTmpFile(".php");

        $result = new BuildResult();

        if(!$zipball->isFile($project->path . "nowhere.json")) {
            echo "Cannot find {$project->path}nowhere.json\n";
            $status = new ManifestMissingBuildError();
            $status->manifestName = $project->path . "plugin.yml";
            $result->addStatus($status);
            return $result;
        }
        $info = json_decode($zipball->getContents($project->path . "nowhere.json"));
        $name = $info->name;
        $class = $phar->getMetadata()["buildClass"];
        $buildNumber = $phar->getMetadata()["projectBuildNumber"];
        $version = "{$info->version->major}.{$info->version->minor}-{$class}#{$buildNumber}";

        $permissions = [];
        if($zipball->isFile($project->path . "permissions.xml")) {
            $permissions = $this->parsePerms(new SimpleXMLElement($zipball->getContents($project->path . "permissions.xml")), [])["children"];
        }

        $phar->setStub('<?php require_once "phar://" . __FILE__ . "/entry/entry.php"; __HALT_COMPILER();');
        $yaml = yaml_emit([
            "name" => $name,
            "author" => $info->author,
            "authors" => $info->authors ?? [],
            "main" => $info->main,
            "api" => $info->api,
            "depend" => $info->depend ?? [],
            "softdepend" => $info->softdepend ?? [],
            "loadbefore" => $info->loadbefore ?? [],
            "description" => $info->description ?? "",
            "website" => $info->website ?? "",
            "prefix" => $info->prefix ?? $name,
            "load" => $info->load ?? "POSTWORLD",
            "version" => $version,
            "commands" => $info->commands ?? [],
            "permissions" => $permissions,
            "generated" => date(DATE_ATOM)
        ]);
        $mainClassFile = $this->lintManifest($zipball, $result, $yaml, $mainClass);
        $phar->addFromString("plugin.yml", $yaml);

        $this->addDir($result, $zipball, $phar, $project->path . "src/", "src/", $mainClassFile);
        $this->addDir($result, $zipball, $phar, $project->path . "entry/", "entry/");
        $this->addDir($result, $zipball, $phar, $project->path . "resources/", "resources/");

        Virion::processLibs($phar, $zipball, $project, function() use ($mainClass) {
            return implode("\\", array_slice(explode("\\", $mainClass), 0, -1)) . "\\";
        });

        return $result;
    }

    protected function addDir(BuildResult $result, RepoZipball $zipball, Phar $phar, string $from, string $localDir, string $mainClassFile = null) {
        /** @type SplFileInfo $file */
        foreach($zipball->iterator("", true) as $file => $getCont) {
            if(substr($file, -1) === "/" or !Lang::startsWith($file, $from)) continue;
            $phar->addFromString($localName = $localDir . substr($file, strlen($from)), $contents = $getCont());
            if($mainClassFile !== null and Lang::endsWith(strtolower($file), ".php")) {
                $this->lintPhpFile($result, $localName, $contents, $localName === $mainClassFile);
            }
        }
    }

    protected function parsePerms(SimpleXMLElement $element, array $parents) {
        $prefix = "";
        foreach($parents as $parent) {
            $prefix .= $parent . ".";
        }
        $description = (string) $element->attributes()->description;
        $default = (string) $element->attributes()->default;
        $children = [];
        foreach($element->children() as $childName => $child) {
            $copy = $parents;
            $copy[] = $childName;
            $children[$prefix . $childName] = $this->parsePerms($child, $copy);
        }
        return [
            "description" => $description,
            "default" => $default,
            "children" => $children,
        ];
    }
}

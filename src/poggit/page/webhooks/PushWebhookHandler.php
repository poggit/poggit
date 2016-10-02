<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\webhooks;

use poggit\model\ProjectThumbnail;
use poggit\page\webhooks\framework\FrameworkBuilder;
use poggit\Poggit;
use poggit\resource\ResourceManager;

class PushWebhookHandler extends WebhookHandler {
    /** @var \stdClass */
    private $repo;
    /** @var string */
    private $branch;
    /** @var string */
    private $token;
    /** @var int */
    private $originalMaxGlobalBuildId, $maxGlobalBuildId;
    /** @var int */
    private $originalMaxProjectId, $maxProjectId;
    /** @var \ZipArchive */
    private $zip;
    /** @var int */
    private $zipPrefix;
    /** @var ProjectThumbnail[] */
    private $projectsBefore, $projectsNow;

    public function handle() {
        $this->branch = $this->refToBranch($this->payload->ref);
        $this->repo = $this->payload->repository;
        $repoId = $this->repo->id;
        echo "Loading repo configuration\n";
        $rows = Poggit::queryAndFetch("SELECT r.owner, r.name, u.token AS accessWith,
            (SELECT IFNULL(MAX(buildId), 0x1AB00) FROM builds) AS maxGlobalBuildId,
            (SELECT IFNULL(MAX(projectId), 0) FROM projects) AS maxProjectId
            FROM repos r INNER JOIN users u ON r.accessWith = u.uid
            WHERE r.repoId = ? AND r.build = 1", "i", $repoId);
        if(count($rows) === 0) {
            $this->setResult(false, "Poggit Build disabled for repo");
        }
        $repoRow = $rows[0];
        $this->token = $repoRow["accessWith"];
        $this->originalMaxGlobalBuildId = $this->maxGlobalBuildId = (int) $repoRow["maxGlobalBuildId"];
        $this->originalMaxProjectId = $this->maxProjectId = (int) $repoRow["maxProjectId"];
        if($repoRow["owner"] !== $this->repo->owner->name or $repoRow["name"] !== $this->repo->name) {
            echo "Repo renamed, updating server-side ref\n";
            Poggit::queryAndFetch("UPDATE repos SET owner = ?, name = ? WHERE repoId = ?", "ssi", $this->repo->owner->login, $this->repo->name, $repoId);
        }

        echo "Downloading repo\n";
        $this->zip = $this->downloadZipball();
        $this->zipPrefix = $this->zip->getNameIndex(0);
        assert(strpos($this->zipPrefix, "/") === strlen($this->zipPrefix) - 1, "Failed to detect root directory");

        echo "Downloading project history\n";
        $this->fetchProjects($repoId);
        echo "Locating projects\n";
        $this->findProjectsFromZip();
        echo "Updating project history\n";
        $this->updateProjects();

        foreach($this->projectsChanged() as $project) {
            echo "Start building project $project->name\n";
            if(!isset(FrameworkBuilder::$builders[strtolower($project->framework)])) {
                echo "Cannot build project $project->name: unknown framework $project->framework\n";
                continue;
            }
            $builder = clone FrameworkBuilder::$builders[strtolower($project->framework)];
            $filters = [];
            if($this->repo->private) {
                $filters[] = [
                    "type" => "repoAccess",
                    "repo" => [
                        "id" => $this->repo->id,
                        "owner" => $this->repo->owner->login,
                        "name" => $this->repo->name,
                        "requiredPerms" => ["pull"]
                    ]
                ];
            }
            $file = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", $filters, $resourceId);
            $phar = new \Phar($file);
            $phar->startBuffering();
            $phar->setSignatureAlgorithm(\Phar::SHA1);
            $builder->build($this, $project, $phar);
            $metadata = [
                "builder" => "Poggit/" . Poggit::POGGIT_VERSION . " " .
                    $builder->getName() . "/" . $builder->getVersion(),
                "buildTime" => date(DATE_ISO8601),
                "poggitBuildId" => $buildId = $this->nextGlobalBuildId(),
                "projectBuildId" => $internalId = ++$project->latestBuildInternalId,
                "class" => Poggit::$BUILD_CLASS_HUMAN[Poggit::BUILD_CLASS_DEV],
            ];
            $lps = [
                $project->path . "LICENSE.md",
                $project->path . "LICENSE.txt",
                $project->path . "LICENSE",
                "LICENSE.md",
                "LICENSE.txt",
                "LICENSE",
            ];
            foreach($lps as $lp) {
                $this->getRepoFileByName($lp, $license);
                if(is_string($license)) {
                    $metadata["license"] = $license;
                    break;
                }
            }
            // TODO libraries
            // TODO lang
            // TODO docs
            $phar->setMetadata($metadata);
            $phar->stopBuffering();
            echo "Finished building $file, md5 hash = " . md5_file($file) . "\n";
            Poggit::queryAndFetch("INSERT INTO builds (buildId, resourceId, projectId, class, branch, head, internal)
                VALUES (?, ?, ?, ?, ?, ?, ?)", "iiiissi", $buildId, $resourceId, $project->id, Poggit::BUILD_CLASS_DEV,
                $this->branch, $this->payload->after, $internalId);
        }
    }

    private function fetchProjects(int $repoId) {
        $rows = Poggit::queryAndFetch("SELECT projectId, name, path, type, framework, lang,
            (SELECT IFNULL(MAX(internal), 0) FROM builds WHERE builds.projectId=projects.projectId) AS maxInternal
            FROM projects WHERE repoId = ?", "i", $repoId);
        foreach($rows as $row) {
            $project = new ProjectThumbnail();
            $project->id = (int) $row["projectId"];
            $project->name = $row["name"];
            $project->path = $row["path"];
            $project->type = (int) $row["type"];
            $project->framework = $row["framework"];
            $project->lang = (bool) (int) $row["lang"];
            $project->latestBuildInternalId = (int) $row["maxInternal"];
            $this->projectsBefore[$project->name] = $project;
        }
    }

    private function downloadZipball() : \ZipArchive {
        $file = Poggit::getTmpFile(".zip");
        file_put_contents($file, Poggit::ghApiGet("repos/{$this->payload->repository->full_name}/zipball/$this->branch", $this->token, false, true));
        echo "Download zipball: $file\n";
        $zip = new \ZipArchive();
        if($zip->open($file) !== true) {
            $this->setResult(false, "Failed to download repo zipball");
        }
        return $zip;
    }

    private function findProjectsFromZip() {
        $this->getRepoFileByName($yp = ".poggit/.poggit.yml", $yaml);
        if($yaml === false) {
            $this->getRepoFileByName($yp = ".poggit.yml", $yaml);
            if($yaml === false) {
                $this->setResult(false, ".poggit.yml not found!");
            }
        }
        $manifest = yaml_parse($yaml);
        if($manifest === false) {
            $this->setResult(false, "Error parsing $yp");
        }
        if(isset($manifest["branches"])) {
            $branches = (array) $manifest["branches"];
            if(count($branches) > 0 and !in_array($this->branch, $manifest)) {
                $this->setResult(false, "Branch $this->branch is not in whitelist");
            }
        }

        $projects = [];
        /**
         * @var string $name
         * @var array  $pa
         */
        foreach($manifest["projects"] as $name => $pa) {
            $po = new ProjectThumbnail();
            $po->name = $name;
            $po->path = (trim($pa["path"] ?? "", "/") . "/");
            if($po->path === "/") $po->path = "";
            $po->framework = $pa["model"] ?? "default";
            static $projectTypes = [
                "lib" => Poggit::PROJECT_TYPE_LIBRARY,
                "library" => Poggit::PROJECT_TYPE_LIBRARY,
            ];
            $po->type = $projectTypes[strtolower($pa["type"] ?? "")] ?? Poggit::PROJECT_TYPE_PLUGIN;
            $po->lang = isset($pa["lang"]);
            $projects[$po->name] = $po;
        }
        $this->projectsNow = $projects;
    }

    private function updateProjects() {
        foreach($this->projectsNow as $project) {
            if(isset($this->projectsBefore[$project->name])) {
                $before = $this->projectsBefore[$project->name];
                // copy Poggit internal info from $before to $project
                $project->id = $before->id;
                $project->latestBuildInternalId = $before->latestBuildInternalId;
                Poggit::queryAndFetch("UPDATE projects SET path = ?, type = ?, framework = ?, lang = ?
                    WHERE projectId = ?", "sisii", $project->path, $project->type, $project->framework,
                    $project->lang ? 1 : 0, $project->id);
            } else {
                $project->id = $this->nextProjectId();
                $project->latestBuildInternalId = 0;
                Poggit::queryAndFetch("INSERT INTO projects (projectId, repoId, name, path, type, framework, lang)
                    VALUES (?, ?, ?, ?, ?, ?, ?)", "iissisi", $project->id, $this->repo->id, $project->name,
                    $project->path, $project->type, $project->framework, $project->lang ? 1 : 0);
            }
        }
    }

    private function nextProjectId() : int {
        return ++$this->maxProjectId;
    }

    public function nextGlobalBuildId() : int {
        return ++$this->maxGlobalBuildId;
    }

    /**
     * @return ProjectThumbnail[]
     */
    private function projectsChanged() : array {
        return array_filter($this->projectsNow, function (ProjectThumbnail $project) {
            if($project->latestBuildInternalId === 0) {
                return true;
            }
            if($project->path === "") {
                return true;
            }
            foreach($this->payload->commits as $commit) {
                // TODO document these two ifs
                if(stripos($commit->message, "\npoggit: build all") !== false) {
                    return true;
                }
                if(stripos($commit->message, "\npoggit: build $project->name") !== false) {
                    return true;
                }
                if(stripos($commit->message, "\npoggit, build all") !== false) {
                    return true;
                }
                if(stripos($commit->message, "\npoggit, build $project->name") !== false) {
                    return true;
                }
                foreach(array_merge($commit->added, $commit->removed, $commit->modified) as $file) {
                    if($file === ".poggit/.poggit.yml" or $file === ".poggit.yml") {
                        return true;
                    }
                    if(strlen($file) < $project->path) {
                        continue;
                    }
                    if(substr($file, 0, strlen($project->path)) === $project->path) {
                        return true;
                    }
                }
            }
            return false;
        });
    }

    public function getRepoFileByName(string $fileName, &$contents = "", &$index = -1) : bool {
        $fileName = $this->zipPrefix . $fileName;
        if($contents === null) {
            $contents = $this->zip->getFromName($fileName);
        }
        $index = $this->zip->locateName($fileName);
        return $index !== false; // whether the file exists
    }

    public function getRepoFileByIndex(int $index, &$fileName, &$contents = "") : bool {
        $ni = $this->zip->getNameIndex($index);
        if($ni === false) {
            return false; // no file with such index
        }
        $fileName = substr($ni, strlen($this->zipPrefix));
        if($contents === null) {
            $contents = $this->zip->getFromIndex($index);
        }
        return true;
    }

    public function getZip() : \ZipArchive {
        return $this->zip;
    }
}

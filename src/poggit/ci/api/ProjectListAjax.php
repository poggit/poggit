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
use poggit\module\AjaxModule;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;

class ProjectListAjax extends AjaxModule {
    public function getName(): string {
        return "ci.project.list";
    }

    protected function impl() {
        $owner = $this->param("owner");
        $repos = [];
        $session = Session::getInstance();
        $token = $session->getAccessToken();
        $session->close();
        foreach(Curl::listHisRepos($owner, $token,
            "id: databaseId 
            owner{ login }
            name 
            admin: viewerCanAdminister") as $repo){
            if($repo->admin && strtolower($repo->owner->login) === strtolower($owner)){
                $repo->projectsCount = 0;
                $repos[$repo->id] = $repo;
            }
        }
        if(count($repos) > 0){
            foreach(Mysql::arrayQuery("SELECT repoId, COUNT(*) projectsCount FROM projects
            WHERE repoId IN (%s) GROUP BY repoId", ["i", array_keys($repos)]) as $row){
                $repos[$row["repoId"]]->projectsCount = (int) $row["projectsCount"];
            }
        }
        echo json_encode($repos);
    }
}

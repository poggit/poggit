<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\page\webhooks;

use poggit\page\Page;
use poggit\Poggit;

class GitHubRepoWebhook extends Page {
    public static function extPath() {
        return Poggit::getSecret("meta.extPath") . "webhooks.gh.repo";
    }

    public function getName() : string {
        return "webhooks.gh.repo";
    }

    public function output() {
        // TODO handle webhook events
    }
}

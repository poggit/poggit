<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\module\webhooks;

use poggit\Poggit;

class PullRequestWebhookHandler extends WebhookHandler {
    /** @var string */
    private $temporalFile;
    /** @var \stdClass */
    private $pr, $repo;
    /** @var string */
    private $token;

    public function handle() {
        $this->temporalFile = Poggit::getTmpFile(".php");

        $this->pr = $this->payload->pull_request;
        $this->repo = $this->payload->repository;
        $rows = Poggit::queryAndFetch("SELECT u.token FROM repos r
            INNER JOIN users u ON r.accessWith = u.uid
            WHERE id=? AND build=1", "i", $this->repo->id);
        if(count($rows) === 0) $this->setResult(false, "Poggit Build disabled for this repo");
        $this->token = $rows[0]["token"];
        switch($this->payload->action) {
            case "opened":
                $this->handleOpen();
                break;
            case "synchronized":
                $this->handleSync();
                break;
        }
    }

    private function handleOpen() {
        if($this->pr->head->label !== $this->pr->base->label) return;
        $cmp = Poggit::ghApiGet("repos/" . $this->repo->full_name . "/compare/" . $this->pr->base->sha . "..." . $this->pr->head->sha, $this->token);

    }

    private function handleSync() {
        if($this->pr->head->label !== $this->pr->base->label) return;
    }
}

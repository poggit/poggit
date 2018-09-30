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

namespace poggit\webhook;

use Exception;
use poggit\Meta;
use poggit\utils\internet\GitHub;
use RuntimeException;
use function wordwrap;

class WebhookException extends Exception {
    const LOG_INTERNAL = 1;
    const OUTPUT_TO_RESPONSE = 2;
    const NOTIFY_AS_COMMENT = 4;
    /** @var string|null */
    public $repoFullName;
    /** @var string|null */
    public $sha;
    /** @var bool */
    public $markdown;

    public function __construct($message = "", $code = WebhookException::OUTPUT_TO_RESPONSE, string $repoFullName = null, string $sha = null, bool $markdown = false) {
        parent::__construct($message, $code);
        if($code & self::NOTIFY_AS_COMMENT) {
            if(!isset($repoFullName, $sha)) {
                throw new RuntimeException('Missing parameters $repoFullName and $sha!');
            }
        }
        $this->repoFullName = $repoFullName;
        $this->sha = $sha;
        $this->markdown = $markdown;
    }

    public function notifyAsComment() {
        GitHub::ghApiPost("repos/{$this->repoFullName}/commits/{$this->sha}/comments", [
            "body" => "Dear Poggit user,\n\n" .
                "This is an automatic message from Poggit-CI. Poggit-CI was triggered by this commit, but failed to " .
                "create builds due to the following error:\n\n" .
                ($this->markdown ? "" : "```\n") .
                wordwrap($this->getMessage()) .
                ($this->markdown ? "" : "\n```") .
                "\n\nAs a result, no builds could be created from this commit. More details might be available for " .
                "repo admins at " .
                "[the webhook delivery response log](https://github.com/{$this->repoFullName}/settings/hooks) &mdash; " .
                "see the webhook starting with `https://poggit.pmmp.io/webhooks.gh.repo` and look for the delivery " .
                "with ID `{$_SERVER["HTTP_X_GITHUB_DELIVERY"]}`.\n\n" .
                "Shall you need any assistance, you may contact us on [on Discord](" . Meta::getSecret("discord.serverInvite") . "), " .
                "or more publicly, [on GitHub](https://github.com/poggit/support/issues). Commenting on this commit " .
                "directly will **not** notify any Poggit staff.\n\n" .
                "Regards,\n" .
                "Poggit Bot (@poggit-bot)\n" .
                "The official Poggit automation account"
        ], Meta::getSecret("app.botToken"));
    }
}

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

namespace poggit\timeline;

use poggit\Meta;
use function dechex;
use function gmdate;

class BuildCompleteTimeLineEvent extends TimeLineEvent {
    public $buildId;
    public $name;

    public function output() {
        ?>
      <!-- TODO process this in js using BuildInfoApi -->
      <div data-eventid="<?= $this->eventId ?>" class="buildCompleteEvent">
        <h6><?= isset($this->name) ? $this->name . " - " : "Unknown " ?> build <a
              href="<?= Meta::getSecret("meta.extPath") ?>babs/<?= dechex((int)$this->buildId) ?>">&amp;<?= dechex($this->buildId) ?></a>
          (<?= gmdate("Y-m-d H:i:s", $this->created) ?>&nbsp;UTC)</h6>
      </div>
        <?php
    }

    public function getType(): int {
        return TimeLineEvent::EVENT_BUILD_COMPLETE;
    }
}

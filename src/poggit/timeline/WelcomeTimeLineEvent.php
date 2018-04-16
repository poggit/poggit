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

use function rtrim;

class WelcomeTimeLineEvent extends TimeLineEvent {

    public $jointime;
    public $details;

    public function output() {
        if(isset($this->jointime)) { ?>
        <div data-eventid="<?= $this->eventId ?>" class="welcomeTimelineEvent">
          <h6>Logged in on <?= rtrim($this->jointime->date, '.000000') ?>&nbsp;<?= $this->jointime->timezone ?></h6>
          </div><?php }
    }

    public function getType(): int {
        return TimeLineEvent::EVENT_WELCOME;
    }

}

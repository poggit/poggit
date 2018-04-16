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

namespace poggit\ci\lint;

class DirectStdoutLint extends BadPracticeLint {
    /** @var bool */
    public $isHtml;
    /** @var bool */
    public $isFileMain;

    public function problemAsNounPhrase(): string {
        return $this->isHtml ? "Use of inline HTML" : "Use of echo";
    }

    public function moreElaboration() {
        ?>
      <p>PocketMine provides a logger mechanism, which can replace the conventional <code class="code">echo</code> /
        <code class="code">print</code> calls. The message is preprocessed such that:</p>
      <ul>
        <li>Timestamp, severity level and source of message (your plugin) is mentioned.</li>
        <li>Messages of the DEBUG level are only written if server has debug mode on</li>
        <li>Accepts color codes in Minecraft chat colors format (<code class="code">&sect;</code>) and converts them
          into ANSI codes depending on the environment
        </li>
        <li>Log messages to the server.log file, and other log watchers (attachments), too</li>
      </ul>
      <p>You can use it like this:</p>
      <pre class="code"><?= $this->isFileMain ? '$this' : '$plugin' ?>-&gt;getLogger()-&gt;info("...");</pre>
        <?php if(!$this->isFileMain) { ?>
        where <code class="code">$plugin</code> is the reference to your main class object
        <?php } ?>
      You can also use <a href="//php.net/heredoc">HEREDOC</a> or <a href="//php.net/nowdoc">NOWDOC</a> if you want
      to store many lines of text into a variable.
        <?php
    }
}

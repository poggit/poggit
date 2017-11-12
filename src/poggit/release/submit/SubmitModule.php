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

namespace poggit\release\submit;

use poggit\Meta;
use poggit\module\Module;
use poggit\utils\OutputManager;

class SubmitModule extends Module {
    public function getName(): string {
        return "submit";
    }

    public function getAllNames(): array {
        return ["submit", "update", "edit"];
    }

    public function output() {
        $minifier = OutputManager::startMinifyHtml();
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Submit Plugin") ?>
        <title>Loading submit form...</title>
      </head>
      <body>
      <?php $this->bodyHeader(); ?>
      <div id="body" class="mainwrapper realsubmitwrapper">
        <div class="submittitle" id="submit-title">
          <div id="submit-title-action"></div>
          <div class='submittitle-gh' id="submit-title-gh"></div>
          <div class='submittitle-badge' id="submit-title-badge">
          </div>
        </div>
        <div class="submitintro" id="submit-intro">
          <div id="submit-intro-last-name" style="display: none;"></div>
          <p class="remark">Your plugin will be reviewed by Poggit reviewers according to <a
                href="<?= Meta::root() ?>pqrs" target="_blank">PQRS</a>.</p>
          <p class="remark">
            <strong>Do no submit plugins written by other people without prior consent from the author. This may
              be considered as plagiarism, and your access to Poggit may be blocked if you do so.</strong>
            If you want them to be available on Poggit, please request it at the
            <a href="https://github.com/poggit-orphanage/office/issues" target="_blank">Poggit Orphanage
              Office</a>.
            <br/>
            If you only rewrote the plugin but did not take any code from the original author, consider using a
            new plugin name, or at least add something like <code>_New</code> behind the plugin name. Consider
            adding the original author as a <em>Requester</em> in the <em>Producers</em> field below.<br/>
            If you have used some code from the original author but have made major changes to the plugin, you are
            allowed to submit this plugin from your <em>fork</em> repo, but you <strong>must</strong> add the
            original author as a <em>collaborator</em> in the <em>Producers</em> field below.
          </p>
          <p class="remark">Note: If you don't submit this form within three hours after loading this page, this
            form will become invalid and you will have to reload this page.</p>
        </div>
        <div class="form-table">
          <h3>Loading...</h3>
          <p>If this page doesn't load in a few seconds, try refreshing the page. JavaScript must be enabled to
            use this page.</p>
        </div>
      </div>
      <div id="wait-spinner" class="loading">Loading...</div>
      <?php
      $this->bodyFooter();
      Module::queueJs("newSubmit");
      $this->flushJsList();
      ?>
      </body>
      </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}

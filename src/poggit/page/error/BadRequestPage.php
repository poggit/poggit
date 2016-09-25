<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\page\error;

use poggit\page\Page;
use const poggit\RES_DIR;

class BadRequestPage extends Page{
    public function getName() : string {
        return "err";
    }

    public function output() {
        http_response_code(500);
        ?>
        <html>
        <head>
            <style type="text/css">
                <?php readfile(RES_DIR . "style.css") ?>
            </style>
            <title>400 Bad Request</title>
        </head>
        <body>
        <h1>400 Bad Request</h1>
        <p>You entered an invalid link that points to an invalid resource.</p>
        <p><?= $this->getQuery() ?></p>
        </body>
        </html>
        <?php
    }
}

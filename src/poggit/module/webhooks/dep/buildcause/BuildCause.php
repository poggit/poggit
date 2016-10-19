<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\module\webhooks\buildcause;

use poggit\module\error\InternalErrorPage;
use poggit\output\OutputManager;

abstract class BuildCause implements \JsonSerializable {
    public $name;

    public function jsonSerialize() {
        $this->name = (new \ReflectionClass($this))->getShortName();
        return $this;
    }

    public static function fromObject(\stdClass $object) : BuildCause {
        if(!isset($object->name)) {
            http_response_code(500);
            OutputManager::terminateAll();
            (new InternalErrorPage("closed-issue:obsolete-builds"))->output();
            die;
        }
        /** @var BuildCause $cause */
        $cause = (new \ReflectionClass(__NAMESPACE__ . "\\" . $object->name))->newInstance();
        foreach($object as $key => $value) {
            $cause->{$key} = $value;
        }
        return $cause;
    }

    public abstract function outputHtml();
}

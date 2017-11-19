<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

namespace poggit\utils;

use Gajus\Dindent\Exception\RuntimeException;
use poggit\Meta;
use const poggit\LOG_DIR;
use poggit\utils\lang\NativeError;

class Log {
    const ACCESS = "access";
    const ERROR = "error";
    const CURL = "curl";
    const MYSQL = "mysql";
    const AUDIT = "audit";
    const WEBHOOK = "webhook";
    const WEBHOOK_ERROR = "webhook.error";

    public static function log(string $channel, string $format, ...$args) {
        $now = round(microtime(true), 3);
        $date = $month = date("M");
        $date .= date(" j H:i:s", $now) . str_pad(strstr((string) $now, "."), 4, "0", STR_PAD_RIGHT);
        $requestId = Meta::getRequestId();
        $line = "$date [$requestId] " . vsprintf($format, $args);
        file_put_contents(LOG_DIR . "$channel.$month.log", $line, FILE_APPEND);
    }

    public static function exception(\Throwable $throwable) {
        if($throwable instanceof NativeError) {
            self::log(self::ERROR, $throwable->getMessage());
        } else {
            self::log(self::ERROR, "%s: %s in %s#L%d (@%s)", get_class($throwable), $throwable->getMessage(), $throwable->getFile(), $throwable->getLine(), substr(Meta::$GIT_COMMIT ?? str_repeat("x", 7), 0, 7));
        }

        foreach($throwable->getTrace() as $i => $trace) {
            self::log(self::ERROR, "+- %02s %s#L%d: %s%s%s %s", $i, $trace["file"], $trace["line"], $trace["class"] ?? "", $trace["type"] ?? "", $trace["function"], self::formatArgs($trace["args"] ?? []));
        }
    }

    public static function access(string $format, ...$args) {
        self::log(self::ACCESS, $format, ...$args);
    }
    public static function audit(string $format, ...$args) {
        self::log(self::AUDIT, $format, ...$args);
    }
    public static function curl(string $format, ...$args) {
        self::log(self::CURL, $format, ...$args);
    }
    public static function mysql(string $format, ...$args) {
        self::log(self::MYSQL, $format, ...$args);
    }
    public static function curlRetry(string $format, ...$args) {
        self::log(self::CURL, "Retry: " . $format, ...$args);
    }
    public static function webhook(string $format, ...$args) {
        self::log(self::WEBHOOK, $format, ...$args);
    }
    public static function webhookError(string $format, ...$args) {
        self::log(self::WEBHOOK_ERROR, $format, ...$args);
    }

    private static function formatArgs(array $args, string $open = "(", string $close = ")", bool $hasKey = false): string {
        $out = [];
        foreach($args as $k => $arg) {
            $prefix = $hasKey ? (json_encode($k) . ": ") : "";
            if($arg === null) $out[] = $hasKey;
            if(is_resource($arg)) return "resource(" . ((int) $arg) . ")";
            if(is_array($arg)) return self::formatArgs($arg, "[", "]", true);
            if(is_string($arg) || is_int($arg) || is_float($arg) || is_bool($arg)) return json_encode($arg);
            if(is_object($arg)) {
                if(get_class($arg) === \stdClass::class) return self::formatArgs($arg, "{", "}", true);
                return get_class($arg);
            }
        }
        return $open . implode(", ", $out) . $close;
    }
}

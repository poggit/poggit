"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var release_1 = require("./release");
exports.Config = {
    MAX_PHAR_SIZE: 2 << 20,
    MAX_ZIPBALL_SIZE: 10 << 20,
    MAX_RAW_VIRION_SIZE: 5 << 20,
    MAX_REVIEW_LENGTH: 512,
    MAX_VERSION_LENGTH: 20,
    MAX_KEYWORD_COUNT: 100,
    MAX_KEYWORD_LENGTH: 20,
    MIN_SHORT_DESC_LENGTH: 10,
    MAX_SHORT_DESC_LENGTH: 128,
    MIN_DESCRIPTION_LENGTH: 100,
    MAX_LICENSE_LENGTH: 51200,
    MIN_CHANGELOG_LENGTH: 10,
    MAX_WEEKLY_BUILDS: 60,
    RECENT_BUILDS_RANGE: 172800,
    MIN_PUBLIC_RELEASE_STATE: release_1.Release.State.Voted,
    MIN_DEV_STATE: release_1.Release.State.Voted,
    VOTED_THRESHOLD: 5,
    MAX_INLINE_SIZE: 4096,
};

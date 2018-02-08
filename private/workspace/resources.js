"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var version_1 = require("../version");
var db_1 = require("../db");
var path = require("path");
function createResource(type, mime, src, duration, accessFilters, consumer, onError) {
    db_1.db.insert("INSERT INTO resources (type, mimeType, accessFilters, duration, src) VALUES (?, ?, ?, ?, ?)", [type, mime, JSON.stringify(accessFilters), duration / 1000, src], onError, function (resourceId) {
        consumer(resourceId, path.join(version_1.POGGIT.INSTALL_ROOT, "resources", Math.floor(resourceId / 1000).toString(), resourceId + "." + type));
    });
}
exports.createResource = createResource;

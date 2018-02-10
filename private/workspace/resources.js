"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var version_1 = require("../version");
var db_1 = require("../db");
var path = require("path");
var fs = require("fs");
function createResource(type, mime, src, duration, accessFilters, consumer, onError) {
    db_1.db.insert("INSERT INTO resources (type, mimeType, accessFilters, duration, src) VALUES (?, ?, ?, ?, ?)", [type, mime, JSON.stringify(accessFilters), duration / 1000, src], onError, function (resourceId) {
        var dir = path.join(version_1.POGGIT.INSTALL_ROOT, "resources", Math.floor(resourceId / 1000).toString());
        var file = path.join(dir, resourceId + "." + type);
        fs.mkdir(dir, function (err) {
            if (err) {
                onError(err);
            }
            else {
                consumer(resourceId, file);
            }
        });
    });
}
exports.createResource = createResource;

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var fs = require("fs");
var path = require("path");
var head = path.join(__dirname, "..", ".git", ".HEAD");
var sha = "0000000", branch = "";
if (fs.existsSync(head)) {
    var contents = fs.readFileSync(head).toString("utf8");
    if (contents.charAt(contents.length - 1) === "\n") {
        contents = contents.substr(0, contents.length - 1);
    }
    if (/^[0-9a-f]{40}$/i.test(contents)) {
        sha = contents.toLowerCase();
    }
    else if (contents.indexOf("ref: ") === 0) {
        branch = contents.split("/", 3)[2];
        var ref = path.join(__dirname, "..", ".git", contents.substr(5));
        if (fs.existsSync(ref)) {
            sha = fs.readFileSync(ref).toString("utf8");
            if (sha.charAt(sha.length - 1) === "\n") {
                sha = sha.substr(0, sha.length - 1);
            }
        }
    }
}
exports.POGGIT = {
    VERSION: "2.0-gamma",
    INSTALL_ROOT: path.join(__dirname, ".."),
    GIT_COMMIT: sha,
    GIT_BRANCH: branch,
};

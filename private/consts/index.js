"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var ui_lib = require("../ui/lib");
var people_1 = require("./people");
var secrets_1 = require("../secrets");
var release_1 = require("./release");
var version_1 = require("../version");
function initAppLocals(locals) {
    locals.PoggitConsts = {
        AdminLevel: people_1.people.AdminLevel,
        Staff: people_1.people.StaffList,
        Release: release_1.Release,
        Debug: secrets_1.secrets.meta.debug,
        App: {
            ClientId: secrets_1.secrets.app.clientId,
            AppId: secrets_1.secrets.app.id,
            AppName: secrets_1.secrets.app.urlName,
        },
    };
    locals.secrets = secrets_1.secrets;
    locals.POGGIT = version_1.POGGIT;
    locals.lib = ui_lib;
}
exports.initAppLocals = initAppLocals;

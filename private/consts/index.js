"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var ui_lib = require("../ui/lib");
var people_1 = require("./people");
var secrets_1 = require("../secrets");
var release_1 = require("./release");
var version_1 = require("../version");
function initAppLocals(locals) {
    locals.PoggitConsts = {
        AdminLevel: people_1.People.AdminLevel,
        Staff: people_1.People.StaffList,
        Release: release_1.Release,
        Debug: secrets_1.SECRETS.meta.debug,
        App: {
            ClientId: secrets_1.SECRETS.app.clientId,
            AppId: secrets_1.SECRETS.app.id,
            AppName: secrets_1.SECRETS.app.slug,
        },
    };
    locals.secrets = secrets_1.SECRETS;
    locals.POGGIT = version_1.POGGIT;
    locals.lib = ui_lib;
}
exports.initAppLocals = initAppLocals;

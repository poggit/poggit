"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var config_1 = require("./config");
var people_1 = require("./people");
var Release;
(function (Release) {
    Release.NAME_REGEX = /^[A-Za-z0-9_.\-]{2,}$/;
    Release.NAME_PATTERN = Release.NAME_REGEX.toString().substring(2, Release.NAME_REGEX.toString().length - 2);
    Release.isValidName = function (name) { return Release.NAME_REGEX.test(name); };
    Release.VERSION_REGEX = /^(0|[1-9]\d*)\.(0|[1-9]\d*)(\.(0|[1-9]\d*))?(-(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(\.(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*)?(\+[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*)?$/;
    Release.isValidVersion = function (version) { return Release.VERSION_REGEX.test(version); };
    Release.VERSION_PATTERN = Release.NAME_REGEX.toString().substring(2, Release.NAME_REGEX.toString().length - 2);
    var State;
    (function (State) {
        State[State["Draft"] = 0] = "Draft";
        State[State["Rejected"] = 1] = "Rejected";
        State[State["Submitted"] = 2] = "Submitted";
        State[State["Checked"] = 3] = "Checked";
        State[State["Voted"] = 4] = "Voted";
        State[State["Approved"] = 5] = "Approved";
        State[State["Featured"] = 6] = "Featured";
    })(State = Release.State || (Release.State = {}));
    var Flag;
    (function (Flag) {
        Flag[Flag["OBSOLETE"] = 1] = "OBSOLETE";
        Flag[Flag["PRE_RELEASE"] = 2] = "PRE_RELEASE";
        Flag[Flag["OUTDATED"] = 4] = "OUTDATED";
        Flag[Flag["OFFICIAL"] = 8] = "OFFICIAL";
    })(Flag = Release.Flag || (Release.Flag = {}));
    var Author;
    (function (Author) {
        Author[Author["COLLABORATOR"] = 1] = "COLLABORATOR";
        Author[Author["CONTRIBUTOR"] = 2] = "CONTRIBUTOR";
        Author[Author["TRANSLATOR"] = 3] = "TRANSLATOR";
        Author[Author["REQUESTER"] = 4] = "REQUESTER";
    })(Author = Release.Author || (Release.Author = {}));
    var Category;
    (function (Category) {
        Category[Category["General"] = 1] = "General";
        Category[Category["Admin Tools"] = 2] = "Admin Tools";
        Category[Category["Informational"] = 3] = "Informational";
        Category[Category["Anti-Griefing Tools"] = 4] = "Anti-Griefing Tools";
        Category[Category["Chat-Related"] = 5] = "Chat-Related";
        Category[Category["Teleportation"] = 6] = "Teleportation";
        Category[Category["Mechanics"] = 7] = "Mechanics";
        Category[Category["Economy"] = 8] = "Economy";
        Category[Category["Minigame"] = 9] = "Minigame";
        Category[Category["Fun"] = 10] = "Fun";
        Category[Category["World Editing and Management"] = 11] = "World Editing and Management";
        Category[Category["World Generators"] = 12] = "World Generators";
        Category[Category["Developer Tools"] = 13] = "Developer Tools";
        Category[Category["Educational"] = 14] = "Educational";
        Category[Category["Miscellaneous"] = 15] = "Miscellaneous";
    })(Category = Release.Category || (Release.Category = {}));
    Release.Permission = {
        1: {
            "name": "Manage plugins",
            "description": "installs/uninstalls/enables/disables plugins",
        },
        2: {
            "name": "Manage worlds",
            "description": "registers worlds",
        },
        3: {
            "name": "Manage permissions",
            "description": "only includes managing user permissions for other plugins",
        },
        4: {
            "name": "Manage entities",
            "description": "registers new types of entities",
        },
        5: {
            "name": "Manage blocks/items",
            "description": "registers new blocks/items",
        },
        6: {
            "name": "Manage tiles",
            "description": "registers new tiles",
        },
        7: {
            "name": "Manage world generators",
            "description": "registers new world generators",
        },
        8: {
            "name": "Database",
            "description": "uses databases not local to this server instance, e.g. a MySQL database",
        },
        9: {
            "name": "Other files",
            "description": "uses SQLite databases and YAML data folders. Do not include non-data-saving fixed-number files (i.e. config & lang files)",
        },
        10: {
            "name": "Permissions",
            "description": "registers permissions",
        },
        11: {
            "name": "Commands",
            "description": "registers commands",
        },
        12: {
            "name": "Edit world",
            "description": "changes blocks in a world; do not check this if your plugin only edits worlds using world generators",
        },
        13: {
            "name": "External Internet clients",
            "description": "starts client sockets to the external Internet, including MySQL and cURL calls",
        },
        14: {
            "name": "External Internet sockets",
            "description": "listens on a server socket not started by PocketMine",
        },
        15: {
            "name": "Asynchronous tasks",
            "description": "uses AsyncTask",
        },
        16: {
            "name": "Custom threading",
            "description": "starts threads; does not include AsyncTask (because they aren't threads)",
        },
    };
    function canAccessState(adminLevel, state) {
        if (adminLevel >= people_1.People.AdminLevel.ADM) {
            return state >= State.Draft;
        }
        if (adminLevel >= people_1.People.AdminLevel.REVIEWER) {
            return state >= State.Rejected;
        }
        if (adminLevel >= people_1.People.AdminLevel.MEMBER) {
            return state >= State.Checked;
        }
        return state >= config_1.Config.MIN_PUBLIC_RELEASE_STATE;
    }
    Release.canAccessState = canAccessState;
})(Release = exports.Release || (exports.Release = {}));

"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var people;
(function (people) {
    var AdminLevel;
    (function (AdminLevel) {
        AdminLevel[AdminLevel["GUEST"] = 0] = "GUEST";
        AdminLevel[AdminLevel["MEMBER"] = 1] = "MEMBER";
        AdminLevel[AdminLevel["CONTRIBUTOR"] = 2] = "CONTRIBUTOR";
        AdminLevel[AdminLevel["MODERATOR"] = 3] = "MODERATOR";
        AdminLevel[AdminLevel["REVIEWER"] = 4] = "REVIEWER";
        AdminLevel[AdminLevel["ADM"] = 5] = "ADM";
    })(AdminLevel = people.AdminLevel || (people.AdminLevel = {}));
    people.StaffList = {
        "awzaw": AdminLevel.ADM,
        "brandon15811": AdminLevel.ADM,
        "dktapps": AdminLevel.ADM,
        "humerus": AdminLevel.ADM,
        "intyre": AdminLevel.ADM,
        "sof3": AdminLevel.ADM,
        "99leonchang": AdminLevel.REVIEWER,
        "falkirks": AdminLevel.REVIEWER,
        "jacknoordhuis": AdminLevel.REVIEWER,
        "knownunown": AdminLevel.REVIEWER,
        "pemapmodder": AdminLevel.REVIEWER,
        "robske110": AdminLevel.REVIEWER,
        "thedeibo": AdminLevel.REVIEWER,
        "thunder33345": AdminLevel.REVIEWER,
    };
    function getAdminLevel(name) {
        return name ? people.StaffList[name.toLowerCase()] ? people.StaffList[name.toLowerCase()] : AdminLevel.MEMBER : AdminLevel.GUEST;
    }
    people.getAdminLevel = getAdminLevel;
})(people = exports.people || (exports.people = {}));

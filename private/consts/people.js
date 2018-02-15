"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var People;
(function (People) {
    var AdminLevel;
    (function (AdminLevel) {
        AdminLevel[AdminLevel["GUEST"] = 0] = "GUEST";
        AdminLevel[AdminLevel["MEMBER"] = 1] = "MEMBER";
        AdminLevel[AdminLevel["CONTRIBUTOR"] = 2] = "CONTRIBUTOR";
        AdminLevel[AdminLevel["MODERATOR"] = 3] = "MODERATOR";
        AdminLevel[AdminLevel["REVIEWER"] = 4] = "REVIEWER";
        AdminLevel[AdminLevel["ADM"] = 5] = "ADM";
    })(AdminLevel = People.AdminLevel || (People.AdminLevel = {}));
    People.StaffList = {
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
        return name ? People.StaffList[name.toLowerCase()] ? People.StaffList[name.toLowerCase()] : AdminLevel.MEMBER : AdminLevel.GUEST;
    }
    People.getAdminLevel = getAdminLevel;
})(People = exports.People || (exports.People = {}));

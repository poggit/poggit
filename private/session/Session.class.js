"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var people_1 = require("../consts/people");
var getAdminLevel = people_1.People.getAdminLevel;
var AdminLevel = people_1.People.AdminLevel;
var Session = (function () {
    function Session(duration) {
        this.auth = null;
        this.tosHidden = false;
        this.refresh(duration);
    }
    Session.prototype.refresh = function (duration) {
        this.expires = Date.now() + duration;
    };
    Session.prototype.expired = function () {
        return Date.now() > this.expires;
    };
    Session.prototype.toSessionData = function () {
        return {
            session: {
                isLoggedIn: this.auth !== null,
                loginName: this.auth !== null ? this.auth.name : undefined,
                adminLevel: this.auth !== null ? people_1.People.getAdminLevel(this.auth.name) : people_1.People.AdminLevel.GUEST,
            },
            opts: {},
            tosHidden: this.auth !== null || this.tosHidden,
        };
    };
    Session.prototype.getAdminLevel = function () {
        if (this.auth !== null) {
            return getAdminLevel(this.auth.name);
        }
        else {
            return AdminLevel.GUEST;
        }
    };
    return Session;
}());
exports.Session = Session;

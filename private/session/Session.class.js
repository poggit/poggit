"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var people_1 = require("../lib/people");
var Session = (function () {
    function Session(duration) {
        this.auth = null;
        this.tosHidden = false;
        this.refresh(duration);
    }
    Session.prototype.refresh = function (duration) {
        this.expires = new Date().getTime() + duration;
    };
    Session.prototype.expired = function () {
        return new Date().getTime() > this.expires;
    };
    Session.prototype.toSessionData = function () {
        return {
            session: {
                isLoggedIn: this.auth !== null,
                loginName: this.auth !== null ? this.auth.name : undefined,
                adminLevel: this.auth !== null ? people_1.people.getAdminLevel(this.auth.name) : people_1.people.AdminLevel.GUEST,
            },
            opts: {},
            tosHidden: this.auth !== null || this.tosHidden,
        };
    };
    return Session;
}());
exports.Session = Session;

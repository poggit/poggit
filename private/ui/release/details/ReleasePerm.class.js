"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var ReleasePerm = (function () {
    function ReleasePerm(session, release) {
        this.session = session;
        this.release = release;
    }
    ReleasePerm.prototype.canEdit = function () {
        return true;
    };
    ReleasePerm.prototype.canReview = function () {
        return true;
    };
    ReleasePerm.prototype.canAssign = function () {
        return true;
    };
    ReleasePerm.prototype.canWriteRepo = function () {
        return false;
    };
    return ReleasePerm;
}());
exports.ReleasePerm = ReleasePerm;

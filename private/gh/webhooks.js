"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var ghWebhooks;
(function (ghWebhooks) {
    function isOrgPayload(payload) {
        return payload.organization !== undefined;
    }
    ghWebhooks.isOrgPayload = isOrgPayload;
    function isRepoPayload(payload) {
        return payload.repository !== undefined;
    }
    ghWebhooks.isRepoPayload = isRepoPayload;
    function isAppPayload(payload) {
        return payload.installation !== undefined;
    }
    ghWebhooks.isAppPayload = isAppPayload;
})(ghWebhooks = exports.ghWebhooks || (exports.ghWebhooks = {}));

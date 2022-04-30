/*
 * Copyright 2016-2018 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

(function() {
    console.info("Help us improve Poggit on GitHub: https://github.com/poggit/poggit");

    if(!isDebug() && window.location.protocol.replace(":", "") !== "https" && window.location.host !== "poggit.pmmp.io") {
        window.location.replace("https://poggit.pmmp.io" + window.location.pathname);
    }
})();

if(String.prototype.hashCode === undefined) {
    String.prototype.hashCode = function() {
        var hash = 0, i, chr, len;
        if(this.length === 0) return hash;
        for(i = 0, len = this.length; i < len; i++) {
            chr = this.charCodeAt(i);
            hash = ((hash << 5) - hash) + chr;
            hash |= 0; // Convert to 32bit integer
        }
        return hash;
    };
}
if(String.prototype.ucfirst === undefined) {
    String.prototype.ucfirst = function() {
        return this.charAt(0).toUpperCase() + this.substr(1)
    };
}
if(String.prototype.startsWith === undefined) {
    String.prototype.startsWith = function(prefix) {
        return this.substring(0, prefix.length) === prefix;
    };
}
if(Math.sign === undefined) {
    Math.sign = function(n) {
        if(n === 0) return 0;
        return n > 0 ? 1 : -1;
    }
}
if(Math.compare === undefined) {
    Math.compare = function(a, b) {
        return Math.sign(a - b);
    }
}
if(Array.prototype.includes === undefined) {
    Array.prototype.includes = function(e) {
        return this.indexOf(e) >= 0;
    };
}
if(RegExp.escape === undefined) {
    RegExp.escape = function(s) {
        // source: https://stackoverflow.com/a/3561711/3990767
        return s.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    };
}

Object.indexOfKey = function(object, needle) {
    var i = 0;
    for(var key in object) {
        if(key === needle) return i;
        ++i;
    }
    return undefined;
};
Object.getKeyByIndex = function(object, index) {
    var i = 0;
    index = Number(index);
    for(var key in object) {
        if(!object.hasOwnProperty(key)) continue;
        if((i++) === Number(index)) {
            return key;
        }
    }
    return undefined;
};
Object.getValueByIndex = function(object, index) {
    var i = 0;
    index = Number(index);
    for(var key in object) {
        if(!object.hasOwnProperty(key)) continue;
        if((i++) === Number(index)) {
            return object[key];
        }
    }
    return undefined;
};
Object.valuesToArray = function(object) {
    var array = [];
    for(var key in object) {
        if(!object.hasOwnProperty(key)) continue;
        array.push(object[key]);
    }
    return array;
};
Object.keysToArray = function(object) {
    var array = [];
    for(var key in object) {
        if(!object.hasOwnProperty(key)) continue;
        array.push(key);
    }
    return array;
};
Object.sizeof = function(object) {
    var i = 0;
    for(var k in object) {
        if(object.hasOwnProperty(k)) ++i;
    }
    return i;
};

var toggleFunc = function($parent) {
    if($parent[0].hasDoneToggleFunc !== undefined) {
        return;
    }
    $parent[0].hasDoneToggleFunc = true;
    var name = $parent.attr("data-name");
    if(name === undefined) {
        console.error($parent[0]);
        return;
    }
    console.assert(name.length > 0);
    var children = $parent.children();
    if(children.length === 0) {
        $parent.append("<h3 class='wrapper-header'>" + name + "</h3>");
        return;
    }
    var wrapper = $("<div class='wrapper'></div>");
    wrapper.attr("id", "wrapper-of-" + name.hashCode());
    $parent.wrapInner(wrapper);
    var header = $("<h5 class='wrapper-header'></h5>");
    header.html(name);
    var img = $("<img width='24' style='margin-left: 10px;'>").attr("src", getRelativeRootPath() + "res/expand_arrow-24.png");
    var clickListener = function() {
        var wrapper = $(`#wrapper-of-${name.hashCode()}`);
        if(wrapper.css("display") === "none") {
            wrapper.css("display", "flex");
            img.attr("src", getRelativeRootPath() + "res/collapse_arrow-24.png");
        } else {
            wrapper.css("display", "none");
            img.attr("src", getRelativeRootPath() + "res/expand_arrow-24.png");
        }
    };
    header.click(clickListener);
    header.append(img);
    $parent.prepend(header);

    if($parent.attr("data-opened") === "true") {
        clickListener();
    }

    return `#wrapper-of-${name.hashCode()}`;
};

var navButtonFunc = function() {
    if(this.hasDoneNavButtonFunc !== undefined) {
        return;
    }
    this.hasDoneNavButtonFunc = true;
    var $this = $(this);
    var target = $this.attr("data-target");
    var ext;
    if(!(ext = $this.hasClass("extlink"))) {
        target = getRelativeRootPath() + target;
    }
    var wrapper = $("<a></a>");
    wrapper.addClass("navlink");
    wrapper.attr("href", target);
    if(ext) {
        wrapper.attr("target", "_blank");
    }
    $this.wrapInner(wrapper);
};

var timeTextFunc = function() {
    if(this.hasDoneTimeTextFunc !== undefined) {
        return;
    }
    this.hasDoneTimeTextFunc = true;
    var $this = $(this);
    var timestamp = Number($this.attr("data-timestamp")) * 1000;
    var date = new Date(timestamp);
    var now = new Date();
    var text;
    if(date.toDateString() === now.toDateString()) {
        text = date.toLocaleTimeString();
    } else {
        text = $this.attr("data-multiline-time") === "on" ?
            (date.toLocaleDateString() + date.toLocaleTimeString()) : date.toLocaleString();
    }
    $this.text(text);
};

var timeElapseFunc = function() {
    var $this = $(this);
    var time = Math.round(new Date().getTime() / 1000 - Number($this.attr("data-timestamp")));
    var maxElapse = $this.attr("data-max-elapse");
    if(typeof maxElapse !== "undefined" && time > Number(maxElapse)) {
        timeTextFunc.call(this);
        return;
    }
    var out = "";
    var hasDay = false;
    var hasHr = false;
    if(time >= 86400) {
        out += Math.floor(time / 86400) + "d ";
        time %= 86400;
        hasDay = true;
    }
    if(time >= 3600) {
        out += Math.floor(time / 3600) + "h ";
        time %= 3600;
        hasHr = true;
    }
    if(time >= 60) {
        out += Math.floor(time / 60) + "m ";
        time %= 60;
    }
    if(out.length === 0 || time !== 0) {
        if(!hasDay && !hasHr) out += time + "s";
    }
    $this.text(out.trim() + (typeof maxElapse === "undefined" ? "" : " ago"));
};

var domainFunc = function() {
    if(this.hasDoneDomainFunc !== undefined) {
        return;
    }
    this.hasDoneDomainFunc = true;
    $(this).text(window.location.origin);
};

var dynamicAnchor = function() {
    if(this.hasDoneDynAnchorFunc !== undefined) {
        return;
    }
    this.hasDoneDynAnchorFunc = true;
    var $this = $(this);
    var parent = $this.parent();
    parent.hover(function() {
        $this.css("visibility", "visible");
    }, function() {
        $this.css("visibility", "hidden");
    });
};

var onCopyableClick = function(copyable) {
    var $this = $(copyable);
    $this.next()[0].select();
    window.execCommand("copy");
    $this.prev().css("display", "block")
        .find("span").css("background-color", "#FF00FF")
        .stop().animate({backgroundColor: "#FFFFFF"}, 500);
};

var timeElapseLoop = function() {
    $(".time-elapse").each(timeElapseFunc);
    setTimeout(timeElapseLoop, 1000);
};

$(function() {
    var pathParts = location.pathname.split(/\//).slice(1);
    var newModule = null;
    switch(pathParts[0]) {
        case "pi":
        case "index":
            newModule = "plugins";
            break;
        case "release":
        case "rel":
        case "plugin":
            newModule = "p";
            break;
        case "build":
        case "b":
        case "dev":
            newModule = "ci";
            break;
    }
    if(newModule !== null) {
        pathParts[0] = newModule;
        history.replaceState(null, "", "/" + pathParts.join("/") + location.search + location.hash);
    }

    $(this).find(".navbutton").each(navButtonFunc);
    $(this).tooltip({
        content: function() {
            var $this = $(this);
            return $this.hasClass("html-tooltip") ? $this.prop("title") : $("<span></span>").text($this.prop("title")).html();
        }
    });
    $(this).find("#toggle-wrapper").each(function() {
        toggleFunc($(this)); // don't return the result from toggleFunc
    });

    $(this).find('li[data-target="' + window.location.pathname.substring(getRelativeRootPath().length) + window.location.search + '"]').each(function() {
        $(this).addClass('active');
    });

    $(this).find(".time").each(timeTextFunc);

    $(this).find(".domain").each(domainFunc);
    timeElapseLoop();
    $(this).find(".dynamic-anchor").each(dynamicAnchor);

    $("#home-timeline").tabs({
        collapsible: true
    });

    ajax("session.online", {
        method: "POST",
        success: function(data) {
            $("#online-user-count").text(`${data} online`).css("display", "list-item");
        }
    });
});

function ajax(path, options) {
    $.post(`${getRelativeRootPath()}csrf/csrf--${path}`, {}, function(token) {
        if(options === undefined) options = {};
        if(options.dataType === undefined) options.dataType = "json";
        if(options.data === undefined) options.data = {};
        if(options.method === undefined) options.method = "POST";
        if(typeof options.headers === "undefined") options.headers = [];
        options.headers["X-Poggit-CSRF"] = token;

        $.ajax(getRelativeRootPath() + path, options);
    });
}

function login(nextStep, opts) {
    if(typeof nextStep === typeof undefined) nextStep = window.location.toString();
    ajax("persistLoc", {
        data: {
            path: nextStep
        },
        success: function() {
            if(opts) {
                window.location = getRelativeRootPath() + "login";
            } else {
                window.location = `https://github.com/login/oauth/authorize?client_id=${getClientId()}&state=${getAntiForge()}&scope=${encodeURIComponent(`user:email,${getCookie("ghScopes", "repo,read:org")}`)}`;
            }
        }
    });
}

function logout() {
    ajax("logout", {
        success: function() {
            window.location.reload(true);
        }
    });
}

function homeBumpNotif(redirect = true) {
    ajax("session.bumpnotif");
    if(redirect) {
        setTimeout(function() {
            window.location = getRelativeRootPath() + "plugins";
        }, 500);
    }
}

function hideTos() {
    ajax("hideTos", {
        success: function() {
            $("#remindTos").css("display", "none");
        }
    });
}

function ghApi(path, data, method, success, beautify, extraHeaders) {
    if(method === undefined) method = "GET";
    if(data === undefined || data === null) data = {};
    if(extraHeaders === undefined) extraHeaders = [];
    else if(typeof extraHeaders === "string") extraHeaders = [extraHeaders];
    ajax("proxy.api.gh", {
        data: {
            url: path,
            input: JSON.stringify(data),
            method: method,
            extraHeaders: JSON.stringify(extraHeaders),
            beautify: beautify === undefined ? isDebug() : Boolean(beautify)
        },
        method: "POST",
        success: success
    });
}

function deleteReview(data) {
    var author = $(data).parent().attr('value');
    var criteria = $(data).attr('criteria');
    var relId = $(data).attr('value');
    ajax("review.admin", {
        data: {
            author: author,
            relId: relId,
            criteria: criteria,
            action: "delete"
        },
        method: "POST",
        success: function() {
            location.reload(true);
        },
        error: function() {
            location.reload(true);
        }
    });
}

function editReview(reviewId, releaseId, author, score, message) {
    ajax("review.admin", {
        data: {
            author: author,
            relId: releaseId,
            reviewId: reviewId,
            score: score,
            message: message,
            action: "edit"
        },
        method: "POST",
        success: function() {
            location.reload();
        },
        error: function() {
            location.reload()
        }
    });
}

function postReviewReply(reviewId, message) {
    ajax("review.reply", {
        data: {
            reviewId: reviewId,
            message: message
        },
        success: function() {
            location.reload(true);
        },
        error: function(request) {
            location.reload(true);
        }
    });
}

function deleteReviewReply(reviewId) {
    ajax("review.reply", {
        data: {
            reviewId: reviewId,
            message: ""
        },
        success: function() {
            location.reload(true);
        },
        error: function(request) {
            location.reload(true);
        }
    });
}

function generateGhLink(link, width, id) {
    var a = $("<a target='_blank'></a>")
        .attr("href", link)
        .append($("<img class='gh-logo'/>")
            .attr("src", getRelativeRootPath() + "res/ghMark.png")
            .attr("width", width != null ? width : 16));
    if(id != null) a.attr("id", id);
    return a;
}


function gaEventRelease(isTop, name, version) {
    ga("send", "event", "Download.Stability", "Release");
    ga("send", "event", "Download.Release.Content", name + " " + version);
    ga("send", "event", "Download.Release.Position", isTop ? "Top" : "Bottom");
}

let lastCancel;

function gaEventCi(isProject, isCancel, projectName, resourceId, altName) {
    if(!isCancel) {
        ga("send", "event", "Download.Stability", "CI");
        ga("send", "event", "Download.CI.Page", isProject ? "ProjectBuildPage" : (altName ? "RepoBuildPage altName" : "RepoBuildPage direct"));
        ga("send", "event", "Download.CI.Content", projectName);
        if(lastCancel === resourceId){
            lastCancel = undefined;
            ga("send", "event", "Download.CI.AfterCancel", isProject ? "ProjectBuildPage" : "RepoBuildPage");
        }
    }else{
        ga("send", "event", "Download.Stability", "CI.Cancel");
        ga("send", "event", "Download.CI.Cancel.Page", isProject ? "ProjectBuildPage" : "RepoBuildPage");
        ga("send", "event", "Download.CI.Cancel.Content", projectName);
        lastCancel = resourceId;
    }
}


function compareApis(v1, v2) {
    var flag1 = v1.indexOf('-') > -1;
    var flag2 = v2.indexOf('-') > -1;
    var arr1 = versplit(flag1, v1);
    var arr2 = versplit(flag2, v2);
    arr1 = convertToNumber(arr1);
    arr2 = convertToNumber(arr2);
    var len = Math.max(arr1.length, arr2.length);
    for(var i = 0; i < len; i++) {
        if(arr1[i] === undefined) {
            return -1;
        } else if(arr2[i] === undefined) {
            return 1;
        }
        if(!(parseInt(arr1[i]) + '' === arr1[i])) {
            arr1[i] = parseInt(arr1[i].toString().replace(/\D/g, ''));
            arr2[i] = parseInt(arr2[i].toString().replace(/\D/g, ''));
        }
        if(arr1[i] > arr2[i]) {
            return 1;
        } else if(arr1[i] < arr2[i]) {
            return -1;
        }
    }
    return 0;
}

function versplit(flag, version) {
    var result = [];
    if(flag) {
        var tail = version.split('-')[1];
        var _version = version.split('-')[0];
        result = _version.split('.');
        tail = tail.split('.');
        result = result.concat(tail);
    } else {
        result = version.split('.');
    }
    return result;
}

function convertToNumber(arr) {
    return arr.map(function(el) {
        return isNaN(el) ? el : parseInt(el);
    });
}


function getParameterByName(name, defaultValue) {
    var url = window.location.href;
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if(!results) return typeof defaultValue === "undefined" ? null : defaultValue; // no query at all
    if(!results[2]) return ""; // empty value
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}

function getCookie(name, defaultValue) {
    // source: https://developer.mozilla.org/en-US/docs/Web/API/Document/cookie
    var result = document.cookie.replace(new RegExp(`(?:(?:^|.*;\\s*)${RegExp.escape(name)}\\s*\\=\\s*([^;]*).*$)|^.*$`), "$1");
    return result === "" ? defaultValue : result;
}

function getRelativeRootPath() {
    return sessionData.path.relativeRoot;
}

function getClientId() {
    return sessionData.app.clientId;
}

function getAntiForge() {
    return sessionData.session.antiForge;
}

function isLoggedIn() {
    return sessionData.session.isLoggedIn;
}

function getLoginName() {
    return sessionData.session.loginName;
}

function getAdminLevel() {
    return sessionData.session.adminLevel;
}

function isDebug() {
    return sessionData.meta.isDebug;
}

var modalPosition = {my: "center top", at: "center top+100", of: window};

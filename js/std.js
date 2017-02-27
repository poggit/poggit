/*
 * Copyright 2016-2017 Poggit
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

console.info("Help us improve Poggit on GitHub: https://github.com/poggit/poggit");

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
String.prototype.ucfirst = function() {
    return this.charAt(0).toUpperCase() + this.substr(1)
};

/**
 * No OPeration placeholder function
 */
function nop() {
}

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
    if(children.length == 0) {
        $parent.append("<h3 class='wrapper-header'>" + name + "</h3>");
        return;
    }
    var wrapper = $("<div class='wrapper'></div>");
    wrapper.attr("id", "wrapper-of-" + name.hashCode());
    $parent.wrapInner(wrapper);
    var header = $("<h3 class='wrapper-header'></h3>");
    header.html(name);
    header.append("&nbsp;&nbsp;");
    var img = $("<img width='24'>");
    img.attr("src", getRelativeRootPath() + "res/expand_arrow-24.png");
    var clickListener = function() {
        var wrapper = $("#wrapper-of-" + name.hashCode());
        if(wrapper.css("display") == "none") {
            wrapper.css("display", "block");
            img.attr("src", getRelativeRootPath() + "res/collapse_arrow-24.png");
        } else {
            wrapper.css("display", "none");
            img.attr("src", getRelativeRootPath() + "res/expand_arrow-24.png");
        }
    };
    header.click(clickListener);
    header.append(img);
    $parent.prepend(header);

    if($parent.attr("data-opened") == "true") {
        clickListener();
    }

    return "#wrapper-of-" + name.hashCode();
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
    if(date.toDateString() == now.toDateString()) {
        text = date.toLocaleTimeString();
    } else {
        text = $this.attr("data-multiline-time") == "on" ?
            (date.toLocaleDateString() + date.toLocaleTimeString()) : date.toLocaleString();
    }
    $this.text(text);
};
var timeElapseFunc = function() {
    var $this = $(this);
    var time = Math.round(new Date().getTime() / 1000 - Number($this.attr("data-timestamp")));
    var out = "";
    var hasDay = false;
    var hasHr = false;
    if(time >= 86400) {
        out += Math.floor(time / 86400) + " d ";
        time %= 86400;
        hasDay = true;
    }
    if(time >= 3600) {
        out += Math.floor(time / 3600) + " hr ";
        time %= 3600;
        hasHr = true;
    }
    if(time >= 60) {
        out += Math.floor(time / 60) + " min ";
        time %= 60;
    }
    if(out.length == 0 || time != 0) {
        if(!hasDay && !hasHr) out += time + " s";
    }
    $this.text(out.trim());
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
        $this.css("display", "inline");
    }, function() {
        $this.css("display", "none");
    });
};
var onCopyableClick = function(copyable) {
    var $this = $(copyable);
    $this.next()[0].select();
    execCommand("copy");
    $this.prev().css("display", "block")
        .find("span").css("background-color", "#FF00FF")
        .stop().animate({backgroundColor: "#FFFFFF"}, 500);
};
var stdPreprocess = function() {
    $(this).find(".navbutton").each(navButtonFunc);
    $(this).tooltip();
    $(this).find(".toggle").each(function() {
        toggleFunc($(this)); // don't return the result from toggleFunc
    });

    $(this).find('li[data-target="' + window.location.pathname.substring("${path.relativeRoot}".length) + '"]').each(function() {
        $(this).addClass('active');
    });

    $(this).find(".time").each(timeTextFunc);
    var timeElapseLoop = function() {
        $(".time-elapse").each(timeElapseFunc);
        setTimeout(timeElapseLoop, 1000);
    };
    $(this).find(".domain").each(domainFunc);
    timeElapseLoop();
    $(this).find(".dynamic-anchor").each(dynamicAnchor);

    $("#searchButton").on("click", function(e) {
        var searchText = $("#pluginSearch").val().split(' ')[0];
        window.location = getRelativeRootPath() + "p/" + searchText;
    });

    var pluginSearch = $("#pluginSearch");
    pluginSearch.on("keyup", function(e) {
        if(e.keyCode === 13) {
            var searchText = $("#pluginSearch").val().split(' ')[0];
            var url = window.location = getRelativeRootPath() + "p/" + searchText;
            window.location = url;
        }
    });
    pluginSearch.focus();
};

$(document).ready(stdPreprocess);

var lastOptions;

function ajax(path, options) {
    $.post(getRelativeRootPath() + "csrf/" + path, {}, function(token) {
        if(options === undefined) {
            options = {};
        }
        if(options.dataType === undefined) {
            options.dataType = "json";
        }
        if(options.data === undefined) {
            options.data = {};
        }
        if(typeof options.headers == "undefined") {
            options.headers = [];
        }
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
                var url = "https://github.com/login/oauth/authorize?client_id=" + getClientId()
                    + "&state=" + getAntiForge() + "&scope=";
                url += encodeURIComponent("repo,read:org");
                window.location = url;
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

function promptDownloadResource(id, defaultName) {
    var name = prompt("Filename to download with:", defaultName);
    if(name === null) {
        return;
    }
    window.location = getRelativeRootPath() + "r/" + id + "/" + name;
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

function updateRelease() {
    var newStatus;
    newStatus = $("#setStatus").val();

    ajax("release.admin", {
        data: {
            relId: relId,
            state: newStatus,
            action: "update"
        },
        method: "POST",
        success: function(data) {
            location = location.href;
        }
    });
}
function addReview(relId, user, criteria, type, cat, score, message) {

    ajax("review.admin", {
        data: {
            relId: relId,
            user: user,
            criteria: criteria,
            type: type,
            category: cat,
            score: score,
            message: message,
            action: "add"
        },
        method: "POST",
        success: function() {
            location = location.href;
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            location = location.href;
        }
    });
}
function deleteReview(data) {
    var author = $(data).parent().attr('value');
    var criteria = $(data).parent().next().find('.review-criteria').attr('value');
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
            location = location.href;
        },
        error: function() {
            location = location.href;
        }
    });
}

function deleteRelease(data) {
    var relId = $(data).attr('value');
    ajax("release.admin", {
        data: {
            relId: relId,
            state: 0,
            action: "delete"
        },
        method: "POST",
        success: function() {
            history.go(-1);
        },
        error: function() {
            history.go(-1);
        }
    });
}

function getRelativeRootPath() {
    return "${path.relativeRoot}";
}

function getClientId() {
    return "${app.clientId}";
}

function getAntiForge() {
    return "${session.antiForge}";
}

function isLoggedIn() {
    return "${session.isLoggedIn}" == "true";
}

function getLoginName() {
    return "${session.loginName}";
}

function getAdminLevel() {
    return "${session.adminLevel}";
}

function isDebug() {
    return "${meta.isDebug}" == "true";
}

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

/** @type String sessionData */

console.info("Help us improve Poggit on GitHub: https://github.com/poggit/poggit");

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
    var img = $("<img width='24' style='margin-left: 10px;'>");
    img.attr("src", getRelativeRootPath() + "res/expand_arrow-24.png");
    var clickListener = function() {
        var wrapper = $("#wrapper-of-" + name.hashCode());
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
    if(out.length == 0 || time != 0) {
        if(!hasDay && !hasHr) out += time + "s";
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
    if($('#mainreleaselist > div').length > 0) {
        filterReleaseResults();
    }
    if($('#recentBuilds > div').length > 16) {
        $('#recentBuilds').paginate({
            perPage: 16,
            scope: $('div'), // targets all div elements
        });
    }
    if($('#repolistbuildwrapper > div').length > 12) {
        $('#repolistbuildwrapper').paginate({
            perPage: 12,
            scope: $('div'), // targets all div elements
        });
    }
    if($('#review-releases > div').length > 16) {
        $('#review-releases').paginate({
            perPage: 16,
            scope: $('div'), // targets all div elements
        });
    }
    $(this).find(".navbutton").each(navButtonFunc);
    $(this).tooltip();
    $(this).find("#togglewrapper").each(function() {
        toggleFunc($(this)); // don't return the result from toggleFunc
    });

    $(this).find('li[data-target="' + window.location.pathname.substring(getRelativeRootPath().length) + window.location.search + '"]').each(function() {
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
    $("#searchAuthorsButton").on("click", function() {
        window.location = getRelativeRootPath() + "plugins/by/" + $("#searchAuthorsQuery").val();
    });

    var pluginSearch = $("#pluginSearch");
    pluginSearch.on("keyup", function(e) {
        if(e.keyCode === 13) {
            var searchText = $("#pluginSearch").val().split(' ')[0];
            var url = window.location = getRelativeRootPath() + "p/" + searchText;
            window.location = url;
        }
    });

    var searchAuthorsQuery = $("#searchAuthorsQuery");
    searchAuthorsQuery.on("keyup", function(e) {
        if(e.keyCode === 13) {
            window.location = getRelativeRootPath() + "plugins/by/" + $("#searchAuthorsQuery").val();
        }
    });

    if(!window.matchMedia('(max-width: 900px)').matches) {
        pluginSearch.focus();
    }
    $(function() {
        $("#tabs").tabs({
            collapsible: true
        });
    });

};

$(stdPreprocess);

var knownReviews = {};

function ajax(path, options) {
    $.post(getRelativeRootPath() + "csrf/csrf--" + path, {}, function(token) {
        if(options === undefined) {
            options = {};
        }
        if(options.dataType === undefined) {
            options.dataType = "json";
        }
        if(options.data === undefined) {
            options.data = {};
        }
        if(typeof options.headers === "undefined") {
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

    ajax("release.statechange", {
        data: {
            relId: relId,
            state: newStatus
        },
        method: "POST",
        success: function() {
            location.reload(true);
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
            location.reload(true);
        },
        error: function() {
            location.reload(true);
        }
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

function addVote(relId, vote, message) {
    ajax("release.vote", {
        data: {
            relId: relId,
            vote: vote,
            message: message
        },
        method: "POST",
        success: function() {
            location.reload(true);
        },
        error: function(request) {
            location.reload(true);
        }
    });
}

function filterReleaseResults() {
    var selectedCat = $('#category-list').val();
    var selectedCatName = $('#category-list option:selected').text();
    var selectedAPI = $('#api-list').val();
    var selectedAPIIndex = $('#api-list').prop('selectedIndex');
    if(selectedCat > 0) {
        $('#category-list').attr('style', 'background-color: #FF3333');
    }
    else {
        $('#category-list').attr('style', 'background-color: #FFFFFF');
    }
    if(selectedAPIIndex > 0) {
        $('#api-list').attr('style', 'background-color: #FF3333');
    }
    else {
        $('#api-list').attr('style', 'background-color: #FFFFFF');
    }
    $('.plugin-entry').each(function(idx, el) {
        var cats = $(el).children('#plugin-categories');
        var catArray = cats.attr("value").split(',');
        var apis = $(el).children('#plugin-apis');
        var apiJSON = apis.attr("value");
        var json = JSON.stringify(eval('(' + apiJSON + ')'));
        var apiArray = [];
        apiArray = $.parseJSON(json);
        var compatibleAPI = false;
        for(var i = 0; i < apiArray.length; i++) {
            var sinceok = compareApis(apiArray[i][0], selectedAPI);
            var tillok = compareApis(apiArray[i][1], selectedAPI);
            if(sinceok <= 0 && tillok >= 0) {
                compatibleAPI = true;
                break;
            }
        }
        if((!catArray.includes(selectedCat) && selectedCat != 0) || (selectedAPIIndex > 0 && !compatibleAPI)) {
            $(el).attr("hidden", true);
        } else {
            $(el).attr("hidden", false);
        }
    })
    var visibleplugins = $('#mainreleaselist .plugin-entry:visible').length;
    if(visibleplugins === 0) {
        //alert("No Plugins Found Matching " + selectedAPI + " in " + selectedCatName);
    }
    if($('#mainreleaselist .plugin-entry:hidden').length == 0 && visibleplugins > 12) {
        $('#mainreleaselist').paginate({
            perPage: 12
        });
    } else {
        if(!$.isEmptyObject($('#mainreleaselist').data('paginate'))) $('#mainreleaselist').data('paginate').kill();
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
            return -1
        } else if(arr2[i] === undefined) {
            return 1
        }
        if(arr1[i] > arr2[i]) {
            return 1
        } else if(arr1[i] < arr2[i]) {
            return -1
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

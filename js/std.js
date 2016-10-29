/*
 * Copyright 2016 poggit
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

function isLoggedIn() {
    return "${session.isLoggedIn}" == "true";
}

var toggleFunc = function() {
    if(this.hasDoneToggleFunc !== undefined) {
        return;
    }
    this.hasDoneToggleFunc = true;
    var $this = $(this);
    var name = $this.attr("data-name");
    if(name === undefined) {
        console.error(this);
        return;
    }
    console.assert(name.length > 0);
    var children = $this.children();
    if(children.length == 0) {
        $this.append("<h2 class='wrapper-header'>" + name + "</h2>");
        return;
    }
    var wrapper = $("<div class='wrapper'></div>");
    wrapper.attr("id", "wrapper-of-" + name.hashCode());
    wrapper.css("display", "none");
    $this.wrapInner(wrapper);
    var header = $("<h2 class='wrapper-header'></h2>");
    header.html(name);
    header.append("&nbsp;&nbsp;");
    var img = $("<img title='Expand Arrow' width='24'>");
    img.attr("src", "https://maxcdn.icons8.com/Android_L/PNG/24/Arrows/expand_arrow-24.png");
    var clickListener = function() {
        var wrapper = $("#wrapper-of-" + name.hashCode());
        if(wrapper.css("display") == "none") {
            wrapper.css("display", "block");
            img.attr("src", "https://maxcdn.icons8.com/Android_L/PNG/24/Arrows/collapse_arrow-24.png");
        } else {
            wrapper.css("display", "none");
            img.attr("src", "https://maxcdn.icons8.com/Android_L/PNG/24/Arrows/expand_arrow-24.png");
        }
    };
    header.click(clickListener);
    header.append(img);
    $this.prepend(header);

    if($this.attr("data-opened") == "true") {
        clickListener();
    }
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
        target = "${path.relativeRoot}" + target;
    }
    var wrapper = $("<a></a>");
    wrapper.addClass("navlink");
    wrapper.attr("href", target);
    if(ext) {
        wrapper.attr("target", "_blank");
    }
    $this.wrapInner(wrapper);
};
var hoverTitleFunc = function() {
    if(this.hasDoneHoverTitleFunc !== undefined) {
        return;
    }
    this.hasDoneHoverTitleFunc = true;
    var $this = $(this);
    $this.click(function() {
        alert($this.attr("title"));
    });
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

var stdPreprocess = function() {
    fixSize();
    $(window).resize(fixSize);
    $(this).find(".navbutton").each(navButtonFunc);
    $(this).find(".hover-title").each(hoverTitleFunc);
    $(this).find(".toggle").each(toggleFunc);
    $(this).find(".time").each(timeTextFunc);
    var timeElapseLoop = function() {
        $(".time-elapse").each(timeElapseFunc);
        setTimeout(timeElapseLoop, 1000);
    };
    $(this).find(".domain").each(domainFunc);
    timeElapseLoop();
    $(this).find(".dynamic-anchor").each(dynamicAnchor);
};
$(document).ready(stdPreprocess);

function fixSize() {
    $("#body").css("top", $("#header").outerHeight());
}

function ajax(path, options) {
    $.post("${path.relativeRoot}csrf/" + path, {}, function(token) {
        if(options === undefined) {
            options = {};
        }
        if(options.dataType === undefined) {
            options.dataType = "json";
        }
        if(options.data === undefined) {
            options.data = {};
        }
        options.data.csrf = token;
        $.ajax("${path.relativeRoot}" + path, options);
    });
}

function login(scopes, nextStep) {
    if(typeof scopes === typeof undefined){
        scopes = ["user:email", "repo"];
    }
    if(typeof nextStep === typeof undefined) nextStep = window.location.toString();
    ajax("persistLoc", {
        data: {
            path: nextStep
        },
        success: function() {
            var url = "https://github.com/login/oauth/authorize?client_id=${app.clientId}&state=${session.antiForge}&scope=";
            url += encodeURIComponent(scopes.join(","));
            //url += "&redirect_uri=";
            //url += encodeURIComponent(window.location.origin + "${path.relativeRoot}");
            window.location = url;
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
    window.location = "${path.relativeRoot}r/" + id + "/" + name + "?cookie";
}

function ghApi(path, data, method, success) {
    if(method === undefined) method = "GET";
    if(data === undefined || data === null) data = {};
    ajax("proxy.api.gh", {
        data: {
            path: path,
            input: JSON.stringify(data),
            method: method
        },
        success: success
    });
}

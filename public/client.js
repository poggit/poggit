"use strict";
$(function(){
"use strict";
console.info("Help us improve Poggit on GitHub: https://github.com/poggit/poggit");
correctPath();
var $document = $(document);
$document.find(".navbutton").each(initNavButton);
$document.tooltip({
    content: function () {
        var $this = $(this);
        return $this.hasClass("html-tooltip") ? $this.prop("title") : $("<span></span>").text($this.prop("title")).html();
    }
});
$document.find("#gh-login").click(function () { return login(); });
$document.find("#toggle-wrapper").each(initToggle);
$document.find(".dynamic-anchor").each(initDynamicAnchor);
$document.find("#hide-tos-button").click(function () { return csrf("session/hideTos", {}, function () {
    var remindTos = document.getElementById("remind-tos");
    remindTos.style.display = "none";
}); });
$document.find("#nav-logout").click(function () { return csrf("login/logout", {}, function () { return window.location.reload(); }); });
setInterval(refreshTime, 300);
setInterval(keepOnline, 60000);
keepOnline();
function correctPath() {
    var pathMap = {
        pi: "plugins",
        index: "plugins",
        release: "p",
        rel: "p",
        plugin: "p",
        build: "ci",
        b: "ci",
        dev: "ci"
    };
    if (!PoggitConsts.Debug &&
        (window.location.protocol.replace(":", "") !== "https" ||
            location.host !== "poggit.pmmp.io")) {
        location.replace("https://poggit.pmmp.io" + window.location.pathname);
    }
    var path = location.pathname.split("/", 3);
    if (path.length === 3 && pathMap[path[1]] !== undefined) {
        history.replaceState(null, "", "/" + pathMap[path[1]] + "/" + path[2]);
    }
}
function initNavButton() {
    if (this.hasAttribute("data-navbutton-init")) {
        return;
    }
    this.setAttribute("data-navbutton-init", "true");
    var $this = $(this);
    var target = this.getAttribute("data-target");
    var internal;
    if (internal = !$this.hasClass("extlink")) {
        target = "/" + target;
    }
    var wrapper = $("<a></a>");
    wrapper.addClass("navlink");
    wrapper.attr("href", target);
    if (!internal) {
        wrapper.attr("target", "_blank");
    }
    $this.wrapInner(wrapper);
}
function initToggle() {
    if (this.hasAttribute("data-toggle-init")) {
        return;
    }
    this.setAttribute("data-toggle-init", "true");
    var $holder = $(this);
    var name = this.getAttribute("data-name");
    var escape = false;
    if (name === null) {
        name = this.getAttribute("data-escaped-name");
        escape = true;
        if (name === null) {
            throw new Error("Toggle name missing");
        }
    }
    var opened = this.getAttribute("data-opened") === "true";
    var wrapper = $("<div class='wrapper' data-opened='false'></div>");
    $holder.wrapInner(wrapper);
    var header = $("<h5 class='wrapper-header'></h5>");
    if (escape) {
        header.text(name);
    }
    else {
        header.html(name);
    }
    var img = $("<img width='24' class='wrapper-toggle-button'/>")
        .attr("src", "/res/expand_arrow-24.png");
    var collapseAction = function () {
        wrapper.attr("data-opened", "false");
        $holder.css("display", "none");
        img.attr("src", "/res/expand_arrow-24.png");
    };
    var expandAction = function () {
        wrapper.attr("data-opened", "true");
        $holder.css("display", "flex");
        img.attr("src", "/res/collapse_arrow-24.png");
    };
    wrapper.prepend(header.append(img.click(function () {
        if (wrapper.attr("data-opened") === "true") {
            collapseAction();
        }
        else {
            expandAction();
        }
    })));
    if (opened) {
        expandAction();
    }
    else {
        collapseAction();
    }
}
function initDynamicAnchor() {
    if (this.hasAttribute("data-dynamic-anchor-init")) {
        return;
    }
    this.setAttribute("data-dynamic-anchor-init", "true");
    var $this = $(this);
    var parent = $this.parent();
    parent.hover(function () { return $this.css("visibility", "visible"); }, function () { return $this.css("visibility", "hidden"); });
}
var MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
var WEEKDAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
function refreshTime() {
    var now = new Date();
    var nowTimestamp = now.getTime();
    $(".time, .time-elapse")
        .each(function () {
        var timestamp = Number(this.getAttribute("data-timestamp"));
        var date = new Date(timestamp);
        var timeDiff = Math.abs(nowTimestamp - timestamp);
        this.title = date.toLocaleTimeString() + ", " + date.toLocaleDateString();
        var hours = date.getHours() < 10 ? "0" + date.getHours() : date.getHours().toString();
        var minutes = date.getMinutes() < 10 ? "0" + date.getMinutes() : date.getMinutes().toString();
        var seconds = date.getSeconds() < 10 ? "0" + date.getSeconds() : date.getSeconds().toString();
        if (now.getFullYear() !== date.getFullYear()) {
            this.innerText = MONTHS[date.getMonth()] + " " + date.getDate() + ", " + date.getFullYear();
            return;
        }
        if (timeDiff > 86400e+3 * 7) {
            this.innerText = MONTHS[date.getMonth()] + " " + date.getDate() + ", " + hours + ":" + minutes + ":" + seconds;
            return;
        }
        if (now.getDate() !== date.getDate()) {
            this.innerText = WEEKDAYS[date.getDay()] + ", " + hours + ":" + minutes + ":" + seconds;
            return;
        }
        if (timeDiff > 3600e+3 * 4) {
            this.innerText = hours + ":" + minutes + ":" + seconds;
            return;
        }
        var text = "";
        if (timeDiff >= 3600e+3) {
            text = Math.floor(timeDiff / 3600e+3) + " hours ";
        }
        else if (timeDiff >= 60e+3) {
            text = Math.floor(timeDiff / 60e+3) + " minutes ";
        }
        else {
            text = Math.floor(timeDiff / 1e+3) + " seconds ";
        }
        this.innerText = text + (nowTimestamp > timestamp ? "ago" : "later");
    });
}
function keepOnline() {
    csrf("session/online", {}, function (onlineCount) { return $("#online-user-count").text(onlineCount + " online").css("display", "list-item"); });
}
function csrf(path, data, success, error) {
    if (data === void 0) { data = {}; }
    if (success === void 0) { success = nop; }
    if (error === void 0) { error = function (message) { return alert("Error POSTing " + path + ": " + message); }; }
    $.post("/csrf", {}, function (token) {
        $.ajax("/csrf/" + path, {
            dataType: "json",
            data: JSON.stringify(data),
            headers: {
                "Content-Type": "application/json",
                "X-Poggit-CSRF": token
            },
            method: "POST",
            success: function (data) {
                if (data.success) {
                    success(data.data);
                }
                else {
                    error(data.message);
                }
            }
        });
    });
}
function login(nextStep) {
    if (nextStep === void 0) { nextStep = window.location.toString(); }
    csrf("login/persistLoc", { path: nextStep }, function (data) {
        window.location.assign("https://github.com/login/oauth/authorize?client_id=" + PoggitConsts.App.ClientId + "&state=" + data.state);
    });
}
function nop() {
}

})
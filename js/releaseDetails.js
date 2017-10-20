$(function() {
    var preprocessMarkdown = function($content) {
        var contentId = $content.attr("id");
        var headerAnchors = [];
        $content.find("a.anchor").each(function() {
            var $this = $(this).addClass("dynamic-anchor").html("&sect;");
            $this.appendTo($this.parent());
            var oldAnchorName = this.id.substring("user-content-".length);
            headerAnchors.push(oldAnchorName);
            var anchorName = contentId + "-header-" + oldAnchorName;
            $this.parent().attr("data-header-id", oldAnchorName);
            $this.attr("name", anchorName).attr("id", "anchor-" + anchorName).attr("href", "#" + anchorName)
                .removeAttr("aria-hidden");
            dynamicAnchor.call(this);
        });
        var customAnchors = [];
        $content.find("a[name^='user-content-']").each(function() {
            var $this = $(this);
            customAnchors.push(this.name.substring("user-content-".length));
            $this.attr("name", contentId + "-custom-" + $this.attr("name"));
        });
        $content.find("a[href]").each(function() {
            var $this = $(this);
            var link = $this.attr("href"); // raw href
            switch(getLinkType(link)) {
                case "switchAnchor":
                    var name;
                    if(link.startsWith("#user-content-")) {
                        name = link.substring("#user-content-".length);
                        if(headerAnchors.indexOf(name) !== -1) {
                            $this.attr("href", "#" + contentId + "-header-" + name);
                        } else if(customAnchors.indexOf(name) !== -1) {
                            $this.attr("href", "#" + contentId + "-custom-" + name);
                        }
                    } else if(link.startsWith("#poggit-")) {
                        $this.attr("href", "#" + link.substring("#poggit-".length));
                    } else {
                        name = link.substring(1);
                        if(headerAnchors.indexOf(name) !== -1) {
                            $this.attr("href", "#" + contentId + "-header-" + name);
                        } else if(customAnchors.indexOf(name) !== -1) {
                            $this.attr("href", "#" + contentId + "-custom-" + name);
                        }
                    }
                    break;
                case "switchName":
                    $this.attr("href", "https://github.com/" + releaseDetails.project.repo.owner + "/" +
                        releaseDetails.project.repo.name + "/" + releaseDetails.build.tree + link);
                    break;
                case "switchPath":
                    $this.attr("href", "https://github.com" + link);
                    break;
                case "switchDomain":
                    $this.attr("href", "https:" + link);
                    break;
            }
        });
    };

    var tabularize = function(tags, $contents, requiredHeaders, isHorizontal, idPrefix) {
        var delimiter = null, i;
        for(i in tags) {
            if(tags.hasOwnProperty(i)) {
                if($contents.children(tags[i]).length >= requiredHeaders) {
                    delimiter = tags[i];
                    break;
                }
            }
        }
        if(delimiter === null) return "The description has less than " + requiredHeaders + " headers.";
        tags = tags.slice(i + 1);

        var ids = [idPrefix + "general"];
        var titles = ["General"];
        $contents.children(delimiter).each(function() {
            var $this = $(this);
            $this.children("a.anchor.dynamic-anchor").remove();
            var myId = $this.attr("data-header-id");
            if(!myId) myId = String(Math.random());
            ids.push(idPrefix + myId);
            titles.push($this.text());
        });
        var tabs = Array(titles.length);
        for(i = 0; i < ids.length; ++i) {
            tabs[i] = $("<div class='release-description-tab-content'></div>")
                .attr("id", ids[i])
                .addClass(isHorizontal ? "release-description-tab-horizontal" : "release-description-tab-vertical");
        }
        i = 0;
        $contents.contents().each(function() {
            if(this instanceof HTMLHeadingElement && this.tagName.toLowerCase() === delimiter) {
                ++i;
                return;
            }
            tabs[i].append(this); // assume tabs[i] exists
        });
        var skipGeneral = tabs[0].children().length === 0;
        var titleTabs = $("<ul></ul>");
        for(i = 0; i < ids.length; ++i) {
            if(skipGeneral && i === 0) continue;
            titleTabs.append($("<li></li>").append($("<a></a>")
                .attr("href", "#" + ids[i])
                .text(titles[i])));
        }

        var result = $("<div></div>").attr("id", idPrefix + "container").append(titleTabs);
        for(i = 0; i < ids.length; ++i) {
            if(skipGeneral && i === 0) continue;
            if(isHorizontal) {
                tabularize(tags, tabs[i], 4, false, ids[i] + "-");
            }
            result.append(tabs[i]);
        }
        result.tabs({
            orientation: isHorizontal ? "horizontal" : "vertical"
        });
        $contents.html("").append(result);

        return null;
    };

    var dialog = $("#release-description-bad-dialog");
    dialog.dialog({
        autoOpen: false
    });

    var desc = $("#release-description-content");
    preprocessMarkdown(desc);
    preprocessMarkdown($("#release-changelog-content"));
    if(sessionData.opts.makeTabs !== false) {
        var notabs = window.location.search.toLowerCase().indexOf("?notabs") !== -1 ||
            window.location.search.toLowerCase().indexOf("&notabs") !== -1; // vulnerable to ?notabsxxx collisions
        if(notabs){
            $(".release-description").append($("<span class='colored-bullet yellow'></span>")
                .css("cursor", "pointer")
                .click(function() {
                    window.location = window.location.origin + window.location.pathname;
                })
                .attr("title", "Click to display description in tabs."));
            return;
        }

        var error = null;
        if(desc.attr("data-desc-type") !== "html") {
            error = "The plugin description is not in markdown format.";
        } else {
            error = tabularize(["h1", "h2", "h3", "h4", "h5", "h6"], desc, 2, true, "rel-desc-tabs-");
        }
        if(error !== null) {
            $("#release-description-bad-reason").html(error);
            $(".release-description").append($("<span class='colored-bullet red'></span>")
                .css("cursor", "pointer")
                // .click(function() {
                //     dialog.dialog("open");
                // })
                .attr("title", "Failed to display description in tabs: " + error));
        } else {
            $(".release-description").append($("<span class='colored-bullet green'></span>")
                .css("cursor", "pointer")
                .click(function() {
                    window.location = window.location.origin + window.location.pathname + "?notabs";
                })
                .attr("title", "Click to display description directly without splitting into tabs."));
        }
    }

    var authors = $("#release-authors");
    authors.find(".release-authors-entry").each(function() {
        var $this = $(this);
        var name = $this.attr("data-name");
        ghApi("users/" + name, {}, "GET", function(data) {
            if(data.name === null) return;
            var span = $("<span class='release-author-realname'></span>").text("(" + data.name + ")");
            $this.append(span);
        });
    });
    ghApi("users/" + authors.attr("data-owner"), {}, "GET", function(data) {
        if(data.type === "User") {
            var ownerLi = $("<li></li>")
                .append($("<img/>")
                    .attr("src", data.avatar_url)
                    .attr("width", "16"))
                .append("@" + data.login)
                .append($("<a></a>")
                    .attr("href", data.html_url)
                    .attr("target", "_blank")
                    .append($("<img class='gh-logo'/>")
                        .attr("src", getRelativeRootPath() + "res/ghMark.png")
                        .attr("width", "16")));
            if(data.name !== null) ownerLi.append("&nbsp;").append($("<span class='release-author-realname'></span>").text("(" + data.name + ")"));
            var li = $("<li>Owner</li>")
                .append($("<ul></ul>")
                    .append(ownerLi));
            li.prependTo($("#release-authors-main"));
        }
    });

    function getLinkType(link) {
        if(/^https?:\/\//i.test(link)) return "switchProtocol";
        if(link.startsWith("//")) return "switchDomain";
        if(link.charAt(0) === "/") return "switchPath";
        if(link.charAt(0) !== "#") return "switchName";
        return "switchAnchor";
    }
});


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

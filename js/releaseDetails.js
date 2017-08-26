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

    preprocessMarkdown($("#release-description-content"));
    preprocessMarkdown($("#release-changelog-content"));

    var authors = $("#release-authors");
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
                        .attr("width", "16")))
                .append("&nbsp;")
                .append($("<span class='release-author-realname'></span>").text("(" + data.name + ")"));
            var li = $("<li>Owner</li>")
                .append($("<ul></ul>")
                    .append(ownerLi));
            li.prependTo($("#release-authors-main"));
        }
    });
    authors.find(".release-authors-entry").each(function() {
        var $this=$(this);
        var name = $this.attr("data-name");
        ghApi("users/" + name, {}, "GET", function(data) {
            if(data.name === null) return;
            var span = $("<span class='release-author-realname'></span>").text("(" + data.name + ")");
            $this.append(span);
        });
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

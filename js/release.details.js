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
                .text(titles[i].substr(0, 35) + (titles[i].length > 35 ? "..." : ""))));
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

    $("#how-to-install").dialog({
        autoOpen: false,
        modal: true,
        position: modalPosition,
        open: function(event, ui) {
            $('.ui-widget-overlay').bind('click', function() {
                $("#how-to-install").dialog('close');
            });
        }
    });
    var dialog = $("#release-description-bad-dialog");
    dialog.dialog({
        autoOpen: false,
        position: modalPosition
    });

    var authors = $("#release-authors");
/*    authors.find(".release-authors-entry").each(function() {
        var $this = $(this);
        var name = $this.attr("data-name");
        ghApi("users/" + name, {}, "GET", function(data) {
            if(data.name === null) return;
            var span = $("<span class='release-author-realname'></span>").text("(" + data.name + ")");
            $this.append(span);
        });
    });
*/
    ghApi("users/" + authors.attr("data-owner"), {}, "GET", function(data) {
        if(data.type === "User") {
            var ownerLi = $("<li class='release-authors-entry'></li>")
                .append($("<img/>")
                    .attr("src", data.avatar_url)
                    .attr("width", "16"))
                .append(" @" + data.login)
                .append(generateGhLink(data.html_url));
            var li = $("<li>Owner</li>")
                .append($("<ul class='plugin-info release-authors-sub'></ul>")
                    .append(ownerLi));
            li.prependTo($("#release-authors-main"));
        }

    });

    $("#license-dialog").dialog({
        position: modalPosition,
        modal: true,
        height: window.innerHeight * 0.8,
        width: window.innerWidth * 0.8,
        autoOpen: false,
        open: function(event, ui) {
            $('.ui-widget-overlay').bind('click', function() {
                $("#license-dialog").dialog('close');
            });
        }
    });

    if(!releaseDetails.isMine) initReview();

    if(sessionData.session.isLoggedIn && releaseDetails.state === PoggitConsts.ReleaseState.checked) {
        var voteupDialog, voteupForm, votedownDialog, votedownForm;

        // VOTING
        function doUpVote() {
            var message = $("#vote-message").val();
            var vote = 1;
            addVote(releaseDetails.releaseId, vote, message);
            voteupDialog.dialog("close");
            return true;
        }

        function doDownVote() {
            var message = $("#vote-message").val();
            if(message.length < 10) {
                $("#vote-error").text("Please type at least 10 characters...");
                return;
            }
            var vote = -1;
            addVote(releaseDetails.releaseId, vote, message);
            votedownDialog.dialog("close");
            return true;
        }

        var buttons = {
            Cancel: function() {
                voteupDialog.dialog("close");
            },
            // <?php if($this->myVote <= 0) { ?>
            Accept: doUpVote
            // <?php } ?>
        };
        if(releaseDetails.myVote <= 0){
            buttons.Accept = doUpVote;
        }

        voteupDialog = $("#voteup-dialog").dialog({
            title: "ACCEPT Plugin",
            autoOpen: false,
            position: modalPosition,
            modal: true,
            buttons: buttons,
            open: function() {
                $('.ui-widget-overlay').bind('click', function() {
                    $("#voteup-dialog").dialog('close');
                });
            },
            close: function() {
                voteupForm[0].reset();
            }
        });
        voteupForm = voteupDialog.find("form").on("submit", function(event) {
            event.preventDefault();
        });

        $("#upvote").button().on("click", function() {
            voteupDialog.dialog("open");
        });

        votedownDialog = $("#votedown-dialog").dialog({
            title: "REJECT Plugin",
            autoOpen: false,
            position: modalPosition,
            modal: true,
            buttons: {
                Cancel: function() {
                    votedownDialog.dialog("close");
                },
                "Reject": doDownVote
            },
            open: function(event, ui) {
                $('.ui-widget-overlay').bind('click', function() {
                    $("#votedown-dialog").dialog('close');
                });
            },
            close: function() {
                votedownForm[0].reset();
            }
        });
        votedownForm = votedownDialog.find("form").on("submit", function(event) {
            event.preventDefault();
        });

        $("#downvote").button().on("click", function() {
            votedownDialog.dialog("open");
        });
    }

    var desc = $("#release-description-content"), chLog = $("#release-changelog-content");
    preprocessMarkdown(desc);
    preprocessMarkdown(chLog);
    if(sessionData.opts.makeTabs !== false) {
        var notabs = getParameterByName("notabs", null) !== null;
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

    if(window.location.hash === "#shield-template"){
        alert("Thank you for submitting your plugin! Please have a look at the shields here and add them to the README on your repo.");
        window.location.hash = "";
    }

    var disabled = [];
    var i = 0;
    chLog.children("ul").children("li").each(function() {
        if(this.hasAttribute("data-disabled")){
            disabled.push(i);
        }
        i++;
    });
    chLog.tabs({
        disabled: disabled
    });

    function getLinkType(link) {
        if(/^https?:\/\//i.test(link)) return "switchProtocol";
        if(link.startsWith("//")) return "switchDomain";
        if(link.charAt(0) === "/") return "switchPath";
        if(link.charAt(0) !== "#") return "switchName";
        return "switchAnchor";
    }

    function initReview() {
        var reviewDialog, reviewForm;

        // REVIEWING
        function doAddReview() {
            var criteria = $("#review-criteria").val();
            var user = "<?= Session::getInstance()->getName() ?>";
            var type = sessionData.session.adminLevel >= PoggitConsts.AdminLevel.MODERATOR ? 1 : 2;
            var cat = releaseDetails.mainCategory;
            var score = $("#votes").val();
            var message = $("#review-message").val();
            addReview(releaseDetails.releaseId, user, criteria, type, cat, score, message);

            reviewDialog.dialog("close");
            return true;
        }

        reviewDialog = $("#review-dialog").dialog({
            title: "Poggit Review",
            autoOpen: false,
            position: modalPosition,
            modal: true,
            buttons: {
                Cancel: function() {
                    reviewDialog.dialog("close");
                },
                "Post Review": doAddReview
            },
            open: function() {
                $('.ui-widget-overlay').bind('click', function() {
                    $("#review-dialog").dialog('close');
                });
            },
            close: function() {
                reviewForm[0].reset();
            }
        });

        reviewForm = reviewDialog.find("form").on("submit", function(event) {
            event.preventDefault();
        });

        var reviewIntent = $(".release-review-intent");
        var reviewIntentImages = reviewIntent.find("> img");
        reviewIntent.hover(function() {
            var score = this.getAttribute("data-score");
            reviewIntent.each(function() {
                // noinspection JSPotentiallyInvalidUsageOfThis
                if(this.getAttribute("data-score") <= score) {
                    $(this).find("> img").attr("src", getRelativeRootPath() + "res/Full_Star_Yellow.svg");
                }
            });
        }, function() {
            reviewIntentImages.attr("src", getRelativeRootPath() + "res/Empty_Star.svg");
        }).click(function() {
            $("#votes").val(this.getAttribute("data-score"));
            reviewDialog.dialog("open");
        });
    }
});


function deleteRelease() {
    var modalPosition = {my: "center top", at: "center top+100", of: window};
    $("#dialog-confirm").dialog({
        resizable: false,
        height: "auto",
        width: 400,
        position: modalPosition,
        clickOut: true,
        modal: true,

        buttons: {
            "Delete Forever": function() {
                $(this).dialog("close");
                ajax("release.statechange", {
                    data: {
                        relId: releaseDetails.releaseId,
                        action: "delete"
                    },
                    method: "POST",
                    success: function() {
                        location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
                    },
                    error: function() {
                        location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
                    }
                });
            },
            Cancel: function() {
                $(this).dialog("close");
            }
        },
        open: function(event, ui) {
            $('.ui-widget-overlay').bind('click', function() {
                $("#dialog-confirm").dialog('close');
            });
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
            location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
        },
        error: function() {
            location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
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
            location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
        },
        error: function(request) {
            location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
        }
    });
}

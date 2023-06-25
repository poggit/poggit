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

$(function() {
    var submitEntries = [];
    const Config = PoggitConsts.Config;
    var descEntry, authorsEntry;
    var submitData;
    let editing = false;

    $(window).bind("beforeunload", () => editing ? "Changes will not be saved" : undefined);

    ajax("submit.form", {
        data: {
            query: window.location.pathname.substr(getRelativeRootPath().length)
        },
        success: function(data) {
            if(data.action === "error/redirect") {
                window.location.replace(data.target);
                if(data.message !== null) {
                    alert(data.message);
                }
                return;
            }
            var body = $("#body");
            if(data.action === "error/bad_query") {
                body.html("<h1>400 Bad Request</h1>");
                if(data.text){
                    $("<p></p>").text(data.message).appendTo(body);
                }else{
                    body.append(`<p>${data.message}</p>`);
                }
                return;
            }
            if(data.action === "error/access_denied") {
                body.html("<h1>401 Access Denied</h1>");
                if(data.text){
                    $("<p></p>").text("Details: " + data.message).appendTo(body);
                }else{
                    body.append(`<p>Details:&nbsp;${data.message}</p>`);
                }
                return;
            }
            if(data.action === "error/not_found") {
                body.html("<h1>404 Not Found</h1>");
                if(data.text){
                    $("<p></p>").text(data.message).appendTo(body);
                }else{
                    body.append(`<p>${data.message}</p>`);
                }
                return;
            }
            if(data.action !== "success") {
                alert("The server returns an unknown result! Please copy (or screenshot) the following data:\n\n" + JSON.stringify(data));
                return;
            }

            submitData = data.submitData;
            var last = data.submitData.last;
            document.title = data.title;
            document.getElementById("submit-title-action").innerHTML = data.actionTitle;
            generateGhLink(data.treeLink).appendTo(document.getElementById("submit-title-gh"));
            var projectFullName = submitData.args.slice(0, 3).join("/");
            $("<a class='colorless-link' target='_blank'></a>")
                .attr("href", getRelativeRootPath() + "ci/" + projectFullName)
                .append($("<img/>").attr("src", `${getRelativeRootPath()}ci.badge/${projectFullName}?build=${submitData.args[3]}`))
                .appendTo(document.getElementById("submit-title-badge"));
            if(last != null) {
                var lastNameIntro = document.getElementById("submit-intro-last-name");
                $("<h5></h5>").text("Updates v" + last.version)
                    .append($("<sub></sub>")
                        .append($("<a class='colorless-link' target='_blank'></a>")
                            .attr("href", `${getRelativeRootPath()}ci/${projectFullName}/${last.internal}`)
                            .text(`Dev Build #${last.internal} (&${last.buildId})`)))
                    .appendTo(lastNameIntro);
                lastNameIntro.style.display = "block";
            }
            main();
            editing = true;
        },
        error: function(xhr) {
            var requestId = xhr.getResponseHeader("X-Poggit-Request-ID");
            editing = false;
            window.location.replace(getRelativeRootPath() + "500ise.template?id=" + requestId);
        }
    });

    function main() {
        submitEntries.push("About this plugin");
        submitEntries.push(new SubmitFormEntry("name", submitData.fields.name, "Plugin Name", "submit2-name", StringEntry({
            size: 32
        }, function(newName, input, event) {
            if(this.locked) {
                if(typeof event !== "undefined") event.preventDefault();
                return;
            }
            this.ajaxLock = newName;
            this.invalid = true;
            ajax("release.submit.validate.name", {
                data: {
                    name: newName
                },
                method: "POST",
                success: (data)=> {
                    if(this.ajaxLock !== newName) {
                        return;
                    }
                    // noinspection JSUnresolvedVariable
                    this.invalid = !data.ok;
                    // noinspection JSUnresolvedVariable
                    this.reactInput(data.message, data.ok ? "form-input-good" : "form-input-error");
                }
            });
        }), function() {
            return {
                valid: !this.invalid,
                message: "Invalid plugin name: " + this.$getRow().find(".form-input-react").text()
            };
        }, false, submitData.mode !== "submit"));
        submitEntries.push(new SubmitFormEntry("shortDesc", submitData.fields.shortDesc, "Synopsis", "submit2-tagline", StringEntry({
            size: 64,
            maxlength: 128
        }), function() {
            var value = this.getValue();
            return {
                valid: value.length >= Config.MIN_SHORT_DESC_LENGTH && value.length <= Config.MAX_SHORT_DESC_LENGTH,
                message: `The length of the synopsis must be within ${Config.MIN_SHORT_DESC_LENGTH} and ${Config.MAX_SHORT_DESC_LENGTH} characters`
            }
        }));
        if(getAdminLevel() >= PoggitConsts.AdminLevel.REVIEWER) submitEntries.push(new SubmitFormEntry("official", submitData.fields.official, "Official?", "submit2-official", BooleanEntry, function() {
            return {valid: true};
        }));
        submitEntries.push(descEntry = new SubmitFormEntry("description", submitData.fields.description, "Description", "submit2-description", HybridEntry({
            cols: 72,
            rows: 15
        }), function() {
            return {
                valid: this.getValue().text.length >= Config.MIN_DESCRIPTION_LENGTH,
                message: `The description must be at least ${Config.MIN_DESCRIPTION_LENGTH} characters long`
            };
        }));

        submitEntries.push("About this version");
        submitEntries.push(new SubmitFormEntry("version", submitData.fields.version, "Version", "submit2-version", StringEntry({
            size: 10,
            maxlength: 16
        }, function(newVersion, input, event) {
            if(this.locked) {
                if(typeof event !== "undefined") event.preventDefault();
                return;
            }
            ajax("release.submit.validate.version", {
                data: {
                    version: newVersion,
                    projectId: submitData.buildInfo.projectId
                },
                method: "POST",
                success: (data) =>{
                    this.invalid = !data.ok;
                    // noinspection JSUnresolvedVariable
                    this.reactInput(data.message, data.ok ? "form-input-good" : "form-input-error");
                }
            })
        }), function() {
            return {
                valid: !this.invalid,
                message: "Invalid plugin version: " + this.$getRow().find(".form-input-react").text()
            };
        }, true, submitData.mode === "edit"));
        submitEntries.push(new SubmitFormEntry("preRelease", submitData.fields.preRelease, "Pre-release?", "submit2-prerelease", BooleanEntry, function() {
            return {valid: true};
        }));
        if(submitData.mode === "edit") submitEntries.push(new SubmitFormEntry("outdated", submitData.fields.outdated, "Outdated?", "submit2-outdated", BooleanEntry, function() {
            return {valid: true};
        }));
        submitEntries.push(new SubmitFormEntry("abandoned", submitData.fields.abandoned, "Abandoned?", "submit2-abandoned", BooleanEntry, function() {
            return {valid: true};
        }));
        if(typeof submitData.fields.changelog === "object") submitEntries.push(new SubmitFormEntry("changelog", submitData.fields.changelog, "What's New", "submit2-changelog", HybridEntry({
            cols: 72,
            rows: 8
        }), function() {
            return {
                valid: this.getValue().text.length >= Config.MIN_CHANGELOG_LENGTH,
                message: `The changelog must be at least ${Config.MIN_CHANGELOG_LENGTH} characters long`
            };
        }));

        submitEntries.push("Help users find this plugin");
        submitEntries.push(new SubmitFormEntry("majorCategory", submitData.fields.majorCategory, "Major Category", "submit2-majorcat", DropListEntry(submitData.consts.categories, {}, function(newValue) {
            var cbs = $("#submit2-minorcats").find(".cbinput");
            cbs.removeClass("submit-cb-disabled").children(".submit-cb").prop("disabled", false);
            var disabledCbInput = cbs.filter(function() {
                var el = this.getElementsByClassName("submit-cb");
                console.assert(el.length === 1);
                return Number(el[0].value) === Number(newValue);
            })
                .addClass("submit-cb-disabled");
            disabledCbInput.find(".submit-cb").prop("disabled", true);
        }), function() {
            return {valid: true};
        }));
        submitEntries.push(new SubmitFormEntry("minorCategories", submitData.fields.minorCategories, "Minor Categories", "submit2-minorcats", CompactMultiSelectEntry(submitData.consts.categories), function() {
            return {valid: true};
        }));
        submitEntries.push(new SubmitFormEntry("keywords", submitData.fields.keywords, "Keywords", "submit2-keywords", StringEntry, function() {
            var value = this.getValue();
            if(value.split(" ").length > Config.MAX_KEYWORD_COUNT) {
                return {
                    valid: false,
                    message: `Too many keywords! Only supply up to ${Config.MAX_KEYWORD_COUNT} keywords.`
                };
            }
            for(var i = 0; i < value.length; ++i) {
                if(value[i].length > Config.MAX_KEYWORD_LENGTH) {
                    return {
                        valid: false,
                        message: `The keyword "${value[i]}" is too long. Each keyword should not have more than ${Config.MAX_KEYWORD_LENGTH} characters.`
                    }
                }
            }
            return {valid: true};
        }));

        submitEntries.push("About installation");
        submitEntries.push(new SubmitFormEntry("deps", submitData.fields.deps, "Dependencies", "submit2-deps", DepTableEntry, function() {
            return {valid: true};
        }));
        submitEntries.push(new SubmitFormEntry("requires", submitData.fields.reqrs, "Manual Setup", "submit2-requires", RequiresTableEntry, function() {
            return {valid: true}
        }));
        submitEntries.push(new SubmitFormEntry("spoons", submitData.fields.spoons, "Supported APIs", "submit2-spoons", SpoonTableEntry, function() {
            return {
                valid: this.getValue().length >= 1,
                message: "There must be at least one supported API version range!"
            };
        }, true, submitData.mode === "edit" && submitData.refRelease.state !== 0));

        submitEntries.push("Other details");
        submitEntries.push(new SubmitFormEntry("license", submitData.fields.license, "License", "submit2-license", LicenseEntry, function() {
            if(this.getValue().custom !== null){
                if(this.getValue().custom.length < 200){
                    return {
                        valid: false,
                        message: "The custom license must be a open source license, and the full text is required."
                    };
                }
            }
            if(this.getValue().type === null || this.getValue().type === "none"){
                return {
                    valid: false,
                    message: "A license is required before it can be submitted."
                };
            }
            return {valid: true};
        }));
        submitEntries.push(new SubmitFormEntry("perms", submitData.fields.perms, "Permissions", "submit2-perms", ExpandedMultiSelectEntry(submitData.consts.perms, true), function() {
            return {valid: true};
        }));
        submitEntries.push(authorsEntry = new SubmitFormEntry("authors", submitData.fields.authors, "Producers", "submit2-authors", AuthorsTableEntry, function() {
            return {
                valid: this.getValue().length >= authorsEntry.type.minRows,
                message: "There must be at least 1 producer!"
            };
        }));

        setupReadmeImports(descEntry);
        setTimeout(refreshAuthors, 500, authorsEntry);

        var form = $(".form-table");
        form.empty();
        for(var i = 0; i < submitEntries.length; ++i) {
            if(submitEntries[i].constructor === String) {
                $("<div class='form-subtitle'></div>").append($("<span></span>").text(submitEntries[i])).appendTo(form);
            } else if(submitEntries[i].constructor === SubmitFormEntry) {
                submitEntries[i].appendTo(form);
            }
        }

        for(var j = 0; j < submitEntries.length; ++j) {
            if(submitEntries[j].constructor === SubmitFormEntry) {
                submitEntries[j].setDefaults();
            }
        }

        form.append($("<div class='form-row'></div>")
            .append("<div class='form-key'>Icon</div>")
            .append($("<div class='form-value'></div>").html(submitData.icon.html))
            .append($("<div class='form-icon-preview'></div>")
                .append($("<img/>").attr("src", submitData.icon.url === null ? (getRelativeRootPath() + "res/defaultPluginIcon2.png") : submitData.icon.url))));


        var submitButtons = $("<div class='submit-buttons'></div>");
        var buttonSubmit = null, buttonDraft = null;
        if(submitData.mode !== "edit") {
            buttonSubmit = submitData.last !== null ? "Submit Update" : "Submit Plugin";
            buttonDraft = "Save as Draft";
        } else {
            if(submitData.refRelease.state === 0) {
                buttonSubmit = submitData.last !== null ? "Submit Update from Draft" : "Submit Plugin from Draft";
                buttonDraft = "Save Edit";
            } else if(submitData.refRelease.state === 2) {
                buttonSubmit = "Save Edit";
                buttonDraft = "Restore to Draft";
            } else { // impossible that state === 1, so state >= 3
                buttonSubmit = "Save Edit";
            }
        }
        if(buttonSubmit !== null) {
            submitButtons.append(
                $("<span class='action-red'></span>")
                    .text(buttonSubmit)
                    .click(function() {
                        uploadForm("submit");
                    }));
        }
        if(buttonDraft !== null) {
            submitButtons.append(
                $("<span class='action-red'></span>")
                    .text(buttonDraft)
                    .click(function() {
                        uploadForm("draft");
                    }));
        }
        submitButtons.appendTo(form)
    }

    function uploadForm(action) {
        var bad = false;
        $(".submit-deps-version").each(function() {
            if(typeof $(this).attr("data-relid") === "undefined") {
                alert("You haven't selected the version in one of the dependencies yet!");
                bad = true;
            }
        });
        if(bad) return;
        var waitSpinner = $('#wait-spinner');
        waitSpinner.modal();
        for(var i = 0; i < submitEntries.length; ++i) {
            if(submitEntries[i].constructor === SubmitFormEntry) {
                if(submitEntries[i].locked) continue;
                var validation = submitEntries[i].validator.call(submitEntries[i]);
                if(!validation.valid) {
                    waitSpinner.modal('hide');
                    if(action === "submit") {
                        alert(`Cannot submit due to a problem in ${submitEntries[i].name}:

${validation.message}`);
                        return;
                    } else if(!confirm(`Warning: This plugin cannot be submitted due to a problem in ${submitEntries[i].name}:

${validation.message}

Do you still want to save this draft?`)) return;
                }
            }
        }
        var data = {
            form: getAllValues(),
            action: action,
            submitFormToken: submitData.submitFormToken
        };
        ajax("submit.new.ajax", {
            method: "POST",
            data: JSON.stringify(data),
            success: function(response) {
                if(response.status) {
                    editing = false;
                    window.location.assign(response.link);
                } else {
                    waitSpinner.modal('hide');
                    alert(response.error);
                }
            },
            error: function(jqXHR) {
                waitSpinner.modal('hide');
                var response = JSON.parse(jqXHR.responseText);
                alert(response.error);
            }
        });
    }

    function showErrorPage(message) {
        var $body = $("#body");
        $body.empty();
        var p = $("<p id='fallback-error'></p>");
        p.text(message);
        p.appendTo($body);
    }

    function applyAttrs($el, attrs) {
        if(typeof attrs === "object") {
            for(var k in attrs) {
                if(!attrs.hasOwnProperty(k)) continue;
                if(typeof attrs[k] === "boolean") {
                    $el.prop(k, attrs[k]);
                } else {
                    $el.attr(k, attrs[k]);
                }
            }
        }
    }

    function setupReadmeImports(descEntry) {
        ghApi("search/code?q=" + encodeURIComponent("readme in:path repo:" + submitData.repoInfo.full_name), {}, "GET", function(data) {
            var items = data.items;
            var projectPath = submitData.buildInfo.path;
            items.sort(function(a, b) {
                var aInDir = a.path.substring(0, projectPath.length) === projectPath;
                var bInDir = b.path.substring(0, projectPath.length) === projectPath;
                if(aInDir && bInDir) return Math.compare(a.path.length, b.path.length);
                if(aInDir !== bInDir) return aInDir ? -1 : 1;
                if(a.path === a.name) return -1;
                if(b.path === b.name) return 1;
                return a.path.localeCompare(b.path);
            });
            var row = descEntry.$getRow();
            for(var i = 0; i < items.length; ++i) {
                var defaults = row.find(".form-value-defaults");
                var button = $("<span class='action form-value-import'></span>");
                button.text("Import from " + items[i].path);
                button.click((function(item) {
                    return function() {
                        var dlPath = `https://raw.githubusercontent.com/${submitData.repoInfo.full_name}/${submitData.repoInfo.default_branch}/${item.path}`;
                        $.get(dlPath, {}, function(data) {
                            row.find(".submit-hybrid-content").val(data);
                            row.find(".submit-hybrid-format").val("sm");
                        });
                    };
                })(items[i]));
                button.appendTo(defaults);
            }
        })
    }

    function refreshAuthors(authorsEntry) {
        var rows = authorsEntry.$getRow().find(".submit-tableentry-row");
        rows.each(function() {
            var row = $(this);
            var nameInput = row.find(".submit-authors-name");
            if(row.prop("data-changed") || row.prop("data-duplicated")) {
                var reactor = row.find(".submit-authors-name-react");
                var name = nameInput.val();
                row.prop("data-changed", false).prop("data-duplicated", false);
                var avatar = row.find(".submit-authors-avatar");
                avatar.attr("src", getRelativeRootPath() + "res/ghMark.png");

                reactor.removeClass("form-input-good form-input-error").html("&nbsp;");
                if(rows.find(".submit-authors-name").filter(function() {
                        return this.value.toLowerCase() === name.toLowerCase();
                    }).length > 1) {
                    reactor.addClass("form-input-error").html("&cross; Duplicated producer");
                    row.prop("data-duplicated", true);
                } else if(/^[a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38}$/i.test(name)) {
                    var lock = name.toLowerCase();
                    row.attr("data-lock", lock);
                    ghApi("users/" + name, {}, "GET", function(data) {
                        if(row.attr("data-lock") !== lock) {
                            return;
                        }

                        if(typeof data.avatar_url !== "undefined") {
                            if(data.type !== "User") {
                                reactor.addClass("form-input-error")
                                    .html(`&cross; Only users can be added as producers. @${name} is an ${data.type.toLowerCase()}.`);
                            } else {
                                avatar.attr("src", data.avatar_url);
                                reactor.addClass("form-input-good")
                                    .html(`&checkmark; Found user${data.name !== null ? ` (${data.name})` : ""}`);
                                row.attr("data-uid", data.id);
                            }
                        } else {
                            reactor.addClass("form-input-error")
                                .html(`&cross; @${name} was not registered on GitHub!`);
                        }
                    });
                } else {
                    reactor.addClass("form-input-error")
                        .html("&cross; Invalid username");
                }
            }
        });
        setTimeout(refreshAuthors, 500, authorsEntry);
    }

    function getAllValues() {
        var data = {};
        for(var i = 0; i < submitEntries.length; ++i) {
            if(submitEntries[i].constructor === SubmitFormEntry) {
                if(submitEntries[i].locked) continue;
                var value = submitEntries[i].getValue();
                if(value !== null) data[submitEntries[i].submitKey] = value;
            }
        }
        return data;
    }

    function SubmitFormEntry(submitKey, field, name, id, type, validator, prefSrc, locked) {
        if(typeof type === "function") type = type();
        console.assert(typeof type.appender === "function");
        console.assert(typeof type.getter === "function");
        console.assert(typeof type.setter === "function");
        console.assert(typeof type.afterRemarks === "boolean");
        console.assert(typeof validator === "function");

        this.type = type;
        this.validator = validator;
        this.id = id;
        this.submitKey = submitKey;
        this.name = name;
        this.rem = field.remarks;
        this.refDefault = field.refDefault;
        this.srcDefault = field.srcDefault;
        this.prefSrc = submitData.mode === "edit" ? false : (typeof prefSrc === "undefined" ? false : prefSrc);
        this.locked = typeof locked === "undefined" ? false : locked;
        this.isThisFirstEntry = this.constructor.isNextFirstEntry;
        this.constructor.isNextFirstEntry = false;
    }

    SubmitFormEntry.isNextFirstEntry = true;

    SubmitFormEntry.prototype.$getRow = function() {
        return $(document.getElementById(this.id));
    };

    SubmitFormEntry.prototype.appendTo = function($form) {
        var row = $("<div class='form-row'></div>")
            .attr("id", this.id);

        $("<div class='form-key'></div>")
            .text(this.name)
            .appendTo(row);

        var remDiv = $("<span></span>").addClass("explain").html(this.rem);
        var valDiv = $("<div class='form-value'></div>");
        if(this.type.afterRemarks) {
            remDiv.addClass("explain-before-input").appendTo(valDiv);
            this.type.appender.call(this, valDiv);
        } else {
            this.type.appender.call(this, valDiv);
            remDiv.addClass("explain-after-input").appendTo(valDiv);
        }
        valDiv.appendTo(row);
        $("<div class='form-input-react'></div>").appendTo(valDiv);

        if(!this.locked) {
            var defDiv = $("<div class='form-value-defaults'></div>"); // TODO improve scrolling

            var refButton = null, srcButton = null;
            if(this.refDefault !== null) {
                refButton = $("<span class='action form-value-import'></span>")
                    .text(submitData.mode === "edit" ? "Reset" : (this.isThisFirstEntry ? "Copy from last release" : "Copy"));
                refButton.click(()=> {
                    this.type.setter.call(this, this.refDefault);
                });
            }
            if(this.srcDefault !== null) {
                srcButton = $("<span class='action form-value-import'></span>").text(this.isThisFirstEntry ? "Detect from code" : "Detect");
                srcButton.click(()=> {
                    this.type.setter.call(this, this.srcDefault);
                });
            }
            if(!this.prefSrc) {
                if(refButton !== null) refButton.appendTo(defDiv);
                if(srcButton !== null) srcButton.appendTo(defDiv);
            } else {
                if(srcButton !== null) srcButton.appendTo(defDiv);
                if(refButton !== null) refButton.appendTo(defDiv);
            }
            defDiv.appendTo(row);
        }

        row.appendTo($form);
    };

    SubmitFormEntry.prototype.setDefaults = function() {
        var set;
        if(this.prefSrc) {
            if(this.srcDefault !== null) {
                set = this.srcDefault;
            } else if(this.refDefault !== null) {
                set = this.refDefault;
            }
        } else {
            if(this.refDefault !== null) {
                set = this.refDefault;
            } else if(this.srcDefault !== null) {
                set = this.srcDefault;
            }
        }
        if(set !== undefined) {
            this.type.setter.call(this, set);
        } else if(typeof this.type.noDefaults === "function") {
            this.type.noDefaults.call(this);
        }
    };

    SubmitFormEntry.prototype.reactInput = function(message, classes) {
        var r = this.$getRow().find(".form-input-react");
        r.html(message);
        if(typeof this.classes === "object" && this.classes.constructor === Array) {
            for(var i = 0; i < this.classes.length; ++i) r.removeClass(this.classes[i])
        }
        this.classes = [];
        if(typeof classes === "string") {
            this.classes.push(classes);
            r.addClass(classes);
        } else if(typeof classes === "object" && classes.constructor === Array) {
            for(var j = 0; j < classes.length; ++j) {
                this.classes.push(classes[j]);
                r.addClass(classes[j]);
            }
        }
    };

    SubmitFormEntry.prototype.getValue = function() {
        return this.locked ? null : this.type.getter.call(this);
    };

    SubmitFormEntry.prototype.isValid = function() {
        return this.validator.call(this);
    };


    function StringEntry(attrs, onInput) {
        return {
            appender: function($val) {
                var input = $("<input/>");
                input.addClass("submit-textinput");
                if(this.locked) input.prop("disabled", true);
                applyAttrs(input, attrs);
                if(typeof onInput === "function") {
                    input.on("input", (e)=> {
                        var ret = onInput.call(this, input.val(), input, e);
                        if(typeof ret === "string") {
                            input.val(ret);
                        }
                    });
                }
                input.appendTo($val);
            },
            getter: function() {
                return this.$getRow().find(".submit-textinput").val();
            },
            setter: function(value) {
                var input = this.$getRow().find(".submit-textinput");
                input.val(value);
                if(typeof onInput === "function") onInput.call(this, value, input, undefined);
            },
            afterRemarks: false
        };
    }

    function BooleanEntry(attrs) {
        return {
            appender: function($val) {
                var input = $("<input type='checkbox'/>");
                input.addClass("submit-checkinput");
                if(this.locked) input.prop("disabled", true);
                applyAttrs(input, attrs);
                input.appendTo($val);
            },
            getter: function() {
                return this.$getRow().find(".submit-checkinput").prop("checked");
            },
            setter: function(value) {
                this.$getRow().find(".submit-checkinput").prop("checked", value);
            },
            afterRemarks: false
        };
    }

    function HybridEntry(attrs) {
        return {
            appender: function($val) {
                var area = $("<textarea class='submit-hybrid-content'></textarea>");
                if(this.locked) area.prop("disabled", true);
                applyAttrs(area, attrs);
                area.appendTo($val);

                var treePath = `https://github.com/${submitData.repoInfo.full_name}/tree/${submitData.buildInfo.sha.substring(0, 7)}/`;

                $("<div></div>")
                    .append("<label style='padding: 5px;'>Format:</label>")
                    .append(
                        $("<select class='submit-hybrid-format' style='display: inline-flex;'></select>")
                            .append($("<option value='txt'>Plain Text (You may indent with spaces)</option>"))
                            .append($("<option value='sm'></option>")
                                .text(`Standard Markdown (rendered like README, links relative to ${treePath})`))
                            .append($("<option value='gfm'></option>")
                                .text(`GFM (rendered like issue comments, links relative to ${treePath})`)))
                    .appendTo($val);
            },
            getter: function() {
                var row = this.$getRow();
                return {
                    text: row.find(".submit-hybrid-content").val(),
                    type: row.find(".submit-hybrid-format").val()
                }
            },
            setter: function(data) {
                var row = this.$getRow();
                row.find(".submit-hybrid-content").val(data.text);
                row.find(".submit-hybrid-format").val(data.type);
            },
            noDefaults: function() {
                this.$getRow().find(".submit-hybrid-format").val("sm");
            },
            afterRemarks: false
        };
    }

    function LicenseEntry() {
        var licenseData = {};
        var createLicenseViewDialog = function() {
            var dialog = $("<div id='licenseViewDialog'></div>");
            var loading = $("<div id='licenseDialogLoading'></div>").css("font-size", "x-large").text("Loading...");
            var innerDialog = $("<div id='licenseDialogInner' autofocus></div>").css("display", "none");
            loading.appendTo(dialog);
            innerDialog.appendTo(dialog);
            var desc = $("<p id='licenseDescription'></p>");
            desc.appendTo(innerDialog);
            var ulList = $("<div id='licenseMetaDiv'></div>").css("display", "flex");
            var ulNames = {"permissions": "Permissions", "conditions": "Conditions", "limitations": "Limitations"};
            var metadataUls = {};
            for(var key in ulNames) {
                if(!ulNames.hasOwnProperty(key)) continue;
                var vertDiv = $("<div class='license-metadata'></div>");
                $("<div></div>").css("font-weight", "bold").text(ulNames[key]).appendTo(vertDiv);
                var ul = $("<ul></ul>");
                metadataUls[key] = ul;
                ul.appendTo(vertDiv);
                vertDiv.appendTo(ulList);
            }
            ulList.appendTo(innerDialog);
            var bodyPre = $("<pre></pre>");
            bodyPre.appendTo(innerDialog);

            dialog.dialog({
                autoOpen: false,
                width: Math.min(window.innerWidth * 0.8, 600),
                modal: true,
                height: window.innerHeight * 0.8,
                position: modalPosition,
                open: function(event, ui) {
                    $('.ui-widget-overlay').bind('click', function() {
                        $("#licenseViewDialog").dialog('close');
                    });
                }
            });

            return {
                dialogDiv: dialog,
                innerLoadingDiv: loading,
                innerDialogDiv: innerDialog,
                ulNames: ulNames,
                ulList: ulList,
                description: desc,
                metadataUls: metadataUls,
                bodyPre: bodyPre
            };
        };

        return {
            appender: function($val) {
                this.ready = false;
                var keySelect, customArea, licenseView;

                keySelect = $("<select></select>").addClass("submit-license-type");

                keySelect.append($("<option></option>").text("No License Selected").attr("value", "none"))

                var specialGroup = ($("<optgroup label='Special'></optgroup>")
                    .append($("<option></option>").text("Custom License").attr("value", "custom")));

                var deprecatedGroup = $("<optgroup></optgroup>").attr("label", "Deprecated License IDs");

                keySelect.change(function() {
                    customArea.css("display", keySelect.val() === "custom" ? "block" : "none");
                    var shouldDisable = keySelect.val() === "none" || keySelect.val() === "custom";
                    var wasDisabled = licenseView.hasClass("disabled");
                    if(wasDisabled !== shouldDisable) {
                        if(shouldDisable) {
                            licenseView.addClass("disabled");
                        } else {
                            licenseView.removeClass("disabled");
                        }
                    }
                })
                    .appendTo($val);

                customArea = $("<textarea></textarea>").addClass("submit-license-custom").css("display", "none").appendTo($val);

                var dialog = createLicenseViewDialog();

                licenseView = $("<span></span>").addClass("action disabled")
                    .attr("id", "licenseView")
                    .text("View license details")
                    .click(function() {
                        var name = keySelect.val();
                        if(licenseData[name] === undefined) return;
                        var license = licenseData[name];
                        window.open(license.reference, "_blank").focus();
                    })
                    .appendTo($val);

                ajax("licenses.ajax", {
                    data: {},
                    success: (data) => {
                        data = JSON.parse(data);
                        data = data.licenses;
                        data.sort(function(a, b) {
                            return a.name.localeCompare(b.name);
                        });

                        for(var i = 0; i < data.length; i++) {
                            if(data[i].isOsiApproved === false) continue;
                            var option = $("<option></option>");
                            option.attr("value", data[i].licenseId);
                            option.attr("data-url", data[i].detailsUrl);
                            option.text(data[i].name + " (" + data[i].licenseId + ")");
                            licenseData[data[i].licenseId] = data[i];
                            if(typeof this.wannaSet !== "undefined" && data[i].licenseId === this.wannaSet.type) {
                                option.prop("selected", true);
                                keySelect.val(data[i].licenseId);
                                licenseView.removeClass("disabled");
                            }
                            // noinspection JSUnresolvedVariable
                            option.appendTo(data[i].isDeprecatedLicenseId ? deprecatedGroup : keySelect);
                        }
                        deprecatedGroup.appendTo(keySelect);
                        specialGroup.appendTo(keySelect);
                        this.ready = true;
                    }
                });
            },
            getter: function() {
                if(!this.ready) {
                    return this.wannaSet;
                }
                var row = this.$getRow();
                var type = row.find(".submit-license-type").val();
                return {
                    type: type,
                    custom: type === "custom" ? row.find(".submit-license-custom").val() : null
                };
            },
            setter: function(data) {
                if(!this.ready && data.type !== "custom" && data.type !== "none") {
                    this.wannaSet = data;
                    return;
                }
                var row = this.$getRow();
                row.find(".submit-license-type").val(data.type);
                if(data.type === "custom") row.find(".submit-license-custom").val(data.custom);
            },
            afterRemarks: false
        };
    }

    function DropListEntry(data, attrs, onChange) {
        return {
            appender: function($val) {
                var input = $("<select></select>");
                input.addClass("submit-selinput");
                if(this.locked) input.prop("disabled", true);
                for(var value in data) {
                    if(!data.hasOwnProperty(value)) continue;
                    var option = $("<option></option>");
                    option.attr("value", value);
                    option.text(data[value]);
                    option.appendTo(input);
                }
                applyAttrs(input, attrs);
                if(typeof onChange === "function") {
                    input.change((e)=> {
                        var ret = onChange.call(this, input.val(), input, e);
                        if(typeof ret === "string") {
                            input.val(ret);
                        }
                    });
                }
                input.appendTo($val);
            },
            getter: function() {
                return this.$getRow().find(".submit-selinput").val();
            },
            setter: function(value) {
                var input = this.$getRow().find(".submit-selinput");
                input.val(value);
                if(typeof onChange === "function") onChange.call(this, value);
            },
            noDefaults: function() {
                if(typeof onChange === "function") onChange.call(this, this.$getRow().find(".submit-selinput").val());
            },
            afterRemarks: false
        };
    }

    function CompactMultiSelectEntry(data, cbAttrs, cbinputAttrs, divAttrs) {
        return {
            appender: function($val) {
                var div = $("<div></div>");
                div.addClass("submit-cbgrp-compact");
                for(var value in data) {
                    if(!data.hasOwnProperty(value)) continue;
                    var container = $("<div class='cbinput'></div>");
                    var cb = $("<input type='checkbox'/>");
                    cb.addClass("submit-cb");
                    cb.attr("value", value);
                    cb.appendTo(container);
                    container.append(data[value]);
                    container.appendTo(div);
                    if(this.locked) cb.prop("disabled", true);
                    applyAttrs(cb, cbAttrs);
                    applyAttrs(container, cbinputAttrs);
                }
                applyAttrs(div, divAttrs);
                div.appendTo($val);
            },
            getter: function() {
                return this.$getRow().find(".submit-cb:checked").map(function() {
                    return this.value;
                }).get();
            },
            setter: function(value) {
                this.$getRow().find(".submit-cb").not(".submit-cb-disabled").each(function() {
                    this.checked = value.map(String).includes(this.value);
                });

            },
            afterRemarks: false
        };
    }

    function ExpandedMultiSelectEntry(data, afterRemarks) {
        return {
            afterRemarks: afterRemarks,
            appender: function($val) {
                var div = $("<div></div>");
                div.addClass("submit-expcb-wrapper");
                for(var value in data) {
                    if(!data.hasOwnProperty(value)) continue;
                    var row = $("<div></div>").addClass("submit-expcb-row");
                    var left = $("<div></div>").addClass("cbinput");
                    var cb = $("<input type='checkbox'/>").addClass("submit-cb")
                        .attr("value", value);
                    cb.appendTo(left);
                    if(this.locked) cb.prop("disabled", true);
                    left.append(`<div class='cblabel'>${data[value].name}</div>`);
                    left.appendTo(row);

                    $("<div></div>").addClass("remark")
                        .text(data[value].description)
                        .appendTo(row);

                    row.appendTo(div);
                }
                div.appendTo($val);
            },
            getter: function() {
                return this.$getRow().find(".submit-cb:checked").map(function() {
                    return this.value;
                }).get();
            },
            setter: function(value) {
                this.$getRow().find(".submit-cb").not(".submit-cb-disabled").each(function() {
                    this.checked = value.map(String).includes(this.value);
                });
            }
        };
    }

    function TableEntry(headerWriter, rowAppender, rowGetter, rowSetter, minRows) {
        if(typeof minRows === "undefined") minRows = 0;

        function addRow(table, entry) {
            var row = $("<tr class='submit-tableentry-row'></tr>");
            rowAppender(row, entry);
            if(!entry.locked) {
                var delCell = $("<td></td>");
                $("<span class='action-red'>&cross;</span>").click(function() {
                    if(table.find(".submit-tableentry-row").length <= minRows) {
                        alert(`There must be at least ${minRows} rows!`);
                        return;
                    }
                    row.remove();
                }).appendTo(delCell);
                delCell.appendTo(row);
            }
            row.appendTo(table);
            return row;
        }

        return {
            appender: function($val) {
                var table = $("<table class='submit-tableentry-table'></table>");
                headerWriter(table);

                for(var i = 0; i < minRows; ++i) addRow(table, this);
                table.appendTo($val);

                if(!this.locked) {
                    $("<span class='action'>&plus;</span>").click(function() {
                        addRow(table, this);
                    }).appendTo($val);
                }
            },
            getter: function() {
                var results = [];
                this.$getRow().find(".submit-tableentry-row").each(function() {
                    var rowResult = rowGetter($(this));
                    if(rowResult !== null) results.push(rowResult);
                });
                return results;
            },
            setter: function(values) {
                console.assert(values.constructor === Array, `values passed for ${this.name} is not array but `, values);
                var table = this.$getRow().find(".submit-tableentry-table");
                table.children(".submit-tableentry-row").remove();
                for(var i = 0; i < values.length; ++i) {
                    var row = addRow(table, this);
                    rowSetter(row, values[i]);
                }
            },
            minRows: minRows,
            afterRemarks: false
        };
    }

    function AuthorsTableEntry() {
        return TableEntry(
            /*header*/ function($table) {
                $("<tr class='submit-authors-row-default'></tr>")
                    .append($("<th>GitHub username</th>").css("width", "200px"))
                    .append("<th>Type</th>")
                    .appendTo($table);
            },
            /*subappender*/ function($row) {
                $("<td class='submit-authors-user-cell'></td>")
                    .append($("<img height='28' class='submit-authors-avatar'/>").attr("src", getRelativeRootPath() + "res/ghMark.png"))
                    .append($("<input class='submit-authors-name' size='15'/>").on("input", function() {
                        $row.prop("data-changed", true);
                        $row.removeAttr("data-uid");
                    }))
                    .append($("<div class='submit-authors-name-react'>&nbsp;</div>"))
                    .appendTo($row);

                var select = $("<select class='submit-authors-level'></select>");
                for(var level in submitData.consts.authors) {
                    if(!submitData.consts.authors.hasOwnProperty(level)) continue;
                    var name = submitData.consts.authors[level];
                    $("<option></option>").attr("value", level).text(name).appendTo(select);
                }
                $("<td class='submit-authors-level-cell'></td>")
                    .append(select)
                    .appendTo($row);
            },
            /*subgetter*/ function($row) {
                return typeof $row.attr("data-uid") === "undefined" ? null :
                    $row.find(".submit-authors-name-react").hasClass("form-input-good") ? {
                        uid: $row.attr("data-uid"),
                        name: $row.find(".submit-authors-name").val(),
                        level: $row.find(".submit-authors-level").val()
                    } : null;
            },
            /*subsetter*/ function($row, value) {
                if(value.uid === submitData.repoInfo.owner.id){
                    //Owner.
                    $row.attr("data-lock", value.name);
                    $row.attr("data-uid", value.uid);
                    $row.prop("data-changed", false);
                    var input = $row.find(".submit-authors-name");
                    input.val(value.name);
                    input.prop("disabled", true);
                    var level = $row.find(".submit-authors-level");
                    level.val(value.level);
                    level.prop("disabled", true);
                    $row.find(".submit-authors-avatar").attr("src", submitData.repoInfo.owner.avatar_url);
                    $row.find(".action-red").remove();
                    var reactor = $row.find(".submit-authors-name-react");
                    reactor.addClass("form-input-good").html(`The repo owner is an explicit collaborator`);
                }else{
                    $row.attr("data-uid", value.uid);
                    $row.prop("data-changed", true);
                    $row.find(".submit-authors-name").val(value.name);
                    $row.find(".submit-authors-level").val(value.level);
                }
            },
            /*minRows*/ 1
        );
    }

    function SpoonTableEntry() {
        function populateSpoonSelect(select) {
            for(var api in submitData.consts.spoons) {
                if(!submitData.consts.spoons.hasOwnProperty(api)) continue;
                $("<option></option>").addClass("submit-spoons-option").attr("value", api).text(api).appendTo(select);
            }
        }

        return TableEntry(
            /*header*/ $.noop,
            /*subappender*/ function($row, entry) {
                var start = $("<select class='submit-spoons-start'></select>");
                var end = $("<select class='submit-spoons-end'></select>");
                if(entry.locked) {
                    start.prop("disabled", true);
                    end.prop("disabled", true);
                }
                var spoons = submitData.consts.spoons;
                var spoonsLength = Object.sizeof(spoons);
                var apiNames = Object.keysToArray(spoons);
                var apisByIndex = Object.valuesToArray(spoons);
                populateSpoonSelect(start);
                populateSpoonSelect(end);
                // noinspection JSValidateTypes
                var startOptions = start.children("option"), endOptions = end.children("option");
                start.change(function() {
                    var startApi = this.value, startIndex = apiNames.indexOf(startApi);
                    var endApi = end.val(), endIndex = apiNames.indexOf(endApi);
                    endOptions.prop("disabled", false);
                    endOptions.slice(0, startIndex).prop("disabled", true);
                    for(var i = startIndex; i < spoonsLength; ++i) {
                        if(spoonsLength !== i + 1) { // this is not the last element
                            if(!apisByIndex[i + 1].incompatible) { // next API is a minor bump, must be compatible too
                                endOptions.eq(i).prop("disabled", true);
                                continue;
                            }
                        }
                        endOptions.eq(i).prop("disabled", false);
                    }
                    if(endIndex < startIndex) endIndex = startIndex;
                    if(endIndex + 1 < spoons.length && !apisByIndex[endIndex + 1].incompatible) {
                        for(var j = endIndex + 1; j < spoonsLength; ++j) {
                            if(j + 1 === spoonsLength || apisByIndex[j + 1].incompatible) {
                                endIndex = j;
                                break;
                            }
                        }
                        console.assert(endIndex === j);
                        endApi = apiNames[endApi];
                        end.val(endApi);
                        end.change();
                    }
                });
                end.change(function() {
                    var startIndex = apiNames.indexOf(start.val());
                    var endIndex = apiNames.indexOf(end.val());
                    startOptions.slice(0, endIndex + 1).prop("disabled", false);
                    startOptions.slice(endIndex + 1).prop("disabled", true);
                    if(startIndex > endIndex) {
                        startIndex = endIndex;
                        start.val(apiNames[startIndex]);
                    }
                });
                start.val(submitData.consts.promotedSpoon);
                end.val(Object.keysToArray(submitData.consts.spoons).slice(-1)[0]);
                start.wrap("<td></td>").parent().appendTo($row);
                $row.append("<td>&mdash;</td>");
                end.wrap("<td></td>").parent().appendTo($row);
            },
            /*subgetter*/ function($row) {
                return [
                    $row.find(".submit-spoons-start").val(),
                    $row.find(".submit-spoons-end").val()
                ]
            },
            /*subsetter*/ function($row, value) {
                var start = $row.find(".submit-spoons-start").val(value[0]);
                var end = $row.find(".submit-spoons-end").val(value[1]);
                start.change();
                end.change();
            },
            1);
    }

    function RequiresTableEntry() {
        return TableEntry(
            /*header*/ function($table) {
                $("<tr></tr>")
                    .append($("<th>Required?</th>").css("width", "150px"))
                    .append($("<th>Type</th>").css("width", "250px"))
                    .append($("<th>Details</th>").css("width", "500px"))
                    .appendTo($table);
            },
            /*subappender*/ function($row) {
                var sel = $("<select class='submit-requires-type'></select>");
                var detailsInput = $("<input class='submit-requires-details'/>").attr("size", 50);
                for(var id in submitData.consts.reqrs) {
                    if(!submitData.consts.reqrs.hasOwnProperty(id)) continue;
                    var name = submitData.consts.reqrs[id].name;
                    $("<option></option>").attr("value", id).text(name).appendTo(sel);
                }
                sel.change(function() {
                    detailsInput.attr("placeholder", submitData.consts.reqrs[this.value].details);
                }).change();
                $row
                    .append($("<select class='submit-requires-isrequire'></select>")
                        .append("<option value='require'>Requirement</option>")
                        .append("<option value='enhance'>Enhancement</option>")
                        .wrap("<td></td>").parent())
                    .append(sel.wrap("<td></td>").parent())
                    .append(detailsInput.wrap("<td></td>").parent());
            },
            /*subgetter*/ function($row) {
                return {
                    type: $row.find(".submit-requires-type").val(),
                    details: $row.find(".submit-requires-details").val(),
                    isRequire: $row.find(".submit-requires-isrequire").val() === "require"
                };
            },
            /*subsetter*/ function($row, value) {
                $row.find(".submit-requires-type").val(value.type).change();
                $row.find(".submit-requires-details").val(value.details);
                $row.find(".submit-requires-isrequire").val(value.isRequire ? "require" : "enhance");
            }
        );
    }

    var $currentVersionTarget, versionDialogLock, currentVersions;
    var dialogData = (function() {
        var dialog = $("<div id='depSelectDialog'></div>");
        var loadingDiv = $("<div></div>").css("font-size", "x-large").text("Loading versions...");
        var errorDiv = $("<div></div>").addClass("fallback-error");
        var innerDialog = $("<div></div>");
        innerDialog.append("<p>Select the <strong>recommended</strong> version of the dependency plugin, usually the <em>latest</em> version. " +
            "You don't need to select older versions; if you want to, mention it in the Description.</p>");
        innerDialog.append("<p>Keep in mind that the state of your plugin will never be higher than the state of your dependencies (except Featured). " +
            "For example, if your plugin depends on a release that has not been approved yet, your plugin won't get approved either before the dependency release gets approved.</p>");

        var table = $("<table></table>");
        table.appendTo(innerDialog);
        dialog.append(loadingDiv).append(errorDiv).append(innerDialog);

        // noinspection JSUnusedGlobalSymbols
        dialog.dialog({
            autoOpen: false,
            width: Math.min(window.innerWidth * 0.8, 600),
            modal: true,
            height: window.innerHeight * 0.8,
            position: modalPosition,
            buttons: {
                Select: function() {
                    var relId = $("input.submit-dep-dialog-version-radio:checked").attr("data-value");
                    var versionName = currentVersions[relId].version;
                    $currentVersionTarget.text("Change: v" + versionName)
                        .attr("data-relid", relId)
                        .attr("data-version", versionName);
                    $(this).dialog("close");
                    currentVersions = versionDialogLock = $currentVersionTarget = undefined;
                }
            },
            open: function(event, ui) {
                $('.ui-widget-overlay').bind('click', function() {
                    $("#depSelectDialog").dialog('close');
                });
            }
        });

        return {
            dialog: dialog,
            loading: loadingDiv,
            error: errorDiv,
            inner: innerDialog,
            table: table
        };
    })();

    function DepTableEntry() {
        return TableEntry(
            /*header*/ function($table) {
                $("<tr></tr>")
                    .append($("<th>Required?</th>").css("width", "100px"))
                    .append($("<th>Plugin Name</th>").css("width", "200px"))
                    .append($("<th>Version</th>"))
                    .appendTo($table);
            },
            /*subappender*/ function($row) {
                var nameInput = $("<input class='submit-deps-name' size='15'/>");
                var versionSel = $("<span class='submit-deps-version action'>Choose a version...</span>");
                nameInput.change(function() {
                    versionSel.text("Choose a version").removeAttr("data-relid");
                });
                versionSel.click(function() {
                    $currentVersionTarget = versionSel;
                    var myLock = versionDialogLock = Math.random();

                    var depName = nameInput.val();
                    dialogData.dialog.dialog("option", "title", 'Choose a version of "' + nameInput.val() + '"');
                    dialogData.loading.css("display", "block");
                    dialogData.error.css("display", "none");
                    dialogData.inner.css("display", "none");
                    dialogData.dialog.dialog("open");
                    ajax("submit.deps.getversions", {
                        data: {name: depName, owner: 'false'},
                        success: function(versions) {
                            if(myLock !== versionDialogLock) return;
                            if(Object.sizeof(versions) === 0) {
                                dialogData.loading.css("display", "none");
                                dialogData.error.text(`There aren't any submitted (and not rejected) plugins on Poggit called ${depName}`).css("display", "block");
                                return;
                            }
                            dialogData.loading.css("display", "none");
                            currentVersions = versions;
                            dialogData.table.find("tr.submit-deps-version-select-row").remove();
                            dialogData.table.find("tr.submit-deps-version-header").remove();
                            for(var relId in versions) {
                                if(!versions.hasOwnProperty(relId)) continue;
                                var dateSpan = $("<span></span>").attr("data-timestamp", versions[relId].submitTime);
                                timeTextFunc.call(dateSpan.get()[0]);
                                // noinspection JSUnresolvedVariable
                                dialogData.table.prepend($("<tr></tr>").addClass("submit-deps-version-select-row")
                                    .append($("<td></td>")
                                        .append($("<input type='radio'/>").addClass("submit-dep-dialog-version-radio").attr("name", versionDialogLock)
                                            .attr("data-value", relId)
                                            .prop("checked", true))
                                        .append($("<label></label>").text(versions[relId].version)))
                                    .append($("<td></td>").append($("<input type='checkbox' disabled/>")
                                        .prop("checked", versions[relId].preRelease)))
                                    .append($("<td></td>").append(versions[relId].stateName))
                                    .append($("<td></td>").append(dateSpan)));
                            }
                            dialogData.table.prepend("<tr class='submit-deps-version-header'><th>Version</th><th>Pre-release</th><th>Status</th><th>Submitted</th></tr>");
                            dialogData.inner.css("display", "block");
                        }
                    })
                });

                $row.append($("<select class='submit-deps-required'></select>")
                    .append("<option value='required'>Required</option>")
                    .append("<option value='optional'>Optional</option>")
                    .wrap("<td></td>").parent())
                    .append(nameInput.wrap("<td></td>").parent())
                    .append(versionSel.wrap("<td></td>").parent());
            },
            /*subgetter*/ function($row) {
                var versionSpan = $row.find("span.submit-deps-version");
                return typeof versionSpan.attr("data-relid") === "undefined" ? null : {
                    name: $row.find("input.submit-deps-name").val(),
                    version: versionSpan.attr("data-version"),
                    depRelId: Number(versionSpan.attr("data-relid")),
                    required: $row.find("select.submit-deps-required").val() === "required"
                };
            },
            /*subsetter*/ function($row, value) {
                $row.find("input.submit-deps-name").val(value.name);
                var versionSpan = $row.find("span.submit-deps-version");
                versionSpan.text(`Change: v${value.version}`)
                    .attr("data-version", value.version)
                    .attr("data-relid", value.depRelId);
                $row.find("select.submit-deps-required").val(value.required ? "required" : "optional")
            });
    }
});

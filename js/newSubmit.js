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
    if(typeof submitData.pluginYml !== "object") return showErrorPage("Cannot submit plugin with error in plugin.yml");

    var entries = [];
    var descEntry, authorsEntry;
    entries.push(new SubmitFormEntry(StringEntry({
        size: 32
    }, function(newName, input, event) {
        var entry = this;
        if(this.locked) {
            if(typeof event !== "undefined") event.preventDefault();
            return;
        }
        entry.ajaxLock = newName;
        entry.invalid = true;
        ajax("release.submit.validate.name", {
            data: {
                name: newName
            },
            method: "POST",
            success: function(data) {
                if(entry.ajaxLock !== newName) {
                    return;
                }
                entry.invalid = !data.ok;
                entry.reactInput(data.message, data.ok ? "form-input-good" : "form-input-error");
            }
        });
    }), "submit2-name", "name", "Plugin Name", submitData.fields.name, false, submitData.mode !== "submit"));
    entries.push(new SubmitFormEntry(StringEntry({
        size: 64,
        maxlength: 128
    }), "submit2-tagline", "shortDesc", "Synopsis", submitData.fields.shortDesc));
    entries.push(new SubmitFormEntry(StringEntry({
        size: 10,
        maxlength: 16
    }, function(newVersion, input, event) {
        var entry = this;
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
            success: function(data) {
                entry.reactInput(data.message, data.ok ? "form-input-good" : "form-input-error");
            }
        })
    }), "submit2-version", "version", "Version", submitData.fields.version, true, submitData.mode === "edit"));
    entries.push(new SubmitFormEntry(BooleanEntry, "submit2-prerelease", "preRelease", "Pre-release?", submitData.fields.preRelease));
    entries.push(descEntry = new SubmitFormEntry(HybridEntry({
        cols: 72,
        rows: 15
    }), "submit2-description", "description", "Description", submitData.fields.description));
    if(typeof submitData.fields.changelog === "object") entries.push(new SubmitFormEntry(HybridEntry({
        cols: 72,
        rows: 8
    }), "submit2-changelog", "changelog", "What's New", submitData.fields.changelog));
    entries.push(new SubmitFormEntry(LicenseEntry, "submit2-license", "license", "License", submitData.fields.license));
    entries.push(new SubmitFormEntry(DroplistEntry(submitData.consts.categories, {}, function(newValue) {
        var cbs = $("#submit2-minorcats").find(".cbinput");
        cbs.removeClass("submit-cb-disabled");
        cbs.children(".submit-cb").prop("disabled", false);
        var disabledCbinput = cbs.filter(function() {
            var el = this.getElementsByClassName("submit-cb");
            console.assert(el.length === 1);
            return Number(el[0].value) === Number(newValue);
        })
            .addClass("submit-cb-disabled");
        disabledCbinput.find(".submit-cb").prop("disabled", true);
    }), "submit2-majorcat", "majorCategory", "Major Category", submitData.fields.majorCategory));
    entries.push(new SubmitFormEntry(CompactMultiSelectEntry(submitData.consts.categories), "submit2-minorcats", "minorCategories", "Minor Categories", submitData.fields.minorCategories));
    entries.push(new SubmitFormEntry(StringEntry, "submit2-keywords", "keywords", "Keywords", submitData.fields.keywords));
    entries.push(new SubmitFormEntry(SpoonTableEntry, "submit2-spoons", "spoons", "Supported APIs", submitData.fields.spoons));
    // entries.push(new SubmitFormEntry(DepTableEntry, "submit2-deps", "deps", "Dependencies", submitData.fields.deps));
    entries.push(new SubmitFormEntry(ExpandedMultiSelectEntry(submitData.consts.perms), "submit2-perms", "perms", "Permissions", submitData.fields.perms));
    // entries.push(new SubmitFormEntry(RequireTableEntry, "submit2-requires", "requires", "Manual Setup", submitData.fields.reqrs));
    entries.push(authorsEntry = new SubmitFormEntry(AuthorsTableEntry, "submit2-authors", "authors", "Producers", submitData.fields.authors));

    // TODO icon
    // TODO assoc

    setupReadmeImports(descEntry);
    setTimeout(refreshAuthors, 500, authorsEntry);

    var form = $(".form-table");
    for(var i = 0; i < entries.length; ++i) {
        entries[i].appendTo(form);
    }
    for(var j = 0; j < entries.length; ++j) {
        entries[j].setDefaults(form);
    }
});

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
                    var dlPath = "https://raw.githubusercontent.com/" + submitData.repoInfo.full_name + "/" + submitData.repoInfo.default_branch + "/" + item.path;
                    $.get(dlPath, {}, function(data) {
                        row.find(".submit-hybrid-content").val(data);
                        row.find(".submit-hybrid-format")
                    });
                };
            })(items[i]));
            button.appendTo(defaults);
        }
    })
}

function refreshAuthors(authorsEntry) {
    authorsEntry.$getRow().find(".submit-tableentry-row").each(function() {
        var row = $(this);
        var nameInput = row.find(".submit-authors-name");
        if(row.prop("data-changed")) {
            var reactor = row.find(".submit-authors-name-react");
            var name = nameInput.val();
            row.prop("data-changed", false);
            var avatar = row.find(".submit-authors-avatar");
            avatar.attr("src", getRelativeRootPath() + "res/ghMark.png");

            reactor.removeClass("form-input-good form-input-error").html("&nbsp;");
            if(/^[a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38}$/i.test(name)) {
                var lock = name.toLowerCase();
                row.attr("data-lock", lock);
                ghApi("users/" + name, {}, "GET", function(data) {
                    if(row.attr("data-lock") !== lock) {
                        return;
                    }

                    if(typeof data.avatar_url !== "undefined") {
                        if(data.type !== "User") {
                            reactor.addClass("form-input-error")
                                .html("&cross; Only users can be added as producers. @" + name + " is an " + data.type.toLowerCase() + ".");
                        } else {
                            avatar.attr("src", data.avatar_url);
                            reactor.addClass("form-input-good")
                                .html("&checkmark; " + (data.name === null ? "Found user" : ("Found user (" + data.name + ")")));
                            row.attr("data-uid", data.id);
                        }
                    } else {
                        reactor.addClass("form-input-error")
                            .html("&cross; No such user called " + data.login + " on GitHub!");
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


function SubmitFormEntry(type, id, submitKey, name, field, prefSrc, locked) {
    if(typeof type === "function") type = type();
    this.id = id;
    this.submitKey = submitKey;
    this.name = name;
    this.rem = field.remarks;
    this.refDefault = field.refDefault;
    this.srcDefault = field.srcDefault;
    this.prefSrc = typeof prefSrc === "undefined" ? false : prefSrc;
    this.locked = typeof locked === "undefined" ? false : locked;
    this.type = type;
}

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

    var entry = this;
    if(!this.locked) {
        var defDiv = $("<div class='form-value-defaults'></div>"); // TODO improve scrolling

        var refButton = null, srcButton = null;
        if(this.refDefault !== null) {
            refButton = $("<span class='action form-value-import'>Inherit from last release</span>");
            refButton.click(function() {
                entry.type.setter.call(entry, entry.refDefault);
            });
        }
        if(this.srcDefault !== null) {
            srcButton = $("<span class='action form-value-import'>Detect from this build</span>");
            srcButton.click(function() {
                entry.type.setter.call(entry, entry.srcDefault);
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
    if(set !== undefined) this.type.setter.call(this, set);
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
    return this.type.getter();
};


function StringEntry(attrs, onInput) {
    return {
        appender: function($val) {
            var input = $("<input/>");
            input.addClass("submit-textinput");
            if(this.locked) input.prop("disabled", true);
            applyAttrs(input, attrs);
            if(typeof onInput === "function") {
                var entry = this;
                input.on("input", function(e) {
                    var ret = onInput.call(entry, input.val(), input, e);
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
        }
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
            this.$getRow().find(".submit-checkinput").prop("checked");
        },
        setter: function(value) {
            this.$getRow().find(".submit-checkinput").prop("checked", value);
        }
    }
}

function HybridEntry(attrs) {
    return {
        appender: function($val) {
            var area = $("<textarea></textarea>");
            area.addClass("submit-hybrid-content");
            if(this.locked) area.prop("disabled", true);
            applyAttrs(area, attrs);
            area.appendTo($val);

            var typeDiv = $("<div'></div>");
            typeDiv.append("<label style='padding: 5px;'>Format:</label>");
            var type = $("<select style='display: inline-flex;'></select>");
            type.addClass("submit-hybrid-format");
            var optTxt = $("<option value='txt'></option>");
            optTxt.text("Plain Text (you may indent with spaces)");
            optTxt.appendTo(type);
            var treePath = "https://github.com/" + submitData.repoInfo.full_name + "/tree/" + submitData.buildInfo.sha.substring(0, 7) + "/";
            var optSm = $("<option value='sm'></option>");
            optSm.text("Standard Markdown (rendered like README, links relative to " + treePath + ")");
            optSm.appendTo(type);
            var optGfm = $("<option value='Gfm'></option>");
            optGfm.text("GFM (rendered like issue comments, links relative to " + treePath + ")");
            optGfm.appendTo(type);
            type.appendTo(typeDiv);
            typeDiv.appendTo($val);
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
        }
    };
}

function LicenseEntry() {
    var licenseData = {};
    var createLicenseViewDialog = function() {
        var dialog = $("<div id='licenseViewDialog'></div>");
        var loading = $("<div id='licenseDialogLoading'></div>").css("font-size", "x-large").text("Loading...");
        var innerDialog = $("<div id='licenseDialogInner'></div>").css("display", "none");
        loading.appendTo(dialog);
        innerDialog.appendTo(dialog);
        var desc = $("<p id='licenseDescription'></p>");
        desc.appendTo(innerDialog);
        var ulList = $("<div id='licenseMetaDiv'></div>").css("display", "flex");
        var ulNames = {"permissions": "Permissions", "conditions": "Conditions", "limitations": "Limitations"};
        var metadataUls = {};
        for(var key in ulNames) {
            if(!ulNames.hasOwnProperty(key)) continue;
            var vertDiv = $("<div class='license-metadata'></div>").css("display", "flex").css("justify-content", "flex-start").css("flex-direction", "column");
            $("<div></div>").css("font-weight", "bold").text(ulNames[key]).appendTo(vertDiv);
            var ul = $("<ul></ul>");
            metadataUls[key] = ul;
            ul.appendTo(vertDiv);
            vertDiv.appendTo(ulList);
        }
        ulList.appendTo(innerDialog);
        var bodyPre = $("<pre></pre>");
        bodyPre.appendTo(innerDialog);
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
            var entry = this;
            this.ready = false;
            var keySelect, customArea, licenseView;

            keySelect = $("<select></select>").addClass("submit-license-type")
                .append($("<optgroup label='Special'></optgroup>")
                    .append($("<option></option>").text("No License").attr("value", "none"))
                    .append($("<option></option>").text("Custom License").attr("value", "custom")));
            var featuredGroup = $("<optgroup></optgroup>").attr("label", "Featured");
            featuredGroup.appendTo(keySelect);
            var otherGroup = $("<optgroup></optgroup>").attr("label", "Others");
            otherGroup.appendTo(keySelect);

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
            // dialog.dialogDiv.dialog({
            //     autoOpen: false,
            //     width: 600,
            //     clickOut: true,
            //     responsive: true,
            //     height: window.innerHeight * 0.8,
            //     position: {my: "center top", at: "center top+100", of: window}
            // }); // FIXME TypeError: handlers.push is not a function?

            licenseView = $("<span></span>").addClass("action disabled")
                .attr("id", "licenseView")
                .text("View license details")
                .click(function() {
                    var name = keySelect.val();
                    if(licenseData[name] === undefined) return;
                    var license = licenseData[name];
                    dialog.dialogDiv.dialog("option", "title", license.name);
                    dialog.dialogDiv.dialog("open");
                    dialog.innerLoadingDiv.css("display", "block");
                    dialog.innerDialogDiv.css("display", "none");
                    ghApi("licenses/" + license.key, {}, "GET", function(data) {
                        dialog.description.text(data.description);
                        for(var ulName in dialog.ulNames) {

                            var ul = dialog.metadataUls[ulName];
                            var lines = data[ulName];
                            ul.empty();
                            for(var j = 0; j < lines.length; ++j) {
                                var li = $("<li></li>");
                                li.text(lines[j]);
                                li.appendTo(ul);
                            }
                        }
                        dialog.bodyPre.text(data.body);
                        dialog.innerLoadingDiv.css("display", "none");
                        dialog.innerDialogDiv.css("display", "block");
                    }, undefined, "Accept: application/vnd.github.drax-preview+json");
                })
                .appendTo($val);

            ghApi("licenses", {}, "GET", function(data) {
                data.sort(function(a, b) {
                    return a.name.localeCompare(b.name);
                });
                for(var i = 0; i < data.length; i++) {
                    var option = $("<option></option>");
                    option.attr("value", data[i].key);
                    option.attr("data-url", data[i].url);
                    option.text(data[i].name);
                    licenseData[data[i].key] = data[i];
                    if(data[i].key === entry.wannaSet.type) {
                        option.prop("selected", true);
                        licenseView.removeClass("disabled");
                    }
                    option.appendTo(data[i].featured ? featuredGroup : otherGroup);
                }
                entry.ready = true;
                keySelect.val(entry.wannaSet.type);
            }, undefined, "Accept: application/vnd.github.drax-preview+json");
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
        }
    };
}

function DroplistEntry(data, attrs, onChange) {
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
                var entry = this;
                input.change(function(e) {
                    var ret = onChange.call(entry, input.val(), input, e);
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
        }
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

        }
    }
}

function ExpandedMultiSelectEntry(data) {
    return {
        afterRemarks: true,
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
                left.append(data[value].name);
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

    function addRow(table) {
        var row = $("<tr class='submit-tableentry-row'></tr>");
        rowAppender(row);
        var delCell = $("<td></td>");
        $("<span class='action'>&cross;</span>").click(function() {
            if(table.find(".submit-tableentry-row").length <= minRows) {
                alert("There must be at least " + minRows + " rows!");
                return;
            }
            row.remove();
        }).appendTo(delCell);
        delCell.appendTo(row);
        row.appendTo(table);
        return row;
    }

    return {
        appender: function($val) {
            var table = $("<table class='submit-tableentry-table'></table>");
            headerWriter(table);

            for(var i = 0; i < minRows; ++i) addRow(table);
            table.appendTo($val);

            $("<span class='action'>&plus;</span>").click(function() {
                addRow(table);
            }).appendTo($val);
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
            console.assert(values.constructor === Array);
            var table = this.$getRow().find(".submit-tableentry-table");
            table.children(".submit-tableentry-row").remove();
            for(var i = 0; i < values.length; ++i) {
                var row = addRow(table);
                rowSetter(row, values[i]);
            }
        }
    };
}

function AuthorsTableEntry() {
    return TableEntry(function($table) /*header*/ {
        $table.append("<th style='width: 200px;'>GitHub username</th>")
            .append("<th>Type</th>");
    }, function($row) /*subappender*/ {
        var userCell = $("<td class='submit-authors-user-cell'></td>");
        userCell.append($("<img height='28' class='submit-authors-avatar'/>").attr("src", getRelativeRootPath() + "res/ghMark.png"));
        userCell.append($("<input class='submit-authors-name' size='15'/>").on("input", function() {
            $row.prop("data-changed", true);
            $row.removeAttr("data-uid");
        }));
        $("<div class='submit-authors-name-react'>&nbsp;</div>").appendTo(userCell);
        userCell.appendTo($row);

        var levelCell = $("<td class='submit-authors-level-cell'></td>");
        var select = $("<select class='submit-authors-level'></select>");
        for(var level in submitData.consts.authors) {
            if(!submitData.consts.authors.hasOwnProperty(level)) continue;
            var name = submitData.consts.authors[level];
            $("<option></option>").attr("value", level).text(name).appendTo(select);
        }
        select.appendTo(levelCell);
        levelCell.appendTo($row);
    }, function($row) /*subgetter*/ {
        return typeof $row.attr("data-uid") === "undefined" ? null : {
            uid: $row.attr("data-uid"),
            name: $row.find(".submit-authors-name").val(),
            level: $row.find(".submit-authors-level").val()
        };
    }, function($row, value) /*subsetter*/ {
        $row.attr("data-uid", value.uid);
        $row.prop("data-changed", true);
        $row.find(".submit-authors-name").val(value.name);
        $row.find(".submit-authors-level").val(value.level);
    }, 1);
}

function SpoonTableEntry() {
    return TableEntry(/*header*/$.noop, function($row) /*subappender*/ {
        var start = $("<select class='submit-spoons-start'></select>");
        var end = $("<select class='submit-spoons-end'></select>");
        var spoons = submitData.consts.spoons;
        var spoonsLength = Object.sizeof(spoons);
        var apiNames = Object.keysToArray(spoons);
        var apisByIndex = Object.valuesToArray(spoons);
        populateSpoonSelect(start);
        populateSpoonSelect(end);
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
            if(!apisByIndex[endIndex + 1].incompatible) {
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
        start.wrap("<td></td>").parent().appendTo($row);
        $row.append("<td>&mdash;</td>");
        end.wrap("<td></td>").parent().appendTo($row);
    }, function($row) /*subgetter*/ {
        return [
            $row.find(".submit-spoons-start").val(),
            $row.find(".submit-spoons-end").val()
        ]
    }, function($row, value) /*subsetter*/ {
        var start = $row.find(".submit-spoons-start").val(value[0]);
        var end = $row.find(".submit-spoons-end").val(value[1]);
        start.change();
        end.change();
    }, 1);
}

function populateSpoonSelect(select) {
    for(var api in submitData.consts.spoons) {
        if(!submitData.consts.spoons.hasOwnProperty(api)) continue;
        $("<option></option>").addClass("submit-spoons-option").attr("value", api).text(api).appendTo(select);
    }
}

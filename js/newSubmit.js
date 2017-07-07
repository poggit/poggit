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
    entries.push(new SubmitFormEntry(StringEntry({
        size: 32
    }, function(newName, input, event) {
        var entry = this;
        if(submitData.last !== null) {
            event.preventDefault();
        }
        ajax("release.submit.validate.name", {
            data: {
                name: newName
            },
            method: "POST",
            success: function(data) {
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
    }, function(newVersion) {
        var entry = this;
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
    // entries.push(new SubmitFormEntry(HybridEntry(), "submit2-description", "description", "Description", submitData.fields.description));
    // if(typeof submitData.fields.changelog === "object") entries.push(new SubmitFormEntry(HybridSubmitFormEntry, "submit2-changelog", "changelog", "What's New", submitData.fields.changelog));
    // entries.push(new SubmitFormEntry(LicenseHybridEntry, "submit2-license", "license", "License", submitData.fields.license));
    entries.push(new SubmitFormEntry(BooleanEntry, "submit2-prerelease", "preRelease", "Pre-release?", submitData.fields.preRelease));
    // entries.push(new SubmitFormEntry(DroplistEntry, "submit2-majorcat", "majorCategory", "Major Category", submitData.fields.majorCategory));
    // entries.push(new SubmitFormEntry(CompactMultiSelectEntry, "submit2-minorcats", "minorCategories", "Minor Categories", submitData.fields.minorCategories));
    entries.push(new SubmitFormEntry(StringEntry, "submit2-keywords", "keywords", "Keywords", submitData.fields.keywords));
    // entries.push(new SubmitFormEntry(SpoonTableEntry, "submit2-spoons", "spoons", "Supported APIs", submitData.fields.spoons));
    // entries.push(new SubmitFormEntry(DepTableEntry, "submit2-deps", "deps", "Dependencies", submitData.fields.deps));
    // entries.push(new SubmitFormEntry(ExpandedMultiSelectEntry, "submit2-perms", "perms", "Permissions", submitData.fields.perms));
    // entries.push(new SubmitFormEntry(RequireTableEntry, "submit2-requires", "requires", "Manual Setup", submitData.fields.reqrs));
    // TODO icon

    var form = $(".form-table");
    for(var i = 0; i < entries.length; ++i) {
        entries[i].appendTo(form);
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
            if(attrs.hasOwnProperty(k)) {
                if(typeof attrs[k] === "boolean") {
                    $el.prop(k, attrs[k]);
                } else {
                    $el.attr(k, attrs[k]);
                }
            }
        }
    }
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

SubmitFormEntry.prototype.jgetRow = function() {
    return $(document.getElementById(this.id));
};

SubmitFormEntry.prototype.appendTo = function($form) {
    var row = $("<div class='form-row'></div>");
    row.attr("id", this.id);
    var keyDiv = $("<div class='form-key'></div>");
    keyDiv.text(this.name);
    keyDiv.appendTo(row);

    var valDiv = $("<div class='form-value'></div>");
    this.type.appender.call(this, valDiv);
    var errDiv = $("<div class='form-input-react'></div>");
    errDiv.appendTo(valDiv);
    var remSpan = $("<span class='explain'></span>");
    remSpan.html(this.rem);
    remSpan.appendTo(valDiv);
    valDiv.appendTo(row);

    var entry = this;
    if(!this.locked) {
        var defDiv = $("<div class='form-value-defaults'></div>");

        var refButton = null, srcButton = null;
        if(this.refDefault !== null) {
            refButton = $("<span class='action form-value-import'>Inherit from last release</span>");
            refButton.click(function() {
                entry.type.setter.call(entry, entry.refDefault);
            });
        }
        if(this.srcDefault !== null) {
            srcButton = $("<span class='action form-value-import'>Detect from this build</span>"); // TODO add styles to fix the wrapping
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

    // set default
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
    if(set !== undefined) entry.type.setter.call(entry, set);
};

SubmitFormEntry.prototype.reactInput = function(message, classes) {
    var r = this.jgetRow().find(".form-input-react");
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

function StringEntry(attrs, onInput, extra) {
    return {
        appender: function($val) {
            var input = $("<input type='text'/>");
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
            if(typeof extra === "function") extra(input);
            input.appendTo($val);
        },
        getter: function() {
            return this.jgetRow().find(".submit-textinput").val();
        },
        setter: function(value) {
            var input = this.jgetRow().find(".submit-textinput");
            input.val(value);
        }
    };
}

function BooleanEntry(attrs, extra) {
    return {
        appender: function($val) {
            var input = $("<input type='checkbox'/>");
            input.addClass("submit-checkinput");
            if(this.locked) input.prop("disabled", true);
            applyAttrs(input, attrs);
            if(typeof extra === "function") extra(input);
            input.appendTo($val);
        },
        getter: function() {
            this.jgetRow().find(".submit-checkinput").prop("checked");
        },
        setter: function(value) {
            this.jgetRow().find(".submit-checkinput").prop("checked", value);
        }
    }
}

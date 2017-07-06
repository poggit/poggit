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
        size: 32,
        disabled: submitData.last !== null
    }, function(newName, input, event) {
        var entry = this;
        if(submitData.last !== null) {
            event.preventDefault();
        }
        ajax("release.submit.namecheck", {
            data: {
                pluginName: newName
            },
            method: "POST",
            success: function(data) {
                entry.reactInput(data.message, data.ok ? "form-input-good" : "form-input-error");
            }
        });
    }), "submit2-name", "name", "Plugin Name", submitData.fields.name));
    entries.push(new SubmitFormEntry(StringEntry({
        size: 64,
        maxlength: 128
    }), "submit2-tagline", "shortDesc", "Synopsis", submitData.fields.shortDesc));
    entries.push(new SubmitFormEntry(StringEntry(), "submit2-version", "version", "Version", submitData.fields.version));
    // entries.push(new SubmitFormEntry(HybridEntry(), "submit2-description", "description", "Description", submitData.fields.description));
    // if(typeof submitData.fields.changelog === "object") entries.push(new SubmitFormEntry(HybridSubmitFormEntry, "submit2-changelog", "changelog", "What's New", submitData.fields.changelog));
    // entries.push(new SubmitFormEntry(LicenseHybridEntry, "submit2-license", "license", "License", submitData.fields.license));
    // entries.push(new SubmitFormEntry(BooleanEntry, "submit2-prerelease", "preRelease", "Pre-release?", submitData.fields.preRelease));
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


function SubmitFormEntry(type, id, submitKey, name, field, prefSrc) {
    if(typeof type === "function") type = type();
    this.id = id;
    this.submitKey = submitKey;
    this.name = name;
    this.rem = field.remarks;
    this.refDefault = field.refDefault;
    this.srcDefault = field.srcDefault;
    this.prefSrc = typeof prefSrc === "undefined" ? false : prefSrc;

    this.type = type;
}

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

    var defDiv = $("<div class='form-value-defaults'></div>");
    var button;
    var entry = this;
    if(this.refDefault !== null) {
        button = $("<span class='action'>Import from last release</span>");
        button.click(function() {
            entry.type.setter.call(entry, entry.refDefault);
        });
        button.appendTo(defDiv);
    }
    if(this.srcDefault !== null) {
        button = $("<span class='action'>Detect from this build</span>");
        button.click(function() {
            console.log(entry);
            entry.type.setter.call(entry, entry.srcDefault);
        });
        button.appendTo(defDiv);
    }
    defDiv.appendTo(row);
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
    console.log(set);
    if(set !== undefined) entry.type.setter.call(entry, set);

    row.appendTo($form);
};

SubmitFormEntry.prototype.reactInput = function(message, classes) {
    console.log(this);
    var r = $(document.getElementById(this.id)).find(".form-input-react");
    console.log(r);
    r.text(message);
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
            if(typeof attrs === "object") {
                for(var k in attrs) {
                    if(attrs.hasOwnProperty(k)) {
                        if(typeof attrs[k] === "boolean") {
                            input.prop(k, attrs[k]);
                        } else {
                            input.attr(k, attrs[k]);
                        }
                    }
                }
            }
            if(typeof onInput === "function") {
                var entry = this;
                input.on("input", function(e) {
                    var ret = onInput.call(entry, input.val(), input, e);
                    if(typeof ret === "string") {
                        input.val(ret);
                    }
                });
            }
            if(typeof extra === "function") {
                extra(input);
            }
            input.appendTo($val);
        },
        getter: function() {
            return $(document.getElementById(this.id)).find(".submit-textinput").val();
        },
        setter: function(value) {
            var input = $(document.getElementById(this.id)).find(".submit-textinput");
            console.log(this.id, value);
            input.val(value);
        }
    };
}

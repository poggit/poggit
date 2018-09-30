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

$(() => {
    const dialog = $("#add-version-dialog").dialog({
        autoOpen: false,
        modal: true,
        position: modalPosition,
        buttons: {
            Submit: () => {
                const name = $("#dialog-name").val();
                const php = $("#dialog-php").val();
                const incompatible = document.getElementById("dialog-incompatible").checked;
                const indev = document.getElementById("dialog-indev").checked;
                const supported = document.getElementById("dialog-supported").checked;
                const desc = $("#dialog-description").val();

                if(!confirm(`Confirm submit?
Name: ${name}
PHP: ${php}
Incompatible: ${incompatible ? "yes" : "no"}
Indev: ${indev ? "yes" : "no"}
Supported: ${supported ? "yes" : "no"}

Description:
${desc.split("\n").map(line => "- " + line.trim()).join("\n")}
`)) {
                    return;
                }

                ajax("spoon.add.ajax", {
                    data: {
                        name: name,
                        php: php,
                        incompatible: incompatible ? 1 : 0,
                        indev: indev ? 1 : 0,
                        supported: supported ? 1 : 0,
                        desc: desc,
                    },
                    success: (data) => {
                        alert(`Added API ${name} as #${data.id}`);
                        window.location.reload(true);
                    },
                });

                dialog.dialog("close");
            },
        },
    });

    $("#add-version").on("click", function() {
        dialog.dialog("open");
    });

    $(".spoon-holder span.editable").on("click", function() {
        const spoonId = $(this).parents(".spoon-holder").attr("data-spoon-id");
        const fieldName = $(this).attr("data-field");
        const newText = prompt(`Change #${spoonId}.${fieldName} to:`, this.innerText);
        if(newText === null) {
            return;
        }
        ajax("spoon.edit.ajax", {
            data: {
                spoon: spoonId,
                field: fieldName,
                to: newText,
            },
            success: (data) => {
                alert(data.message);
                window.location.reload(true);
            },
        });
    });

    $(".spoon-holder input.editable:checkbox").on("change", function() {
        const spoonId = $(this).parents(".spoon-holder").attr("data-spoon-id");
        const fieldName = $(this).attr("data-field");
        const newValue = confirm(`Change #${spoonId}.${fieldName} to ${this.checked ? "true" : "false"}?`);
        if(!newValue) {
            this.checked = !this.checked;
            return;
        }
        ajax("spoon.edit.ajax", {
            data: {
                spoon: spoonId,
                field: fieldName,
                to: this.checked ? 1 : 0,
            },
            success: (data) => {
                alert(data.message);
                window.location.reload(true);
            },
        });
    });
});

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
    const dialog = $("#add-rule-dialog").dialog({
        autoOpen: false,
        modal: true,
        position: modalPosition,
        buttons: {
            Submit: () => {
                const id = $("#dialog-id").val();
                const title = $("#dialog-title").val();
                const content = $("#dialog-content").val();

                ajax("rule.add.ajax", {
                    data: {id, title, content},
                    success: (data) => {
                        alert(`Added rule ${id}`);
                        window.location.reload();
                    },
                });

                dialog.dialog("close");
            },
        },
    });

    $("#add-rule").on("click", function() {
        dialog.dialog("open");
    });

    $(".rule-holder .editable").on("click", function() {
        const id = $(this).parents(".rule-holder").attr("data-rule-id");
        const fieldName = $(this).attr("data-field");
        const newText = prompt(`Change #${id}.${fieldName} to:`, this.innerText);
        if(newText === null) {
            return;
        }
        ajax("rule.edit.ajax", {
            data: {id, fieldName, newText},
            success: (data) => {
                alert(data.message);
                window.location.reload();
            },
        });
    });
});

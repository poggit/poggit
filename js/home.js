/*
 * Copyright 2016 poggit
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

$(document).ready(function() {
    $(".repo-boolean").each(function() {
        var $this = $(this);
        var type = $this.attr("data-type");
        var repoId = $this.attr("data-repo");
        var failure = function(data) {
            if(data !== undefined) {
                console.error(data);
            }
            alert("Error configuring repo");
        };
        $this.change(function() {
            var target = this.checked;
            $this.prop("disabled", true);
            ajax("ajax.toggleRepo", {
                data: {
                    repoId: repoId,
                    property: type,
                    enabled: target
                },
                method: "POST",
                success: function(data) {
                    if(data.status === true) {
                        $this.prop("disabled", false);
                        if(data.created.constructor === Array) {
                            if(prompt((data.created.overwritten ? "Overwrote" : "Created") + " .poggit/.poggit.yml. See changes?")) {
                                window.open(data.created.commit, "_blank");
                            }
                        }
                    } else {
                        failure(data);
                    }
                },
                failure: failure
            });
        });
    });
    $("input:checkbox").each(function() {
        var $this = $(this);
        if(typeof $this.attr("data-depends") === "string") {
            var other = document.getElementById($this.attr("data-depends"));
            var $other = $(other);
            if($other.is(":checkbox")) {
                if(!other.checked) {
                    this.disabled = true;
                }
                $other.click(function() {
                    $this[0].disabled = !this.checked;
                });
            }
        }
    })
});

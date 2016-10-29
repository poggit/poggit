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
    var tag = $("<div id='remindTos'></div>");
    tag.html("<p>By continuing to use this site, you agree to the <a href='${path.relativeRoot}tos'>Terms of Service</a> of this website.</p>" +
        "<p><span class='action' onclick='hideTos()'>OK, Don't show this again</span></p>");
    $("#body").prepend(tag);
});

function hideTos() {
    ajax("hideTos", {
        success: function() {
            $("#remindTos").css("display", "none");
        }
    });
}


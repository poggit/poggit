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
    var scopes = $("input:checkbox.authScope");
    $("#checkAll").change(function() {
        scopes.prop("checked", $(this).prop("checked"));
    });
    scopes.each(function() {
        var $this = $(this);
        if(hasScopes.indexOf($this.attr("data-scope")) !== -1) {
            $this.prop("checked", true);
        }
    });
    $("#submitScopes").click(function() {
        var url = "https://github.com/login/oauth/authorize?client_id=" + getClientId() + "&state=" + getAntiForge() + "&scope=";
        url += encodeURIComponent(scopes.filter(":checked").map(function() {
            return this.getAttribute("data-scope");
        }).get().join());
        window.location = url;
    });
});

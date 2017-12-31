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
    var reviewReleases = $("#review-releases");
    if(reviewReleases.find('> div').length > 16) {
        if(getParameterByName("usePages", sessionData.opts.usePages !== false ? "on" : "off") === "on") {
            reviewReleases.paginate({
                perPage: 16,
                scope: $('div') // targets all div elements
            });
        }
    }
});

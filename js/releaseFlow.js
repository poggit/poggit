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
    var svg = document.getElementById("flow-svg");
    var states = svg.getElementsByClassName("state");
    var i;
    for(i in states) {
        if(!states.hasOwnProperty(i)) continue;
        var text = states[i];
        var rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
        var bBox = text.getBBox();
        rect.setAttribute("x", bBox.x - 10);
        rect.setAttribute("y", bBox.y - 5);
        rect.setAttribute("width", bBox.width + 20);
        rect.setAttribute("height", bBox.height + 10);
        rect.setAttribute("fill", text.getAttribute("fill"));
        rect.setAttribute("title", text.getAttribute("title"));
        svg.insertBefore(rect, text);
        text.setAttribute("fill", text.getAttribute("stroke"));
        $(rect).tooltip();
        $(text).tooltip();
    }
    var arrows = svg.getElementsByClassName("arrow");
    for(i in arrows) {
        if(!arrows.hasOwnProperty(i)) continue;
        var arrow = arrows[i];
        var d = arrow.getAttribute("d");
        var match = /^\s*M\s*(\d+)\s*,\s*(\d+)\s*L\s*(\d+)\s*,\s*(\d+)\s*$/i.exec(d);
        var x1 = Number(match[1]),
            y1 = Number(match[2]),
            x2 = Number(match[3]),
            y2 = Number(match[4]);
        createArrow(x1, y1, x2, y2, arrow);
        if(arrow.hasAttribute("data-arrow-reject")) {
            var rejectPoint = arrow.getAttribute("data-arrow-reject").split(",");
            var rejectArrow = createArrow((x1 + x2) / 2, (y1 + y2) / 2, Number(rejectPoint[0]), Number(rejectPoint[1]));
            svg.insertBefore(rejectArrow, arrow);
        }
        if(arrow.hasAttribute("data-comment")){
            arrow.setAttribute("title", arrow.getAttribute("data-comment"));
            $(arrow).tooltip();
        }
        formatStroke(arrow);
        if(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2) > 1000 && !arrow.hasAttribute("stroke-dasharray")){
            arrow.setAttribute("stroke-width", "5");
        }
    }

    function createArrow(x1, y1, x2, y2, patch) {
        var dx = x2 - x1,
            dy = y2 - y1;
        var distSq = dx * dx + dy * dy;
        console.assert(distSq > 0);
        var sectLenSq = distSq > 5000 ? 8 : 5;
        var prop = Math.sqrt(sectLenSq * sectLenSq / distSq);
        var x3 = x2 - dx * prop;
        var y3 = y2 - dy * prop;
        var xa = x3 - dy * prop,
            ya = y3 + dx * prop,
            xb = x3 + dy * prop,
            yb = y3 - dx * prop;
        var d = "M" + x1 + "," + y1;
        d += "L" + x2 + "," + y2;
        d += "L" + xa + "," + ya;
        d += "L" + xb + "," + yb;
        d += "L" + x2 + "," + y2;
        if(patch == null) {
            patch = document.createElementNS("http://www.w3.org/2000/svg", "path");
            formatStroke(patch);
        }
        patch.setAttribute("d", d);
        return patch;
    }

    function formatStroke(path){
        path.setAttribute("stroke", "black");
        path.setAttribute("fill", "black");
    }
});

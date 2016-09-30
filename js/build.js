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
    var inputUser = $("#inputUser");
    var inputRepo = $("#inputRepo");
    var inputProject = $("#inputProject");
    var inputBuild = $("#inputBuild");
    var gotoSelf = $("#gotoSelf");
    var gotoUser = $("#gotoUser");
    var gotoRepo = $("#gotoRepo");
    var gotoProject = $("#gotoProject");
    var gotoBuild = $("#gotoBuild");
    var listener = function() {
        var disableUser = !Boolean(inputUser.val());
        var disableRepo = !(Boolean(inputUser.val()) && Boolean(inputRepo.val()));
        var disableProject = !(Boolean(inputUser.val()) && Boolean(inputRepo.val()) && Boolean(inputProject.val()));
        var disableBuild = !(Boolean(inputUser.val()) && Boolean(inputRepo.val()) && Boolean(inputProject.val()) && Boolean(inputBuild.val()));
        if(gotoUser.hasClass("disabled") !== disableUser) gotoUser.toggleClass("disabled");
        if(gotoRepo.hasClass("disabled") !== disableRepo) gotoRepo.toggleClass("disabled");
        if(gotoProject.hasClass("disabled") !== disableProject) gotoProject.toggleClass("disabled");
        if(gotoBuild.hasClass("disabled") !== disableBuild) gotoBuild.toggleClass("disabled");
    };

    inputUser.keydown(function() {
        setTimeout(listener, 50)
    });
    inputUser.change(listener);
    inputUser.keyup(function(event) {
        if(event.keyCode == 13) gotoUser.click();
    });
    inputRepo.keydown(function() {
        setTimeout(listener, 50)
    });
    inputRepo.change(listener);
    inputRepo.keyup(function(event) {
        if(event.keyCode == 13) gotoRepo.click();
    });
    inputProject.keydown(function() {
        setTimeout(listener, 50)
    });
    inputProject.change(listener);
    inputProject.keyup(function(event) {
        if(event.keyCode == 13) gotoProject.click();
    });
    inputBuild.keydown(function() {
        setTimeout(listener, 50)
    });
    inputBuild.change(listener);
    inputBuild.keyup(function(event) {
        if(event.keyCode == 13) gotoBuild.click();
    });

    gotoSelf.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build";
        }
    });
    gotoUser.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val();
        }
    });
    gotoRepo.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val() + "/" + inputRepo.val();
        }
    });
    gotoProject.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val() + "/" + inputRepo.val() + "/" +
                inputProject.val();
        }
    });
    gotoBuild.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val() + "/" + inputRepo.val() + "/" +
                inputProject.val() + "/" + inputBuild.val();
        }
    });
});

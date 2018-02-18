const path = require("path");
const gulp = require("gulp");
const ts = require("gulp-typescript");
const wrap = require("gulp-wrap");
const cc = require("google-closure-compiler").gulp();

const backendProject = ts.createProject(path.join(__dirname, "server", "tsconfig.json"));
const frontendProject = ts.createProject(path.join(__dirname, "client", "tsconfig.json"));

gulp.task("backend", function(){
    return backendProject.src()
        .pipe(backendProject())
        .js
        .pipe(gulp.dest("./private"));
});

gulp.task("frontend", function(){
    return frontendProject.src()
        .pipe(frontendProject())
        .js
        .pipe(wrap('"use strict";\n$(function(){\n<%= contents %>\n})'))
        .pipe(gulp.dest("./public"))
        .pipe(cc({
            compilation_level: "SIMPLE",
            warning_level: "DEFAULT",
            language_in: "ECMASCRIPT5",
            language_out: "ECMASCRIPT3",
            output_wrapper: "",
            js_output_file: "client.min.js",
        }))
        .pipe(gulp.dest("./public"));
});

gulp.task("watch", function(){
    gulp.watch(path.join(__dirname, "server", "**", "*.ts"), ["backend"]);
    gulp.watch(path.join(__dirname, "client", "**", "*.ts"), ["frontend"]);
});

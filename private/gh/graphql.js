"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var index_1 = require("./index");
var ghGraphql;
(function (ghGraphql) {
    function repoData(token, repos, fields, handle, onError) {
        var query = "query(";
        for (var i = 0; i < repos.length; ++i) {
            query += "$o" + i + ": String! $n" + i + ": String! ";
        }
        query += "){";
        for (var i = 0; i < repos.length; ++i) {
            query += "r" + i + ": repository(owner: $o" + i + ", name: $n" + i + "){ " + fields + " } ";
        }
        query += "}";
        var vars = {};
        for (var i = 0; i < repos.length; ++i) {
            vars["o" + i] = repos[i].owner;
            vars["n" + i] = repos[i].name;
        }
        index_1.gh.post(token, "graphql", {
            query: query,
            variables: vars,
        }, function (result) {
            var mapped = [];
            for (var rid in result.data) {
                var id = rid.substring(1);
                var repoArg = repos[parseInt(id)];
                var datum = result.data[rid];
                datum._repo = repoArg;
                mapped.push(datum);
            }
            handle(mapped);
        }, onError);
    }
    ghGraphql.repoData = repoData;
})(ghGraphql = exports.ghGraphql || (exports.ghGraphql = {}));

// global
var sessionData = {
    "path": {
        "relativeRoot": "/"
    },
    "app": {
        "clientId": ""
    },
    "session": {
        "antiForge": "",
        "isLoggedIn": true,
        "loginName": "SOF3",
        "adminLevel": 5
    },
    "meta": {
        "isDebug": true
    }
};

// submit
var submitData = {
    repoInfo: {
        "id": 0,
        "name": "",
        "full_name": "",
        "owner": {
            "login": "",
            "id": 0,
            "avatar_url": "0",
            "gravatar_id": "",
            "type": "Organization",
            "site_admin": false
        },
        "private": false,
        "description": "",
        "fork": false,
        "created_at": "1970-00-00T00:00:00Z",
        "updated_at": "1970-00-00T00:00:00Z",
        "pushed_at": "1970-00-00T00:00:00Z",
        "homepage": "https://example.com",
        "size": 0,
        "stargazers_count": 0,
        "watchers_count": 0,
        "language": "PHP",
        "has_issues": true,
        "has_projects": true,
        "has_downloads": true,
        "has_wiki": true,
        "has_pages": false,
        "forks_count": 0,
        "mirror_url": null,
        "open_issues_count": 0,
        "forks": 0,
        "open_issues": 0,
        "watchers": 0,
        "default_branch": "",
        "permissions": {
            "admin": true,
            "push": true,
            "pull": true
        },
        "allow_squash_merge": true,
        "allow_merge_commit": true,
        "allow_rebase_merge": true,
        "organization": {
            "login": "",
            "id": 0,
            "gravatar_id": "",
            "type": "Organization",
            "site_admin": false
        },
        "network_count": 0,
        "subscribers_count": 0
    },
    buildInfo: {
        repoId: 0,
        projectId: 0,
        buildId: 0,
        devBuildRsr: 0,
        internal: 0,
        projectName: "",
        projectType: 1,
        path: "",
        buildTime: 0,
        sha: "0000000000000000000000000000000000000000",
        branch: "",
        releaseId: 0,
        thisState: 0,
        lastReleaseId: 0,
        lastState: 0,
        lastVersion: ""
    },
    args: ["poggit", "libasynql", "PoolExample", 4],
    refRelease: {
        releaseId: 0,
        parent_releaseId: null,
        name: "",
        shortDesc: "",
        version: "",
        state: 0,
        buildId: 0,
        flags: 0,
        description: -1,
        desctype: "md",
        descrMd: 1,
        changelog: -1,
        changelogType: "txt",
        chlogMd: 1,
        license: "",
        licenseRes: null,
        licMd: 1,
        submitTime: 0,
        keywords: [""],
        perms: [0],
        mainCategory: 0,
        categories: [0],
        spoons: [
            [0, 0]
        ],
        authors: {
            2: {
                1: "mojombo",
                2: "defunkt"
            },
            4: {3: "pjhyett"}
        },
        childAssocs: {
            "": {
                releaseId: 0,
                version: ""
            }
        },
        deps: [
            {
                name: "",
                version: "",
                depRelMd: 0,
                isHard: false
            }
        ],
        requires: [
            {
                type: 0,
                details: "",
                isRequire: false
            }
        ]
    },
    mode: "submit",
    pluginYml: {
        name: "",
        version: "",
        main: "",
        api: []
    },
    fields: {
        name: {refDefault: "", srcDefault: "", remarks: ""},
        shortDesc: {refDefault: "", srcDefault: "", remarks: ""},
        version: {refDefault: "", srcDefault: "", remarks: ""},
        description: {
            refDefault: {
                type: "", text: ""
            }, srcDefault: null, remarks: ""
        },
        changelog: {refDefault: null, srcDefault: null, remarks: ""}, // may be undefined
        license: {
            refDefault: {
                type: "", custom: "" // custom might be null
            }, srcDefault: {type: "", custom: null}, remarks: ""
        },
        preRelease: {refDefault: false, srcDefault: null, remarks: ""},
        majorCategory: {refDefault: 0, srcDefault: null, remarks: "", data: {0: ""}},
        minorCategories: {refDefault: [0], srcDefault: null, remarks: "", data: {0: ""}},
        keywords: {refDefault: [""], srcDefault: [""], remarks: ""},
        spoons: {refDefault: [["", ""]], srcDefault: [["", ""]], remarks: "", data: {
            "":{description: "", php:"", incompatible: false}
        }},
        deps: {
            refDefault: {
                name: "", version: "", depRelId: 0, required: false
            }, srcDefault: {
                name: "", version: "", depRelId: 0, required: false
            }, remarks: ""
        },
        perms: {refDefault: [0], srcDefault: null, remarks: "", data: {0: ["name", "explain"]}},
        reqrs: {refDefault: [{
            type: 0, details: "", isRequire: false
        }], srcDefault: null, remarks: "", data: {0: ""}}
    },
    last: {name: "", version: ""} // or null
};

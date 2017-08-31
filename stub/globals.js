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

var PoggitConsts = {
    AdminLevel: {
        GUEST: 0,
        MEMBER: 1,
        CONTRIBUTOR: 2,
        MODERATOR: 3,
        REVIEWER: 4,
        ADM: 5
    },
    BuildClass: {
        1: "Dev",
        4: "PR"
    },
    LintLevel: {
        0: "OK",
        1: "Lint",
        2: "Warning",
        3: "Error",
        4: "Build Error"
    },
    Config: {
        MAX_PHAR_SIZE: 2097152,
        MAX_ZIPBALL_SIZE: 10485760,
        MAX_RAW_VIRION_SIZE: 5242880,
        MAX_WEEKLY_BUILDS: 60,
        MAX_VERSION_LENGTH: 20,
        MAX_LICENSE_LENGTH: 51200,
        MIN_DESCRIPTION_LENGTH: 100,
        MIN_CHANGELOG_LENGTH: 10,
        MIN_PUBLIC_RELEASE_STATE: 3,
        MAX_KEYWORD_COUNT: 100,
        MAX_KEYWORD_LENGTH: 20,
        MIN_SHORT_DESC_LENGTH: 10,
        MAX_SHORT_DESC_LENGTH: 128,
        VOTED_THRESHOLD: 5,
        MAX_REVIEW_LENGTH: 512
    }
};

// submit
var submitData = {
    repoInfo: {
        id: 0,
        name: "",
        full_name: "",
        owner: {
            login: "",
            id: 0,
            avatar_url: "0",
            gravatar_id: "",
            type: "Organization",
            site_admin: false
        },
        private: false,
        description: "",
        fork: false,
        created_at: "1970-00-00T00:00:00Z",
        updated_at: "1970-00-00T00:00:00Z",
        pushed_at: "1970-00-00T00:00:00Z",
        homepage: "https://example.com",
        size: 0,
        stargazers_count: 0,
        watchers_count: 0,
        language: "PHP",
        has_issues: true,
        has_projects: true,
        has_downloads: true,
        has_wiki: true,
        has_pages: false,
        forks_count: 0,
        mirror_url: null,
        open_issues_count: 0,
        forks: 0,
        open_issues: 0,
        watchers: 0,
        default_branch: "",
        permissions: {
            admin: true,
            push: true,
            pull: true
        },
        allow_squash_merge: true,
        allow_merge_commit: true,
        allow_rebase_merge: true,
        organization: {
            login: "",
            id: 0,
            gravatar_id: "",
            type: "Organization",
            site_admin: false
        },
        network_count: 0,
        subscribers_count: 0
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
                depRelId: 0,
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
        official: {refDefault: false, srcDefault: null, remarks: ""},
        outdated: {refDefault: false, srcDefault: null, remarks: ""},
        majorCategory: {refDefault: 0, srcDefault: null, remarks: ""},
        minorCategories: {refDefault: [0], srcDefault: null, remarks: ""},
        keywords: {refDefault: [""], srcDefault: [""], remarks: ""},
        spoons: {refDefault: [["", ""]], srcDefault: [["", ""]], remarks: ""},
        deps: {
            refDefault: [{
                name: "", version: "", depRelId: 0, required: false
            }], srcDefault: [{
                name: "", version: "", depRelId: 0, required: false
            }], remarks: ""
        },
        perms: {refDefault: [0], srcDefault: null, remarks: ""},
        reqrs: {
            refDefault: [{
                type: 0, details: "", isRequire: false
            }], srcDefault: null, remarks: ""
        },
        authors: {refDefault: {2: {1: "mojombo"}}, srcDefault: [], remarks: ""},
        assocParent: {refDefault: {} /* nullable */, srcDefault: null, remarks: ""},
        assocChildren: {refDefault: null, srcDefault: null, remarks: ""}
    },
    consts: {
        categories: {0: ""},
        spoons: {
            "": {description: "", php: "", incompatible: false}
        },
        promotedSpoon: "",
        perms: {0: {name: "", description: ""}},
        reqrs: {0: {name: "", details: ""}},
        authors: {0: ""}
    },
    assocChildren: {
        0: {name: "", version: ""}
    },
    icon: {
        url: "",
        html: ""
    },
    last: {name: "", version: ""}, // or null
    submitFormToken: ""
};

// ProjectBuildPage
var projectData = {
    path: [
        "LegendOfMCPE",
        "WorldEditArt",
        "WorldEditArt-Epsilon"
    ],
    project: {
        repoId: 44738130,
        repoOwner: "LegendOfMCPE",
        repoName: "WorldEditArt",
        private: false,
        projectName: "WorldEditArt-Epsilon",
        projectType: 1,
        projectModel: "default",
        projectId: 724,
        projectPath: "WorldEditArt/",
        main: "LegendsOfMCPE\\WorldEditArt\\Epsilon\\WorldEditArt",
        buildId: 27675,
        internal: 37
    },
    subs: {
        "31364333": 1,
        "19623715": 2
    }
};

// ReleaseDetailsModule
var releaseDetails = {
    name: "Hormones",
    version: "2.0.2-beta",
    project: {
        repo: {
            owner: "LegendOfMCPE",
            name: "Hormones"
        },
        path: "Hormones/",
        name: "Hormones"
    },
    build: {
        buildId: 0,
        sha: "{sha}",
        tree: "tree/{sha}/"
    }
};

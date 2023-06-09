// global
declare const sessionData: {
    path: {
        relativeRoot: string
    }
    app: {
        clientId: string
    }
    session: {
        antiForge: string
        isLoggedIn: boolean
        loginName: string
        adminLevel: number
    }
    meta: {
        isDebug: boolean
    }
    opts: {
        makeTabs?: boolean
        usePages?: boolean
        showIcons?: boolean
        allowSu?: boolean
    }
};

declare const PoggitConsts: {
    AdminLevel: {
        GUEST: 0,
        MEMBER: 1,
        CONTRIBUTOR: 2,
        MODERATOR: 3,
        REVIEWER: 4,
        ADM: 5
    },
    Staff: {
        sof3: 5,
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
    },
    ReleaseState: {
        draft: 0,
        rejected: 1,
        submitted: 2,
        checked: 3,
        voted: 4,
        approved: 5,
        featured: 6
    }
};

// submit
declare const __submit_form_response: {
    action: "success"
    submitData: {
        repoInfo: {
            id: number
            name: string
            full_name: string
            owner: {
                login: string
                id: number
                avatar_url: string
                gravatar_id: string
                type: "User" | "Organization"
                site_admin: boolean
            }
            private: boolean
            description: string
            fork: boolean
            created_at: string
            updated_at: string
            pushed_at: string
            homepage: string
            size: number
            stargazers_count: number
            watchers_count: number
            language: string | "PHP"
            has_issues: boolean
            has_projects: boolean
            has_downloads: boolean
            has_wiki: boolean
            has_pages: boolean
            forks_count: number
            mirror_url: null | string
            open_issues_count: number
            forks: number
            open_issues: number
            watchers: number
            default_branch: string
            permissions: {
                admin: boolean
                push: boolean
                pull: boolean
            }
            allow_squash_merge: boolean
            allow_merge_commit: boolean
            allow_rebase_merge: boolean
            organization: {
                login: string
                id: number
                gravatar_id: string
                type: "Organization" | "User"
                site_admin: boolean
            }
            network_count: number
            subscribers_count: number
        }
        buildInfo: {
            repoId: number
            projectId: number
            buildId: number
            devBuildRsr: number
            internal: number
            projectName: string
            projectType: number
            path: string
            buildTime: number
            sha: string
            branch: string
            releaseId: number
            thisState: number
            lastReleaseId: number
            lastState: number
            lastVersion: string
        }
        args: [string, string, string, number]
        refRelease: {
            releaseId: number
            name: string
            shortDesc: string
            version: string
            state: number
            buildId: number
            flags: number
            description: number
            descType: "md" | "txt"
            descrMd: number
            changelog: number
            changelogType: "txt" | "md"
            chlogMd: number
            license: string
            licenseRes: number | null
            licMd: number | 0
            submitTime: number
            keywords: string[]
            perms: number[]
            mainCategory: number
            categories: number[]
            spoons: [number, number][]
            authors: { [level: number]: { [userId: number]: string } }
            deps: {
                name: string
                version: string
                depRelId: number
                isHard: boolean
            }[]
            requires: {
                type: number
                details: string
                isRequire: boolean
            }[]
        }
        mode: "submit" | "update" | "edit"
        fields: {
            name: __submit_form_SubmitEntry<string>
            shortDesc: __submit_form_SubmitEntry<string>
            version: __submit_form_SubmitEntry<string>
            description: __submit_form_SubmitEntry<{ type: string, text: string }, null>
            changelog?: __submit_form_SubmitEntry<{ type: string, text: string } | null>
            license: __submit_form_SubmitEntry<{ type: string, custom: string | null }>
            preRelease: __submit_form_SubmitEntry<boolean, null>
            official: __submit_form_SubmitEntry<boolean, null>
            outdated: __submit_form_SubmitEntry<boolean, null>
            majorCategory: __submit_form_SubmitEntry<number, null>
            minorCategories: __submit_form_SubmitEntry<number[], null>
            keywords: __submit_form_SubmitEntry<string[]>
            spoons: __submit_form_SubmitEntry<[string, string][]>
            deps: __submit_form_SubmitEntry<{ name: string, version: string, depRelId: number, required: boolean }>
            perms: __submit_form_SubmitEntry<number[], null>
            reqrs: __submit_form_SubmitEntry<{ type: number, details: string, isRequire: boolean }[], null>
            authors: __submit_form_SubmitEntry<{ [level: number]: { [userId: number]: string } }>
        }
        consts: {
            categories: { [category: number]: string }
            spoons: { [name: string]: { description: string, php: string, incompatible: boolean } }
            promotedSpoon: string
            perms: { [perm: number]: { name: string, description: string } }
            reqrs: { [reqr: number]: { name: string, details: string } }
            authors: { [level: number]: string }
        }
        icon: {
            url: string
            html: string
        }
        last: { name: string, version: string, sha: string, internal: number, buildId: number } | null
        submitFormToken: string
    }
    title: string
    actionTitle: string
    treeLink: string
};
declare type __submit_form_SubmitEntry<T, U = T> = { refDefault: T, srcDefault: U, remarks: string }

// ProjectBuildPage
declare const projectData: {
    path: [string, string, string]
    project: {
        repoId: number
        repoOwner: string
        repoName: string
        private: boolean
        projectName: string
        projectType: number
        projectModel: string
        projectId: number
        projectPath: string
        main: string
        buildId: number
        internal: number
    }
    release: object
    readPerm: boolean
    writePerm: boolean
    subs: { [userId: number]: number }
};

// ReleaseDetailsModule
declare const releaseDetails: {
    releaseId: number
    name: string
    version: string
    state: number
    created: number
    mainCategory: number
    project: {
        repo: {
            owner: string
            name: string
        },
        path: string
        name: string
    },
    build: {
        buildId: number
        internal: number
        sha: string
        tree: string
    },
    rejectPath: string,
    isMine: boolean
    myVote: number
    myVoteMessage: string
};

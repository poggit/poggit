declare interface FullRepoIdentifier{
	repoId: number
	repoOwner: string
	repoName: string
}

declare interface FullProjectIdentifier extends FullRepoIdentifier{
	projectId: string
	projectName: string
}

declare interface BriefProjectInfo extends FullProjectIdentifier{
}

declare interface FullBuildIdentifier extends FullProjectIdentifier{
	buildId: number
	buildClass: number
	buildNumber: string
}

declare interface BriefBuildInfo extends FullBuildIdentifier, BriefProjectInfo{
	branch: string
	sha: string
	main: string
	buildPath: string
}

declare interface FullReleaseIdentifier{
	releaseId: number
	name: string
	version: string
}

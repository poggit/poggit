import {gh} from "./index"

export namespace ghGraphql{
	export function repoData<RepoData>(token: string, repos: {owner: string, name: string}[], fields: string, handle: (r: (RepoData & {_repo: {owner: string, name: string}})[]) => void, onError: ErrorHandler){
		let query = "query("
		for(let i = 0; i < repos.length; ++i){
			query += `$o${i}: String! $n${i}: String! `
		}
		query += "){"
		for(let i = 0; i < repos.length; ++i){
			query += `r${i}: repository(owner: $o${i}, name: $n${i}){ ${fields} } `
		}
		query += "}"
		const vars = {} as StringMap<string>
		for(let i = 0; i < repos.length; ++i){
			vars[`o${i}`] = repos[i].owner
			vars[`n${i}`] = repos[i].name
		}
		gh.post(token, "graphql", {
			query: query,
			variables: vars,
		}, (result: {data: StringMap<RepoData>}) =>{
			const mapped = [] as (RepoData & {_repo: {owner: string, name: string}})[]
			for(const rid in result.data){
				const id = rid.substring(1)
				const repoArg = repos[Number(id)]
				const datum = result.data[rid] as any
				datum._repo = repoArg
				mapped.push(datum)
			}
			handle(mapped)
		}, onError)
	}
}

<?php

declare(strict_types=1);

const SELECT_BATCH_SIZE = 1000;
$INSERT_BATCH_SIZE = 500;

if(!isset($argv[8])){
	echo "Usage: port-old-poggit <old host> <old username> <old password> <old schema> <new host> <new username> <new password> <new schema>\n";
	exit(1);
}

function console(string $message){
	echo date("[H:i:s] "), $message, PHP_EOL;
}

$token = getenv("GITHUB_TOKEN");

mkdir("/gh_cache");

function toFileName(string $string){
	return str_replace(["=", "+", "/"], ["-", ".", "_"], base64_encode($string));
}

function github(string $url, array $headers = []){
	$file = "/gh_cache/" . toFileName($url);
	if(is_file($file)){
		return json_decode(file_get_contents($file));
	}

	global $token;
	if($token){
		$headers[] = "Authorization: bearer $token";
	}

	$ch = curl_init($url);
	/** @noinspection CurlSslServerSpoofingInspection */
	curl_setopt_array($ch, [
		CURLOPT_AUTOREFERER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_FORBID_REUSE => 1,
		CURLOPT_FRESH_CONNECT => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_HTTPHEADER => array_merge(["User-Agent: Poggit-Port/4.0"], $headers),
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_SSL_VERIFYPEER => false,
	]);
	console("curl: $url");
	$result = curl_exec($ch);
	file_put_contents($file, $result);
	$data = json_decode($result);
	if($error = curl_error($ch)){
		throw new RuntimeException("GitHub $url: $error");
	}
	curl_close($ch);
	return $data;
}

$src = new mysqli($argv[1], $argv[2], $argv[3], $argv[4]);
$dest = new mysqli($argv[5], $argv[6], $argv[7], $argv[8]);

function change(mysqli $db, string $query, array $args = []){
	$generator = query($db, $query, $args);
	$generator->rewind();
	$generator->current();
	assert(!$generator->valid());
}

function query(mysqli $db, string $query, array $args = []) : Generator{
	if($db->connect_error){
		throw new RuntimeException($db->connect_error);
	}
	$types = "";
	foreach($args as $i => &$arg){
		switch(gettype($arg)){
			case "boolean":
				$types .= "i";
				$arg = $arg ? 1 : 0;
				break;
			case "integer":
				$types .= "i";
				break;
			case "string":
				$types .= "s";
				break;
			case "double":
				$types .= "f";
				break;
			case "NULL":
				$types .= "s";
				break;
			default:
				throw new RuntimeException("Unexpected arg #$i " . json_encode($arg) . " in query $query ; " . json_encode($args));
		}
	}
	unset($arg);

	$stmt = $db->prepare($query);
	if(!$stmt){
		throw new RuntimeException("Error $db->error in query: $query ; " . json_encode($args));
	}
	if(!empty($args)){
		if(!$stmt->bind_param($types, ...$args)){
			throw new RuntimeException("Error $stmt->error in query: $query ; " . json_encode($args));
		}
	}
	if(!$stmt->execute()){
		throw new RuntimeException("Error $stmt->error in query: $query ; " . json_encode($args));
	}
	$result = $stmt->get_result();
	if($result instanceof mysqli_result){
		$rowId = 0;
		while($row = $result->fetch_assoc()){
			$rowId++;
			yield (object) $row;
			if($rowId % SELECT_BATCH_SIZE === 0){
				console("Fetched $rowId rows");
			}
		}
		if($rowId % SELECT_BATCH_SIZE !== 0){
			console("Fetched $rowId rows, done");
		}
	}
}

$insertParams = null;
$insertStack = [];
function insert(mysqli $db, string $table, array $columns, array $values){
	global $insertParams, $insertStack, $INSERT_BATCH_SIZE;
	if($insertParams === [$db, $table, $columns]){
		$insertStack[] = $values;
		if(count($insertStack) >= $INSERT_BATCH_SIZE){
			flushInsert();
		}
	}else{
		flushInsert();
		$insertParams = [$db, $table, $columns];
		$insertStack[] = $values;
	}
}

function flushInsert(){
	global $insertParams, $insertStack;
	if($insertParams === null || empty($insertStack)){
		return;
	}
	/** @var mysqli $db */
	[$db, $table, $columns] = $insertParams;
	$c = implode("`, `", $columns);
	$query = "INSERT INTO `$table` (`$c`) VALUES ";
	$rowSubst = "(" . implode(", ", array_fill(0, count($columns), "?")) . ")";
	$query .= implode(", ", array_fill(0, count($insertStack), $rowSubst));
	change($db, $query, array_merge(...$insertStack));
	$insertStack = [];
}

function toTimestamp(int $ts) : string{
	return date("Y-m-d H:i:s", $ts - date("Z"));
}

define("CURRENT_TIMESTAMP", toTimestamp(time()));

$userCache = [];
$userNameCache = [];

function resetTables(){
	global $dest;
	console("Resetting tables");
	change($dest, "DELETE FROM build");
	change($dest, "DELETE FROM resource");
	change($dest, "DELETE FROM project");
	change($dest, "DELETE FROM repo");
	change($dest, "DELETE FROM `user`");
}

function portUsers(){
	global $src, $dest, $userCache, $userNameCache;
	console("Porting users");
	foreach(query($src, "SELECT uid, name, email, opts FROM users") as $user){
		insert($dest, "user",
			["id", "name", "registered", "isOrg", "email", "firstLogin", "lastLogin"],
			[(int) $user->uid, $user->name, false, false, $user->email, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP]
		);
		$userCache[$user->name] = $user->uid;
		$userNameCache[$user->uid] = $user->name;
	}
}

function findUserId(string $name) : ?int{
	global $userCache, $dest, $userNameCache;
	if(!isset($userCache[$name])){
		$data = github("https://api.github.com/users/$name");
		if(!isset($data->id)){
			console("Warning: User $name not found");
			return null;
		}
		if($rename = isset($userNameCache[$data->id])){
			console("Warning: {$userNameCache[$data->id]} renamed to {$name}");
		}
		$userCache[$name] = $data->id;
		$userNameCache[$data->id] = $name;

		if(!$rename){
			insert($dest, "user",
				["id", "name", "registered", "isOrg", "email", "firstLogin", "lastLogin"],
				[$data->id, $name, false, $data->type === "Organization", $data->email ?? "", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP]);
		}
	}
	return $userCache[$name];
}

$repoOwnerCache = [];
$skippedRepos = [];

function portRepos(){
	global $src, $dest, $userCache, $userNameCache, $repoOwnerCache, $skippedRepos;
	console("Porting repos");
	foreach(query($src, "SELECT repoId, name, IF(private,1,0) private, IF(fork,1,0) fork, IF(build,1,0) build, owner FROM repos") as $repo){
		$userId = findUserId($repo->owner);
		$repoName = $repo->name;
		if($userId === null){
			$info = github("https://api.github.com/repositories/{$repo->repoId}");
			if(!isset($info->id)){
				console("Warning: Repo {$repo->owner}/{$repo->name}#{$repo->repoId} is not found");
				$skippedRepos[$repo->repoId] = true;
				continue;
			}
			console("Warning: Repo {$repo->owner}/{$repo->name} changed to {$info->owner->login}/{$info->name}");
			$repoName = $info->name;
			$userId = $info->owner->id;
			if(isset($userNameCache[$userId]) && $userNameCache[$userId] !== $info->owner->login){
				console("Warning: {$userNameCache[$userId]} renamed to {$info->owner->login}");
				change($dest, "UPDATE user SET name=? WHERE id=?", [$info->owner->login, $info->owner->id]);
				$userCache[$info->owner->login] = $userId;
				$userNameCache[$userId] = $info->owner->login;
			}else{
				console("Warning: Organization {$repo->owner} renamed to {$info->owner->login}");
				findUserId($info->owner->login);
			}
		}
		$repoOwnerCache[$repo->repoId] = $userId;
		insert($dest, "repo",
			["id", "name", "private", "fork", "enabled", "ownerId"],
			[(int) $repo->repoId, $repoName, $repo->private, $repo->fork, $repo->build, (int) $userId]);
	}
}

$skippedProjects = [];
$projectCache = [];

function portProjects(){
	global $src, $dest, $repoOwnerCache, $skippedRepos, $skippedProjects, $projectCache;
	console("Porting projects");
	foreach(query($src, "SELECT projectId, name, repoId FROM projects") as $project){
		if(isset($skippedRepos[$project->repoId])){
			$skippedProjects[$project->projectId] = true;
			continue;
		}
		$ownerId = $repoOwnerCache[$project->repoId];
		$projectCacheName = strtolower("$ownerId/{$project->name}");
		if(isset($projectCache[$projectCacheName])){
			console("Notice: #$projectCacheName has been duplicated, #{$projectCache[$projectCacheName]} is used, #{$project->projectId} is skipped.");
			$skippedProjects[$project->projectId] = true;
			continue;
		}
		$projectCache[$projectCacheName] = $project->projectId;
		insert($dest, "project",
			["id", "name", "ownerId", "repoId"],
			[(int) $project->projectId, $project->name, (int)
			$ownerId, (int) $project->repoId]);
	}
}

function portResources(){
	global $src, $dest, $repoOwnerCache;
	console("Porting resources");
	foreach(query($src, "SELECT resourceId, mimeType, created, UNIX_TIMESTAMP(created) + duration expiry, dlCount, src, fileSize, accessFilters FROM resources") as $resource){
		$repoId = null;
		$filters = json_decode($resource->accessFilters);
		if(count($filters) > 0){
			$repoId = $filters[0]->repo->id;
		}
		if(!isset($repoOwnerCache[$repoId])){
			$repoId = null;
		}
		$values = [
			"id" => $resource->resourceId,
			"mime" => $resource->mimeType,
			"created" => $resource->created,
			"expiry" => toTimestamp((int) $resource->expiry),
			"downloads" => $resource->dlCount,
			"source" => $resource->src ?? "unknown",
			"size" => $resource->fileSize,
			"requiredRepoViewId" => $repoId,
		];
		insert($dest, "resource", array_keys($values), array_values($values));
	}
}

$buildCache = [];

function portBuilds(){
	global $src, $dest, $skippedProjects, $userNameCache, $userCache, $buildCache;
	console("Porting builds");
	foreach(query($src, "SELECT buildId, cause, internal, created, branch, sha, path, projectId, resourceId, triggerUser FROM builds WHERE class IS NOT NULL") as $build){
		if(isset($skippedProjects[$build->projectId])){
			continue;
		}

		$cause = json_decode($build->cause ?? "null");
		$isPull = $cause !== null && $cause->name === "V2PullRequestBuildCause";
		$values = [
			"id" => $build->buildId,
			"cause" => $isPull ? "pr" : "dev",
			"number" => $build->internal,
			"created" => $build->created,
			"branch" => $build->branch,
			"sha" => $build->sha,
			"prHeadRepo" => null,
			"prNumber" => $isPull ? $cause->prNumber : null,
			"path" => $build->path,
			"log" => "",
			"projectId" => $build->projectId,
			"resourceId" => $build->resourceId !== 1 ? $build->resourceId : null,
			"triggerUserId" => $build->triggerUser,
		];

		if(!isset($userNameCache[$build->triggerUser])){
			$info = github("https://api.github.com/user/{$build->triggerUser}");
			if(!isset($info->login)){
				console("triggerUser for build {$build->buildId} is deleted");
				$values["triggerUserId"] = null;
			}else{
				console("Unregistered user triggered build: {$info->login}#{$build->triggerUser}");

				if(isset($userCache[$info->login])){
					$id2 = $userCache[$info->login];
					console("User #{$id2} has been renamed");
					$info2 = github("https://api.github.com/user/$id2");
					if(isset($info2->login)){
						change($dest, "UPDATE user SET name=? WHERE id=?", [$info2->login, $info2->id]);
						$userCache[$info2->login] = $info2->id;
						$userNameCache[$info2->id] = $info2->login;
					}else{
						console("The user has been deleted");
					}
				}

				$userNameCache[$build->triggerUser] = $info->login;
				$userCache[$info->login] = $build->triggerUser;
				insert($dest, "user",
					["id", "name", "registered", "isOrg", "email", "firstLogin", "lastLogin"],
					[$build->triggerUser, $info->login, false, $info->type === "Organization", $info->email ?? "", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP]);
			}
		}

		$buildCacheName = "{$build->projectId}:{$values["cause"]}:{$values["number"]}";
		if(isset($buildCache[$buildCacheName])){
			console("Notice: #$buildCacheName has been duplicated, #{$buildCache[$buildCacheName]} is used, #{$build->buildId} is skipped.");
			continue;
		}
		$buildCache[$buildCacheName] = $build->buildId;

		insert($dest, "build", array_keys($values), array_values($values));
	}
}

try{
	resetTables();

	portUsers();
	portRepos();
	portProjects();
	portResources();
	portBuilds();

	flushInsert();
}catch(RuntimeException $e){
	echo "Uncaught error: " . $e->getMessage() . "\n";
	echo $e->getTraceAsString();
}

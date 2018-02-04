const fs = require("fs");
const secrets = JSON.parse(fs.readFileSync(process.argv[2]).toString("utf8"));

let indents = 1;

function getValueType(value){
	const type = typeof value;
	if(type === "string" || type === "number" || type === "boolean"){
		return type;
	}
	if(Array.isArray(value)){
		if(value.length === 0){
			return "never[]";
		}
		++indents;
		const output = getValueType(value[0]);
		--indents;
		return output + "[]";
	}

	let numeric = true;
	let key;
	for(key in value){
		if(value.hasOwnProperty(key) && !/^[0-9]+$/.test(key)){
			numeric = false;
			break;
		}
	}
	if(key === undefined){
		return "{}";
	}
	if(numeric){
		let output = "NumericStringMap<";
		++indents;
		output += getValueType(value[key]);
		--indents;
		output += ">";
		return output;
	}

	let output = "{\n";
	for(let key in value){
		if(!value.hasOwnProperty(key)) continue;
		output += "\t".repeat(indents) + key + ": ";
		++indents;
		output += getValueType(value[key]);
		--indents;
		output += "\n";
	}
	output += "\t".repeat(indents - 1);
	output += "}";
	return output;
}

// language=TypeScript
const data = `import * as fs from "fs"
import * as path from "path"

interface NumericStringMap<T> extends StringMap<T>{
}

type ISecrets = ${getValueType(secrets)}

export const secrets: ISecrets = JSON.parse(fs.readFileSync(path.join(__dirname, "..", "secret", "secrets.json")).toString("utf8"))
`;
fs.writeFileSync(process.argv[3], data);

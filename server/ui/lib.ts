import * as date_format from "dateformat"

export function date(date: Date){
	return date_format(date, "mmm d yyyy");
}

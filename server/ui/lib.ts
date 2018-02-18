import * as date_format from "dateformat"

export function isoDate(date: Date): string{
	return date_format("isoDateTime")
}

export function date(date: Date):string{
	return `<span title="${date_format(date, "HH:MM:ss")}">${date_format(date, "mmm d yyyy")}</span>`
}

export function quantity(quantity: number, singular: string, plural: string = singular + "s"): string{
	return quantity === 0 ? `no ${plural}` : `${quantity} ${quantity > 1 ? plural : singular}`
}

export function average(array: number[], n: any = NaN): number | (typeof n){
	if(array.length === 0){
		return NaN
	}
	return array.reduce((a, b) => a + b, 0) / array.length
}

export {SECTION_CI, SECTION_RELEASE} from "./componentTerms"

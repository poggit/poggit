declare interface StringMap<T>{
	[index: string]: T
}

declare type TypeOrArray<T> = T | T[]

declare interface ObjectConstructor{
	assign<T, U, V, W>(t: T, u: U, v?: V, w?: W): T

	values(object: {}): any[]
}

declare interface Array<T>{
	fill(t: T, start?: number, end?: number): T[]
}

declare type BareFx = () => void

declare type ErrorHandler = (err: Error) => void

export namespace util{
	export function sizeOfObject(object: object): number{
		let i = 0
		for(const k in object){
			if(object.hasOwnProperty(k)){
				++i
			}
		}
		return i
	}

	export function flattenArray(arrays: any[][]){
		return ([] as any[]).concat(...arrays)
	}

	type SimplePromise = (complete: BareFx) => void

	/**
	 * Calls `eventually` when all functions in `forAll` have called their complete() function.
	 *
	 * @param {(SimplePromise} forAll An array of Promise-like functions, except there is only one
	 * @param {BareFx} eventually the final
	 */
	export function waitAll(forAll: SimplePromise[], eventually: BareFx): void{
		let left = forAll.length
		for(let i = 0; i < forAll.length; ++i){
			forAll[i](() =>{
				if(--left === 0){
					eventually()
				}
			})
		}
	}

}

export const nop: BareFx = () =>{
}

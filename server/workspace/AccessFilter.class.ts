import {MyRequest, MyResponse} from "../extensions"

export interface AccessFilter{
	allow(req: MyRequest, res: MyResponse, next: BareFx, error: ErrorHandler): void
}

import * as express from "express"
import {NextFunction, RequestHandler} from "express"
import {PageInfo} from "./ui/PageInfo.class"
import {ThumbnailRelease} from "./release/ThumbnailRelease.class"
import {jsQueue} from "./ui/jsQueue.class"
import {Session} from "./session/Session.class"

export interface MyRequest extends express.Request{
	session: Session
	realIp: string

	requireBoolean(name: string): boolean

	requireNumber(name: string): number

	requireString(name: string): string
}

export interface MyResponse extends express.Response{
	locals: ResponseLocal

	ajaxSuccess: (data?: any) => void
	ajaxError: (message: string) => void
}

export interface MyRequestHandler extends RequestHandler{
	(req: MyRequest, res: MyResponse, next: NextFunction): void
}

export interface ResponseLocal{
	pageInfo: PageInfo
	sessionData: SessionData
	js: jsQueue
	css: (file: string) => string

	index: {
		recentReleases: ThumbnailRelease[]
	}
}

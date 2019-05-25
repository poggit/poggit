/**
 * Copyright 2019 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

import {Express, Request, RequestHandler, Response} from "express"
import {Session} from "../session/Session"

export interface Page{
	register(app: Express, handler: RequestHandler): void

	visit(session: Session, req: Request): Promise<PageResult>
}

export interface PageResult{
	write(req: Request, res: Response): Promise<void>
}

// Poggit
// Copyright (C) Poggit
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affer General Public License
// along with this program. If not, see <https://www.gnu.org/licenses/>.

#[allow(unused_imports)]
use crate::prelude::*;

use common::web;
use rocket::http::ContentType as CT;
use rocket::response::content::*;

type Bin = &'static [u8];

#[get("/favicon.ico")]
pub fn favicon() -> Content<Bin> { Content(CT::PNG, web::FAVICON) }

#[get("/js")]
pub fn js() -> JavaScript<Bin> { JavaScript(web::JS) }

#[get("/css")]
pub fn css() -> Css<Bin> { Css(web::CSS) }

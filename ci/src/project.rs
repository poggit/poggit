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

use rocket::http::RawStr;
use rocket::request::FromParam;

#[derive(Serialize)]
struct Context {
    build: Option<BuildSerial>,
}

#[get("/<user>/<project>")]
pub fn project(backend: State<Backend>, user: String, project: String) -> Template {
    Template::render("project", Context { build: None })
}

#[derive(Serialize)]
pub enum BuildCategory { Dev, PullRequest }

#[derive(Serialize)]
pub struct BuildSerial {
    category: BuildCategory,
    serial: u32,
}

impl<'a> FromParam<'a> for BuildSerial {
    type Error = String;

    fn from_param(param: &'a RawStr) -> Result<Self, Self::Error> {
        let param = param.url_decode().map_err(|err| format!("{}", err))?;
        if let Some(offset) = param.find(':') {
            let category = if param[0..offset].eq_ignore_ascii_case("dev") {
                BuildCategory::Dev
            } else if param[0..offset].eq_ignore_ascii_case("pr") {
                BuildCategory::PullRequest
            }  else {
                return Err("Unknown build category".into());
            };
            let serial = param[offset+1..].parse::<u32>().map_err(|err| format!("{}", err))?;
            Ok(Self { category, serial })
        } else {
            let serial = param.parse::<u32>().map_err(|err| format!("{}", err))?;
            Ok(Self { category: BuildCategory::Dev, serial })
        }
    }
}

#[get("/<user>/<project>/<build>", rank = 8)]
pub fn build(backend: State<Backend>, user: String, project: String, build: BuildSerial) -> Template {
    Template::render("project", Context { build: Some(build) })
}

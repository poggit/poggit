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

use rocket::response::Redirect;

#[derive(Serialize)]
pub struct Context {
}

#[get("/<name>", rank = 4)]
pub fn latest(state: State<Backend>, name: String) -> Template {
    Template::render("detail", Context {
    })
}

#[get("/<name>/<version>", rank = 4)]
pub fn version(state: State<Backend>, name: String, version: String) -> Template {
    Template::render("detail", Context {
    })
}


// TODO implement routing by file extension
#[get("/<name>", rank = 1)]
pub fn latest_download(state: State<Backend>, name: String) -> Redirect { unimplemented!() }
#[get("/<name>", rank = 2)]
pub fn latest_download_md5(state: State<Backend>, name: String) -> Redirect { unimplemented!() }
#[get("/<name>", rank = 3)]
pub fn latest_download_sha1(state: State<Backend>, name: String) -> Redirect { unimplemented!() }

#[get("/<name>/<version>", rank = 1)]
pub fn version_download(state: State<Backend>, name: String, version: String) -> Redirect { unimplemented!() }
#[get("/<name>/<version>", rank = 2)]
pub fn version_download_md5(state: State<Backend>, name: String, version: String) -> Redirect { unimplemented!() }
#[get("/<name>/<version>", rank = 3)]
pub fn version_download_sha1(state: State<Backend>, name: String, version: String) -> Redirect { unimplemented!() }

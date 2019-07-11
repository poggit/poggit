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

#![feature(decl_macro, proc_macro_hygiene)]

#[allow(unused_imports)]
use crate::prelude::*;

use common::config::Config;
use rocket::routes;

mod detail;
mod index;
mod prelude;
mod resources;

fn main() {
    common::init();
    let server = rocket::ignite()
        .mount("/", routes![
               resources::getter, resources::get_sha1, resources::get_md5,
               index::index,
               detail::latest, detail::latest_download, detail::latest_download_md5, detail::latest_download_sha1,
               detail::version, detail::version_download, detail::version_download_md5, detail::version_download_sha1,
        ])
        .manage(Backend::new())
        .manage(Config::new());
    info!("Starting plugins server");
    let err = server.launch();
    panic!("{}", err);
}

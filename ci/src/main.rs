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

mod by_id;
mod prelude;
mod project;
mod recent;
mod shield;
mod user;

fn main() {
    common::init();
    let server = rocket::ignite()
        .mount("/", routes![
               recent::endpoint,
               user::endpoint,
               project::project, project::build,
               by_id::endpoint,
               shield::project, shield::branch,
        ])
        .attach(Template::fairing())
        .manage(Config::new());
    info!("Starting CI server");
    let err = server.launch();
    panic!("{:?}", err);
}

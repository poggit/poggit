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

#[macro_use] extern crate common;
#[macro_use] extern crate rocket;
extern crate rocket_contrib;
extern crate serde;
#[macro_use] extern crate serde_derive;

use common::config::Config;

fn main() {
    common::init();
    let server = rocket::ignite()
        .mount("/", routes![
        ])
        .manage(Config::new());
    info!("Starting plugins server");
    let err = server.launch();
    panic!("{}", err);
}

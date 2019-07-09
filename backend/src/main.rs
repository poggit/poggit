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
#[macro_use] extern crate diesel;
#[macro_use] extern crate diesel_derive_enum;
#[macro_use] extern crate juniper;
extern crate juniper_rocket;
#[macro_use] extern crate rocket;
extern crate rocket_contrib;
extern crate r2d2;
extern crate serde;
#[macro_use] extern crate serde_derive;

use common::config::Config;
use juniper::RootNode;

mod gql;
mod interface;
mod schema;

pub type Schema = RootNode<'static, gql::RootQuery, gql::Mutations>;

pub struct Context {
    pub pool: r2d2::Pool<diesel::r2d2::ConnectionManager<diesel::pg::PgConnection>>,
}

#[allow(non_camel_case_types)]
pub enum Account_type { Org, Guest, Beta, User }

fn main() {
    common::init();
    let config = Config::new();
    let p = &config.postgres;
    let pool = r2d2::Pool::new(diesel::r2d2::ConnectionManager::new(
            format!("postgres://{}:{}@{}/{}", p.user, p.password, p.host, p.db)))
        .expect("Database connection failed");
    let server = rocket::ignite()
        .mount("/", routes![
               interface::web,
               interface::api,
        ])
        .manage(config)
        .manage(Context { pool })
        .manage(Schema::new(gql::RootQuery, gql::Mutations));
    info!("Starting backend server");
    let err = server.launch();
    panic!("{}", err);
}

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
use juniper::RootNode;
use r2d2_postgres::PostgresConnectionManager;
use rocket::routes;

mod gql;
mod interface;
mod prelude;

pub type Schema = RootNode<'static, gql::RootQuery, gql::Mutations>;

pub struct BackendContext {
    pub pool: r2d2::Pool<PostgresConnectionManager<postgres::NoTls>>,
}

impl_sync!(BackendContext | backend_context_sync);

fn main() {
    common::init();
    let config = Config::new();

    let pool = r2d2::Pool::new(PostgresConnectionManager::new({
        let p = &config.postgres;
        let mut r = postgres::Config::new();
        r.user(&p.user);
        r.password(&p.password);
        r.dbname(&p.db);
        r.host(&p.host);
        r
    }, postgres::NoTls)).expect("Database connection failed");

    let context = BackendContext { pool };

    let server = rocket::ignite()
        .mount("/", routes![
               interface::web,
               interface::api,
        ])
        .manage(config)
        .manage(Schema::new(gql::RootQuery, gql::Mutations));
    info!("Starting backend server");
    let err = server.launch();
    panic!("{}", err);
}

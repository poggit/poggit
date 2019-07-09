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

extern crate config as config_crate;
extern crate dotenv;
extern crate env_logger;
#[macro_use] pub extern crate log;
extern crate serde;
#[macro_use] extern crate serde_derive;

pub use log::*;
pub use std::env;

pub mod config;

pub fn init() {
    if let Err(_) = env::var("IS_DOCKER") {
        info!("Loading .env");
        dotenv::dotenv().unwrap();
    }
    env_logger::init();
}

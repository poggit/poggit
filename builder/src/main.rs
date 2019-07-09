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

#![feature(decl_macro, proc_macro_hygiene, try_trait)]

#[macro_use] extern crate common;
extern crate hex;
#[macro_use] extern crate lazy_static;
extern crate ring;
#[macro_use] extern crate rocket;
extern crate serde;
#[macro_use] extern crate serde_derive;

use common::config::Config;
use ring::{digest, hmac};
use rocket::request::State;

mod payload;

#[post("/", data = "<payload>")]
fn endpoint(payload: payload::WebhookPayload) -> String {
    "Not implemented".into()
}

pub struct WebhookKey(hmac::VerificationKey);

impl WebhookKey {
    pub fn new(config: &Config) -> Self {
        Self(hmac::VerificationKey::new(&digest::SHA1, config.github.webhook.secret.as_bytes()))
    }
}

fn main() {
    common::init();
    let config = Config::new();
    let webhook_key = WebhookKey::new(&config);
    let server = rocket::ignite()
        .mount("/", routes![
               endpoint,
        ])
        .manage(config)
        .manage(webhook_key);
    info!("Starting builder server");
    let err = server.launch();
    panic!("{}", err);
}

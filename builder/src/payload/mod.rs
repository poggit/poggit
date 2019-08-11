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

use std::io::Read;

use ring::hmac;
use rocket::data::{Data, FromDataSimple, Outcome};
use rocket::http::Status;
use rocket::request::{Request, State};

pub mod push;

pub enum WebhookPayload {
    Push(push::Payload),
}

impl FromDataSimple for WebhookPayload {
    type Error = String;

    fn from_data(request: &Request, data: Data) -> Outcome<Self, String> {
        let sig = match request.headers().get_one("X-Hub-Signature") {
            Some(sig) => sig,
            None => {
                return Outcome::Failure((Status::BadRequest, "Missing X-Hub-Signature".into()));
            }
        };
        if &sig[0..5] != "sha1=" {
            return Outcome::Failure((Status::BadRequest, "Expected sha1 signature".into()));
        }
        let sig = match hex::decode(&sig[5..]) {
            Ok(vec) => vec,
            Err(_) => {
                return Outcome::Failure((Status::BadRequest, "Bad signature".into()));
            }
        };

        let mut buf = Vec::<u8>::new();
        if let Err(err) = data.open().take(1048576).read_to_end(&mut buf) {
            return Outcome::Failure((Status::InternalServerError, format!("{}", err)));
        }

        let key = request
            .guard::<State<crate::WebhookKey>>()
            .expect("WebhookKey not defined");
        if let Err(_) = hmac::verify(&key.0, &buf[..], &sig[..]) {
            return Outcome::Failure((Status::BadRequest, "Incorrect signature".into()));
        }

        let event = match request.headers().get_one("X-GitHub-Event") {
            Some(event) => event,
            None => {
                return Outcome::Failure((Status::BadRequest, "Missing X-GitHub-Event".into()));
            }
        };

        Outcome::Failure((Status::InternalServerError, "Not implemented yet".into()))
    }
}

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

use crate::BackendContext;
use super::account::*;

pub struct RootQuery {
    pub context: BackendContext,
}

#[juniper::object]
impl RootQuery {
    pub fn login_by_token(token: String) -> FieldResult<Option<Login>> {
        // TODO implement
        Ok(Some(Login {
            token: token,
            account: Account {
                id: ID::new("abc"),
                name: "SOFe".into(),
                acc_type: AccountType::User,
                email: Some("sofe2038@gmail.com".into()),
                first_login: Some(chrono::offset::Utc::now()),
                last_login: Some(chrono::offset::Utc::now()),
            },
            ip: "1.2.3.4".into(),
            target: "/".into(),
            request_time: chrono::offset::Utc::now(),
            success_time: chrono::offset::Utc::now(),
        }))
    }
}

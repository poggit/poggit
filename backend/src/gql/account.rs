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

#[derive(GraphQLObject)]
pub struct Account {
    pub id: ID,
    pub name: String,
    pub acc_type: AccountType,
    pub email: Option<String>,
    pub first_login: Option<Timestamp>,
    pub last_login: Option<Timestamp>,
}

#[derive(GraphQLEnum)]
pub enum AccountType {
    Org,
    Guest,
    Beta,
    User,
}

#[derive(GraphQLObject)]
pub struct Login {
    pub token: String,
    pub account: Account,
    pub ip: String,
    pub target: String,
    pub request_time: Timestamp,
    pub success_time: Timestamp,
}

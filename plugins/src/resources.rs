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

#[get("/resources/<id>")]
pub fn getter(id: u32) -> Vec<u8> {
    unimplemented!()
}

#[get("/resources/<id>/sha1")]
pub fn get_sha1(id: u32) -> String {
    unimplemented!()
}

#[get("/resource/<id>/md5")]
pub fn get_md5(id: u32) -> String {
    unimplemented!()
}

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

pub fn require_core_marker_send<T>(_: T)
where T: ::core::marker::Send {}

pub fn require_core_marker_sync<T>(_: T)
where T: ::core::marker::Send {}

#[macro_export]
macro_rules! impl_send {
    ($ty: ty | $name: ident) => {
        #[test]
        fn $name(x: $ty) { require_core_marker_send(x) }
    };
}
#[macro_export]
macro_rules! impl_sync {
    ($ty: ty | $name: ident) => {
        #[test]
        fn $name(x: $ty) { require_core_marker_sync(x) }
    };
}

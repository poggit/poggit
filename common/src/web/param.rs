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

use core::marker::PhantomData;
use core::ops::Deref;

use rocket::http::RawStr;
use rocket::request::FromParam;

pub trait Suffix {
    fn suffix() -> &'static str;
}

pub struct SuffixParam<T, U = String> where T: Suffix {
    inner: U,
    phantom: PhantomData<T>,
}

impl<'a, T, U> FromParam<'a> for SuffixParam<T, U>
where T: Suffix, U: for<'b> From<&'b str> {
    type Error = ();

    fn from_param(param: &'a RawStr) -> Result<Self, Self::Error> {
        let string = param.url_decode().map_err(|_| ())?;
        let suffix = <T as Suffix>::suffix();
        if string.ends_with(suffix) {
            let length = string.len() - suffix.len();
            Ok(Self {
                inner: U::from(&string[0..length]),
                phantom: PhantomData::default(),
            })
        } else {
            Err(())
        }
    }
}

impl<T, U> Deref for SuffixParam<T, U> where T: Suffix {
    type Target = U;

    fn deref(&self) -> &U { &self.inner }
}

#[macro_export]
macro_rules! define_suffix {
    ($suffix: literal as $name: ident) => {
        pub struct $name;

        impl $crate::web::param::Suffix for $name {
            fn suffix() -> &'static str { $suffix }
        }
    }
}

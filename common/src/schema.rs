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

#[cfg(feature = "back")]
mod back {
    #[allow(unused_imports)]
    use crate::prelude::*;

    juniper_from_schema::graphql_schema_from_file!("../gql/schema.graphql");
}
#[cfg(feature = "back")]
pub use back::*;


#[cfg(feature = "client")]
mod client {
    #[allow(unused_imports)]
    use crate::prelude::*;

    use graphql_client::*;

    include!(concat!(env!("OUT_DIR"), "/queries.rs"));
}
#[cfg(feature = "client")]
pub use client::*;

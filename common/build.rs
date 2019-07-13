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

use std::env;
use std::fs::File;
use std::io::Write;
use std::path::Path;

use walkdir::WalkDir;

fn main() {
    let out = env::var("OUT_DIR").unwrap();
    let mut f = File::create(Path::new(&out).join("queries.rs")).unwrap();
    for file in WalkDir::new("../gql/queries") {
        let file = file.unwrap();
        if !file.file_type().is_file() { continue; }

        let name = file.file_name().to_str().unwrap();
        if !name.ends_with(".graphql") { continue; }

        let path = file.path().to_str().unwrap();

        let code = format!("
            #[derive(GraphQLQuery)]
            #[graphql(
                query_path = \"{}\",
                schema_path = \"{}\")]
            struct {};",
            path, "../gql/schema.graphql", name);
        f.write_all(code.as_bytes()).unwrap();
    }
}

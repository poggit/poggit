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

use std::{env, process};

fn main() {
    println!("cargo:rerun-if-changed=.no-such-file");

    let out_dir = env::var("OUT_DIR").unwrap() + "/";

    let a = std::fs::File::create(out_dir.clone() + "help-me");
    drop(a);

    let status = process::Command::new("bash")
        .arg("build.sh")
        .env("OUT_DIR", out_dir)
        .current_dir(env::var("CARGO_MANIFEST_DIR").unwrap())
        .status()
        .unwrap();
    dbg!(status.code());
    process::exit(status.code().unwrap());
}

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

#![feature(decl_macro, proc_macro_hygiene)]

#[macro_use] extern crate common;
#[macro_use] extern crate rocket;
extern crate url;

use rocket::request::{FromQuery, Query};
pub use rocket::response::Redirect;

macro_rules! redir {
    ($name: ident: $path: literal -> $url: expr) => {
        #[get($path)]
        pub fn $name() -> crate::Redirect {
            crate::Redirect::permanent($url)
        }
    };
    ($name: ident: $path: literal with $($args: ident),+ -> $fmt: literal with $($out: expr),+) => {
        #[get($path)]
        pub fn $name($($args: String),+) -> crate::Redirect {
            crate::Redirect::permanent(format!($fmt, $({
                url::form_urlencoded::byte_serialize($out.as_bytes()).collect::<String>()
            }),+))
        }
    };
}

#[derive(Debug)]
pub struct AllParams(String);

impl<'f> FromQuery<'f> for AllParams {
    type Error = ();

    fn from_query(query: Query<'f>) -> Result<Self, ()> {
        Ok(Self(query.map(|item| format!("{}={}", item.key, item.value)).collect::<Vec<_>>().join("&")))
    }
}

redir!(index: "/" -> "https://plugins.pmmp.io");

mod ci {
    redir!(root: "/ci" -> "https://ci.pmmp.io");
    redir!(user: "/ci/<user>" with user -> "https://ci.pmmp.io/{}" with user);
    redir!(repo: "/ci/<user>/<_repo>" with user, _repo -> "https://ci.pmmp.io/{}" with user);
    redir!(project: "/ci/<user>/<_repo>/<project>" with user, _repo, project -> "https://ci.pmmp.io/{}/{}" with user, project);
    redir!(build: "/ci/<user>/<_repo>/<project>/<build>" with user, _repo, project, build -> "https://ci.pmmp.io/{}/{}/{}" with user, project, build);
    redir!(babs: "/babs/<id>" with id -> "https://ci.pmmp.io/build/{}" with id);
    redir!(badge_proj: "/ci.badge/<owner>/<_repo>/<project>" with owner, _repo, project -> "https://ci.pmmp.io/shield/{}/{}" with owner, project);
    redir!(badge_branch: "/ci.badge/<owner>/<_repo>/<project>/<branch>" with owner, _repo, project, branch -> "https://ci.pmmp.io/shield/{}/{}/{}" with owner, project, branch);
    redir!(shield_proj: "/ci.shield/<owner>/<_repo>/<project>" with owner, _repo, project -> "https://ci.pmmp.io/shield/{}/{}" with owner, project);
    redir!(shield_branch: "/ci.shield/<owner>/<_repo>/<project>/<branch>" with owner, _repo, project, branch -> "https://ci.pmmp.io/shield/{}/{}/{}" with owner, project, branch);
}

mod plugins {
    redir!(root: "/plugins" -> "https://plugins.pmmp.io");
    redir!(pi: "/pi" -> "https://plugins.pmmp.io");
    redir!(index: "/index" -> "https://plugins.pmmp.io");

    #[get("/releases.json?<ap..>")]
    pub fn rj(ap: crate::AllParams) -> crate::Redirect { crate::Redirect::permanent(format!("https://plugins.pmmp.io/api/all?{}", ap.0)) }
    #[get("/plugins.json?<ap..>")]
    pub fn pj(ap: crate::AllParams) -> crate::Redirect { crate::Redirect::permanent(format!("https://plugins.pmmp.io/api/all?{}", ap.0)) }
    #[get("/releases.list?<ap..>")]
    pub fn rl(ap: crate::AllParams) -> crate::Redirect { crate::Redirect::permanent(format!("https://plugins.pmmp.io/api/all?{}", ap.0)) }
    #[get("/plugins.list?<ap..>")]
    pub fn pl(ap: crate::AllParams) -> crate::Redirect { crate::Redirect::permanent(format!("https://plugins.pmmp.io/api/all?{}", ap.0)) }

    redir!(plugin: "/plugin/<name>" with name -> "https://plugins.pmmp.io/{}" with name);
    redir!(p: "/p/<name>" with name -> "https://plugins.pmmp.io/{}" with name);
    redir!(plugin_v: "/plugin/<name>/<version>" with name, version -> "https://plugins.pmmp.io/{}/{}" with name, version);
    redir!(p_v: "/p/<name>/<version>" with name, version -> "https://plugins.pmmp.io/{}/{}" with name, version);
    
    redir!(rid: "/rid/<id>" with id -> "https://plugins.pmmp.io/?id={}" with id);
    redir!(get: "/get/<name>" with name -> "https://plugins.pmmp.io/{}.phar" with name);
    redir!(get_version: "/get/<name>/<version>" with name, version -> "https://plugins.pmmp.io/{}/{}.phar" with name, version);
    redir!(md5: "/get.md5/<name>" with name -> "https://plugins.pmmp.io/{}.phar.md5" with name);
    redir!(md5_version: "/get.md5/<name>/<version>" with name, version -> "https://plugins.pmmp.io/{}/{}.phar.md5" with name, version);
    redir!(sha1: "/get.sha1/<name>" with name -> "https://plugins.pmmp.io/{}.phar.sha1" with name);
    redir!(sha1_version: "/get.sha1/<name>/<version>" with name, version -> "https://plugins.pmmp.io/{}/{}.phar.sha1" with name, version);

    pub mod shield {
        pub mod latest {
            redir!(dl: "/shield.dl/<name>" with name -> "https://plugins.pmmp.io/shield/dl/{}/latest" with name);
            redir!(download: "/shield.download/<name>" with name -> "https://plugins.pmmp.io/shield/dl/{}/latest" with name);
            redir!(downloads: "/shield.downloads/<name>" with name -> "https://plugins.pmmp.io/shield/dl/{}/latest" with name);
        }
        pub mod total{
            redir!(dl: "/shield.dl.total/<name>" with name -> "https://plugins.pmmp.io/shield/dl/{}/total" with name);
            redir!(download: "/shield.download.total/<name>" with name -> "https://plugins.pmmp.io/shield/dl/{}/total" with name);
            redir!(downloads: "/shield.downloads.total/<name>" with name -> "https://plugins.pmmp.io/shield/dl/{}/total" with name);
        }
    }
}

mod links {
    redir!(tos: "/tos" -> "https://poggit.github.io/support/tos");
    redir!(ghhst: "/ghhst" -> "https://help.github.com/articles/about-required-status-checks/");
    redir!(orgperms: "/orgperms" -> "https://github.com/settings/connections/applications/27a6a18555e95fce1a74");
    redir!(defavt: "/defavt" -> "https://assets-cdn.github.com/images/gravatars/gravatar-user-420.png");
    redir!(std: "/std" -> "https://github.com/poggit/support/blob/master/pqrs.md");
    redir!(pqrs: "/pqrs" -> "https://github.com/poggit/support/blob/master/pqrs.md");
    redir!(virion: "/virion" -> "https://github.com/poggit/support/blob/master/virion.md");
    redir!(help_api: "/help.api" -> "https://github.com/poggit/support/blob/master/api.md");
    redir!(gh_topics: "/gh.topics" -> "https://github.com/blog/2309-introducing-topics");
    redir!(gh_pmmp: "/gh.pmmp" -> "https://github.com/pmmp/PocketMine-MP");
    redir!(faq: "/faq" -> "https://poggit.github.io/support/faq");
    redir!(submit_rules: "/submit.rules" -> "https://poggit.pmmp.io/rules.edit");
}

fn main() {
    common::init();
    info!("Starting redirect server");
    let err = rocket::ignite()
        .mount("/", routes![
               index,
               ci::root, ci::user, ci::repo, ci::project, ci::build,
               ci::babs,
               ci::badge_proj, ci::badge_branch, ci::shield_proj, ci::shield_branch,
               plugins::root, plugins::pi, plugins::index,
               plugins::rj, plugins::pj, plugins::rl, plugins::pl,
               plugins::plugin, plugins::p,
               plugins::plugin_v, plugins::p_v,
               plugins::rid,
               plugins::get, plugins::get_version, plugins::md5, plugins::md5_version, plugins::sha1, plugins::sha1_version,
               plugins::shield::latest::dl, plugins::shield::latest::download, plugins::shield::latest::downloads,
               plugins::shield::total::dl, plugins::shield::total::download, plugins::shield::total::downloads,
               links::tos, links::ghhst, links::orgperms, links::defavt, links::std, links::pqrs, links::virion,
               links::help_api, links::gh_topics, links::gh_pmmp, links::faq, links::submit_rules,
        ])
        .launch();
    panic!("{}", err);
}

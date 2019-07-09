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

#[derive(Deserialize, Debug)]
pub struct Config {
    pub postgres: PostgresConfig,
    pub github: GithubConfig,
}

#[derive(Deserialize, Debug)]
pub struct PostgresConfig {
    pub host: String,
    pub user: String,
    pub password: String,
    pub db: String,
}

#[derive(Deserialize, Debug)]
pub struct GithubConfig {
    pub app: GithubAppConfig,
    pub webhook: GithubWebhookConfig,
}

#[derive(Deserialize, Debug)]
pub struct GithubAppConfig {
    pub id: u32,
    pub slug: String,
    pub client: String,
    pub secret: String,
}

#[derive(Deserialize, Debug)]
pub struct GithubWebhookConfig {
    pub secret: String,
}

impl Config {
    pub fn new() -> Self {
        let mut merger = config_crate::Config::new();
        merger.merge(config_crate::Environment::new().separator("_")).expect("config error");
        merger.try_into().expect("Config error")
    }
}

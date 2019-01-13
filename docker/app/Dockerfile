# Poggit-Delta
#
# Copyright (C) 2018-2019 Poggit
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published
# by the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.

FROM node:8

USER root
RUN apt-get update && apt-get install make wget openjdk-8-jre -y

EXPOSE 3000

WORKDIR /home/node
RUN npm install -g typescript ts-node

RUN wget https://dl.google.com/closure-compiler/compiler-latest.tar.gz
RUN tar xzf compiler-latest.tar.gz
RUN mv closure-compiler-*.jar closure-compiler.jar

RUN mkdir /home/node/ex/server -p
WORKDIR /home/node/ex
RUN mkdir node_modules

ADD run.sh run.sh
RUN chmod +x run.sh

CMD ./run.sh

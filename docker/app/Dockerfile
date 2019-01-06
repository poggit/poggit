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
USER node

EXPOSE 3000

WORKDIR /home/node
RUN echo "prefix=/home/node/.npm-packages" > /home/node/.npmrc
RUN mkdir /home/node/.npm-packages
RUN NPM_PACKAGES=/home/node/.npm-packages npm install -g typescript ts-node

RUN mkdir /home/node/ex/server -p
WORKDIR /home/node/ex
RUN mkdir node_modules
ADD run.sh run.sh

USER root
RUN chmod +x run.sh
USER node

CMD ./run.sh

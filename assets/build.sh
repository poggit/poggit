#!/bin/bash

set -x

cd "$(dirname "$0")"

function require-cmd {
	if ! command -v "$1" >/dev/null; then
		echo "Required command \"$1\" is missing or could not be installed."
		exit 1
	fi
}

function install-cmd {
	if ! command -v "$1" >/dev/null; then
		echo "Installing \"$1\"..."
		bash -c "$2"
		require-cmd "$1"
	fi
}

function make-tree {
	ROOT="$1"
	EXT="$2"
	TARGET="$3"
	[ -f .return-zero ] && rm .return-zero
	find "$ROOT" -type f -name "*.$EXT" | \
		while read -r FILE; do
			if [ "$TARGET" -ot "$FILE" ]; then
				touch .return-zero
				break
			fi
		done
	[ -f .return-zero ]
	RETURN_CODE="$?"
	[ $RETURN_ZERO ] && rm .return-zero
	return $RETURN_CODE
}

if [ -z "$OUT_DIR" ]; then
	echo "OUT_DIR environment variable not present."
	exit 1
fi

export NPM_PREFIX="$(pwd)/.npm-deps"
[ -d .npm-deps ] || mkdir $NPM_PREFIX
export PATH="$PATH:$NPM_PREFIX/bin"

require-cmd npm
install-cmd sass "npm install --prefix $NPM_PREFIX -g sass"
install-cmd tsc "npm install --prefix $NPM_PREFIX -g typescript"
install-cmd browserify "npm install --prefix $NPM_PREFIX -g browserify"
install-cmd uglifyjs "npm install --prefix $NPM_PREFIX -g uglify-js"

pushd css >/dev/null
if ! [ -d materialize-src ]; then
	wget -O materialize.zip https://github.com/Dogfalo/materialize/releases/download/1.0.0/materialize-src-v1.0.0.zip
	unzip materialize.zip
	rm materialize.zip
fi

make-tree src sass "$OUT_DIR"/output.css && \
	sass src/main.sass "$OUT_DIR"/output.css --style=compressed

[ -f "$OUT_DIR"/output.css ] || exit 1

[ -f .return-zero ] && rm .return-zero
popd >/dev/null

pushd js >/dev/null
if [ .npm-install-time -ot package.json ]; then
	npm install
	touch .npm-install-time
fi

make-tree src ts "$OUT_DIR"/output.js && \
	([ ! -d tsc ] || mkdir tsc) && \
	tsc --outDir tsc && \
	browserify tsc/main.js > "$OUT_DIR"/output.fat.js && \
	uglifyjs --compress --mangle -o "$OUT_DIR"/output.js -- "$OUT_DIR"/output.fat.js

[ -f "$OUT_DIR"/output.js ] || exit 1

[ -f .return-zero ] && rm .return-zero
popd >/dev/null

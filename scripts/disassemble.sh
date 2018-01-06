#!/bin/bash
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

wasmfile=$1
wastfile=${wasmfile%.*}.wast

# $JSSHELL -e "putstr(wasmBinaryToText(read('$wasmfile', 'binary')));" >$wastfile
$BYNARYEN_ROOT/wasm-dis "$wasmfile" -o "$wastfile"

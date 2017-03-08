#!/bin/bash
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

wastfile=$1
wasmfile=${wastfile%.*}.wasm

# $BYNARYEN_ROOT/wasm-as "$wastfile" -o "$wasmfile"
$JSSHELL -e "os.file.writeTypedArrayToFile('$wasmfile', wasmTextToBinary(read('$wastfile')));"

#!/bin/bash
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

wastfile=$1
wasmfile=${wastfile%.*}.wasm

grep "(call_indirect (type " $wastfile
if [ $? -eq 0 ]
then
  $BYNARYEN_ROOT/wasm-as "$wastfile" -o "$wasmfile"
else
  $JSSHELL -e "os.file.writeTypedArrayToFile('$wasmfile', wasmTextToBinary(read('$wastfile')));"
fi

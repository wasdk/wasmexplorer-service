#!/bin/bash
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

cfile=$1
options=$2
approot=".."
bcfile=${cfile%.*}.bc
sfile=${cfile%.*}.s
wastfile=${cfile%.*}.wast

incroot="$approot/misc/include"
includes="-nostdinc -I$incroot/compat -I$incroot/libcxx -I$incroot/libc -U__APPLE__ -D__EMSCRIPTEN__"

$LLVM_ROOT/clang -emit-llvm $coptions $includes --target=wasm32 $options "$cfile" -c -o "$bcfile"
if [ $? != 0 ];then
  exit 1
fi

$LLVM_ROOT/llc -asm-verbose=false -o "$sfile~" "$bcfile"
if [ $? != 0 ];then
  exit 2
fi

$JSSHELL "$approot/scripts/fix-sfile.js" "$sfile~" > "$sfile"
rm "$sfile~"

$BYNARYEN_ROOT/s2wasm "$sfile" > "$wastfile"
if [ $? != 0 ];then
  exit 3
fi

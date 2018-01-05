#!/bin/bash
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

cfile=$1
options=$2
approot=".."
wasmfile=${cfile%.*}.wasm
wastfile=${cfile%.*}.wast

sysroot="$approot/misc/sysroot"
clang_flags="--target=wasm32-unknown-unknown-wasm --sysroot=$sysroot -nostartfiles -O3 $sysroot/lib/wasmception.wasm -D__WASM32__ -Wl,--allow-undefined -Wl,--strip-debug"

clang=$LLVM_WASM_ROOT/clang
if [[ $cfile == *".cpp" ]]; then
  clang=$LLVM_WASM_ROOT/clang++
fi

$clang $clang_flags $options "$cfile" -o "$wasmfile"
if [ $? != 0 ];then
  exit 1
fi

$BYNARYEN_ROOT/wasm-dis "$wasmfile" > "$wastfile"
if [ $? != 0 ];then
  exit 2
fi

#/bin/bash
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

cfile=$1
options=$2
bcfile=${cfile%.*}.bc
sfile=${cfile%.*}.s
x86file=${cfile%.*}.x86
binfile=${cfile%.*}.bin
wastfile=${cfile%.*}.wast


$LLVM_ROOT/clang $coptions $options "$cfile" -c -S -mllvm --x86-asm-syntax=intel -o "$x86file"
if [ $? != 0 ];then
  exit 1
fi

$LLVM_ROOT/clang -emit-llvm $coptions --target=wasm32 $options "$cfile" -c -o "$bcfile"
if [ $? != 0 ];then
  exit 1
fi

$LLVM_ROOT/llc -asm-verbose=false -o "$sfile" "$bcfile"
if [ $? != 0 ];then
  exit 2
fi

$BYNARYEN_ROOT/s2wasm "$sfile" > "$wastfile"
if [ $? != 0 ];then
  exit 3
fi

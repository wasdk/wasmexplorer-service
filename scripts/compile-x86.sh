#!/bin/bash
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

cfile=$1
options=$2
x86file=${cfile%.*}.x86

$LLVM_ROOT/clang $coptions $options "$cfile" -c -S -mllvm --x86-asm-syntax=intel -o "$x86file"
if [ $? != 0 ];then
  exit 1
fi

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

var wast = read(scriptArgs[0]);
var wasm = wasmTextToBinary(wast);
var module = new WebAssembly.Module(wasm);
var instance = new WebAssembly.Instance(module, {}); 
var exports = instance.exports;
putstr(exports.main());
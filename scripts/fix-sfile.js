/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

var content = read(scriptArgs[0]); 
var re = /(\t\.import_global[^\n]*)\n\t\.size[^\n]*/g;
putstr(content.replace(re, "$1"));
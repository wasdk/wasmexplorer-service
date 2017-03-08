/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

(function (exports) {
  var serviceUrl = new URL("./service.php", document.currentScript.src);
  function wast2wasm(wast) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open("POST", serviceUrl, true);
      xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.onload = function () {
        var response = xhr.responseText;
        if (!response.startsWith("-----WASM binary data")) {
          reject(new Error(response));
          return;
        }
        var buf = atob(response.split('\n', 2)[1]);
        var data = new Uint8Array(buf.length);
        for (var i = 0; i < buf.length; i++)
          data[i] = buf.charCodeAt(i);
        resolve(data);
      };
      xhr.onerror = function () {
        reject(xhr.error);
      };
      xhr.send("action=wast2wasm&input=" +
              encodeURIComponent(wast).replace('%20', '+'));
    });
  }

  function cpp2wasm(code, isCpp) {
    isCpp = typeof isCpp === 'undefined' ? true : isCpp;
    return new Promise(function (resolve, reject) {
    var xhr = new XMLHttpRequest();
      xhr.open("POST", serviceUrl, true);
      xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.onload = function () {
        var response = xhr.responseText;
        wast2wasm(response).then(resolve, reject);
      };
      xhr.onerror = function () {
        reject(xhr.error);
      };
      var action = isCpp ? "cpp2wast" : "c2wasm";
      var options = "-O3";
      xhr.send("action=" + action + 
               "&options=" + encodeURIComponent(options).replace('%20', '+') +
               "&input=" + encodeURIComponent(code).replace('%20', '+'));
    });
  }

  exports.wast2wasm = wast2wasm;
  exports.cpp2wasm = cpp2wasm;
})(this);

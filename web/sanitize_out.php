<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

 // Cleaning shell output from sensitive information.
$sanitize_shell_output = function ($s)
    use ($upload_folder_path, $jsshell_path, $llvm_root,
        $binaryen_root, $other_sensitive_paths)
{
  $sensitive_strings = array_merge(array(
    $upload_folder_path, $jsshell_path, $llvm_root, $llvm_wasm_root, $binaryen_root, getcwd()),
    $other_sensitive_paths);
  $out = $s;
  foreach ($sensitive_strings as $i) {
    $out = str_replace($i, "...", $out);
  }
  return $out;
};

?>
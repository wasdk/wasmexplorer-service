<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

include 'config.php';

$clang_path = $llvm_root . '/clang';
//$binaryen_repo_path = $binaryen_root . '/..';

$js_output = shell_exec($jsshell_path . ' --version');
$clang_output = shell_exec($clang_path . ' --version');
//$binaryen_git_output = shell_exec('cd ' . $binaryen_repo_path . ' && ' .
//                                  'git log --format="%h" -n 1');

$js_version = trim($js_output);
$clang_output_lines = explode("\n", $clang_output);
$clang_version = str_replace("clang version ", "", $clang_output_lines[0]);
$binaryen_version = 'n/a'; // trim($binaryen_git_output);

header('Content-Type: application/json');

echo <<<JSON
{
  "version": "$service_version",
  "js": "$js_version",
  "clang": "$clang_version",
  "binaryen": "$binaryen_version"
}
JSON;

?>

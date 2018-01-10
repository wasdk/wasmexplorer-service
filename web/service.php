<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Ignore HTTP OPTIONS request -- it's probably CORS.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  exit;
}

include 'config.php';
include 'sanitize_out.php';
include 'build.php';

// External scripts paths.
$scripts_path = $app_root_dir . 'scripts/';
$c_compiler_path = $scripts_path . 'compile.sh';
$c_compiler_v2_path = $scripts_path . 'compile2.sh';
$c_x86_compiler_path = $scripts_path . 'compile-x86.sh';
$translate_script_path = $scripts_path . 'translate.sh';
$disassemble_script_path = $scripts_path . 'disassemble.sh';
$get_wasm_jit_script_path = $scripts_path . 'get_wasm_jit.js';
$run_wasm_script_path = $scripts_path . 'run.js';
$clean_wast_script_path = $scripts_path . 'clean_wast.js';
$timeout_command = 'timeout 10s time';

$input = $_POST["input"];
$action = $_POST["action"];
$compiler_version = $_POST["version"];
$options = isset($_POST["options"]) ? $_POST["options"] : '';

// The temp file name is calculated based on MD5 of the input values.
$input_md5 = md5($input . $options . $action);
$result_file_base = $upload_folder_path . $input_md5;

$cleanup = function () use ($result_file_base) {
  foreach(glob($result_file_base . '*') as $f) {
    unlink($f);
  }
};

if ($action == 'build') {
  // Input: JSON in the following format
  // {
  //     output: "wasm",
  //     files: [
  //         {
  //             type: "cpp",
  //             name: "file.cpp",
  //             options: "-O3 -std=c++98",
  //             src: "puts(\"hi\")"
  //         }
  //     ],
  //     link_options: "--import-memory"
  // }
  // Output: JSON in the following format
  // {
  //     success: true,
  //     message: "Success",
  //     output: "AGFzbQE.... =",
  //     tasks: [
  //         {
  //             name: "building file.cpp",
  //             file: "file.cpp",
  //             success: true,
  //             console: ""
  //         },
  //         {
  //             name: "linking wasm",
  //             success: true,
  //             console: ""
  //         }
  //     ]
  // }
  build_project($input, $result_file_base);
  exit;
}

if ((strpos($action, "cpp2") === 0) or (strpos($action, "c2") === 0)) {
  // The $action has the format (c|cpp)2(wast|x86|run).
  // The query string has format: action=c2...&input=puts("hi")&options=-O3+-std=89
  // Output: wast or error message.
  $fileExt = '.cpp';
  if (strpos($action, "c2") === 0) {
    $fileExt = '.c';
  }
  $fileName = $result_file_base . $fileExt;
  file_put_contents($fileName, $input);

  $available_options = array(
    '-O0', '-O1', '-O2', '-O3', '-O4', '-Os', '-fno-exceptions', '-fno-rtti',
    '-ffast-math', '-fno-inline', '-std=c99', '-std=c89', '-std=c++14',
    '-std=c++1z', '-std=c++11', '-std=c++98');
  $safe_options = '-fno-verbose-asm';
  foreach ($available_options as $o) {
    if (strpos($options, $o) !== false) {
      $safe_options .= ' ' . $o;
    } else if ((strpos($o, '-std=') !== false) and
               (strpos(strtolower($options), $o) !== false)) {
      $safe_options .= ' ' . $o;
    }
  }

  if (strpos($action, "2x86")) {
    $x86FileName = $result_file_base . '.x86';
    $output = shell_exec($c_x86_compiler_path . ' ' .
                         $fileName . ' "' . $safe_options . '"' . ' 2>&1');
    if (!file_exists($x86FileName)) {
      echo $sanitize_shell_output($output);
    } else {
      echo $sanitize_shell_output(file_get_contents($x86FileName));
    }
    $cleanup();
    exit;
  }

  // Compiling C/C++ code to get WAST.
  $selected_c_compiler_path = $compiler_version == "2" ? $c_compiler_v2_path : $c_compiler_path;
  $output = shell_exec($selected_c_compiler_path . ' ' .
                       $fileName . ' "' . $safe_options . '"' . ' 2>&1');
  $wastFileName = $result_file_base . '.wast';
  if (!file_exists($wastFileName)) {
    echo $sanitize_shell_output($output);
    $cleanup();
    exit;
  }

  if (strpos($action, "2wast")) {
    if (strpos($options, "--clean") !== false) {
      echo $sanitize_shell_output(
        shell_exec($jsshell_path . ' ' .
                   $clean_wast_script_path . ' ' . $wastFileName));
    } else {
      echo file_get_contents($wastFileName);
    }
  } else if (strpos($action, "2run")) {
    echo $sanitize_shell_output(
      shell_exec($timeout_command . ' ' . $jsshell_path . ' ' .
                 $run_wasm_script_path. ' ' . $wastFileName . ' 2>&1'));
  }
  $cleanup();
  exit;
}

if ($action == "wasm2wast") {
  // Converts binary to text format (wasm2wast)
  // The query string has format: action=wasm2wast&input=AGFzbQE...
  // Output: wast or error message.
  $wastFileName = $result_file_base . '.wast';
  $wasmFileName = $result_file_base . '.wasm';
  file_put_contents($wasmFileName, base64_decode($input));
  $output = shell_exec($disassemble_script_path . ' ' . $wasmFileName . ' 2>&1');
  if (!file_exists($wastFileName)) {
    echo $sanitize_shell_output($output);
    $cleanup();
    exit;
  }
  if (strpos($options, "--clean") !== false) {
    echo $sanitize_shell_output(
      shell_exec($jsshell_path . ' ' .
                 $clean_wast_script_path . ' ' . $wastFileName));
  } else {
    echo file_get_contents($wastFileName);
  }
  $cleanup();
  exit;
}

if ($action == "wast2assembly" || $action == "wasm2assembly") {
  // The query string has format: action=wast2assembly&input=(module...)
  //                          or: action=wasm2assembly&input=AGFzbQEAAAA....
  // Output: JSON in the following format
  // {
  //      "regions":[
  //           {
  //               "name": "wasm-function[0]",
  //               "entry": 0,
  //               "index": 0,
  //               "bytes": "SIPsCLg1AAAAZpBIg8QIww=="
  //           }
  //      ],
  //      "wasm":"AGFzbQEAAAA...."
  // }
  if ($action == "wast2assembly") {
    $fileName = $result_file_base . '.wast';
    file_put_contents($fileName, $input);
  } else {
    $fileName = $result_file_base . '.wasm';
    file_put_contents($fileName, base64_decode($input));
  }
  $jit_options = '';
  if (strpos($options, '--wasm-always-baseline') !== false) {
    $jit_options = ' --wasm-always-baseline';
  }
  $output = shell_exec($jsshell_path . $jit_options . ' ' .
                       $get_wasm_jit_script_path . ' ' . $fileName);
  echo $sanitize_shell_output($output);
  $cleanup();
  exit;
}

if ($action == "wast2wasm") {
  // Converts binary to text format (wasm2wast)
  // The query string has format: action=wast2wasw&input=(module...)
  // Output: wasm base64 or error message.
  $fileName = $result_file_base . '.wast';
  file_put_contents($fileName, $input);
  $output = shell_exec($translate_script_path . ' ' . $fileName . ' 2>&1');
  $fileName = $result_file_base . '.wasm';
  if (!file_exists($fileName)) {
    echo $sanitize_shell_output($output);
    $cleanup();
    exit;
  }
  echo "-----WASM binary data\n";
  $wasm = file_get_contents($fileName);
  echo base64_encode($wasm);
  $cleanup();
  exit;
}

$cleanup();
?>

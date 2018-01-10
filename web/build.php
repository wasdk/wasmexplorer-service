<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

function get_clang_options($options) {
  global $app_root_dir;
  $sysroot = $app_root_dir . 'misc/sysroot';
  $clang_flags = "--target=wasm32-unknown-unknown-wasm --sysroot=$sysroot -fdiagnostics-print-source-range-info";

  if (is_null($options)) {
    return $clang_flags;
  }

  $available_options = array(
    '-O0', '-O1', '-O2', '-O3', '-O4', '-Os', '-fno-exceptions', '-fno-rtti',
    '-ffast-math', '-fno-inline', '-std=c99', '-std=c89', '-std=c++14',
    '-std=c++1z', '-std=c++11', '-std=c++98', '-g');
  $safe_options = '-c';
  foreach ($available_options as $o) {
    if (strpos($options, $o) !== false) {
      $safe_options .= ' ' . $o;
    } else if ((strpos($o, '-std=') !== false) and
                (strpos(strtolower($options), $o) !== false)) {
      $safe_options .= ' ' . $o;
    }
  }
  return $clang_flags . ' ' . $safe_options;
}

function get_lld_options($options) {
  global $app_root_dir;
  $sysroot = $app_root_dir . 'misc/sysroot';
  $clang_flags = "--target=wasm32-unknown-unknown-wasm --sysroot=$sysroot -nostartfiles $sysroot/lib/wasmception.wasm -D__WASM32__ -Wl,--allow-undefined -Wl,--strip-debug";

  if (is_null($options)) {
    return $clang_flags;
  }

  $available_options = array('--import-memory', '-g');
  $safe_options = '';
  foreach ($available_options as $o) {
    if (strpos($options, $o) !== false) {
      $safe_options .= ' -Wl,' . $o;
    }
  }
  return $clang_flags . $safe_options;
}

function build_c_file($input, $options, $output, $result_obj) {
  global $llvm_wasm_root, $sanitize_shell_output;
  $cmd = $llvm_wasm_root . '/clang ' . get_clang_options($options) . ' ' . $input . ' -o ' . $output;
  $out = shell_exec($cmd . ' 2>&1');
  $result_obj->{'console'} = $sanitize_shell_output($out);
  if (!file_exists($output)) {
    $result_obj->{'success'} = false;
    return false;
  }
  $result_obj->{'success'} = true;
  $result_obj->{'output'} = base64_encode(file_get_contents($output));
  return true;
}

function build_cpp_file($input, $options, $output, $result_obj) {
  global $llvm_wasm_root, $sanitize_shell_output;
  $cmd = $llvm_wasm_root . '/clang++ ' . get_clang_options($options) . ' ' . $input . ' -o ' . $output;
  $out = shell_exec($cmd . ' 2>&1');
  $result_obj->{'console'} = $sanitize_shell_output($out);
  if (!file_exists($output)) {
    $result_obj->{'success'} = false;
    return false;
  }
  $result_obj->{'success'} = true;
  $result_obj->{'output'} = base64_encode(file_get_contents($output));
  return true;
}

function validate_filename($name) {
  if (!preg_match("/^[0-9a-zA-Z\-_.]+(\/[0-9a-zA-Z\-_.]+)*$/", $name)) {
    return false;
  }
  $parts = preg_split("/\//", $name);
  foreach($parts as $p) {
    if ($p == '.' || $p == '..') {
      return false;
    }
  }
  return true;
}

function link_obj_files($obj_files, $options, $has_cpp, $output, $result_obj) {
  global $llvm_wasm_root, $sanitize_shell_output;
  $files = join(' ', $obj_files);

  if ($has_cpp) {
    $clang = $llvm_wasm_root . '/clang++';
  } else {
    $clang = $llvm_wasm_root . '/clang';    
  }
  $cmd = $clang . ' ' . get_lld_options($options) . ' ' . $files . ' -o ' . $output;
  $out = shell_exec($cmd . ' 2>&1');
  $result_obj->{'console'} = $sanitize_shell_output($out);
  if (!file_exists($output)) {
    $result_obj->{'success'} = false;
    return false;
  }
  $result_obj->{'success'} = true;
  return true;
}

function build_project($json, $base) {
  $project = json_decode($json);
  $output = $project->{'output'};
  file_put_contents($base . '.txt', $json);

  $build_result = (object) [ ];
  $old_dir = getcwd();

  $complete = function ($success, $message) use ($dir, $old_dir, $build_result) {
    exec(sprintf("rm -rf %s", escapeshellarg($dir)));
    if (file_exists($result)) {
      unlink($result);
    }
  
    chdir($old_dir);

    $build_result->{'success'} = $success;
    $build_result->{'message'} = $message;
    echo json_encode($build_result);
    return $success;
  };

  if ($output != 'wasm') {
    return $complete(false, 'Invalid output type ' . $output);
  }

  $dir = $base . '.$';
  $result = $base . '.wasm';

  if (!file_exists($dir)) {
    mkdir($dir, 0777);
  }
  chdir($dir);

  $build_result->{'tasks'} = array();

  $files = $project->{'files'};
  foreach ($files as $file) {
    $name = $file->{'name'};
    if (!validate_filename($name)) {
      return $complete(false, 'Invalid filename ' . $name);
    }
    $fileName = $dir . '/' . $name;
    $subdir = dirname($fileName);
    if (!file_exists($subdir)) {
      mkdir($subdir, 0777, true);
    }
    $src = $file->{'src'};
    file_put_contents($fileName, $src);
  }

  $obj_files = array();
  $clang_cpp = false;
  foreach ($files as $file) {
    $name = $file->{'name'};
    $fileName = $dir . '/' . $name;
    $type = $file->{'type'};
    $options = $file->{'options'};
    $success = true;
    $result_obj = (object) [
      'name' => "building $name",
      'file' => $name
    ];
    array_push($build_result->{'tasks'}, $result_obj);
    if ($type == 'c') {
      $success = build_c_file($fileName, $options, $fileName . '.o', $result_obj);
      array_push($obj_files, $fileName . '.o');
    } elseif ($type == 'cpp') {
      $clang_cpp = true;
      $success = build_cpp_file($fileName, $options, $fileName . '.o', $result_obj);
      array_push($obj_files, $fileName . '.o');
    }

    if (!$success) {
      return $complete(false, 'Error during build of ' . $name);
    }
  }

  $link_options = $project->{'link_options'};
  $result_obj = (object) [
    'name' => 'linking wasm'
  ];
  array_push($build_result->{'tasks'}, $result_obj);
  if (!link_obj_files($obj_files, $link_options, $clang_cpp, $result, $result_obj)) {
    return $complete(false, 'Error during linking');
  }
  
  $build_result->{'output'} = base64_encode(file_get_contents($result));
  return $complete(true, 'Success');
}

?>

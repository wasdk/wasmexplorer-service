<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

function get_clang_options($options) {
  global $app_root_dir;
  $sysroot = $app_root_dir . 'misc/sysroot';
  $clang_flags = "--target=wasm32-unknown-unknown-wasm --sysroot=$sysroot";
  
  $available_options = array(
    '-O0', '-O1', '-O2', '-O3', '-O4', '-Os', '-fno-exceptions', '-fno-rtti',
    '-ffast-math', '-fno-inline', '-std=c99', '-std=c89', '-std=c++14',
    '-std=c++1z', '-std=c++11', '-std=c++98');
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

function build_c_file($input, $options, $output) {
  global $llvm_wasm_root, $sanitize_shell_output;
  $cmd = $llvm_wasm_root . '/clang ' . get_clang_options($options) . ' ' . $input . ' -o ' . $output;
  $out = shell_exec($cmd . ' 2>&1');
  if (!file_exists($output)) {
    echo $sanitize_shell_output($out);
    return false;
  }
  return true;
}

function build_cpp_file($input, $options, $output) {
  global $llvm_wasm_root, $sanitize_shell_output;
  $cmd = $llvm_wasm_root . '/clang++ ' . get_clang_options($options) . ' ' . $input . ' -o ' . $output;
  $out = shell_exec($cmd . ' 2>&1');
  if (!file_exists($output)) {
    echo $sanitize_shell_output($out);
    return false;
  }
  return true;
}

function validate_filename($name) {
  $parts = preg_split("/\//", $name);
  foreach($parts as $p) {
    if ($p == '' || $p == '.' || $p == '..') {
      return false;
    }
  }
  return true;
}

function link_obj_files($obj_files, $options, $has_cpp, $output) {
  global $app_root_dir, $llvm_wasm_root, $sanitize_shell_output;
  $sysroot = $app_root_dir . 'misc/sysroot';
  $clang_flags = "--target=wasm32-unknown-unknown-wasm --sysroot=$sysroot -nostartfiles $sysroot/lib/wasmception.wasm -D__WASM32__ -Wl,--allow-undefined -Wl,--strip-debug";
  $files = join(' ', $obj_files);

  if ($has_cpp) {
    $clang = $llvm_wasm_root . '/clang++';
  } else {
    $clang = $llvm_wasm_root . '/clang';    
  }
  $cmd = $clang . ' ' . $clang_flags . ' ' . $files . ' -o ' . $output;
  $out = shell_exec($cmd . ' 2>&1');
  if (!file_exists($output)) {
    echo $sanitize_shell_output($out);
    return false;
  }
  return true;
}

function build_project($json, $base) {
  $project = json_decode($json);
  $output = $project->{'output'};
  file_put_contents($base . '.txt', $json);

  if ($output != 'wasm') {
    echo 'Invalid output type ' . $output;
    return false;
  }  

  $old_dir = getcwd();
  $dir = $base . '.$';
  $result = $base . '.wasm';

  $cleanup = function () use ($dir, $old_dir) {
    exec(sprintf("rm -rf %s", escapeshellarg($dir)));
    if (file_exists($result)) {
      unlink($result);
    }
  
    chdir($old_dir);  
  };

  if (!file_exists($dir)) {
    mkdir($dir);
  }
  chdir($dir);

  $files = $project->{'files'};
  foreach ($files as $file) {
    $name = $file->{'name'};
    if (!validate_filename($name)) {
      echo 'Invalid filename ' . $name;
      $cleanup();
      return false;
    }
    $fileName = $dir . '/' . $name;
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
    if ($type == 'c') {
      $success = build_c_file($fileName, $options, $fileName . '.o');
      array_push($obj_files, $fileName . '.o');
    } elseif ($type == 'cpp') {
      $clang_cpp = true;
      $success = build_cpp_file($fileName, $options, $fileName . '.o');      
      array_push($obj_files, $fileName . '.o');
    }

    if (!$success) {
      echo 'Error during build of ' . $name;
      $cleanup();
      return false;
    }
  }

  if (!link_obj_files($obj_files, '', $clang_cpp, $result)) {
    echo 'Error during linking';
    $cleanup();
    return false;
  }
  
  echo base64_encode(file_get_contents($result));

  $cleanup();
  return true;
}

?>

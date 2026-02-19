<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

function out($code, $arr) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$ini = [
  'file_uploads'        => ini_get('file_uploads'),
  'upload_max_filesize' => ini_get('upload_max_filesize'),
  'post_max_size'       => ini_get('post_max_size'),
  'memory_limit'        => ini_get('memory_limit'),
  'upload_tmp_dir'      => ini_get('upload_tmp_dir'),
];

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
  if (!mkdir($uploadDir, 0777, true)) {
    out(500, ['success'=>false, 'error'=>'uploads 폴더 생성 실패', 'dir'=>$uploadDir, 'ini'=>$ini]);
  }
}

if (empty($_FILES['file'])) {
  out(400, ['success'=>false, 'error'=>'파일이 없습니다', '_FILES'=>$_FILES, 'ini'=>$ini]);
}

$f = $_FILES['file'];
$err = [
  0=>'OK',
  1=>'UPLOAD_ERR_INI_SIZE',
  2=>'UPLOAD_ERR_FORM_SIZE',
  3=>'UPLOAD_ERR_PARTIAL',
  4=>'UPLOAD_ERR_NO_FILE',
  6=>'UPLOAD_ERR_NO_TMP_DIR',
  7=>'UPLOAD_ERR_CANT_WRITE',
  8=>'UPLOAD_ERR_EXTENSION'
];

if ($f['error'] !== UPLOAD_ERR_OK) {
  out(400, [
    'success'=>false,
    'error'=>'PHP 업로드 에러',
    'error_code'=>$f['error'],
    'error_name'=>$err[$f['error']] ?? 'UNKNOWN',
    'ini'=>$ini
  ]);
}

if (!is_uploaded_file($f['tmp_name'])) {
  out(500, ['success'=>false, 'error'=>'is_uploaded_file=false', 'tmp'=>$f['tmp_name'], 'ini'=>$ini]);
}

$ext = pathinfo($f['name'] ?? '', PATHINFO_EXTENSION) ?: 'jpg';
$new = uniqid('memo_', true) . '.' . $ext;
$dest = $uploadDir . $new;

$base = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($base === '/' || $base === '\\') $base = '';
$urlPath = $base . '/uploads/' . $new;

clearstatcache();
$writable = is_writable($uploadDir);

if (!@move_uploaded_file($f['tmp_name'], $dest)) {
  // 마지막 수단: 권한 문제 회피용 copy 시도
  if (@copy($f['tmp_name'], $dest)) {
    out(200, ['success'=>true, 'url'=>$urlPath, 'fallback'=>'copy', 'writable'=>$writable, 'ini'=>$ini]);
  }
  $perm = is_dir($uploadDir) ? substr(sprintf('%o', fileperms($uploadDir)), -4) : 'NA';
  out(500, [
    'success'=>false,
    'error'=>'move_uploaded_file 실패',
    'dest'=>$dest,
    'writable'=>$writable,
    'perm'=>$perm,
    'free_bytes'=>@disk_free_space($uploadDir),
    'ini'=>$ini
  ]);
}

out(200, ['success'=>true, 'url'=>$urlPath]);

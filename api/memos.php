<?php
// /shim-on/api/memos.php
// GET:    ?feed_id=...&limit=200           → [items]
// POST:   JSON or form {feed_id,text,photo_url,author} → {success:true,id:...}
// DELETE: ?feed_id=...  body: id=...       → {ok:true}  (작성자만 삭제)

// 세션은 최상단에서 시작 (카카오/앱 로그인 식별용)
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

// ===== 경로/파일 준비 =====
$DATA_DIR  = __DIR__ . '/data';
$MEMO_FILE = $DATA_DIR . '/memos.json';
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0755, true); }
if (!file_exists($MEMO_FILE)) { file_put_contents($MEMO_FILE, '[]'); }

// ===== 유틸 =====
function load_all() {
  global $MEMO_FILE;
  $raw = @file_get_contents($MEMO_FILE);
  $arr = json_decode($raw ?: '[]', true);
  return is_array($arr) ? $arr : [];
}
function save_all($arr) {
  global $MEMO_FILE;
  // 간단 파일락
  $fp = fopen($MEMO_FILE, 'c+');
  if (!$fp) return false;
  flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  rewind($fp);
  $ok = fwrite($fp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) !== false;
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return $ok;
}
function json_body() {
  $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
  }
  return [];
}
function param($key, $default=null) {
  return $_GET[$key] ?? $_POST[$key] ?? $default;
}
// 현재 로그인 사용자 식별 (카카오 우선 → 앱 이메일 → guest)
function current_user_id() {
  if (!empty($_SESSION['kakao_id'])) return (string)$_SESSION['kakao_id'];
  if (!empty($_SESSION['user']['email'])) return (string)$_SESSION['user']['email'];
  return 'guest';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ====== GET: 목록 ======
if ($method === 'GET') {
  $feed_id = param('feed_id', '');
  $limit   = max(1, (int)param('limit', 200));

  $all = load_all();

  // feed_id가 주어지면 필터
  if ($feed_id !== '') {
    $all = array_values(array_filter($all, function($m) use ($feed_id) {
      return (string)($m['feed_id'] ?? '') === (string)$feed_id;
    }));
  }

  // 공개글만
  $all = array_values(array_filter($all, fn($m) => !empty($m['public'])));

  // 최신순 (ISO8601 / 문자열 비교)
  usort($all, function($a,$b){
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
  });

  if (count($all) > $limit) $all = array_slice($all, 0, $limit);

  echo json_encode($all, JSON_UNESCAPED_UNICODE);
  exit;
}

// ====== POST: 저장 (JSON/폼 모두 지원) ======
if ($method === 'POST' && (($_POST['_method'] ?? '') !== 'DELETE')) {
  $body = json_body();
  $feed_id   = $body['feed_id']   ?? param('feed_id', '');
  $text      = $body['text']      ?? param('text', '');
  $photo_url = $body['photo_url'] ?? param('photo_url', '');
  $author    = $body['author']    ?? param('author', 'guest');

  if ($feed_id === '') {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'feed_id가 필요합니다']);
    exit;
  }
  if ($photo_url === '' && trim($text) === '') {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'text 또는 photo_url 중 하나는 필요합니다']);
    exit;
  }

  $all = load_all();

  $id = 'm_' . (string)round(microtime(true)*1000);
  $rec = [
    'id'         => $id,
    'feed_id'    => (string)$feed_id,
    'text'       => (string)$text,
    'photo_url'  => (string)$photo_url,
    'author'     => (string)($author !== '' ? $author : 'guest'),
    'user_id'    => current_user_id(),   // ✅ 작성자 식별 저장
    'public'     => true,
    'created_at' => gmdate('c'),         // ISO8601(UTC)
  ];

  // 앞에 추가(최신이 위로)
  array_unshift($all, $rec);
  if (!save_all($all)) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'저장 실패']);
    exit;
  }

  echo json_encode(['success'=>true, 'id'=>$id, 'item'=>$rec], JSON_UNESCAPED_UNICODE);
  exit;
}

// ====== DELETE: 삭제 (작성자만) ======
if ($method === 'DELETE' || ($method === 'POST' && (($_POST['_method'] ?? '') === 'DELETE'))) {
  // x-www-form-urlencoded DELETE 바디 파싱
  $raw = file_get_contents('php://input');
  $body = [];
  if ($raw) parse_str($raw, $body);

  $id      = $body['id']      ?? $_POST['id']      ?? $_GET['id']      ?? '';
  $feed_id = $body['feed_id'] ?? $_POST['feed_id'] ?? $_GET['feed_id'] ?? '';

  if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'id가 필요합니다']);
    exit;
  }
  if ($feed_id === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'feed_id가 필요합니다']);
    exit;
  }

  $me  = current_user_id();
  $all = load_all();

  $found   = false;
  $removed = false;
  $kept = [];

  foreach ($all as $m) {
    $sameId   = ((string)($m['id'] ?? '') === (string)$id);
    $sameFeed = ((string)($m['feed_id'] ?? '') === (string)$feed_id);

    if ($sameId && $sameFeed) {
      $found = true;
      $owner = (string)($m['user_id'] ?? 'guest');
      if ($owner === $me) {
        // 소유자 일치 → 삭제
        $removed = true;
        continue;
      } else {
        // 소유자 불일치 → 유지
        $kept[] = $m;
      }
    } else {
      $kept[] = $m;
    }
  }

  if (!$found) {
    http_response_code(404);
    echo json_encode(['ok'=>false, 'error'=>'대상 메모를 찾을 수 없습니다']);
    exit;
  }
  if (!$removed) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'삭제 권한이 없습니다']);
    exit;
  }

  if (!save_all($kept)) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'삭제 저장 실패']);
    exit;
  }

  echo json_encode(['ok'=>true, 'id'=>$id]);
  exit;
}

// 그 외 메서드
http_response_code(405);
echo json_encode(['error'=>'Method Not Allowed']);

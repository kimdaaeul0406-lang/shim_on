<?php
// /shim-on/api/memos.php
// GET:    ?feed_id=...&limit=200           → [items]
// POST:   JSON or form {feed_id,text,photo_url,author} → {success:true,id:...}
// DELETE: ?feed_id=...  body: id=...       → {ok:true}  (작성자만 삭제)

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../db.php';

// 유틸
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
// 작성자 식별
function current_user_id_val() {
  if (isset($_SESSION['user']['id'])) return $_SESSION['user']['id'];
  return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ====== GET: 목록 ======
if ($method === 'GET') {
  $feed_id = param('feed_id', '');
  $limit   = max(1, min(200, (int)param('limit', 200)));

  try {
    $sql = "SELECT * FROM feeds WHERE public=1";
    $params = [];
    
    if ($feed_id !== '') {
      $sql .= " AND feed_id=?";
      $params[] = $feed_id;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT $limit";
    
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    
    // JSON 응답 포맷 맞추기 (null -> 빈 문자열 등)
    foreach($rows as &$r){
      $r['public'] = (bool)$r['public'];
      // 시간 포맷을 ISO8601로? DB는 'YYYY-MM-DD HH:MM:SS'
      // JS에서 Date parse 잘 됨. 그대로 둠.
    }
    
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  } catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
  }
  exit;
}

// ====== POST: 저장 ======
if ($method === 'POST') {
  // DELETE tunneling check
  if (($_POST['_method'] ?? '') === 'DELETE') {
      goto handle_delete; // Go to DELETE section
  }

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

  // ID 생성 (기존 로직 유지: m_timestamp)
  $id = 'm_' . (string)round(microtime(true)*1000);
  $uid = current_user_id_val(); // 로그인 유저 ID (INT or NULL)
  
  try {
    $st = db()->prepare("INSERT INTO feeds (id, feed_id, user_id, author, text, photo_url, public) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $st->execute([$id, $feed_id, $uid, $author, $text, $photo_url]);
    
    echo json_encode([
      'success'=>true, 
      'id'=>$id, 
      'item'=>[
        'id'=>$id, 'feed_id'=>$feed_id, 'author'=>$author, 'text'=>$text, 'photo_url'=>$photo_url, 'created_at'=>date('Y-m-d H:i:s')
      ]
    ], JSON_UNESCAPED_UNICODE);
  } catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
  }
  exit;
}

// ====== DELETE: 삭제 ======
handle_delete:
if ($method === 'DELETE' || ($method === 'POST' && (($_POST['_method'] ?? '') === 'DELETE'))) {
  $raw = file_get_contents('php://input');
  $body = [];
  if ($raw) parse_str($raw, $body); // Parse x-www-form if needed or just use param logic

  // JSON body might be sent for DELETE too? Usually params or clean body
  // Let's check both
  $jbody = json_body();
  
  $id      = $jbody['id']      ?? $body['id']      ?? $_POST['id']      ?? $_GET['id']      ?? '';
  // $feed_id check obsolete for deletion by ID, but keep if needed logic? No, ID is unique PK in DB.

  if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'id가 필요합니다']);
    exit;
  }

  $uid = current_user_id_val();
  
  try {
    // Check ownership
    $st = db()->prepare("SELECT user_id FROM feeds WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    
    if (!$row) {
      http_response_code(404);
      echo json_encode(['ok'=>false, 'error'=>'대상 메모를 찾을 수 없습니다']);
      exit;
    }
    
    // Guest posts (user_id IS NULL) -> Can anyone delete? Or only Admin? 
    // Existing logic: current_user_id() 'guest' could delete 'guest' posts? 
    // Security: Only logged in owner can delete their own. Guests cannot delete once posted unless we track session?
    // Let's assume only logged-in users can delete their own posts. 
    // And Admin can delete anything.
    
    $is_admin = isset($_SESSION['user']['role']) && $_SESSION['user']['role']==='admin';
    $is_owner = ($uid !== null && (string)$row['user_id'] === (string)$uid);
    
    if (!$is_owner && !$is_admin) {
       // If it was posted by 'guest' and I am 'guest', I can't delete it easily without session tracking.
       // For now, restrict deletion to owner/admin.
       http_response_code(403);
       echo json_encode(['ok'=>false, 'error'=>'삭제 권한이 없습니다']);
       exit; 
    }

    $st = db()->prepare("DELETE FROM feeds WHERE id=?");
    $st->execute([$id]);

    echo json_encode(['ok'=>true, 'id'=>$id]);
  } catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
  }
  exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method Not Allowed']);


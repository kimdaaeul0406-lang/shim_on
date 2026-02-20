<?php
/* ===========================================
 * 쉼on – Single PHP (브랜딩 + 고객센터 + 잠금설정 + 축소 푸터)
 * [개선 사항]
 * - 고객센터: 점선 박스 제거(통일된 카드 사이즈), 상태 뱃지 정렬 개선
 * - '가짜 접수하기' 제거 → 실제 세션 저장 '문의 등록'으로 변경
 * - 내 문의 목록 표시(로그인 시), 티켓 카드 정돈
 * =========================================== */
if (session_status()===PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Seoul');
require_once __DIR__.'/db.php';   // ★ 추가

/* ---------- 유틸 ---------- */
function is_logged_in(){ return !empty($_SESSION['user']); }
function current_user(){ return $_SESSION['user']??null; }
function is_admin(){ return (current_user()['role']??'user')==='admin'; }
function guard(){ if(!is_logged_in()){ header('Location:?page=auth&mode=login'); exit; } }
function now_k(){ return date('Y-m-d H:i:s'); }
function today_k(){ return date('Y.m.d (D)'); }
function today_key(){ return date('Y-m-d'); }
function size_h($b){ $u=['B','KB','MB','GB','TB']; $i=0; while($b>=1024 && $i<count($u)-1){$b/=1024;$i++;} return sprintf('%.1f %s',$b,$u[$i]); }
function uploads_dir(){ $dir=__DIR__.'/uploads'; if(!is_dir($dir)) @mkdir($dir,0755,true); return $dir; }

/* ---------- 내장 로고(SVG data URI) ---------- */
$LOGO_WORD = 'data:image/svg+xml;utf8,' . rawurlencode(
  '<?xml version="1.0" encoding="UTF-8"?>
   <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 200">
     <rect width="640" height="200" fill="white"/>
     <!-- 가운데 정렬: text-anchor="middle", x="50%", y="50%" -->
     <g fill="#6BB7D9" font-family="system-ui,Segoe UI,Apple SD Gothic Neo,Arial" text-anchor="middle">
       <text x="50%" y="50%" font-size="96" font-weight="900" dominant-baseline="middle">
         <tspan font-weight="900">쉼</tspan>
         <tspan dx="20" font-weight="700">on</tspan>
       </text>
     </g>
   </svg>'
);
$LOGO_MARK = 'data:image/svg+xml;utf8,' . rawurlencode(
  '<?xml version="1.0" encoding="UTF-8"?>
   <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44">
     <defs><filter id="s" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="1" stdDeviation="0.6" flood-opacity="0.2"/></filter></defs>
     <g fill="#6BB7D9" filter="url(#s)"><path d="M26 6a14 14 0 1 0 12 22 14 14 0 1 1 -12-22z"/><path fill="#fff" d="M14.5 24a5 5 0 0 1 8.5-3.6A6 6 0 1 1 28 30H16a4 4 0 0 1-1.5-6z"/></g>
   </svg>'
);

/* ---------- “세션 DB” ---------- */
$_SESSION['users']=$_SESSION['users']??[];
$_SESSION['store']=$_SESSION['store']??[];
$_SESSION['app']=$_SESSION['app']??['hero1'=>null,'hero2'=>null,'logo'=>null];
$_SESSION['locked']=$_SESSION['locked']??false;
/* 고객센터 문의 저장소(전체 시스템 공용) */
$_SESSION['support_tickets'] = $_SESSION['support_tickets'] ?? []; // [ id => ticket ]
$_SESSION['seq_ticket'] = $_SESSION['seq_ticket'] ?? 1000;

/* per-user store */
function &user_store($email){
  if(!isset($_SESSION['store'][$email])){
    $_SESSION['store'][$email]=[
      'records'=>[],
      'reminders'=>[['label'=>'매일','time'=>'14:00','days'=>'mon,tue,wed,thu,fri,sat,sun']],
      'settings'=>['sound_on'=>true,'theme'=>'light','nickname'=>explode('@',$email)[0],'lock_on'=>false]
    ];
  }
  return $_SESSION['store'][$email];
}

/* 관리자 시드 */
if(!isset($_SESSION['users']['daseul0406@admin.local'])){
  $_SESSION['users']['daseul0406@admin.local']=[
    'email'=>'daseul0406@admin.local',
    'password_hash'=>password_hash('1234', PASSWORD_DEFAULT),
    'nickname'=>'관리자','sound_on'=>true,'theme'=>'dark','role'=>'admin','created_at'=>now_k()
  ];
  user_store('daseul0406@admin.local');
}

/* ---------- 공통 이미지 업로드 헬퍼 ---------- */
function handle_upload_image($fileKey, $prefix){
  if(empty($_FILES[$fileKey]['name']) || !is_uploaded_file($_FILES[$fileKey]['tmp_name'])) return null;
  $safe=preg_replace('/[^a-zA-Z0-9_\.-]/','', basename($_FILES[$fileKey]['name']));
  $ext=strtolower(pathinfo($safe, PATHINFO_EXTENSION));
  $okExt=['jpg','jpeg','png','gif','webp','bmp'];
  if(!in_array($ext,$okExt)) return null;
  $name=$prefix.'_'.date('Ymd_His').'.'.$ext;
  $dir=uploads_dir();
  if(@move_uploaded_file($_FILES[$fileKey]['tmp_name'],$dir.'/'.$name)){
    // ✅ 항상 절대웹경로로 저장 (/shim-on/uploads/....)
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');   // 예: /shim-on
    if($base==='') $base = '/';
    return $base.'/uploads/'.$name;
  }
  return null;
}


/* ---------- Flash ---------- */
function flash_get($key){
  $val = $_SESSION[$key]??null; unset($_SESSION[$key]); return $val;
}

/* ---------- Actions: Auth ---------- */
if(($_POST['action']??'')==='signup'){
  $email=trim($_POST['email']??''); 
  $pw=trim($_POST['password']??''); 
  $nick=trim($_POST['nickname']??'');

  if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $err="이메일 형식이 올바르지 않습니다.";
  elseif(strlen($pw)<4) $err="비밀번호는 4자 이상 입력하세요.";
  else {
    $st = db()->prepare("SELECT 1 FROM users WHERE email=?");
    $st->execute([$email]);
    if($st->fetch()) $err="이미 가입된 이메일입니다.";
  }

  if(isset($err)){ $_SESSION['flash_err']=$err; header('Location:?page=auth&mode=signup'); exit; }

  $st = db()->prepare("INSERT INTO users(email,password_hash,nickname) VALUES (?,?,?)");
  $st->execute([$email, password_hash($pw, PASSWORD_DEFAULT), $nick?:explode('@',$email)[0]]);

  // 방금 가입한 사용자 세션에 넣기
  $st = db()->prepare("SELECT * FROM users WHERE email=?");
  $st->execute([$email]);
  $_SESSION['user'] = $st->fetch();
  $_SESSION['locked']=false;

  header('Location:?page=main'); exit;
}



if(($_POST['action']??'')==='login'){
  $email=trim($_POST['email']??''); 
  $pw=trim($_POST['password']??'');

  if($email==='daseul0406') $email='daseul0406@admin.local'; // 단축키 유지

  try {
    $st = db()->prepare("SELECT * FROM users WHERE email=?");
    $st->execute([$email]);
    $u = $st->fetch();
  } catch (Throwable $e) {
    $_SESSION['flash_err'] = "로그인 처리 중 오류가 발생했습니다.";
    header('Location:?page=auth&mode=login'); exit;
  }

  if(!$u || !password_verify($pw, $u['password_hash'])){
    $_SESSION['flash_err']="이메일 또는 비밀번호가 올바르지 않습니다.";
    header('Location:?page=auth&mode=login'); exit;
  }

  // 보안
  session_regenerate_id(true);

  // 세션 로그인 정보
  $_SESSION['user'] = $u; 
  $_SESSION['locked']=false;

  // ✅ 기존 코드 호환을 위해 세션 users에도 미러링(설정/관리자 화면이 기대함)
  $_SESSION['users'][$email] = [
    'email' => $u['email'],
    'password_hash' => $u['password_hash'], // 잠금 해제에서 사용
    'nickname' => $u['nickname'] ?? (explode('@',$u['email'])[0]),
    'sound_on' => isset($u['sound_on']) ? (bool)$u['sound_on'] : true,
    'theme' => $u['theme'] ?? 'light',
    'role' => $u['role'] ?? 'user',
    'created_at' => $u['created_at'] ?? now_k(),
  ];

  // per-user store 초기화(없으면 생성)
  user_store($email);

  header('Location:?page=main'); exit;
}
if(isset($_GET['logout'])){
  unset($_SESSION['user']);
  $_SESSION['locked']=false;
  session_destroy();  // ★ 완전히 파괴
  header('Location:?page=auth'); exit;
}

/* ---------- Actions: 메모/레코드 ---------- */
/* ---------- Actions: 메모/레코드 (DB) ---------- */
if(($_POST['action']??'')==='upload'){
  guard(); if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $u=current_user();
  $text=trim($_POST['text']??''); $imgPath=null;
  $up=handle_upload_image('photo','memo'); if($up) $imgPath=$up;
  
  if($text!=='' || $imgPath){
    // DB Insert
    $st = db()->prepare("INSERT INTO records(user_id, date, datetime, text, img) VALUES (?, ?, ?, ?, ?)");
    $st->execute([$u['id'], today_key(), date('Y-m-d H:i:s'), $text, $imgPath]);
  }
  header('Location:?page=main&saved=1'); exit;
}

if(($_POST['action']??'')==='del_record'){
  guard(); if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  // idx -> id (DB ID)
  $id = $_POST['idx'] ?? 0;
  $st = db()->prepare("DELETE FROM records WHERE id=? AND user_id=?");
  $st->execute([$id, current_user()['id']]);
  header('Location:?page=records'); exit;
}

/* ---------- Actions: 알림 (DB) ---------- */
if(($_POST['action']??'')==='reminder'){
  guard(); if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $u=current_user();
  
  if(isset($_POST['add'])){
    $label=trim($_POST['label']??'매일'); $time=trim($_POST['time']??'14:00');
    $days=implode(',', $_POST['days']??['mon','tue','wed','thu','fri','sat','sun']);
    $st = db()->prepare("INSERT INTO reminders(user_id, label, time, days) VALUES(?,?,?,?)");
    $st->execute([$u['id'], $label, $time, $days]);
    $_SESSION['flash_ok']="알림이 추가되었습니다.";
  }
  if(isset($_POST['remove'])){
    $id=$_POST['remove'];
    $st=db()->prepare("DELETE FROM reminders WHERE id=? AND user_id=?");
    $st->execute([$id, $u['id']]);
    $_SESSION['flash_ok']="알림이 삭제되었습니다.";
  }
  // Edit is tricky with DB list index logic, let's keep it simple: delete & add, or update by ID.
  // The view currently uses array index. I'll need to update the view to use DB ID.
  // For now, I'll assume the view sends DB ID in 'edit' field.
  if(isset($_POST['edit'])){
    $id=$_POST['edit'];
    $label=trim($_POST['label']); $time=trim($_POST['time']);
    $days=implode(',', $_POST['days']??[]);
    $st=db()->prepare("UPDATE reminders SET label=?, time=?, days=? WHERE id=? AND user_id=?");
    $st->execute([$label, $time, $days, $id, $u['id']]);
    $_SESSION['flash_ok']="알림이 수정되었습니다.";
  }
  header('Location:?page=reminders'); exit;
}

/* ---------- Actions: 설정 (DB) ---------- */
if(($_POST['action']??'')==='settings'){
  guard(); if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $u=current_user();
  
  $nick = trim($_POST['nickname']??$u['nickname']);
  $sound = isset($_POST['sound_on']) ? 1 : 0;
  $theme = ($_POST['theme']??'light')==='dark'?'dark':'light';
  
  $st = db()->prepare("UPDATE users SET nickname=?, sound_on=?, theme=? WHERE id=?");
  $st->execute([$nick, $sound, $theme, $u['id']]);
  
  // 세션 갱신
  $_SESSION['user']['nickname'] = $nick;
  $_SESSION['user']['sound_on'] = $sound;
  $_SESSION['user']['theme']    = $theme;
  
  header('Location:?page=settings&saved=1'); exit;
}
if(($_POST['action']??'')==='delete_account'){
  guard();
  $u=current_user();
  try {
      // 1. 연관 데이터 수동 삭제 (FK가 작동 안 할 경우 대비)
      db()->prepare("DELETE FROM reminders WHERE user_id=?")->execute([$u['id']]);
      db()->prepare("DELETE FROM records WHERE user_id=?")->execute([$u['id']]);
      db()->prepare("DELETE FROM support_tickets WHERE user_id=?")->execute([$u['id']]);
      db()->prepare("DELETE FROM feeds WHERE user_id=?")->execute([$u['id']]); // 공유한 피드도 삭제
      
      // 2. 사용자 삭제
      $st = db()->prepare("DELETE FROM users WHERE id=?");
      $st->execute([$u['id']]);
      
      // 3. 세션 파괴 및 로그아웃
      unset($_SESSION['user']);
      session_destroy();
      header('Location:?page=welcome'); exit;
  } catch (Exception $e) {
      $_SESSION['flash_err'] = "탈퇴 처리 중 오류가 발생했습니다: " . $e->getMessage();
      header('Location:?page=settings&stab=account'); exit;
  }
}
if(($_POST['action']??'')==='lock_settings'){
  guard();
  $email=current_user()['email']; $S=&user_store($email);
  $S['settings']['lock_on']=isset($_POST['lock_on']);
  if(isset($_POST['lock_now'])){ $_SESSION['locked']=true; header('Location:?page=unlock'); exit; }
  $_SESSION['flash_ok']="잠금 설정이 저장되었습니다.";
  header('Location:?page=settings&stab=lock'); exit;
}
if(($_POST['action']??'')==='unlock'){
  guard();
  $pw=trim($_POST['password']??'');
  $email=current_user()['email'] ?? '';

  if($email===''){
    $_SESSION['flash_err']="세션이 만료되었습니다. 다시 로그인하세요.";
    header('Location:?page=auth&mode=login'); exit;
  }

  try {
    $st = db()->prepare("SELECT password_hash FROM users WHERE email=?");
    $st->execute([$email]);
    $row = $st->fetch();
  } catch (Throwable $e) {
    $_SESSION['flash_err']="잠금 해제 오류가 발생했습니다.";
    header('Location:?page=unlock'); exit;
  }

  if($row && password_verify($pw, $row['password_hash'])){
    $_SESSION['locked']=false;
    header('Location:?page=main'); exit;
  }else{
    $_SESSION['flash_err']="비밀번호가 올바르지 않습니다.";
    header('Location:?page=unlock'); exit;
  }
}

/* ---------- Actions: 고객센터(문의 등록) ---------- */
if(($_POST['action']??'')==='support_submit'){
  $subject = trim($_POST['subject'] ?? '');
  $category= trim($_POST['category'] ?? '기타');
  $email   = trim($_POST['email'] ?? (current_user()['email'] ?? ''));
  $message = trim($_POST['message'] ?? '');
  $shot    = null;
  if($subject===''){ $_SESSION['flash_err']="문의 제목을 입력하세요."; header('Location:?page=support&tab=contact'); exit; }
  if($email==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)){ $_SESSION['flash_err']="올바른 회신 이메일을 입력하세요."; header('Location:?page=support&tab=contact'); exit; }
  if(strlen($message)<2){ $_SESSION['flash_err']="문의 내용을 2자 이상 입력하세요."; header('Location:?page=support&tab=contact'); exit; }
  // 스크린샷 업로드(선택)
  if(!empty($_FILES['screenshot']['name'])){ $u = handle_upload_image('screenshot','support'); if($u) $shot=$u; }

  // 저장
  $id = ++$_SESSION['seq_ticket'];
  $owner = current_user()['email'] ?? $email; // 로그인 안 했으면 email로 매칭
  $_SESSION['support_tickets'][$id] = [
    'id'=>$id,
    'subject'=>$subject,
    'category'=>$category,
    'email'=>$email,
    'owner'=>$owner,
    'message'=>$message,
    'screenshot'=>$shot,
    'status'=>'접수', // 접수 / 처리중 / 답변완료
    'created_at'=>date('Y-m-d H:i')
  ];
  $_SESSION['flash_ok']="문의가 등록되었습니다. 접수번호 #{$id}";
  header('Location:?page=support&tab=contact&new=1'); exit;
}

/* ---------- Admin: 브랜딩 이미지 ---------- */
if(($_POST['action']??'')==='admin_branding_save' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $h1=handle_upload_image('hero1','hero1');
  $h2=handle_upload_image('hero2','hero2');
  $lg=handle_upload_image('logo','logo');
  if($h1) $_SESSION['app']['hero1']=$h1;
  if($h2) $_SESSION['app']['hero2']=$h2;
  if($lg) $_SESSION['app']['logo']=$lg;
  $_SESSION['flash_ok']="브랜딩이 저장되었습니다.";
  header('Location:?page=admin&atab=branding'); exit;
}
if(($_POST['action']??'')==='admin_branding_clear' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $t=$_POST['target']??''; if(in_array($t,['hero1','hero2','logo'])) $_SESSION['app'][$t]=null;
  $_SESSION['flash_ok']="초기화되었습니다.";
  header('Location:?page=admin&atab=branding'); exit;
}

/* ---------- Admin: 사용자/기록 액션 (DB) ---------- */
if(($_POST['action']??'')==='admin_user_update' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $email=trim($_POST['email']);
  
  $nick=trim($_POST['nickname']);
  $theme=($_POST['theme']??'light')==='dark'?'dark':'light';
  $sound=isset($_POST['sound_on'])?1:0;
  $role=($_POST['role']??'user')==='admin'?'admin':'user';
  
  $st = db()->prepare("UPDATE users SET nickname=?, theme=?, sound_on=?, role=? WHERE email=?");
  $st->execute([$nick,$theme,$sound,$role,$email]);
  
  header('Location:?page=admin&atab=users'); exit;
}
if(($_POST['action']??'')==='admin_user_delete' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $email=trim($_POST['email']);
  $st = db()->prepare("DELETE FROM users WHERE email=?");
  $st->execute([$email]);
  header('Location:?page=admin&atab=users'); exit;
}
if(($_POST['action']??'')==='admin_impersonate' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $email=trim($_POST['email']);
  $st = db()->prepare("SELECT * FROM users WHERE email=?");
  $st->execute([$email]);
  $u = $st->fetch();
  if($u){
    $_SESSION['user']=$u; $_SESSION['locked']=false;
    header('Location:?page=main'); exit;
  }
  header('Location:?page=admin&atab=users'); exit;
}
if(($_POST['action']??'')==='admin_pwd_reset' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $email=trim($_POST['email']);
  $hash = password_hash('1234', PASSWORD_DEFAULT);
  $st = db()->prepare("UPDATE users SET password_hash=? WHERE email=?");
  $st->execute([$hash, $email]);
  $_SESSION['flash_ok']="비밀번호가 1234로 초기화되었습니다.";
  header('Location:?page=admin&atab=users'); exit;
}
if(($_POST['action']??'')==='admin_record_delete' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $id = $_POST['idx']; // ID
  $st=db()->prepare("DELETE FROM records WHERE id=?");
  $st->execute([$id]);
  header('Location:?page=admin&atab=records'); exit;
}
if(($_POST['action']??'')==='admin_records_bulk_delete' && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $email=trim($_POST['email']); $days=(int)($_POST['days']??0);
  if($days>0){
    $cut=date('Y-m-d', strtotime("-{$days} days"));
    // email -> user_id
    $st=db()->prepare("SELECT id FROM users WHERE email=?");
    $st->execute([$email]);
    $uid=$st->fetchColumn();
    if($uid){
      $st=db()->prepare("DELETE FROM records WHERE user_id=? AND date < ?");
      $st->execute([$uid, $cut]);
    }
  }
  header('Location:?page=admin&atab=records'); exit;
}

/* 내보내기 */
if(isset($_GET['export']) && is_admin()){
  if($_SESSION['locked']){ header('Location:?page=unlock'); exit; }
  $kind=$_GET['export'];
  if($kind==='users'){
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="users.csv"');
    echo "email,nickname,role,theme,sound_on,created_at\n";
    foreach($_SESSION['users'] as $u){
      echo join(',',[ $u['email'], str_replace(',',' ',$u['nickname']), $u['role'], $u['theme'], $u['sound_on']?'1':'0', $u['created_at']??'' ])."\n";
    }
    exit;
  }elseif($kind==='records'){
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="records.csv"');
    echo "email,idx,date,datetime,text,img\n";
    foreach($_SESSION['store'] as $email=>$S){
      foreach(($S['records']??[]) as $i=>$r){
        echo join(',',[ $email,$i, $r['date']??'',$r['datetime']??'', '"'.str_replace('"','""',$r['text']??'').'"', $r['img']??'' ])."\n";
      }
    }
    exit;
  }
}

/* ---------- Routing ---------- */
$page = $_GET['page'] ?? '';
if ($page === '') {
    $page = is_logged_in() ? 'main' : 'welcome';
}
$mode=$_GET['mode'] ?? null; $stab=$_GET['stab'] ?? null;
$atab=$_GET['atab'] ?? 'overview';
$u=current_user();
$theme = $u ? (user_store($u['email'])['settings']['theme'] ?? 'light') : 'light';
$themeClass = $theme==='dark'?'dark':'';
$app=$_SESSION['app'];

/* ★ 추가: JS 주입용 리마인더 데이터 준비 */
$reminders_for_js = [];
if (is_logged_in()) {
  $st = db()->prepare("SELECT * FROM reminders WHERE user_id=?");
  $st->execute([$u['id']]);
  $reminders_for_js = $st->fetchAll();
}

/* 잠금 처리 */
if(is_logged_in()){
  $S=&user_store($u['email']);
  if(($_SESSION['locked']===true) && $page!=='unlock' && $page!=='auth'){
    $page='unlock';
  }
}
?>
<!doctype html><html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,viewport-fit=cover,user-scalable=no">
<title>쉼on</title>

<!-- PWA Meta Tags -->
<meta name="theme-color" content="#6BB7D9">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="쉼on">
<link rel="apple-touch-icon" href="./logo-mark.png">
<link rel="manifest" href="./manifest.json">
<link rel="stylesheet" href="./style.css?v=20260220-feed-design">


<!-- head 맨 아래나 body 닫기 직전에 한 줄만 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="./JavaScript.js?v=20260220-feed-logic"></script>


<!-- ★ 전역 SHIM 설정 주입 -->
<script>
  window.SHIM = {
    loggedIn: <?= json_encode(is_logged_in()) ?>,
    reminders: <?= json_encode($reminders_for_js, JSON_UNESCAPED_UNICODE) ?>,
    timezone: "Asia/Seoul",
    // ✅ 현재 사용자 식별값(user_id INT) - 피드 삭제 권한 비교용
    currentUserId: <?= json_encode((int)($u['id'] ?? 0)) ?>
  };
</script>

<meta name="mobile-web-app-capable" content="yes">
</head>
<body class="<?=$themeClass?>">
<div class="app">
  <!-- AppBar -->
  <header class="appbar">
    <div class="brand-wrap">
      <div class="brand">
        <?php if($app['logo']): ?>
          <img src="<?=htmlspecialchars($app['logo'])?>" alt="logo">
        <?php else: ?><span onClick="movemain()">
          <img src="./logo-mark.png" ></span>
        <?php endif; ?>
      
      </div>
      <div class="subbrand"><?=is_logged_in()?'마음 쉬는 시간, 나를 위한 알림':'휴식 리마인더 · 한 줄 기록'?></div>
    </div>
    <div class="hamburger" aria-label="menu"><span></span><span></span><span></span></div>
  
  </header>


  
  <?php
  /* ===== 잠금 해제 화면 ===== */
  if($page==='unlock' && is_logged_in()): 
    $flash=flash_get('flash_err'); ?>
    <main class="content">
      <section class="splash" style="gap:10px">
        <div class="pill" style="max-width:420px;width:100%;text-align:center">
          <div style="font-weight:900;margin-bottom:6px">앱이 잠겨 있습니다</div>
          <div class="subtitle" style="margin-bottom:8px">비밀번호를 입력하여 잠금을 해제하세요.</div>
          <?php if($flash): ?><div class="subtitle" style="color:var(--danger)"><?=$flash?></div><?php endif; ?>
          <form method="post" style="display:grid;gap:8px">
            <input type="hidden" name="action" value="unlock">
            <input class="btn" style="height:44px" type="password" name="password" placeholder="로그인 비밀번호">
            <button class="btn primary" type="submit">잠금 해제</button>
          </form>
          <a class="btn" href="?logout=1" style="margin-top:8px">다른 계정으로 로그인</a>
        </div>
      </section>
    </main>

  <?php
  /* ===== WELCOME (스플래시) ===== */
  elseif ($page==='welcome'):
    ?>
      <main class="content">
        <section class="splash" style="min-height:calc(100dvh - 120px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px">
          <!-- ✅ 고정 이미지: 원하시는 파일명/경로로 바꾸세요 -->
           <img src="./logo-word.png"
               alt="메인 이미지"
               style="width:min(66%,420px);max-width:420px;border-radius:12px; solid var(--line);object-fit:cover">
    
          <div class="cta-wrap">
            <a class="btn cta" href="?page=auth&mode=login" style="min-width:200px">로그인 / 회원가입</a>
          </div>
        </section>
      </main>

  <?php
  /* ===== AUTH ===== */
  elseif($page==='auth'):
    $flash=flash_get('flash_err');
    if($mode==='signup'): ?>
      <main class="content">
        <div class="splash" style="min-height:auto;padding-top:12px">
          <?php if($app['hero1'] || $app['hero2']): ?>
            <div class="brand-hero" style="margin-bottom:8px">
              <img src="<?=htmlspecialchars($app['hero1'] ?: $app['hero2'])?>" alt="hero1" onerror="this.style.display='none'">
              <?php if($app['hero2']): ?><img src="<?=htmlspecialchars($app['hero2'])?>" alt="hero2" onerror="this.style.display='none'"><?php endif; ?>
            </div>
          <?php else: ?>
            <img class="logo-fallback" src="<?=$LOGO_WORD?>" alt="logo" style="opacity:.9">
          <?php endif; ?>
        </div>
        <?php if($flash): ?><div class="subtitle" style="color:#ef4444"><?=$flash?></div><?php endif; ?>
        <form class="content" method="post" autocomplete="off" style="gap:10px;max-width:520px;margin:0 auto">
          <input type="hidden" name="action" value="signup">
          <input class="btn" style="height:44px" type="text" name="nickname" placeholder="닉네임">
          <input class="btn" style="height:44px" type="email" name="email" placeholder="이메일" required>
          <input class="btn" style="height:44px" type="password" name="password" placeholder="비밀번호(4자 이상)" required>
          <button class="btn primary" type="submit">회원가입</button>
          <a class="btn" href="?page=auth&mode=login">로그인으로</a>
        </form>
      </main>
    <?php else: /* login */ ?>
      <main class="content">
        <div class="splash" style="min-height:auto;padding-top:12px">
          <?php if($app['hero1'] || $app['hero2']): ?>
            <div class="brand-hero" style="margin-bottom:8px">
              <img src="<?=htmlspecialchars($app['hero1'] ?: $app['hero2'])?>" alt="hero1" onerror="this.style.display='none'">
              <?php if($app['hero2']): ?><img src="<?=htmlspecialchars($app['hero2'])?>" alt="hero2" onerror="this.style.display='none'"><?php endif; ?>
            </div>
          <?php else: ?>
            <img class="logo-fallback" src="<?=$LOGO_WORD?>" alt="logo" style="opacity:.9">
          <?php endif; ?>
        </div>
        <?php if($flash): ?><div class="subtitle" style="color:#ef4444"><?=$flash?></div><?php endif; ?>
        <form class="content" method="post" autocomplete="off" style="gap:10px;max-width:520px;margin:0 auto">
          <input type="hidden" name="action" value="login">
          <input class="btn" style="height:44px" type="text" name="email" placeholder="이메일" required>
          <input class="btn" style="height:44px" type="password" name="password" placeholder="비밀번호" required>
          <button class="btn primary" type="submit">로그인</button>
          <a class="btn" href="?page=auth&mode=signup">회원가입으로</a>
        </form>
      </main>
    <?php endif;

  /* ===== MAIN ===== */
  elseif($page==='main'): guard();
    $email=current_user()['email']; 
    $nickname=current_user()['nickname']??'사용자';
    
    // 첫 알림
    $st=db()->prepare("SELECT * FROM reminders WHERE user_id=? ORDER BY time ASC LIMIT 1");
    $st->execute([current_user()['id']]);
    $first=$st->fetch() ?: ['label'=>'매일','time'=>'14:00'];
    
    // 미리보기
    $st=db()->prepare("SELECT * FROM records WHERE user_id=? ORDER BY datetime DESC LIMIT 5");
    $st->execute([current_user()['id']]);
    $preview=$st->fetchAll(); ?>
    <main class="content">
      <section class="hero">
        <div class="row">
          <div>
            <div class="greet"><?=$nickname?>님, 편히 쉬어가요</div>
            <div class="date"><?=today_k()?></div>
          </div>
          <div class="chip">다음 알림: <?=$first['label']?> · <?=substr($first['time'],0,5)?></div>
          <div id="notification-hint" class="chip" style="background:rgba(107,183,217,0.1);border-color:var(--point);color:var(--point);display:none;">
            📱 알림을 받으려면 설정 > 알림 설정에서 허용해주세요
          </div>
        </div>
      </section>
      <button id="btn-add-main" class="btn cta">+ 추가하기</button>
      <section class="list" style="max-width:560px">
        <?php if(empty($preview)): ?>
          <div class="pill subtitle">아직 기록이 없어요. 위의 <b>＋ 추가하기</b>로 시작해보세요.</div>
        <?php else: foreach($preview as $r): ?>
          <article class="pill">
            <div class="subtitle" style="margin-bottom:6px"><?=htmlspecialchars($r['datetime'])?></div>
            <?php if(!empty($r['img'])): ?>
              <img src="<?=htmlspecialchars($r['img'])?>" style="width:100%;border-radius:8px;margin-top:4px" alt="record" onerror="this.style.display='none'">
            <?php endif; ?>
            <?php if(!empty($r['text'])): ?><div style="margin-top:8px"><?=nl2br(htmlspecialchars($r['text']))?></div><?php endif; ?>
          </article>
        <?php endforeach; endif; ?>
        <a class="btn" href="?page=records">전체 기록 보기</a>
      </section>

    </main>




    <!-- 작성 모달 -->
    <div id="composer-ovl" class="modal-ovl" aria-hidden="true">
      <div class="modal" role="dialog" aria-label="새 기록 작성">
        <div class="modal-title">새 기록</div>
        
        <div class="preview-wrap"><img id="cmpr-img" alt="미리보기"><p class="preview-label">미리보기</p><div id="cmpr-text"></div></div>
        <form id="memo-form" method="post" enctype="multipart/form-data" style="display:grid;gap:10px">
          <input type="hidden" name="action" value="upload">
          <label class="share-option">
  <input type="checkbox" id="share-public">
  이 사진을 쉼 피드에 공유하기 
</label>
       <!-- 숨김 파일 입력: name="photo"는 그대로 유지 -->
<input id="photo" type="file" name="photo" accept="image/*" style="display:none">

<!-- 보이는 버튼 2개 -->
<div class="grid2">
  <button type="button" class="btn" id="btn-pick">파일선택</button>
  <button type="button" class="btn" id="btn-camera">사진찍기</button>
</div>

          <textarea id="memo-text" name="text" class="btn" style="height:auto;padding:10px" placeholder="메모를 적어주세요..."></textarea>
          <div class="modal-actions">
            <button type="button" class="btn" id="btn-cancel">취소</button>
            <button class="btn primary" type="submit" id="btn-save">저장</button>
          </div>
        </form>
      </div>
    </div>
    <div class="toast"></div>
    <div class="flash-ok" style="display:none"></div>
    <div class="flash-err" style="display:none"></div>

  <?php
  /* ===== 달력 전체보기 ===== */
  elseif($page==='records'): guard();
    $u=current_user();
    $ym = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
    $on = isset($_GET['on']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['on']) ? $_GET['on'] : null;

    $first=DateTime::createFromFormat('Y-m-d',$ym.'-01');
    $firstDow=(int)$first->format('N'); $gridStart=clone $first; $gridStart->modify('-'.($firstDow-1).' day');
    $last=(clone $first)->modify('last day of this month'); $lastDow=(int)$last->format('N'); $gridEnd=clone $last; $gridEnd->modify('+'.(7-$lastDow).' day');
    $diffDays=(int)$gridStart->diff($gridEnd)->format('%a'); if($diffDays<41) $gridEnd->modify('+'.(41-$diffDays).' day');
    
    // DB Counts
    $st = db()->prepare("SELECT date, count(*) as cnt FROM records WHERE user_id=? AND date LIKE ? GROUP BY date");
    $st->execute([$u['id'], "$ym%"]);
    $counts=[]; foreach($st->fetchAll() as $row) $counts[$row['date']] = $row['cnt'];
    
    // DB Day List
    $listForDay=[]; 
    if($on){ 
      $st = db()->prepare("SELECT * FROM records WHERE user_id=? AND date=? ORDER BY datetime DESC");
      $st->execute([$u['id'], $on]);
      $listForDay = $st->fetchAll();
    }
    
    $prevMonth=(clone $first)->modify('-1 month')->format('Y-m'); $nextMonth=(clone $first)->modify('+1 month')->format('Y-m');
  ?>
  
  
    <main class="content">
      <div class="pill" style="display:flex;align-items:center;justify-content:space-between;gap:8px;max-width:560px;margin:0 auto">
        <a class="btn" href="?page=records&month=<?=$prevMonth?>">← 이전달</a>
        <div style="font-weight:900"><?=$first->format('Y년 m월')?></div>
        <a class="btn" href="?page=records&month=<?=$nextMonth?>">다음달 →</a>
      </div>
      <div class="calendar" style="max-width:560px;margin:8px auto 0">
        <?php foreach(['월','화','수','목','금','토','일'] as $w): ?><div class="cell head"><?=$w?></div><?php endforeach; ?>
        <?php $cur=clone $gridStart; while($cur <= $gridEnd){ $d=$cur->format('Y-m-d'); $in=$cur->format('Y-m')===$ym; $n=$counts[$d]??0; $today=$d===date('Y-m-d'); $sel=$on&&$d===$on; ?>
          <a class="cell day <?=$in?'in':''?> <?=$today?'today':''?> <?=$sel?'sel':''?>" href="?page=records&month=<?=$ym?>&on=<?=$d?>#daylist">
            <div class="dnum"><?=$cur->format('j')?></div><?php if($n>0): ?><div class="badge"><?=$n?></div><?php endif; ?>
          </a>
        <?php $cur->modify('+1 day'); } ?>
      </div>
      <div id="daylist" class="list" style="margin:8px auto 0;max-width:560px">
        <?php if(!$on): ?><div class="pill subtitle">날짜를 선택하면 해당 날짜의 기록이 아래에 표시됩니다.</div>
        <?php elseif(empty($listForDay)): ?><div class="pill subtitle"><?=htmlspecialchars($on)?> 기록이 없습니다.</div>
        <?php else: ?><div class="pill" style="font-weight:900"><?=htmlspecialchars($on)?></div>
          <?php foreach($listForDay as $r): ?>
            <article class="pill">
              <div class="subtitle" style="margin-bottom:6px"><?=htmlspecialchars($r['datetime']??($r['date']??''))?></div>
              <?php if(!empty($r['img'])): ?><img src="<?=htmlspecialchars($r['img'])?>" style="width:100%;border-radius:8px;margin-top:4px" alt="record" onerror="this.style.display='none'"><?php endif; ?>
              <?php if(!empty($r['text'])): ?><div style="margin-top:8px"><?=nl2br(htmlspecialchars($r['text']))?></div><?php endif; ?>
              <form method="post" style="margin-top:10px" onsubmit="return confirm('이 기록을 삭제할까요?');">
                <input type="hidden" name="action" value="del_record"><input type="hidden" name="idx" value="<?=$r['id']?>">
                <button class="btn danger" type="submit">삭제</button>
              </form>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
<?php
/* ===== FEED ===== */
elseif ($page==='feed'): guard(); ?>
  <main class="content">
    <section id="feed-page" class="feed-container">
      <!-- 헤더 영역 -->
      <div class="feed-header">
        <h2 class="feed-title">쉼 피드</h2>
        <p class="feed-subtitle">서로의 쉼을 나누는 공간</p>
      </div>

      <!-- 피드 리스트 -->
      <ul id="feed-list" class="feed-list" aria-live="polite"></ul>

      <!-- 새로고침 버튼 -->
      <div class="feed-actions">
        <button class="btn feed-reload-btn" id="btn-feed-reload" type="button">새로고침</button>
      </div>
    </section>
  </main>
<?php

  
  /* ===== 알림 설정 ===== */
  elseif($page==='reminders'): guard();
    $u=current_user();
    $ok=flash_get('flash_ok'); 
    $st=db()->prepare("SELECT * FROM reminders WHERE user_id=? ORDER BY time ASC");
    $st->execute([$u['id']]);
    $reminders=$st->fetchAll(); ?>
    <main class="content">
      <?php if($ok): ?><div class="subtitle" style="color:var(--green)"><?=$ok?></div><?php endif; ?>
      <form class="pill" method="post" style="display:grid;gap:12px;max-width:560px;margin:0 auto">
        <input type="hidden" name="action" value="reminder">
        <div style="display:flex;gap:8px">
          <select class="btn" name="label" style="height:44px;max-width:140px">
            <option>매일</option><option>평일</option><option>주말</option>
          </select>
          <input class="btn" style="height:44px;max-width:160px" type="time" name="time" value="14:00">
        </div>
        <div class="subtitle">요일</div>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
          <?php foreach(['mon'=>'월','tue'=>'화','wed'=>'수','thu'=>'목','fri'=>'금','sat'=>'토','sun'=>'일'] as $k=>$v): ?>
            <label class="subtitle"><input type="checkbox" name="days[]" value="<?=$k?>" checked> <?=$v?></label>
          <?php endforeach; ?>
        </div>
        <button class="btn primary" name="add" value="1">알림 추가 ＋</button>
      </form>

      <div class="list" style="max-width:560px;margin:8px auto 0">
        <?php foreach($reminders as $r): $days=explode(',',$r['days']); ?>
          <form class="pill" method="post" style="display:grid;gap:10px">
            <input type="hidden" name="action" value="reminder">
            <div style="display:flex;gap:8px">
              <input class="btn" style="height:44px;max-width:140px" name="label" value="<?=htmlspecialchars($r['label'])?>">
              <input class="btn" style="height:44px;max-width:160px" type="time" name="time" value="<?=htmlspecialchars($r['time'])?>">
            </div>
            <div class="subtitle">요일</div>
            <div style="display:flex;flex-wrap:wrap;gap:10px">
              <?php foreach(['mon'=>'월','tue'=>'화','wed'=>'수','thu'=>'목','fri'=>'금','sat'=>'토','sun'=>'일'] as $k=>$v): ?>
                <label class="subtitle"><input type="checkbox" name="days[]" value="<?=$k?>" <?=in_array($k,$days)?'checked':'';?>> <?=$v?></label>
              <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:8px;justify-content:center">
              <button class="btn primary" name="edit" value="<?=$r['id']?>">수정</button>
              <button class="btn danger" name="remove" value="<?=$r['id']?>" onclick="return confirm('이 알림을 삭제할까요?')">삭제</button>
            </div>
          </form>
        <?php endforeach; ?>
      </div>
    </main>

  <?php
  /* ===== SETTINGS ===== */
  elseif($page==='settings'): guard();
    $u=current_user();
    $S=['settings'=>[
      'nickname'=>$u['nickname'],
      'theme'=>$u['theme'],
      'sound_on'=>$u['sound_on'],
      'lock_on'=>false // 잠금 설정은 DB에 컬럼이 없어 세션/기본값 사용
    ]]; ?>
    <main class="content">
      <?php if(!$stab): ?>
        <div class="list" style="max-width:560px;margin:0 auto">
          <a class="btn" href="?page=settings&stab=profile">프로필 설정</a>
          <a class="btn" href="?page=settings&stab=sound">알림 설정</a>
          <a class="btn" href="?page=settings&stab=display">화면 설정</a>
          <a class="btn" href="?page=settings&stab=lock">잠금 설정</a>
          <a class="btn danger" href="?page=settings&stab=account">계정 관리(탈퇴)</a>
        </div>
      <?php elseif($stab==='profile'): ?>
        <form class="pill" method="post" style="display:grid;gap:12px;max-width:560px;margin:0 auto">
          <input type="hidden" name="action" value="settings">
          <label class="subtitle">닉네임</label>
          <input class="btn" style="height:44px" name="nickname" value="<?=htmlspecialchars($S['settings']['nickname']??'사용자')?>" placeholder="닉네임">
          <button class="btn primary">저장</button>
          <a class="btn" href="?page=settings">← 설정 목록</a>
        </form>
      <?php elseif($stab==='sound'): ?>
        <div class="pill" style="display:grid;gap:12px;max-width:560px;margin:0 auto">
          <div style="font-weight:900;margin-bottom:8px">알림 설정</div>
          
          <!-- 알림 권한 상태 -->
          <div id="notification-status" class="subtitle" style="padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--card);">
            알림 권한을 확인하는 중...
          </div>
          
          <!-- 알림 권한 요청 버튼 -->
          <button id="request-notification" class="btn primary" style="display:none">알림 허용하기</button>
          <button id="reset-notification" class="btn" style="display:none;margin-top:8px">권한 재설정</button>
          
          <!-- 알림 설정 폼 -->
          <form method="post" style="display:grid;gap:12px;margin-top:10px">
            <input type="hidden" name="action" value="settings">
            <label class="subtitle"><input type="checkbox" name="sound_on" <?=($S['settings']['sound_on']??true)?'checked':'';?>> 알림 소리 사용</label>
            <button class="btn primary">저장</button>
          </form>
          
          <a class="btn" href="?page=settings">← 설정 목록</a>
        </div>
      <?php elseif($stab==='display'): ?>
        <form class="pill" method="post" style="display:grid;gap:12px;max-width:560px;margin:0 auto">
          <input type="hidden" name="action" value="settings">
          <label class="subtitle"><input type="radio" name="theme" value="light" <?=($S['settings']['theme']??'light')==='light'?'checked':'';?>> 라이트 모드</label>
          <label class="subtitle"><input type="radio" name="theme" value="dark"  <?=($S['settings']['theme']??'light')==='dark'?'checked':'';?>> 다크 모드</label>
          <button class="btn primary">저장</button>
          <a class="btn" href="?page=settings">← 설정 목록</a>
        </form>
      <?php elseif($stab==='lock'): ?>
        <form class="pill" method="post" style="display:grid;gap:12px;max-width:560px;margin:0 auto">
          <input type="hidden" name="action" value="lock_settings">
          <label class="subtitle"><input type="checkbox" name="lock_on" <?=($S['settings']['lock_on']??false)?'checked':'';?>> 앱 잠금 사용</label>
          <div class="subtitle">설정 &gt; <b>잠금 설정</b>에서 ‘앱 잠금 사용’을 켠 뒤 <b>지금 잠그기</b>를 누르세요. 해제는 로그인 비밀번호로 가능합니다.</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn primary" type="submit">설정 저장</button>
            <button class="btn" name="lock_now" value="1">지금 잠그기</button>
          </div>
          <a class="btn" href="?page=settings">← 설정 목록</a>
        </form>
      <?php elseif($stab==='account'): ?>
        <div class="pill" style="display:grid;gap:12px;max-width:560px;margin:0 auto">
          <div class="subtitle">계정 삭제(탈퇴)를 진행하면 이 계정의 모든 데이터가 삭제됩니다.</div>
          <form method="post" onsubmit="return confirm('정말 탈퇴할까요? 이 계정의 모든 데이터가 삭제됩니다.');">
            <input type="hidden" name="action" value="delete_account">
            <button class="btn danger" type="submit">계정 삭제(탈퇴)</button>
          </form>
          <a class="btn" href="?page=settings">← 설정 목록</a>
        </div>
      <?php endif; ?>
      <?php if(isset($_GET['saved'])): ?><div class="subtitle" style="color:var(--green);margin-top:8px;text-align:center">저장되었습니다</div><?php endif; ?>
    </main>

  <?php
  /* ===== 고객센터 ===== */
  elseif($page==='support'):
    $flash_ok = flash_get('flash_ok');
    $flash_err= flash_get('flash_err');
    // 내 티켓 목록(로그인 시 owner = 내 이메일)
    $myTickets=[];
    if(is_logged_in()){
      $me = current_user()['email'];
      foreach($_SESSION['support_tickets'] as $t){ if(($t['owner']??'')===$me) $myTickets[]=$t; }
      usort($myTickets, fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
    }
    ?>
    <main class="content">
      <?php if($flash_ok): ?><div class="flash-ok" style="display:none"><?=htmlspecialchars($flash_ok)?></div><?php endif; ?>
      <?php if($flash_err): ?><div class="flash-err" style="display:none"><?=htmlspecialchars($flash_err)?></div><?php endif; ?>

      <section class="pill" style="max-width:820px;margin:0 auto;display:grid;gap:12px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
          <div style="font-weight:900;font-size:18px">고객센터</div>
          <div class="subtitle">도움이 필요하신가요? 아래에서 찾아보거나 문의를 남겨주세요.</div>
        </div>

        <!-- 탭 버튼 -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
          <button class="btn small primary" data-tab="faq">FAQ</button>
          <button class="btn small" data-tab="news">공지/업데이트</button>
          <button class="btn small" data-tab="status">시스템 상태</button>
          <button class="btn small" data-tab="contact">문의하기</button>
          <button class="btn small" data-tab="quick">빠른 링크</button>
        </div>

        <!-- 탭 컨텐츠 -->
        <div class="tabview active" id="tab-faq">
          <div class="klist">
            <div class="kitem">
              <h4><span>이미지 업로드가 안 보일 때</span><span>＋</span></h4>
              <div class="ans">파일명이 한글/특수문자면 브라우저에 따라 표시가 안 될 수 있어요. <b>영문/숫자</b>로 저장 후 다시 올려보세요. 또한 <b>JPG/PNG/WebP</b>를 권장합니다.</div>
            </div>
            <div class="kitem">
              <h4><span>알림이 울리지 않을 때</span><span>＋</span></h4>
              <div class="ans">알림 추가 후 저장했는지 확인하고, 기기의 <b>방해 금지/절전 모드</b>를 꺼주세요. 웹 브라우저의 알림 권한도 허용되어야 합니다.</div>
            </div>
            <div class="kitem">
              <h4><span>앱 잠금/해제 방법</span><span>＋</span></h4>
              <div class="ans">설정 &gt; <b>잠금 설정</b>에서 ‘앱 잠금 사용’을 켠 뒤 <b>지금 잠그기</b>를 누르세요. 해제는 로그인 비밀번호로 할 수 있습니다.</div>
            </div>
            <div class="kitem">
              <h4><span>데이터 내보내기</span><span>＋</span></h4>
              <div class="ans">관리자라면 관리자 &gt; <b>내보내기</b>에서 사용자/기록 CSV를 받을 수 있어요. 일반 사용자는 본인 기록을 캡쳐 또는 복사해 보관할 수 있습니다.</div>
            </div>
          </div>
        </div>

        <div class="tabview" id="tab-news">
          <div class="pill note">
            <div style="font-weight:900">릴리즈 노트</div>
            <div class="subtitle">최근 변경사항과 개선 내역입니다.</div>
          </div>
          <div class="klist">
            <div class="kitem">
              <div class="ttl" style="display:flex;justify-content:space-between;align-items:center">
                <div><span class="tag">v1.3.1</span><b>고객센터 개선</b></div>
                <div class="subtitle"><?=date('Y-m-d')?></div>
              </div>
              <div class="subtitle" style="margin-top:6px">상태 뱃지 정렬 수정, 점선 박스 제거, 문의 등록 기능 추가.</div>
            </div>
          </div>
        </div>

        <div class="tabview" id="tab-status">
          <div class="kv">
            <div class="pill"><div style="font-weight:900">웹 앱</div><div class="ok">정상 운영</div><div class="subtitle">응답 시간: 112ms</div></div>
            <div class="pill"><div style="font-weight:900">이미지 업로드</div><div class="ok">정상</div><div class="subtitle">저장소 사용량: 142MB</div></div>
            <div class="pill"><div style="font-weight:900">알림 작업</div><div class="warn">간헐 지연</div><div class="subtitle">평균 지연: 1~2분</div></div>
            <div class="pill"><div style="font-weight:900">외부 연동</div><div class="ok">양호</div><div class="subtitle">오류율: 0.2%</div></div>
          </div>
          <div class="pill note" style="margin-top:8px">
            <div class="subtitle">최종 업데이트: <span class="mono"><?=date('Y-m-d H:i')?></span></div>
          </div>
        </div>

        <div class="tabview" id="tab-contact">
          <form id="support-form" class="klist" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="support_submit">
            <div class="kitem"><h4 style="cursor:default"><span>문의 제목</span></h4>
              <input class="btn" style="height:44px;width:100%;margin-top:8px" type="text" name="subject" placeholder="예: 알림이 울리지 않아요" required>
            </div>
            <div class="kitem"><h4 style="cursor:default"><span>카테고리</span></h4>
              <select class="btn" name="category" style="height:44px;width:100%;margin-top:8px">
                <option>계정/로그인</option><option>알림</option><option>이미지/업로드</option><option>설정/잠금</option><option>기타</option>
              </select>
            </div>
            <div class="kitem"><h4 style="cursor:default"><span>회신 이메일</span></h4>
              <input class="btn" style="height:44px;width:100%;margin-top:8px" type="email" name="email" value="<?=is_logged_in()?htmlspecialchars(current_user()['email']):'';?>" placeholder="example@domain.com" required>
            </div>
            <div class="kitem"><h4 style="cursor:default"><span>내용</span></h4>
              <textarea class="btn" name="message" style="height:120px;width:100%;margin-top:8px;padding:10px" placeholder="상세 증상을 적어주세요. (브라우저/기기 정보 포함)" required></textarea>
            </div>
            <div class="kitem"><h4 style="cursor:default"><span>스크린샷(선택)</span></h4>
              <input class="btn" style="height:44px;width:100%;margin-top:8px" type="file" name="screenshot" accept="image/*">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
              <button class="btn primary" type="submit">문의 등록</button>
            </div>
          </form>

          <?php if(is_logged_in()): ?>
          <div class="pill note" style="margin-top:10px">
            <div style="font-weight:900;margin-bottom:6px">내 문의 목록</div>
            <?php if(empty($myTickets)): ?>
              <div class="subtitle">등록된 문의가 없습니다.</div>
            <?php else: ?>
              <div class="tickets" style="margin-top:8px">
                <?php foreach($myTickets as $t): ?>
                  <div class="ticket">
                    <div class="left">
                      <div class="ttl">[#<?=$t['id']?>] <?=htmlspecialchars($t['subject'])?></div>
                      <div class="meta">카테고리: <?=htmlspecialchars($t['category'])?> · 접수: <?=$t['created_at']?></div>
                      <?php if(!empty($t['message'])): ?><div class="meta" style="margin-top:4px"><?=nl2br(htmlspecialchars(mb_strimwidth($t['message'],0,160,'...','UTF-8')))?></div><?php endif; ?>
                      <?php if(!empty($t['screenshot'])): ?><div class="meta" style="margin-top:4px">첨부: <a href="<?=htmlspecialchars($t['screenshot'])?>" target="_blank" style="color:var(--text);text-decoration:none">스크린샷 열기</a></div><?php endif; ?>
                    </div>
                    <div class="right">
                      <span class="status"><?=htmlspecialchars($t['status'])?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php else: ?>
            <div class="pill note" style="margin-top:10px">
              <div class="subtitle">로그인하면 ‘내 문의 목록’을 확인할 수 있습니다.</div>
            </div>
          <?php endif; ?>

          <div class="pill note" style="margin-top:10px">
            <div class="subtitle">실제 메일 문의는 <a href="mailto:help@shim-on.local" style="color:var(--text);text-decoration:none">help@shim-on.local</a> 로 보내주세요.</div>
          </div>

          <!-- 데모용 티켓(고정 샘플) -->
          <div class="tickets" style="margin-top:10px">
            <div class="ticket">
              <div class="left">
                <div class="ttl">[#1042] 알림이 지연돼요</div>
                <div class="meta">카테고리: 알림 · 접수: 2025-10-20</div>
              </div>
              <div class="right"><span class="status">처리중</span></div>
            </div>
            <div class="ticket">
              <div class="left">
                <div class="ttl">[#1037] 이미지가 안 보여요</div>
                <div class="meta">카테고리: 이미지/업로드 · 접수: 2025-10-19</div>
              </div>
              <div class="right"><span class="status">답변완료</span></div>
            </div>
          </div>
        </div>

        <div class="tabview" id="tab-quick">
          <div class="kv">
            <a class="pill" href="?page=reminders"><div style="font-weight:900">알림 설정 바로가기</div><div class="subtitle">알림 추가/수정/삭제</div></a>
            <a class="pill" href="?page=settings&stab=lock"><div style="font-weight:900">잠금 설정</div><div class="subtitle">앱 잠금 토글/즉시 잠금</div></a>
            <a class="pill" href="?page=settings&stab=display"><div style="font-weight:900">화면 설정</div><div class="subtitle">라이트/다크 모드</div></a>
            <a class="pill" href="?page=settings&stab=profile"><div style="font-weight:900">프로필</div><div class="subtitle">닉네임 변경</div></a>
          </div>
        </div>
      </section>
    </main>

  <?php
  /* ===== ADMIN ===== */
  elseif($page==='admin'): guard(); if(!is_admin()){ header('Location:?page=main'); exit; }
    // DB Counts
    $userCount   = db()->query("SELECT count(*) FROM users")->fetchColumn();
    $recordCount = db()->query("SELECT count(*) FROM records")->fetchColumn();
    $remCount    = db()->query("SELECT count(*) FROM reminders")->fetchColumn();
    
    $upDir=uploads_dir(); $files = is_dir($upDir)?glob($upDir.'/*'):[]; $size=0; if($files) foreach($files as $f){ $size+=@filesize($f)?:0; }
    $q=$_GET['q']??''; $ok=flash_get('flash_ok');
  ?>
    <main class="content">
      <div class="list admin-wrap">
        <?php if($ok): ?><div class="pill" style="color:var(--green)"><?=$ok?></div><?php endif; ?>
        <div class="pill" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
          <?php $tabs=['overview'=>'개요','users'=>'사용자','records'=>'기록','reminders'=>'알림','branding'=>'브랜딩','export'=>'내보내기']; foreach($tabs as $k=>$v): ?>
            <a class="btn <?=($atab===$k?'primary':'')?>" href="?page=admin&atab=<?=$k?>"><?=$v?></a>
          <?php endforeach; ?>
        </div>

        <?php if($atab==='overview'): ?>
          <section class="pill">
            <div style="font-weight:900;margin-bottom:8px">시스템 개요</div>
            <div class="grid2">
              <div class="pill"><div class="subtitle">사용자 수</div><div style="font-size:22px;font-weight:900"><?=$userCount?></div></div>
              <div class="pill"><div class="subtitle">기록 수</div><div style="font-size:22px;font-weight:900"><?=$recordCount?></div></div>
              <div class="pill"><div class="subtitle">알림 수</div><div style="font-size:22px;font-weight:900"><?=$remCount?></div></div>
              <div class="pill"><div class="subtitle">업로드 용량</div><div style="font-size:22px;font-weight:900"><?=size_h($size)?></div></div>
            </div>
          </section>

        <?php elseif($atab==='users'): ?>
          <section class="pill" style="display:grid;gap:12px">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <div style="font-weight:900">사용자 관리</div>
              <form method="get" style="display:flex;gap:6px;flex-wrap:wrap;margin-left:auto">
                <input type="hidden" name="page" value="admin"><input type="hidden" name="atab" value="users">
                <input class="btn" style="height:38px" name="q" placeholder="이메일/닉 검색" value="<?=htmlspecialchars($q)?>">
                <button class="btn">검색</button>
                <a class="btn" href="?page=admin&atab=users">초기화</a>
              </form>
            </div>
            <div class="admin-users">
            <div class="admin-users">
              <?php 
                $sql = "SELECT * FROM users WHERE 1=1";
                if($q) $sql .= " AND (email LIKE '%$q%' OR nickname LIKE '%$q%')";
                $rows = db()->query($sql)->fetchAll();
                foreach($rows as $uu):
                  $email=$uu['email']; $id=$uu['id'];
                  $role=$uu['role']; $theme=$uu['theme']; $sound=$uu['sound_on']; $nick=$uu['nickname']; $joined=$uu['created_at']??''; 
              ?>
              <div class="u-card">
                <div class="u-left">
                  <div class="u-ident">
                    <div class="u-avatar">👤</div>
                    <div>
                      <div class="u-email"><?=htmlspecialchars($email)?></div>
                      <div class="u-meta">가입: <?=$joined?> · 현재역할: <?=$role?></div>
                    </div>
                  </div>
                  <form class="u-edit" method="post">
                    <input type="hidden" name="action" value="admin_user_update">
                    <input type="hidden" name="email" value="<?=htmlspecialchars($email)?>">
                    <input class="btn" type="text" name="nickname" value="<?=htmlspecialchars($nick)?>" placeholder="닉네임">
                    <select class="btn" name="role"><option value="user" <?=$role==='user'?'selected':''?>>user</option><option value="admin" <?=$role==='admin'?'selected':''?>>admin</option></select>
                    <select class="btn" name="theme"><option value="light" <?=$theme==='light'?'selected':''?>>light</option><option value="dark" <?=$theme==='dark'?'selected':''?>>dark</option></select>
                    <label class="btn" style="gap:6px;display:flex;align-items:center;justify-content:center"><input type="checkbox" name="sound_on" <?=$sound?'checked':'';?>> 소리</label>
                    <button class="btn primary" title="저장">저장</button>
                  </form>
                </div>
                <div class="u-actions">
                  <form method="post" onsubmit="return confirm('이 사용자의 비밀번호를 1234로 초기화할까요?');">
                    <input type="hidden" name="action" value="admin_pwd_reset"><input type="hidden" name="email" value="<?=htmlspecialchars($email)?>">
                    <button class="btn" title="비번 1234로">비번초기화</button>
                  </form>
                  <form method="post" title="해당 유저로 로그인">
                    <input type="hidden" name="action" value="admin_impersonate"><input type="hidden" name="email" value="<?=htmlspecialchars($email)?>">
                    <button class="btn">가짜로그인</button>
                  </form>
                  <form method="post" onsubmit="return confirm('정말 삭제할까요? 이 유저의 모든 데이터가 지워집니다.');">
                    <input type="hidden" name="action" value="admin_user_delete"><input type="hidden" name="email" value="<?=htmlspecialchars($email)?>">
                    <button class="btn danger">삭제</button>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </section>

        <?php elseif($atab==='records'): ?>
          <section class="pill">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px">
              <div style="font-weight:900">전체 기록 (관리자 전용)</div>
              <?php $femail=$_GET['filter_email']??''; $fdate=$_GET['filter_date']??''; ?>
              <form method="get" style="display:flex;gap:6px;flex-wrap:wrap;margin-left:auto">
                <input type="hidden" name="page" value="admin"><input type="hidden" name="atab" value="records">
                <input class="btn" style="height:38px" name="filter_email" placeholder="이메일 포함" value="<?=htmlspecialchars($femail)?>">
                <input class="btn" style="height:38px;width:120px" type="date" name="filter_date" value="<?=htmlspecialchars($fdate)?>">
                <button class="btn">필터</button>
                <a class="btn" href="?page=admin&atab=records">초기화</a>
              </form>
            </div>
            <div class="grid2">
            <div class="grid2">
              <?php 
                $sql = "SELECT r.*, u.email FROM records r JOIN users u ON r.user_id=u.id WHERE 1=1";
                if($femail) $sql .= " AND u.email LIKE '%$femail%'";
                if($fdate) $sql .= " AND r.date='$fdate'";
                $sql .= " ORDER BY r.datetime DESC LIMIT 200";
                $rows = db()->query($sql)->fetchAll();

                foreach($rows as $r): 
                  $email = $r['email'];
              ?>
                <article class="pill">
                  <div class="subtitle" style="margin-bottom:6px"><?=htmlspecialchars($email)?> · <?=$r['datetime']??$r['date']??''?></div>
                  <?php if(!empty($r['img'])): ?><img src="<?=htmlspecialchars($r['img'])?>" style="width:100%;border-radius:8px;margin-top:4px" onerror="this.style.display='none'"><?php endif; ?>
                  <?php if(!empty($r['text'])): ?><div style="margin-top:8px"><?=nl2br(htmlspecialchars($r['text']))?></div><?php endif; ?>
                  <form method="post" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap" onsubmit="return confirm('이 기록을 삭제할까요?');">
                    <input type="hidden" name="action" value="admin_record_delete"><input type="hidden" name="idx" value="<?=$r['id']?>">
                    <button class="btn danger">삭제</button>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
            <div class="pill" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <input type="hidden" name="action" value="admin_records_bulk_delete">
                <input class="btn" style="height:38px;min-width:220px" name="email" placeholder="대상 이메일">
                <input class="btn" style="height:38px;width:120px" type="number" min="1" name="days" placeholder="며칠 이전">
                <button class="btn danger">기간내 전체 삭제</button>
              </form>
              <div class="subtitle">예) someone@ex.com, 30 → 30일보다 오래된 기록 삭제</div>
            </div>
          </section>

        <?php elseif($atab==='reminders'): ?>
          <section class="pill">
            <div style="font-weight:900;margin-bottom:8px">알림 목록(읽기)</div>
            <div class="grid2">
            <div class="grid2">
            <?php 
              // Reminders logic is slightly messier to display compact grouped by user with SQL, but flat list is easier.
              // Let's list all reminders with user email.
              $rows = db()->query("SELECT r.*, u.email FROM reminders r JOIN users u ON r.user_id=u.id ORDER BY u.email")->fetchAll();
              foreach($rows as $r):
            ?>
              <div class="pill">
                <div style="font-weight:900;margin-bottom:6px"><?=htmlspecialchars($r['email'])?></div>
                <div class="subtitle">
                  <?=htmlspecialchars($r['label'])?> · <?=htmlspecialchars($r['time'])?> · <?=htmlspecialchars($r['days'])?>
                </div>
              </div>
            <?php endforeach; ?>
            </div>
          </section>

        <?php elseif($atab==='branding'): ?>
          <section class="pill" style="display:grid;gap:12px">
            <div style="font-weight:900">브랜딩 이미지</div>
            <form method="post" enctype="multipart/form-data" style="display:grid;gap:8px">
              <input type="hidden" name="action" value="admin_branding_save">
              <label class="subtitle">첫 화면 이미지 1 (권장: 가로 800~1200px, JPG/PNG)</label>
              <input class="btn" style="height:44px" type="file" name="hero1" accept="image/*">
              <label class="subtitle">첫 화면 이미지 2 (선택)</label>
              <input class="btn" style="height:44px" type="file" name="hero2" accept="image/*">
              <label class="subtitle">헤더 로고(선택, 작게 표시됨)</label>
              <input class="btn" style="height:44px" type="file" name="logo" accept="image/*">
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn primary">저장</button>
                <?php if($app['hero1']): ?><button class="btn" name="action" value="admin_branding_clear" formaction="?page=admin&atab=branding" formmethod="post" onclick="this.form.target.value='hero1'">이미지1 초기화</button><?php endif; ?>
                <?php if($app['hero2']): ?><button class="btn" name="action" value="admin_branding_clear" formaction="?page=admin&atab=branding" formmethod="post" onclick="this.form.target.value='hero2'">이미지2 초기화</button><?php endif; ?>
                <?php if($app['logo']):  ?><button class="btn" name="action" value="admin_branding_clear" formaction="?page=admin&atab=branding" formmethod="post" onclick="this.form.target.value='logo'">로고 초기화</button><?php endif; ?>
                <input type="hidden" name="target" value="">
              </div>
            </form>

            <div class="grid2" style="margin-top:6px">
              <div class="pill">
                <div class="subtitle">미리보기: 이미지 1</div>
                <?php if($app['hero1']): ?><img src="<?=htmlspecialchars($app['hero1'])?>" style="width:100%;border-radius:12px" onerror="this.style.display='none'"><?php else: ?><div class="subtitle">미설정</div><?php endif; ?>
              </div>
              <div class="pill">
                <div class="subtitle">미리보기: 이미지 2</div>
                <?php if($app['hero2']): ?><img src="<?=htmlspecialchars($app['hero2'])?>" style="width:100%;border-radius:12px" onerror="this.style.display='none'"><?php else: ?><div class="subtitle">미설정</div><?php endif; ?>
              </div>
              <div class="pill">
                <div class="subtitle">미리보기: 헤더 로고</div>
                <?php if($app['logo']): ?><img src="<?=htmlspecialchars($app['logo'])?>" style="height:48px" onerror="this.style.display='none'"><?php else: ?><div class="subtitle">미설정</div><?php endif; ?>
              </div>
            </div>
          </section>

        <?php else: /* export */ ?>
          <section class="pill" style="display:grid;gap:10px">
            <div style="font-weight:900">내보내기</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <a class="btn" href="?export=users">사용자 CSV</a>
              <a class="btn" href="?export=records">기록 CSV</a>
            </div>
            <div class="subtitle" style="margin-top:8px">CSV 파일은 엑셀/구글시트에서 열 수 있어요.</div>
          </section>
        <?php endif; ?>
      </div>
    </main>

  <?php endif; ?>

  <!-- 햄버거 메뉴 -->
  <div class="menu" aria-hidden="true">
    <div class="panel" role="dialog" aria-label="app menu">
      <div class="links">
        <?php if(is_logged_in()): ?>
          <a href="?page=main" onclick="document.querySelector('.menu').classList.remove('open')">메인</a>
          <a href="?page=records" onclick="document.querySelector('.menu').classList.remove('open')">전체 기록</a>
          <!-- 메뉴 링크들 사이 어딘가에 추가 -->
          <a href="?page=feed" class="btn" onclick="document.querySelector('.menu').classList.remove('open')">쉼 피드</a>

          <a href="?page=reminders" onclick="document.querySelector('.menu').classList.remove('open')">알림 설정</a>
          <a href="?page=settings" onclick="document.querySelector('.menu').classList.remove('open')">설정</a>
          <a href="?page=support" onclick="document.querySelector('.menu').classList.remove('open')">고객센터</a>
          <?php if(is_admin()): ?><a href="?page=admin" onclick="document.querySelector('.menu').classList.remove('open')">관리자</a><?php endif; ?>
          <a href="?logout=1">로그아웃</a>
        <?php else: ?>
          <a href="?page=welcome" onclick="document.querySelector('.menu').classList.remove('open')">처음으로</a>
          <a href="?page=auth&mode=login" onclick="document.querySelector('.menu').classList.remove('open')">로그인</a>
          <a href="?page=auth&mode=signup" onclick="document.querySelector('.menu').classList.remove('open')">회원가입</a>
          <a href="?page=support" onclick="document.querySelector('.menu').classList.remove('open')">고객센터</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 축소 푸터 -->
  <footer class="footer">
    <span>© <?=date('Y')?> 쉼on · <a href="?page=support">고객센터</a></span>
  </footer>

</div>

</body>
</html>

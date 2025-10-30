<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = $_POST["username"];
  $password = $_POST["password"];

  $sql = "SELECT * FROM users WHERE username='$username'";
  $result = mysqli_query($conn, $sql);

  if ($row = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $row["password"])) {
      $_SESSION["user"] = $row["username"];
      echo "<script>alert('로그인 성공! 환영합니다 😊');location.href='index.php';</script>";
      exit;
    } else {
      echo "<script>alert('비밀번호가 틀렸습니다.');history.back();</script>";
    }
  } else {
    echo "<script>alert('존재하지 않는 아이디입니다.');history.back();</script>";
  }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>로그인 | 쉼on</title>
</head>
<body>
  <h2>로그인</h2>
  <form method="post">
    <p><input type="text" name="username" placeholder="아이디" required></p>
    <p><input type="password" name="password" placeholder="비밀번호" required></p>
    <p><button type="submit">로그인</button></p>
  </form>
  <a href="register.php">회원가입</a>
</body>
</html>

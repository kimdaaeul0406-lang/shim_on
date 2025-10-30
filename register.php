<?php
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = $_POST["username"];
  $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
  $email = $_POST["email"];

  $check = "SELECT * FROM users WHERE username='$username'";
  $result = mysqli_query($conn, $check);

  if (mysqli_num_rows($result) > 0) {
    echo "<script>alert('이미 존재하는 아이디입니다.');history.back();</script>";
    exit;
  }

  $sql = "INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')";
  if (mysqli_query($conn, $sql)) {
    echo "<script>alert('회원가입 완료! 로그인해주세요.');location.href='login.php';</script>";
  } else {
    echo "회원가입 실패: " . mysqli_error($conn);
  }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>회원가입 | 쉼on</title>
</head>
<body>
  <h2>회원가입</h2>
  <form method="post">
    <p><input type="text" name="username" placeholder="아이디" required></p>
    <p><input type="email" name="email" placeholder="이메일" required></p>
    <p><input type="password" name="password" placeholder="비밀번호" required></p>
    <p><button type="submit">가입하기</button></p>
  </form>
  <a href="login.php">로그인으로 돌아가기</a>
</body>
</html>

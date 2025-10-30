<?php
function db(){
  static $pdo;
  if($pdo) return $pdo;

  $host = 'localhost';
  $db   = 'shim_on';      // schema.sql로 만든 DB 이름
  $user = 'root';         // ★ XAMPP 기본
  $pass = '';             // ★ 비밀번호 없음(빈 문자열)
  $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
<?php
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=bayanalert;charset=utf8mb4', 'root', '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function q($sql, $a = []){ global $pdo; $s = $pdo->prepare($sql); $s->execute($a); return $s; }

<?php
try {
  new PDO('mysql:host=127.0.0.1;port=3307;dbname=aala_niroo;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
  ]);
  echo 'OK';
} catch (Throwable $e) { echo $e->getMessage(); }
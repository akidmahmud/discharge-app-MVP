<?php
$db = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
$res = $db->query("SHOW DATABASES");
while($row = $res->fetch()) echo $row[0] . "\n";

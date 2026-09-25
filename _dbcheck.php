<?php
$c = @new mysqli('127.0.0.1', 'root', '', 'edu_flow');
if ($c->connect_error) { echo 'ERR: ' . $c->connect_error . PHP_EOL; exit; }
echo 'CONNECTED' . PHP_EOL;
$r = $c->query('SHOW TABLES');
while ($x = $r->fetch_array()) { echo $x[0] . PHP_EOL; }
$r2 = $c->query('SELECT COUNT(*) c FROM users');
echo 'users=' . $r2->fetch_assoc()['c'] . PHP_EOL;
$r3 = $c->query("SELECT VERSION() v");
echo 'server=' . $r3->fetch_assoc()['v'] . PHP_EOL;

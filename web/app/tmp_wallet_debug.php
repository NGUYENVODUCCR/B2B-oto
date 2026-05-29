<?php
$c = new mysqli('localhost', 'root', '1234', 'b2b_oto_dev');
if ($c->connect_error) { echo 'connect_error: '.$c->connect_error.PHP_EOL; exit(1);} 

function q($c, $sql, $title){
  echo "\n=== {$title} ===\n";
  $r = $c->query($sql);
  if(!$r){ echo 'sql_error: '.$c->error.PHP_EOL; return; }
  while($row=$r->fetch_assoc()){
    echo json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL;
  }
}

q($c, "SELECT option_id, option_name, LEFT(option_value, 400) AS option_value FROM wp_options WHERE option_name LIKE 'b2b_wallet_external_txn_%' ORDER BY option_id DESC LIMIT 20", 'external transactions');
q($c, "SELECT option_id, option_name, LEFT(option_value, 400) AS option_value FROM wp_options WHERE option_name='b2b_wallet_unmatched_external_txns' ORDER BY option_id DESC LIMIT 1", 'unmatched bucket');
q($c, "SELECT option_id, option_name, LEFT(option_value, 400) AS option_value FROM wp_options WHERE option_name LIKE 'b2b_wallet_deposit_request_%' ORDER BY option_id DESC LIMIT 20", 'deposit requests');
q($c, "SELECT option_id, option_name, option_value FROM wp_options WHERE option_name LIKE 'b2b_wallet_balance_%' ORDER BY option_id DESC LIMIT 20", 'balances');

$c->close();

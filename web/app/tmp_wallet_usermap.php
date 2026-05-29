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

q($c, "SELECT ID, user_login, display_name FROM wp_users ORDER BY ID", 'wp_users');
q($c, "SELECT id, user_id, company_id, company_role, created_at FROM wp_b2b_company_members ORDER BY user_id, id", 'company_members');
q($c, "SELECT user_id, COUNT(*) AS total_memberships, GROUP_CONCAT(company_id ORDER BY id) AS company_ids FROM wp_b2b_company_members GROUP BY user_id HAVING COUNT(*)>1", 'users with multiple memberships');

$c->close();

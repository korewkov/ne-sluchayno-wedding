<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
$password=$data['admin_password']??'';if(strlen($password)<12)throw new RuntimeException('Use an admin password of at least 12 characters.');
$data['admin_hash']=password_hash($password,PASSWORD_DEFAULT);unset($data['admin_password']);$data['session_secret']=bin2hex(random_bytes(32));
file_put_contents($argv[1],json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n",LOCK_EX);echo "Configuration saved outside public_html.\n";

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
$db=database();$sql=file_get_contents(__DIR__.'/schema.sql');foreach(explode(';',$sql) as $statement){$statement=trim($statement);if($statement!=='')$db->exec($statement);}
echo "Database schema ready.\n";

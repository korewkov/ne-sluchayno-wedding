<?php
$root=dirname(__DIR__).'/dist/public_html';$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/api/')){require $root.'/api.php';return true;}
if($path==='/'||$path==='/admin'||str_starts_with($path,'/admin/')){header('Content-Type: text/html; charset=utf-8');readfile($root.'/index.html');return true;}
$file=realpath($root.$path);$realRoot=realpath($root);if($file&&str_starts_with($file,$realRoot.DIRECTORY_SEPARATOR)&&is_file($file)&&!str_starts_with(basename($file),'.'))return false;
http_response_code(404);echo 'Not found';return true;

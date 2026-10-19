<?php
declare(strict_types=1);
function config(): array {
 static $config=null;
 if($config!==null)return $config;
 $file=__DIR__.'/config.json';
 if(!is_file($file))throw new RuntimeException('Configuration missing');
 $config=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
 if(empty($config['public_url'])||empty($config['db_dsn'])||empty($config['admin_hash'])||empty($config['session_secret']))throw new RuntimeException('Configuration incomplete');
 if(!str_starts_with($config['public_url'],'https://')&&empty($config['development']))throw new RuntimeException('HTTPS required');
 return $config;
}
function database(): PDO {
 static $db=null;if($db)return $db;$cfg=config();
 $db=new PDO($cfg['db_dsn'],$cfg['db_user']??'',$cfg['db_password']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=5000');}
 return $db;
}
function query(string $sql,array $params=[]): PDOStatement {$q=database()->prepare($sql);$q->execute($params);return $q;}
function is_sqlite(): bool {return database()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
function respond(mixed $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
function fail(int $status,string $message): never {respond(['error'=>$message],$status);}
function request_body(): array {
 if(($_SERVER['HTTP_ORIGIN']??'')!==rtrim(config()['public_url'],'/'))fail(403,'Запрос с другого сайта отклонён.');
 if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))fail(415,'Ожидается JSON.');
 $raw=file_get_contents('php://input',false,null,0,24001);if(strlen($raw)>24000)fail(413,'Слишком большой запрос.');
 try{$value=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable){fail(400,'Некорректный запрос.');}
 if(!is_array($value))fail(400,'Некорректный запрос.');return $value;
}
function field(mixed $value,int $max,bool $required=false): string {if(!is_string($value)||strlen($value)>$max*4||preg_match('//u',$value)!==1)fail(400,'Проверьте заполненные поля.');$value=trim($value);$length=function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);if($length>$max||($required&&$value===''))fail(400,'Проверьте заполненные поля.');return $value;}
function id(): string {return bin2hex(random_bytes(16));}
function transaction(callable $action): mixed {$db=database();$db->beginTransaction();try{$value=$action();$db->commit();return $value;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}}
function rate(string $key,int $max=12,int $seconds=60): void {
 $key=hash_hmac('sha256',$key,config()['session_secret']);$window=(int)floor(time()/$seconds);
 $count=transaction(function()use($key,$window){
  query(is_sqlite()?'INSERT OR IGNORE INTO rate_limits (`key`, `window`, `count`) VALUES (?, ?, 0)':'INSERT IGNORE INTO rate_limits (`key`, `window`, `count`) VALUES (?, ?, 0)',[$key,$window]);
  $row=query('SELECT `window`,`count` FROM rate_limits WHERE `key`=?'.(is_sqlite()?'':' FOR UPDATE'),[$key])->fetch();
  $count=(int)$row['window']===$window?(int)$row['count']+1:1;query('UPDATE rate_limits SET `window`=?,`count`=? WHERE `key`=?',[$window,$count,$key]);return $count;
 });if($count>$max)fail(429,'Слишком много попыток. Попробуйте позже.');
}
function session_start_secure(): void {
 if(session_status()===PHP_SESSION_ACTIVE)return;
 $dir=__DIR__.'/sessions';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Session storage unavailable');
 session_save_path($dir);session_name('wedding_admin');ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>empty(config()['development']),'httponly'=>true,'samesite'=>'Strict']);session_start();
}
function authenticated(): bool {session_start_secure();return isset($_SESSION['admin_until'])&&(int)$_SESSION['admin_until']>time();}
function require_admin(): void {if(!authenticated())fail(403,'Доступ только для организаторов.');}
function invitation(): array {
 $authorization=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
 if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m))fail(401,'Откройте персональную ссылку из приглашения.');
 $row=query('SELECT * FROM invitations WHERE token_hash=?',[hash('sha256',$m[1])])->fetch();if(!$row)fail(404,'Приглашение не найдено или ссылка обновлена. Свяжитесь с нами.');return $row;
}
function guest(array $row): array {return ['id'=>$row['id'],'name'=>$row['name'],'kind'=>$row['kind'],'companion'=>(bool)$row['companion'],'attendance'=>$row['attendance'],'drinks'=>json_decode($row['drinks'],true),'food'=>$row['food'],'comment'=>$row['comment'],'updatedAt'=>$row['updated_at']];}
function guest_rows(string $id): array {return query('SELECT * FROM guests WHERE invitation_id=? ORDER BY companion,sort_order,id',[$id])->fetchAll();}
function validate_guest(array $g): array {
 $g['id']=field($g['id']??null,100,true);$g['name']=field($g['name']??null,100,true);
 if(!in_array($g['kind']??null,['adult','child'],true)||!is_bool($g['companion']??null)||!in_array($g['attendance']??null,['yes','no','maybe'],true))fail(400,'Заполните анкету каждого гостя.');
 $allowed=['Игристое','Белое вино','Красное вино','Крепкие напитки','Безалкогольные'];if(!is_array($g['drinks']??null)||count($g['drinks'])>5||count(array_diff($g['drinks'],$allowed)))fail(400,'Проверьте напитки.');
 $g['food']=field($g['food']??null,600);$g['comment']=field($g['comment']??null,1200);return $g;
}

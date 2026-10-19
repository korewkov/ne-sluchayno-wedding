<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
try{
 $method=$_SERVER['REQUEST_METHOD'];$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$path=rtrim($path,'/');
 if($path==='/api/session'){
  if($method==='GET')respond(['authenticated'=>authenticated()]);
  $data=request_body();
  if($method==='DELETE'){session_start_secure();$_SESSION=[];session_destroy();setcookie('wedding_admin','',['expires'=>1,'path'=>'/','secure'=>empty(config()['development']),'httponly'=>true,'samesite'=>'Strict']);respond(['ok'=>true]);}
  if($method!=='POST')fail(405,'Метод не поддерживается.');rate('login:'.($_SERVER['REMOTE_ADDR']??'unknown'),5,900);$password=field($data['password']??null,200,true);
  if(!password_verify($password,config()['admin_hash']))fail(401,'Неверный пароль.');session_start_secure();session_regenerate_id(true);$_SESSION['admin_until']=time()+43200;respond(['ok'=>true]);
 }
 if($path==='/api/settings'){
  if($method==='GET'){$row=query('SELECT value FROM settings WHERE `key`=?',['wedding'])->fetch();respond($row?json_decode($row['value'],true):['gatheringTime'=>'16:00','celebration'=>'Место празднования уточняется','celebrationAddress'=>'','extraInfo'=>'']);}
  require_admin();$data=request_body();if($method!=='POST')fail(405,'Метод не поддерживается.');$settings=['gatheringTime'=>field($data['gatheringTime']??null,5,true),'celebration'=>field($data['celebration']??null,200,true),'celebrationAddress'=>field($data['celebrationAddress']??null,300),'extraInfo'=>field($data['extraInfo']??null,1000)];if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$settings['gatheringTime']))fail(400,'Проверьте время.');
  transaction(function()use($settings){query('DELETE FROM settings WHERE `key`=?',['wedding']);query('INSERT INTO settings (`key`,value) VALUES (?,?)',['wedding',json_encode($settings,JSON_UNESCAPED_UNICODE)]);});respond(['ok'=>true]);
 }
 if($path==='/api/invitation'){
  if($method!=='GET')fail(405,'Метод не поддерживается.');$i=invitation();respond(['id'=>$i['id'],'label'=>$i['label'],'greeting'=>$i['greeting'],'allowPlusOne'=>(bool)$i['allow_plus_one'],'guests'=>array_map('guest',guest_rows($i['id']))]);
 }
 if($path==='/api/wishes'){
  if($method!=='POST')fail(405,'Метод не поддерживается.');$i=invitation();$data=request_body();$name=field($data['name']??null,100,true);$message=field($data['message']??null,2000,true);rate('wish:'.$i['id'],3);query('INSERT INTO wishes (id,invitation_id,name,message,created_at) VALUES (?,?,?,?,?)',[id(),$i['id'],$name,$message,gmdate('c')]);respond(['ok'=>true]);
 }
 if($path==='/api/rsvp'){
  if($method!=='POST')fail(405,'Метод не поддерживается.');$i=invitation();$data=request_body();$submitted=$data['guests']??null;if(!is_array($submitted)||count($submitted)<1||count($submitted)>12)fail(400,'Проверьте состав гостей.');foreach($submitted as $g)if(!is_array($g))fail(400,'Некорректная анкета.');$submitted=array_map('validate_guest',$submitted);$ids=array_column($submitted,'id');if(count(array_unique($ids))!==count($ids))fail(400,'Повторяющиеся участники.');$existing=guest_rows($i['id']);$map=array_column($existing,null,'id');
  foreach($existing as $g)if(!$g['companion']&&!in_array($g['id'],$ids,true))fail(400,'Заполните анкету каждого приглашённого.');$companions=array_filter($submitted,fn($g)=>$g['companion']);if(count($companions)>1||(!$i['allow_plus_one']&&count($companions)))fail(403,'Добавление спутника не предусмотрено этим приглашением.');
  foreach($submitted as $g){$original=$map[$g['id']]??null;if($original){if((bool)$original['companion']!==$g['companion']||(!$original['companion']&&($original['name']!==$g['name']||$original['kind']!==$g['kind'])))fail(403,'Состав приглашения изменён. Обновите страницу.');}elseif(!$g['companion']||$g['id']!=='new-companion')fail(403,'Нельзя изменять данные другого приглашения.');}
  rate('rsvp:'.$i['id']);$now=gmdate('c');transaction(function()use($submitted,$existing,$ids,$i,$now){foreach($submitted as $g){$drinks=json_encode($g['kind']==='child'?[]:array_values(array_unique($g['drinks'])),JSON_UNESCAPED_UNICODE);if($g['id']==='new-companion')query('INSERT INTO guests (id,invitation_id,name,kind,companion,attendance,drinks,food,comment,updated_at,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[id(),$i['id'],$g['name'],'adult',1,$g['attendance'],$drinks,$g['food'],$g['comment'],$now,100]);else query('UPDATE guests SET name=?,attendance=?,drinks=?,food=?,comment=?,updated_at=? WHERE id=? AND invitation_id=?',[$g['name'],$g['attendance'],$drinks,$g['food'],$g['comment'],$now,$g['id'],$i['id']]);}foreach($existing as $g)if($g['companion']&&!in_array($g['id'],$ids,true))query('DELETE FROM guests WHERE id=? AND invitation_id=? AND companion=1',[$g['id'],$i['id']]);});respond(['ok'=>true,'savedAt'=>$now]);
 }
 if($path==='/api/admin'){
  require_admin();
  if($method==='GET'){$invitations=query('SELECT id,label,greeting,allow_plus_one,created_at FROM invitations ORDER BY created_at DESC')->fetchAll();foreach($invitations as &$i)$i['guests']=array_map('guest',guest_rows($i['id']));unset($i);$wishes=query('SELECT wishes.*,invitations.label FROM wishes JOIN invitations ON invitations.id=wishes.invitation_id ORDER BY created_at DESC')->fetchAll();respond(['invitations'=>$invitations,'wishes'=>$wishes]);}
  $data=request_body();
  if($method==='POST'){
   $label=field($data['label']??null,100,true);$greeting=field($data['greeting']??null,300,true);if(!is_bool($data['allowPlusOne']??null)||!is_array($data['guests']??null)||count($data['guests'])<1||count($data['guests'])>10)fail(400,'Проверьте состав приглашения.');$people=[];foreach($data['guests'] as $g){$name=field($g['name']??null,100,true);if(!in_array($g['kind']??null,['adult','child'],true))fail(400,'Проверьте возраст гостя.');$people[]=['name'=>$name,'kind'=>$g['kind']];}
   $id=id();$token=bin2hex(random_bytes(32));transaction(function()use($id,$token,$label,$greeting,$data,$people){query('INSERT INTO invitations (id,token_hash,label,greeting,allow_plus_one,created_at) VALUES (?,?,?,?,?,?)',[$id,hash('sha256',$token),$label,$greeting,$data['allowPlusOne']?1:0,gmdate('c')]);foreach($people as $index=>$g)query('INSERT INTO guests (id,invitation_id,name,kind,sort_order) VALUES (?,?,?,?,?)',[id(),$id,$g['name'],$g['kind'],$index]);});respond(['id'=>$id,'token'=>$token],201);
  }
  if($method==='PATCH'){$id=field($data['id']??null,100,true);$token=bin2hex(random_bytes(32));$result=query('UPDATE invitations SET token_hash=? WHERE id=?',[hash('sha256',$token),$id]);if(!$result->rowCount())fail(404,'Приглашение не найдено.');respond(['token'=>$token]);}
  if($method==='DELETE'){$id=field($data['id']??null,100,true);transaction(function()use($id){query('DELETE FROM wishes WHERE invitation_id=?',[$id]);query('DELETE FROM guests WHERE invitation_id=?',[$id]);query('DELETE FROM invitations WHERE id=?',[$id]);});respond(['ok'=>true]);}
  fail(405,'Метод не поддерживается.');
 }
 fail(404,'Страница не найдена.');
}catch(Throwable $e){if(isset($GLOBALS['db'])&&$GLOBALS['db']->inTransaction())$GLOBALS['db']->rollBack();error_log('Wedding request failed: '.get_class($e));respond(['error'=>'Сервис временно недоступен. Попробуйте позже.'],503);}

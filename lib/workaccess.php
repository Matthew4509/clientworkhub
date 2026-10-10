<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0

declare(strict_types=1);
if(!defined('CM_ROOT')){http_response_code(404);exit;}
require_once CM_LIB.'/jobs.php'; require_once CM_LIB.'/format.php'; require_once CM_LIB.'/mail.php';
function cm_work_access_views():array{return ['today'=>'Today','do_next'=>'Do next','just_done'=>'Just done'];}
function cm_work_access_flag(array $in,string $key):int{return isset($in[$key])?1:0;}














function cm_work_access_all():array{return cm_all('SELECT * FROM work_access ORDER BY created_at DESC');}
function cm_work_access_items(int $id):array{$o=['job'=>[],'task'=>[]];foreach(cm_all('SELECT item_type,item_id FROM work_access_item WHERE access_id=?',[$id])as$r)if(isset($o[$r['item_type']]))$o[$r['item_type']][]=(int)$r['item_id'];return$o;}
function cm_work_access_token():string{return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}
























function cm_work_access_save(array$in,string$actor):array{
 require_once CM_LIB.'/linkexpiry.php';
 $name=mb_substr(trim((string)($in['name']??'')),0,120);$user=mb_substr(trim((string)($in['username']??'')),0,80);$pass=(string)($in['password']??'');$view=(string)($in['screen_view']??'today');
 $auth=cm_work_access_flag($in,'auth_required');if($name==='')return['ok'=>false,'error'=>'Give this screen a name.','id'=>0];if($auth&&($user===''||strlen($pass)<10))return['ok'=>false,'error'=>'Add a username and a passcode of at least 10 characters, or untick "Require sign-in" to make this a public display.','id'=>0];if(!isset(cm_work_access_views()[$view]))$view='today';if(!$auth){$user='public-display';$pass=bin2hex(random_bytes(16));}


 $exp=cm_link_expires_at(cm_link_expiry_days($in['expiry_days']??null,true));
 $rawExp=trim((string)($in['expires']??''));
 if($rawExp!==''){
  $ts=strtotime($rawExp.' 23:59:59');
  if($ts===false)return['ok'=>false,'error'=>'Choose a valid expiry date.','id'=>0];
  if($ts<time())return['ok'=>false,'error'=>'That expiry date has already passed, so the link would never open. Choose today or later, or leave the date empty.','id'=>0];
  $exp=date('Y-m-d H:i:s',$ts);
 }

 $token=cm_work_access_token();
 $heading=mb_substr(trim((string)($in['public_heading']??'')),0,120);
 $max=max(1,min(100,(int)($in['max_items']??10)));
 try{
  $id=cm_insert('INSERT INTO work_access(name,username,password_hash,token_hash,token_enc,status,screen_view,auth_required,public_heading,show_business_name,show_client_name,show_first_name,show_job_name,show_task_name,show_estimate,show_status,highlight_vip,max_items,expires_at,created_at,updated_at)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$name,$user,password_hash($pass,PASSWORD_DEFAULT),hash('sha256',$token),cm_encrypt($token),'active',$view,$auth,$heading,cm_work_access_flag($in,'show_business_name'),cm_work_access_flag($in,'show_client_name'),0,cm_work_access_flag($in,'show_job_name'),1,cm_work_access_flag($in,'show_estimate'),1,cm_work_access_flag($in,'highlight_vip'),$max,$exp,cm_now(),cm_now()]);
 }catch(Throwable $ex){
  error_log('work screen create failed: '.$ex->getMessage());
  return['ok'=>false,'id'=>0,'error'=>'The work screen could not be created and nothing was saved. Check Update & backup for a waiting database update; if there is none, the server error log has the detail.'];
 }
 cm_audit('work_access.created','',null,$name.' created by '.$actor);return['ok'=>true,'error'=>'','id'=>$id];
}

function cm_work_access_display_save(int$id,array$in,string$actor):void{$heading=mb_substr(trim((string)($in['public_heading']??'')),0,120);$view=(string)($in['screen_view']??'today');if(!isset(cm_work_access_views()[$view]))$view='today';$max=max(1,min(100,(int)($in['max_items']??10)));cm_q('UPDATE work_access SET screen_view=?,auth_required=?,public_heading=?,show_business_name=?,show_client_name=?,show_first_name=0,show_job_name=?,show_task_name=1,show_estimate=?,show_status=1,highlight_vip=?,max_items=?,updated_at=? WHERE id=?',[$view,cm_work_access_flag($in,'auth_required'),$heading,cm_work_access_flag($in,'show_business_name'),cm_work_access_flag($in,'show_client_name'),cm_work_access_flag($in,'show_job_name'),cm_work_access_flag($in,'show_estimate'),cm_work_access_flag($in,'highlight_vip'),$max,cm_now(),$id]);unset($_SESSION['work_access'][$id]);cm_audit('work_access.display','',null,'work access '.$id.' display changed by '.$actor);}
function cm_work_access_url(array$a):string{return cm_url('work.php?t='.rawurlencode(cm_decrypt((string)$a['token_enc'])));}
function cm_work_access_rotate(int$id,string$actor):void{$t=cm_work_access_token();cm_q("UPDATE work_access SET token_hash=?,token_enc=?,status='active',updated_at=? WHERE id=?",[hash('sha256',$t),cm_encrypt($t),cm_now(),$id]);unset($_SESSION['work_access'][$id]);cm_audit('work_access.rotated','',null,'work access '.$id.' rotated by '.$actor);}
function cm_work_access_status(int$id,string$s,string$actor):void{if(!in_array($s,['active','disabled'],true))return;cm_q('UPDATE work_access SET status=?,updated_at=? WHERE id=?',[$s,cm_now(),$id]);if($s==='disabled')unset($_SESSION['work_access'][$id]);cm_audit('work_access.status','',null,'work access '.$id.' '.$s.' by '.$actor);}












function cm_work_access_from_token(string$t):?array{
 if(strlen($t)<32)return null;
 try{$a=cm_row('SELECT * FROM work_access WHERE token_hash=?',[hash('sha256',$t)]);}
 catch(Throwable $e){error_log('work access lookup failed: '.$e->getMessage());return null;}
 if(!$a||(string)$a['status']!=='active'||($a['expires_at']!==null&&(string)$a['expires_at']<cm_now()))return null;
 return$a;}
function cm_work_access_login(array$a,string$u,string$p):bool{if(!hash_equals((string)$a['username'],trim($u))||!password_verify($p,(string)$a['password_hash']))return false;session_regenerate_id(true);$_SESSION['work_access'][(int)$a['id']]=time();return true;}
function cm_work_access_signed_in(array$a):bool{if(!(int)($a['auth_required']??1))return true;return isset($_SESSION['work_access'][(int)$a['id']])&&(int)$_SESSION['work_access'][(int)$a['id']]>=time()-43200;}






function cm_work_access_rows(array$a):array{$view=(string)($a['screen_view']??'today');$max=max(1,min(100,(int)($a['max_items']??10)));$select="SELECT o.id,o.status,o.scheduled_date,o.completed_at,o.est_minutes,o.job_id,COALESCE(NULLIF(ja.name,''),'Work item') AS shared_text,j.name AS job_name,c.vip,COALESCE(NULLIF(TRIM(c.first_name),''),'Client') AS client_name FROM action_occurrence o LEFT JOIN job_action ja ON ja.id=o.action_id JOIN job j ON j.id=o.job_id JOIN client c ON c.id=j.client_id ";if($view==='just_done')return cm_all($select."WHERE o.status='completed' AND o.completed_at>=? ORDER BY o.completed_at DESC,o.id DESC LIMIT ".$max,[date('Y-m-d').' 00:00:00']);$where="WHERE o.status IN('scheduled','in_progress') AND j.status='active' ";$args=[];if($view==='today'){$where.='AND o.scheduled_date<=? ';$args[]=date('Y-m-d');}return cm_all($select.$where."ORDER BY o.status='in_progress' DESC,o.scheduled_date,o.id LIMIT ".$max,$args);}

function cm_work_access_occurrence(array $access,int $occurrenceId): ?array
{
    foreach(cm_work_access_rows($access) as $row) if((int)$row['id']===$occurrenceId) return cm_occurrence($occurrenceId);
    return null;
}

function cm_work_access_action(array $access,array $post): string
{
    if (!(int)($access['auth_required'] ?? 1)) return 'This public screen is read-only.';
    $occ=cm_work_access_occurrence($access,(int)($post['occ_id']??0));
    if(!$occ)return 'That work item is no longer available.';
    $actor='work-access:'.(string)$access['username'];
    if((string)($post['work_action']??'')==='start'){











        $refusal=cm_timer_start($occ,$actor);
        if($refusal==='')return'Work started.';
        if(str_starts_with($refusal,'Already timing')){
            error_log('work access start refused: '.$refusal);
            return'Another task is already being timed. Finish or pause it first.';
        }
        return $refusal;
    }
    if((string)($post['work_action']??'')==='complete'){
        $note=mb_substr(trim((string)($post['completion_note']??'')),0,500);
        cm_occurrence_complete($occ,$note!==''?$note:null,null,$actor,null);
        cm_audit('work_access.completed','',null,'work item '.(int)$occ['id'].' completed through access '.(int)$access['id']);
        return 'Work completed.';
    }
    return '';
}

<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!SET_SESSION) return;

require_once('database.php');

class DBSession {
    private static $lifetime;
    private static $name;

    public static function open($path, $name) {
        self::$lifetime = ini_get("session.gc_maxlifetime");
        return true;
    }

    public static function close() {
        //self::gc(self::$lifetime);
        return true;
    }

    public static function read($name) {
    	$ret = DB::GetOne('SELECT data FROM session WHERE name = %s AND expires > %d', array($name, time()-self::$lifetime));
		if($ret) {
			if (strtoupper(substr(PHP_OS, 0, 3))!=='WIN' && DATABASE_DRIVER=='postgres') $ret = pg_unescape_bytea($ret);
	    	$_SESSION = unserialize($ret);
		}
		if(CID!==false && ($ret = DB::GetOne('SELECT data FROM session_client WHERE session_name = %s AND client_id=%d', array($name,CID)))) {
			if (strtoupper(substr(PHP_OS, 0, 3))!=='WIN' && DATABASE_DRIVER=='postgres') $ret = pg_unescape_bytea($ret);
			$_SESSION['client'] = unserialize($ret);
		}
		return '';
    }

    public static function write($name, $data) {
	$ret = true;
    	DB::StartTrans();
		if(CID!==false && isset($_SESSION['client'])) {
			$data = serialize($_SESSION['client']);
			if (strtoupper(substr(PHP_OS, 0, 3))!=='WIN' && DATABASE_DRIVER=='postgres') $data = pg_escape_bytea($data);
			$ret &= DB::Replace('session_client',array('data'=>$data,'session_name'=>$name,'client_id'=>CID),array('session_name','client_id'),true);
		}
		if(isset($_SESSION['client'])) unset($_SESSION['client']);
		$data = serialize($_SESSION);
		if (strtoupper(substr(PHP_OS, 0, 3))!=='WIN' && DATABASE_DRIVER=='postgres') $data = pg_escape_bytea($data);
		$ret &= DB::Replace('session',array('expires'=>time(),'data'=>$data,'name'=>$name),'name',true);
		DB::CompleteTrans();
		return ($ret>0)?true:false;
    }

    public static function destroy($name) {
    	DB::StartTrans();
    	DB::Execute('DELETE FROM history WHERE session_name=%s',array($name));
    	DB::Execute('DELETE FROM session_client WHERE session_name=%s',array($name));
    	DB::Execute('DELETE FROM session WHERE name=%s',array($name));
		DB::CompleteTrans();
    	return true;
    }

    public static function gc($lifetime) {
    	$t = time()-$lifetime;
		$ret = DB::Execute('SELECT name FROM session WHERE expires < %d',array($t));
		while($row = $ret->FetchRow()) {
	    	DB::StartTrans();
			DB::Execute('DELETE FROM history WHERE session_name=%s',array($row['name']));
			DB::Execute('DELETE FROM session_client WHERE session_name=%s',array($row['name']));
			DB::Execute('DELETE FROM session WHERE name=%s',array($row['name']));
			DB::CompleteTrans();
		}
/*		DB::Execute('DELETE FROM history WHERE session_name IN (SELECT name FROM session WHERE expires < %d)',array($t));
    	DB::Execute('DELETE FROM session_client WHERE session_name IN (SELECT name FROM session WHERE expires < %d)',array($t));
	   	DB::Execute('DELETE FROM session WHERE expires < %d',array($t));*/
        return true;
    }
}

if(defined('EPESI_PROCESS')) {
	ini_set('session.gc_divisor', 100);
	ini_set('session.gc_probability', 30); // FIXDEADLOCK - set to 1
} else {
	ini_set('session.gc_probability', 0);
}
ini_set('session.save_handler', 'user');

session_set_save_handler(array('DBSession','open'),
                             array('DBSession','close'),
                             array('DBSession','read'),
                             array('DBSession','write'),
                             array('DBSession','destroy'),
                             array('DBSession','gc'));

$subdir = substr(getcwd(),strlen(dirname(dirname(__FILE__))));
$fulldir = dirname($_SERVER['SCRIPT_NAME']);
$document_root = str_replace('\\','/',substr($fulldir,0,strlen($fulldir)-strlen($subdir)));
$document_root = trim($document_root,'/');
if($document_root) $document_root = '/'.$document_root.'/';
	else $document_root = '/';

if(!defined('CID')) {
	if(isset($_SERVER['HTTP_X_CLIENT_ID']) && is_numeric($_SERVER['HTTP_X_CLIENT_ID']))
		define('CID', (int)$_SERVER['HTTP_X_CLIENT_ID']);
	else
		trigger_error('Invalid request without client id');
}

session_set_cookie_params(0,$document_root);
session_start();
?>

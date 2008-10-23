<?php
/**
 * Flash clock
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-clock
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_ClockCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Clock";
	}

	public static function applet_info() {
		return "Analog JS clock"; //here can be associative array
	}

	public static function applet_settings() {
		$browser = stripos($_SERVER['HTTP_USER_AGENT'],'msie');
		if($browser!==false)
			return array(
				array('name'=>'skin','label'=>'Clock configurable only on non-IE browsers only.','type'=>'static','values'=>'')
			);
		else
			return array(
				array('name'=>'skin','label'=>'Clock skin','type'=>'select','default'=>'swissRail','rule'=>array(array('message'=>'Field required', 'type'=>'required')),'values'=>array('swissRail'=>'swissRail','chunkySwiss'=>'chunkySwiss','chunkySwissOnBlack'=>'chunkySwissOnBlack','fancy'=>'fancy','machine'=>'machine','classic'=>'classic','modern'=>'modern','simple'=>'simple','securephp'=>'securephp','Tes2'=>'Tes2','Lev'=>'Lev','Sand'=>'Sand','Sun'=>'Sun','Tor'=>'Tor','Babosa'=>'Babosa','Tumb'=>'Tumb','Stone'=>'Stone','Disc'=>'Disc','flash'=>'flash'))
			);
	}	
}

?>
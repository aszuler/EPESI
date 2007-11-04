<?php
/**
 * MaintenanceMode_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage maintenance-mode-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MaintenanceMode_AdministratorInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Theme', 'version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Base/MaintenanceMode','version'=>0),
			array('name'=>'Base/Acl','version'=>0));
	}
}

?>

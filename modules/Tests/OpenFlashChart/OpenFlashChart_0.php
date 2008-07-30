<?php
/**
 * Testing flash charts
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license SPL
 * @version 0.1
 * @package tests-openflashchart
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_OpenFlashChart extends Module {

	public function body() {
		$f = $this->init_module('Libs/OpenFlashChart');

		$title = new title( date("D M d Y") );
		$f->set_title( $title );

		$bar = new bar();
		$bar->set_values( array(9,8,7,6,5,4,3,2,1) );
		$f->add_element( $bar );

		$this->display_module($f);

		$f2 = $this->init_module('Libs/OpenFlashChart');

		$title = new title( date("D M d Y") );
		$f2->set_title( $title );

		$bar = new bar();
		$data = array();
		for($i=1; $i<10; $i++)
			$data[] = rand()%10;
		$bar->set_values( $data );
		$f2->add_element( $bar );

		$this->display_module($f2);

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/OpenFlashChart/OpenFlashChartInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/OpenFlashChart/OpenFlashChart_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/OpenFlashChart/OpenFlashChartCommon_0.php');
	
	}

}

?>
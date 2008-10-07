<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ContactsCommon extends ModuleCommon {
	public static $paste_or_new = 'new';

	public static function get_contacts($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('contact', $crits, $cols, $order);
	}
	public static function get_companies($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('company', $crits, $cols, $order);
	}
	public static function no_contact_message() {
		print(Base_LangCommon::ts('CRM_Contacts','Your user doesn\'t have contact, please assign one'));
	}
	public static function get_contact_by_user_id($uid) {
		static $cache = array();
		if (isset($cache[$uid])) { 
			if ($cache[$uid] == -1) return null; 
			else return $cache[$uid]; 
		}
		$cid = Utils_RecordBrowserCommon::get_id('contact','login',$uid);
		if ($cid === false || $cid === null){
			$cache[$uid] = -1;
			return null;
		}
		$cache[$uid] = $ret = CRM_ContactsCommon::get_contact($cid);
		return $ret;
	}
	public static function get_contact($id) {
		return Utils_RecordBrowserCommon::get_record('contact', $id);
	}
	public static function get_company($id) {
		return Utils_RecordBrowserCommon::get_record('company', $id);
	}
	public static function get_main_company() {
		try {
			return Variable::get('main_company');
		} catch(NoSuchVariableException $e) {
			return null;
		}
	}
	public static function get_my_record() {
		$me = self::get_contact_by_user_id(Acl::get_user());
		if ($me===null) $me = array('id'=>-1, 'first_name'=>'', 'last_name'=>'', 'company_name'=>array(), 'login'=>-1);
		return $me;
	}
	public static function access_company($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'add':
			case 'browse':	return $i->acl_check('browse companies');
			case 'view':	if ($i->acl_check('view company')) return array('(!permission'=>2, '|:Created_by'=>Acl::get_user());
							$me = self::get_my_record();
							if ($me) return array('company_name'=>$me['company_name']);
							else return false;
			case 'edit':	if ($param['permission']>=1 && $param['created_by']!=Acl::get_user()) return false;
							$me = self::get_my_record();
							if ($me && in_array($param['id'],$me['company_name']) && $i->acl_check('edit my company')) return true; //my company
							return $i->acl_check('edit company');
			case 'delete':	return $i->acl_check('delete company');
			case 'fields':	if($i->acl_check('edit company')) return array();
							return array('company_name'=>'read-only','short_name'=>'read-only','group'=>'read-only');
		}
		return false;
	}
	public static function access_contact($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'add':
			case 'browse':	return $i->acl_check('browse contacts');
			case 'view':	if ($i->acl_check('view contact')) return array('(!permission'=>2, '|login'=>Acl::get_user(), '|:Created_by'=>Acl::get_user());
							else return array('login'=>Acl::get_user());
			case 'delete':	return $i->acl_check('delete contact');
			case 'edit':	if ($param['login']==Acl::get_user()) return true; //me
							if ($param['permission']>=1 && $param['created_by']!=Acl::get_user()) return false;
							if ($i->acl_check('edit contact')) return true;
							if ($i->acl_check('edit my company contacts')) {
								$me = self::get_my_record();
								foreach($param['company_name'] as $cid)
									if(in_array($cid,$me['company_name'])) return true; //customer
							}
							return false;
			case 'fields':	if ($i->acl_check('edit contact')) return array();
							return array('company_name'=>'read-only','last_name'=>'read-only','first_name'=>'read-only','group'=>'read-only');
		}
		return false;
	}

	/*--------------------------------------------------------------------*/
	public static function menu() {
		return array('CRM'=>array('__submenu__'=>1,'Contacts'=>array('mode'=>'contact','__icon__'=>'contacts.png','__icon_small__'=>'contacts-small.png'),'Companies'=>array('mode'=>'company','__icon__'=>'companies.png','__icon_small__'=>'companies-small.png')));
	}
	public static function caption() {
		return 'Companies & Contacts';
	}
	public static function admin_caption() {
		return 'Main Company';
	}
	public static function crm_company_datatype($field = array()) {
		if (!isset($field['QFfield_callback'])) $field['QFfield_callback'] = array('CRM_ContactsCommon', 'QFfield_company');
		if (!isset($field['display_callback'])) $field['display_callback'] = array('CRM_ContactsCommon', 'display_company');
		$field['type'] = $field['param']['field_type'];
		$param = 'company::Company Name';
		if (isset($field['param']['crits'])) $param .= ';'.implode('::',$field['param']['crits']);
		else $param .= ';::';
		$field['param'] = $param;
		return $field;
	}
	public static function crm_contact_datatype($field = array()) {
		if (!isset($field['QFfield_callback'])) $field['QFfield_callback'] = array('CRM_ContactsCommon', 'QFfield_contact');
		if (!isset($field['display_callback'])) $field['display_callback'] = array('CRM_ContactsCommon', 'display_contact');
		$field['type'] = $field['param']['field_type'];
		$param = 'contact::Last Name|First Name';
		if (isset($field['param']['format'])) $param .= ';'.implode('::',$field['param']['format']);
		else $param .= ';::';
		if (isset($field['param']['crits'])) $param .= ';'.implode('::',$field['param']['crits']);
		else $param .= ';::';
		$field['param'] = $param;
		return $field;
	}

	public static function display_contact($record, $nolink, $desc) {
		$v = $record[$desc['id']];
		$def = '';
		$first = true;
		$param = explode(';',$desc['param']);
		if ($param[1] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
		else $callback = explode('::', $param[1]);
		if (!is_array($v)) $v = array($v);
		foreach($v as $k=>$w){
			if ($w=='') break;
			if ($first) $first = false;
			else $def .= '<br>';
			$def .= Utils_RecordBrowserCommon::no_wrap(call_user_func($callback, self::get_contact($w), $nolink));
		}
		if (!$def) 	$def = '---';
		return $def;
	}
	public static function contact_format_default($record, $nolink=false){
		$ret = '';
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['id']);
		$ret .= $record['last_name'].(isset($record['first_name'][0])?' '.$record['first_name'][0].'.':'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
		if (isset($record['company_name'][0])) $ret .= ' ['.Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $record['company_name'][0], $nolink).']';
		return $ret;
	}
	public static function contact_format_no_company($record, $nolink=false){
		$ret = '';
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['id']);
		$ret .= $record['last_name'].(($record['first_name']!=='')?' '.$record['first_name']:'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
		return $ret;
	}
	public static function contacts_chainedselect_crits($default, $desc, $format_func, $ref_field){
		Utils_ChainedSelectCommon::create($desc['id'],array($ref_field),'modules/CRM/Contacts/update_contact.php', array('format'=>implode('::', $format_func), 'required'=>$desc['required']), $default);
		return null;
	}
	public function compare_names($a, $b) {
		return strcasecmp(strip_tags($a),strip_tags($b));
	}
	public static function QFfield_contact(&$form, $field, $label, $mode, $default, $desc, $rb_obj = null) {
		$cont = array();
		$param = explode(';',$desc['param']);
		if ($mode=='add' || $mode=='edit') {
			if ($param[1] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
			else $callback = explode('::', $param[1]);
			if ($param[2] != '::') {
				$crit_callback = explode('::',$param[2]);
				if ($crit_callback[0]=='ChainedSelect') {
					$crits = null;
				} else {
					$crits = call_user_func($crit_callback, false);
					$adv_crits = call_user_func($crit_callback, true);
					if ($adv_crits === $crits) $adv_crits = null;
					if ($adv_crits !== null) {
						$rp = $rb_obj->init_module('Utils/RecordBrowser/RecordPicker');
						$rb_obj->display_module($rp, array('contact', $field, $callback, $adv_crits, array('work_phone'=>false, 'mobile_phone'=>false, 'zone'=>false, 'Actions'=>false), array('last_name'=>'ASC')));
						$form->addElement('static', $field.'_rpicker_advanced', null, $rp->create_open_link(Base_LangCommon::ts('CRM_Contacts','Advanced')));
					}
				}
			} else $crits = array();

			if ($crits!==null) {
				$contacts = self::get_contacts($crits);
				if (!$desc['required'] && $desc['type']!='multiselect') $cont[''] = '---';
				if (!is_array($default)) {
					if ($default!='') $default = array($default); else $default=array();
				} 
				$ext_rec = array_flip($default);
				foreach ($contacts as $v) { 
					$cont[$v['id']] = call_user_func($callback, $v, true);
					unset($ext_rec[$v['id']]);
				}
				foreach($ext_rec as $k=>$v) {
					$c = CRM_ContactsCommon::get_contact($k);
					if ($c===null) continue;
					$cont[$k] = call_user_func($callback, $c, true);
				}
				uasort($cont, array('CRM_ContactsCommon', 'compare_names'));
			} else $cont = array();
			$form->addElement($desc['type'], $field, $label, $cont, array('id'=>$field));
			$form->setDefaults(array($field=>$default));
			if ($param[2] != '::')
				if ($crit_callback[0]=='ChainedSelect')
					self::contacts_chainedselect_crits($form->exportValue($field), $desc, $callback, $crit_callback[1]);
		} else {
			$form->addElement('static', $field, $label, array('id'=>$field));
			$callback = $rb_obj->get_display_method($desc['name']);
			if (!is_callable($callback)) $callback = array('CRM_ContactsCommon','display_contact');
			$def = call_user_func($callback, $rb_obj->record, false, $desc);
			$form->setDefaults(array($field=>$def));
		}
	}

	public static function display_company($record, $nolink=false, $desc=null) {
		if ($desc!==null) $v = $record[$desc['id']];
		else $v = $record['id'];
		if (!is_numeric($v) && !is_array($v)) return '---';
		$def = '';
		$first = true;
		if (!is_array($v)) $v = array($v);
		foreach($v as $k=>$w){
			if ($w=='') break;
			if ($first) $first = false;
			else $def .= '<br>';
			$def .= Utils_RecordBrowserCommon::no_wrap(Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $w, $nolink));
		}
		if (!$def) return '---';
		return $def;
	}
	public static function QFfield_company(&$form, $field, $label, $mode, $default, $desc, $rb, $display_callbacks) {
		$comp = array();
		$param = explode(';',$desc['param']);
		if ($mode=='add' || $mode=='edit') {
			if ($param[1] != '::') $crits = call_user_func(explode('::',$param[1]));
			else $crits = array();
			if (isset($crits['_no_company_option'])) {
				$no_company_option = true;
				unset($crits['_no_company_option']);
			} else $no_company_option = false;
			$companies = self::get_companies($crits);
			if (!is_array($default)) {
				if ($default!='') $default = array($default); else $default=array();
			} 
			$ext_rec = array_flip($default);
			foreach ($companies as $v) {
				$comp[$v['id']] = $v['company_name'];
				unset($ext_rec[$v['id']]);
			}
			foreach($ext_rec as $k=>$v) {
				$c = CRM_ContactsCommon::get_company($k);
				$comp[$k] = $c['company_name'];
			}
			natcasesort($comp);
			$key = '';
			if ($no_company_option) {
				$comp = array(''=>'['.Base_LangCommon::ts('CRM_Contacts','w/o company').']') + $comp;
				$key = '_no_company';
			}
			if (!$desc['required'] && $desc['type']!='multiselect') $comp = array($key => '---') + $comp;
			$form->addElement($desc['type'], $field, $label, $comp, array('id'=>$field));
			if ($mode!=='add') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label, array('id'=>$field));
			/*$def = '';
			$first = true;
			if (is_numeric($default) || is_array($default)) {
				if (!is_array($default)) $default = array($default);
				foreach($default as $k=>$v){
					if ($v=='') break;
					if ($first) $first = false;
					else $def .= '<br>';
					$def .= Utils_RecordBrowserCommon::no_wrap(Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $v));
				}
			}
			if (!$def) 	$def = '---';
			$form->setDefaults(array($field=>$def));*/
			if (isset($display_callbacks[$desc['name']])) $callback = $display_callbacks[$desc['name']];
			else $callback = array('CRM_ContactsCommon', 'display_company');
			$form->setDefaults(array($field=>call_user_func($callback, array('company'=>$default), false, array('id'=>'company'))));
		}
	}

	public static function QFfield_webaddress(&$form, $field, $label, $mode, $default) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, $label);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_webaddress(array('webaddress'=>$default), null, array('id'=>'webaddress'))));
		}
	}
	public static function QFfield_email(&$form, $field, $label, $mode, $default) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, $label);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_email(array('email'=>$default), null, array('id'=>'email'))));
		}
	}
	public static function QFfield_login(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
		if ($mode=='view') {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_login(array('login'=>$default), null, array('id'=>'login'))));
			return;
		}
		if ($mode=='add' || $mode=='edit') {
			if (self::$paste_or_new=='new') {
				$form->addElement('checkbox', 'create_company', Base_LangCommon::ts('CRM/Contacts','Create new company'), null, array('onClick'=>'document.getElementsByName("company_namefrom[]")[0].disabled=document.getElementsByName("company_nameto[]")[0].disabled=this.checked;document.getElementsByName("create_company_name")[0].disabled=!this.checked;'));
				$form->addElement('text', 'create_company_name', Base_LangCommon::ts('CRM/Contacts','New company name'), array('disabled'=>1));
				if (isset($rb)) $form->setDefaults(array('create_company_name'=>$rb->record['last_name'].' '.$rb->record['first_name']));
				eval_js('Event.observe(\'last_name\',\'change\', update_create_company_name_field);'.
						'Event.observe(\'first_name\',\'change\', update_create_company_name_field);'.
						'function update_create_company_name_field() {'.
							'document.forms[\''.$form->getAttribute('name').'\'].create_company_name.value = document.forms[\''.$form->getAttribute('name').'\'].last_name.value+" "+document.forms[\''.$form->getAttribute('name').'\'].first_name.value;'.
						'}');
			} else {
				$comp = self::get_company(self::$paste_or_new);
				$paste_company_info =
					'document.getElementsByName("address_1")[0].value="'.$comp['address_1'].'";'.
					'document.getElementsByName("address_2")[0].value="'.$comp['address_2'].'";'.
					'document.getElementsByName("work_phone")[0].value="'.$comp['phone'].'";'.
					'document.getElementsByName("fax")[0].value="'.$comp['fax'].'";'.
					'document.getElementsByName("city")[0].value="'.$comp['city'].'";'.
					'document.getElementsByName("postal_code")[0].value="'.$comp['postal_code'].'";'.
					'var country = $(\'country\');'.
					'var k = 0; while (k < country.options.length) if (country.options[k].value=="'.$comp['country'].'") break; else k++;'.
					'country.selectedIndex = k;'.
					'country.fire(\'e_u_cd:load\');'.
					'zone = $(\'zone\');'.
					'setTimeout("'.
					'k = 0; while (k < zone.options.length) if (zone.options[k].value==\''.$comp['zone'].'\') break; else k++;'.
					'zone.selectedIndex = k;'.
					'",900);'.
					'document.getElementsByName("web_address")[0].value="'.$comp['web_address'].'";';
				;
				$form->addElement('button', 'paste_company_info', 'Paste Company Info', array('onClick'=>$paste_company_info));
			}
		}
		if ($mode!='view' && Base_AclCommon::i_am_admin()) {
			$form->addElement('checkbox', 'create_new_user', Base_LangCommon::ts('CRM/Contacts','Create new user'), null, array('id'=>'crm_contacts_new_user','onClick'=>'document.getElementsByName("'.$field.'")[0].disabled=this.checked;$("crm_contacts_new_login").style.display=this.checked?"inline":"none";$("crm_contacts_select_user").style.display=this.checked?"none":"inline";'));
			$form->addElement('text', 'new_login', Base_LangCommon::ts('CRM/Contacts','New user login'), array('id'=>'crm_contacts_new_login'));
			eval_js('$("crm_contacts_new_login").style.display=$("crm_contacts_new_user").checked?"inline":"none";');
			eval_js('$("crm_contacts_select_user").style.display=$("crm_contacts_new_user").checked?"none":"inline";');
			$form->addFormRule(array('CRM_ContactsCommon','check_new_username'));
		}
		if (($default!==Acl::get_user() && $default!=='' && !Base_AclCommon::i_am_admin()) || $mode=='view') {
			$form->addElement('select', $field, $label, array($default=>($default!=='')?Base_UserCommon::get_user_login($default):'---'));
			$form->setDefaults(array($field=>$default));
			$form->freeze($field);
		} else {
			$ret = DB::Execute('SELECT id, login FROM user_login ORDER BY login');
			$users = array(''=>'---');
			while ($row=$ret->FetchRow()) {
				$contact_id = Utils_RecordBrowserCommon::get_id('contact','login',$row['id']);
				if ($contact_id===false || $contact_id===null || ($row['id']===$default && $mode!='add'))
					if (Base_AclCommon::i_am_admin() || $row['id']==Acl::get_user())
						$users[$row['id']] = $row['login'];
			}
			$form->addElement('select', $field, $label, $users, array('id'=>'crm_contacts_select_user'));
			$form->setDefaults(array($field=>$default));
		}
	}
	
	public static function check_new_username($arg) {
		if (!isset($arg['create_new_user']) || !$arg['create_new_user']) return true;
		if (!$arg['email']) return array('login'=>Base_LangCommon::ts('CRM/Contacts','E-mail is required when creating new user.'));
		if (!$arg['new_login']) return array('login'=>Base_LangCommon::ts('Libs/QuickForm','Field required'));
		if (Base_UserCommon::get_user_id($arg['new_login'])) return array('login'=>Base_LangCommon::ts('Base/User/Administrator','Username already taken'));
		return true;
	}
	
	public static function maplink($r,$nolink,$desc) {
		return Utils_TooltipCommon::create('<a href="http://maps.google.com/?'.http_build_query(array('q'=>Utils_CommonDataCommon::get_value('Countries/'.$r['country']).', '.$r['city'].', '.$r['address_1'].' '.$r['address_2'])).'" target="_blank">'.$r[$desc['id']].'</a>',Base_LangCommon::ts('CRM_Contacts','Click here to search this location using google maps'));
	}
	
	public static function display_fname($v, $nolink) {
		return Utils_RecordBrowserCommon::create_linked_label_r('contact', 'First Name', $v, $nolink);
	}
	public static function display_lname($v, $nolink) {
		return Utils_RecordBrowserCommon::create_linked_label_r('contact', 'Last Name', $v, $nolink);
	}
	public static function display_cname($v, $nolink) {
		return Utils_RecordBrowserCommon::create_linked_label_r('company', 'Company Name', $v, $nolink);
	}
	public static function display_webaddress($record, $nolink, $desc) {
		$v = $record[$desc['id']];
		$v = trim($v, ' ');
		if ($v=='') return '';
		if (strpos(strtolower($v), 'http://')===false && 
			strpos(strtolower($v), 'https://')===false && 
			$v) $v = 'http://'.$v;
		return '<a href="'.$v.'" target="_blank">'.$v.'</a>';
	}
	public static function display_email($record, $nolink, $desc) {
		$v = $record[$desc['id']];
		return '<a href="mailto:'.$v.'">'.$v.'</a>';
	}
	public static function display_login($record, $nolink, $desc) {
		$v = $record[$desc['id']];
		if (isset($_REQUEST['crm_contacts_edit_user']) &&
			is_numeric($_REQUEST['crm_contacts_edit_user']) &&
			Base_AclCommon::i_am_admin() &&
			Base_UserCommon::get_user_login($_REQUEST['crm_contacts_edit_user'])) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			$x->push_main('CRM/Contacts','edit_user_form',array($_REQUEST['crm_contacts_edit_user']));
		}
		if (!$v)
			return '---';
		else {
			$login = Base_UserCommon::get_user_login($v);
			if (Base_AclCommon::i_am_admin()) return '<a '.Module::create_href(array('crm_contacts_edit_user'=>$v)).'>'.$login.'</a>';
			else return $login;
		}
	}
	public static function submit_contact($values, $mode) {
		switch ($mode) {
		case 'view':
			$is_employee = false;
			if (is_array($values['company_name']) && in_array(CRM_ContactsCommon::get_main_company(), $values['company_name'])) $is_employee = true;
			$me = CRM_ContactsCommon::get_my_record();
			$emp = array($me['id']);
			$cus = array();
			if ($is_employee) $emp[] = $values['id'];
			else $cus[] = $values['id'];
			$ret = array();
			if (ModuleManager::is_installed('CRM/Calendar')!==-1) $ret['new_event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Event')).' '.CRM_CalendarCommon::create_new_event_href(array('emp_id'=>$emp,'cus_id'=>$cus)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/Tasks')!==-1) $ret['new_task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/PhoneCall')!==-1) $ret['new_phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Contacts','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('date_and_time'=>date('Y-m-d H:i:s'),'contact'=>$values['id'],'employees'=>$me['id'],'status'=>0, 'permission'=>0, 'priority'=>1,'company_name'=>((isset($values['company_name'][0]))?$values['company_name'][0]:''))).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
			return $ret;
		case 'add':
			if ($values['email']=='' && $values['login']!=0 && $mode=='add')
				$values['email'] = DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d', array($values['login']));
		case 'edit':
			if (isset($values['create_company'])) {
				$comp_id = Utils_RecordBrowserCommon::new_record('company',
					array(	'company_name'=>$values['create_company_name'],
							'address_1'=>$values['address_1'],
							'address_2'=>$values['address_2'],
							'country'=>$values['country'],
							'city'=>$values['city'],
							'zone'=>isset($values['zone'])?$values['zone']:'',
							'postal_code'=>$values['postal_code'],
							'phone'=>$values['work_phone'],
							'fax'=>$values['fax'],
							'web_address'=>$values['web_address'],
							'permission'=>$values['permission'])
				);
				if (!isset($values['company_name'])) $values['company_name'] = array(); 
				if (!is_array($values['company_name'])) $values['company_name'] = array($values['company_name']);
				$values['company_name'][] = $comp_id;
			}
			if (Base_AclCommon::i_am_admin() && isset($values['create_new_user']) && $values['create_new_user']) {
				Base_User_LoginCommon::add_user($values['new_login'], $values['email']);
				$values['login'] = Base_UserCommon::get_user_id($values['new_login']);
			}
		}
		return $values;
	}

	public static function search($word){
		$ret = array();
		if(self::Instance()->acl_check('browse contacts')) {
			$result = self::get_contacts(array('"first_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('contact', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Contact #%d, %s %s', array($row['id'], $row['first_name'], $row['last_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
	 		
			$result = self::get_contacts(array('"last_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('contact', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Contact #%d, %s %s', array($row['id'], $row['first_name'], $row['last_name'])).Utils_RecordBrowserCommon::record_link_close_tag();

			$attachs = Utils_AttachmentCommon::search_group('CRM/Contact',$word);
			$attach_contact_ids = array();
			foreach($attachs as $x) {
				if(ereg('CRM/Contact/([0-9]+)',$x['group'],$reqs))
					$attach_contact_ids[$reqs[1]] = true;
			}
			$attach_contact_ids2 = array_keys($attach_contact_ids);
			$result = self::get_contacts(array('id'=>$attach_contact_ids2));

	 		foreach ($result as $row)
 				$ret['a_'.$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('contact', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Contact (attachment) #%d, %s %s', array($row['id'], $row['first_name'], $row['last_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
 		}
		if(self::Instance()->acl_check('browse companies')) {
			$result = self::get_companies(array('"company_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('company', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Company #%d, %s', array($row['id'], $row['company_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
	 		
			$result = self::get_companies(array('"short_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('company', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Company #%d, %s', array($row['id'], $row['company_name'])).Utils_RecordBrowserCommon::record_link_close_tag();

			$attachs = Utils_AttachmentCommon::search_group('CRM/Company',$word);
			$attach_company_ids = array();
			foreach($attachs as $x) {
				if(ereg('CRM/Company/([0-9]+)',$x['group'],$reqs))
					$attach_company_ids[$reqs[1]] = true;
			}
			$attach_company_ids2 = array_keys($attach_company_ids);
			$result = self::get_companies(array('id'=>$attach_company_ids2));

	 		foreach ($result as $row)
 				$ret['a_'.$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('company', $row['id']).Base_LangCommon::ts('CRM_Contacts', 'Company (attachment) #%d, %s', array($row['id'], $row['company_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
 		}
		
		return $ret;
	}

	public static function contact_watchdog_label($rid = null, $events = array()) {
		return Utils_RecordBrowserCommon::watchdog_label(
				'contact',
				Base_LangCommon::ts('CRM_Contacts','Contacts'),
				$rid,
				$events,
				array('CRM_ContactsCommon','contact_format_default')
			);
	}
	public static function company_watchdog_label($rid = null, $events = array()) {
		return Utils_RecordBrowserCommon::watchdog_label(
				'company',
				Base_LangCommon::ts('CRM_Contacts','Companies'),
				$rid,
				$events,
				'company_name'
			);
	}
	
	//////////////////////////
	// mobile devices
	public function mobile_menu() {
		if(!Acl::is_user())
			return array();
		return array('Contacts'=>'mobile_contacts','Companies'=>'mobile_companies');
	}
	
	public function mobile_contacts() {
		Utils_RecordBrowserCommon::mobile_rb('contact',array(),array('last_name'=>'ASC', 'first_name'=>'ASC'));
	}
	
	public function mobile_companies() {
		Utils_RecordBrowserCommon::mobile_rb('company',array(),array('company_name'=>'ASC'));	
	}
}
?>

<?php
class epesi_archive extends rcube_plugin
{
  public $task = 'mail';
  public $archive_mbox = 'Epesi Archive';

  function init()
  {
    $this->register_action('plugin.epesi_archive', array($this, 'request_action'));

    $rcmail = rcmail::get_instance();

    if ($rcmail->action == '' || $rcmail->action == 'show') {
      $skin_path = $this->local_skin_path();

      $this->include_script('archive.js');
      $this->add_texts('localization', true);
      $this->add_button(
        array(
            'command' => 'plugin.epesi_archive',
            'imagepas' => $skin_path.'/archive_pas.png',
            'imageact' => $skin_path.'/archive_act.png',
            'title' => 'buttontitle',
            'domain' => $this->ID,
        ),
        'toolbar');

      // register hook to localize the archive folder
      $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));

      // set env variable for client
      $rcmail->output->set_env('archive_mailbox', $this->archive_mbox);
      $rcmail->output->set_env('archive_mailbox_icon', $this->url($skin_path.'/foldericon.png'));

      // add archive folder to the list of default mailboxes
      if (($default_folders = $rcmail->config->get('default_imap_folders')) && !in_array($archive_mbox, $default_folders)) {
        $default_folders[] = $archive_mbox;
        $rcmail->config->set('default_imap_folders', $default_folders);
      }

      if(!$rcmail->config->get('create_default_folders'))
        $this->add_hook('list_mailboxes', array($this, 'add_mailbox'));
    }
  }

  function render_mailboxlist($p)
  {
    $rcmail = rcmail::get_instance();
    $archive_mbox = $this->archive_mbox;

    // set localized name for the configured archive folder
    if (isset($p['list'][$archive_mbox]))
        $p['list'][$archive_mbox]['name'] = $this->gettext('archivefolder');

    return $p;
  }

  function look_contact($addr) {
    global $E_SESSION;
    $contact = DB::GetOne('SELECT id FROM contact_data_1 WHERE active=1 AND f_email=%s AND (f_permission<2 OR created_by=%d)',array($addr,$E_SESSION['user']));
    if($contact!==false) {
        return 'P:'.$contact;
    }
    $company = DB::GetOne('SELECT id FROM company_data_1 WHERE active=1 AND f_email=%s AND (f_permission<2 OR created_by=%d)',array($addr,$E_SESSION['user']));
    if($company!==false) {
        return 'C:'.$company;
    }

    $rcmail = rcmail::get_instance();
    $rcmail->output->command('display_message', $this->gettext('contactnotfound').' '.$addr, 'error');
    $rcmail->output->send();
  }

  function request_action()
  {
    global $E_SESSION;
    $this->add_texts('localization');

    $rcmail = rcmail::get_instance();
    $uids = get_input_value('_uid', RCUBE_INPUT_POST);
    $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
    if($mbox==$this->archive_mbox || $mbox==$rcmail->config->get('drafts_mbox')) {
        $rcmail->output->command('display_message', $this->gettext('invalidfolder'), 'error');
        $rcmail->output->send();
        return;
    }
    $sent_mbox = ($rcmail->config->get('sent_mbox')==$mbox);

    $msgs = array();
    $uids = explode(',',$uids);
    foreach($uids as $uid) {
        $msg = new rcube_message($uid);
        if (empty($msg->headers)) {
            $rcmail->output->show_message('messageopenerror', 'error');
            $rcmail->output->send();
            return;
        } else {
            $msgs[] = $msg;
        }
    }

    $map = array();
    foreach($msgs as $k=>$msg) {
        //error_log(print_r($msg->mime_parts,true));
        if($sent_mbox) {
            $sends = $rcmail->imap->decode_address_list($msg->headers->to);
            $map[$k] = array();
            foreach($sends as $send) {
                $addr = $send['mailto'];
                $ret = $this->look_contact($addr);
                if(!$ret) return;
                $map[$k][] = $ret;
            }
        } else {
            $addr = $msg->sender['mailto'];
            $ret = $this->look_contact($addr);
            if(!$ret) return;
            $map[$k] = array($ret);
        }
    }

    $attachments_dir = '../../../../'.DATA_DIR.'/CRM_Roundcube/attachments/';
    $epesi_mails = array();
    if(!file_exists($attachments_dir)) mkdir($attachments_dir);
    foreach($msgs as $k=>$msg) {
        $contacts = $map[$k];//'__'.implode('__',$map[$k]).'__';
        $mime_map = array();
        foreach($msg->mime_parts as $mid=>$m)
            $mime_map[$m->mime_id] = md5($k.microtime(true).$mid);
        if($msg->has_html_part()) {
            $body = $msg->first_html_part();
            foreach ($msg->mime_parts as $mime_id => $part) {
                $mimetype = strtolower($part->ctype_primary . '/' . $part->ctype_secondary);
                if ($mimetype == 'text/html') {
                    if(isset($part->replaces))
                        $cid_map = $part->replaces;
                    else
                        $cid_map = array();
                    break;
                }
            }
            foreach($cid_map as $k=>&$v) {
                $x = strrchr($v,'=');
                if(!$x) unset($cid_map[$k]);
                else {
                    $mid = substr($x,1);
                    if(isset($mime_map[$mid]))
                        $v = 'get.php?'.http_build_query(array('mail_id'=>'__MAIL_ID__','mime_id'=>$mime_map[$mid]));
                }
            }
            $body = rcmail_wash_html($body,array('safe'=>true,'inline_html'=>true),$cid_map);
        } else {
            $body = '<pre>'.$msg->first_text_part().'</pre>';
        }
        $date = $msg->get_header('timestamp');
        $headers = array();
        foreach($msg->headers as $k=>$v) {
            if(is_string($v))
                $headers[] = $k.': '.$v;
        }
        $employee = DB::GetOne('SELECT id FROM contact_data_1 WHERE active=1 AND f_login=%d',array($E_SESSION['user']));
        $id = Utils_RecordBrowserCommon::new_record('rc_mails',array('contacts'=>$contacts,'date'=>$date,'employee'=>$employee,'subject'=>substr($msg->subject,0,256),'body'=>$body,'headers_data'=>implode("\n",$headers),'direction'=>$sent_mbox));
        $epesi_mails[] = $id;
        foreach($contacts as $c) {
            list($rs,$con_id) = explode(':',$c);
            if($rs=='P')
                Utils_WatchdogCommon::new_event('contact',$con_id,'N_New mail');
            else
                Utils_WatchdogCommon::new_event('company',$con_id,'N_New mail');
        }
        Utils_WatchdogCommon::new_event('contact',$employee,'N_New mail');
        /*DB::Execute('INSERT INTO rc_mails_data_1(created_on,created_by,f_contacts,f_date,f_employee,f_subject,f_body,f_headers_data,f_direction) VALUES(%T,%d,%s,%T,%d,%s,%s,%s,%b)',array(
                    time(),$E_SESSION['user'],$contacts,$date,$employee,substr($msg->subject,0,256),$body,implode("\n",$headers),$sent_mbox));
        $id = DB::Insert_ID('rc_mails_data_1','id');*/
        foreach($msg->mime_parts as $mid=>$m) {
            if(!$m->disposition) continue;
            if(isset($cid_map['cid:'.$m->content_id]))
                $attachment = 0;
            else
                $attachment = 1;
            DB::Execute('INSERT INTO rc_mails_attachments(mail_id,type,name,mime_id,attachment) VALUES(%d,%s,%s,%s,%b)',array($id,$m->mimetype,$m->filename,$mime_map[$m->mime_id],$attachment));
            if(!file_exists($attachments_dir.$id)) mkdir($attachments_dir.$id);
            $fp = fopen($attachments_dir.$id.'/'.$mime_map[$m->mime_id],'w');
            $msg->get_part_content($m->mime_id,$fp);
            fclose($fp);
        }
    }

    //$rcmail->output->command('delete_messages');
    $rcmail->output->command('move_messages', $this->archive_mbox);
    $rcmail->output->command('display_message', $this->gettext('archived'), 'confirmation');

    global $E_SESSION_ID;
    $lifetime = ini_get("session.gc_maxlifetime");
    if(DATABASE_DRIVER=='mysqlt') {
        if(!DB::GetOne('SELECT GET_LOCK(%s,%d)',array($E_SESSION_ID,ini_get('max_execution_time'))))
            trigger_error('Unable to get lock on session name='.$E_SESSION_ID,E_USER_ERROR);
    }
    $ret = DB::GetOne('SELECT data FROM session WHERE name = %s AND expires > %d', array($E_SESSION_ID, time()-$lifetime));
    if($ret) {
        $ret = unserialize($ret);
        $ret['rc_mails_cp'] = $epesi_mails;
        $data = serialize($ret);
        if(DATABASE_DRIVER=='postgres') $data = '\''.DB::BlobEncode($data).'\'';
        else $data = DB::qstr($data);
        $ret &= DB::Replace('session',array('expires'=>time(),'data'=>$data,'name'=>DB::qstr($E_SESSION_ID)),'name');
        if(DATABASE_DRIVER=='mysqlt') {
            DB::Execute('SELECT RELEASE_LOCK(%s)',array($E_SESSION_ID));
        }
    }

    $rcmail->output->send();
  }

  function add_mailbox($p) {
    if($p['root']=='') {
        $rcmail = rcmail::get_instance();
        if(!$rcmail->imap->mailbox_exists($this->archive_mbox))
            $rcmail->imap->create_mailbox($this->archive_mbox,true);
        elseif(!$rcmail->imap->mailbox_exists($this->archive_mbox,true))
            $rcmail->imap->subscribe($this->archive_mbox);
    }
  }
}
<?php
/**
 * Download file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage RecordBrowser
 */
if (!isset($_REQUEST['cid']) || !isset($_REQUEST['path']) || !isset($_REQUEST['tab']) || !isset($_REQUEST['admin'])) 
    die('Invalid usage - missing param');
$cid = $_REQUEST['cid'];
$tab = $_REQUEST['tab'];
$admin = $_REQUEST['admin'];
$path = $_REQUEST['path'];
define('CID', $cid);
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$order = Module::static_get_module_variable($path, 'order_stuff', null);
if ($crits === null || $order === null) {
    $crits = $order = array();
}
ModuleManager::load_modules();
if (!Utils_RecordBrowserCommon::get_access($tab, 'export') && !Base_AclCommon::i_am_admin()) 
    die('Access denied');
set_time_limit(0);
$tab_info = Utils_RecordBrowserCommon::init($tab);
$records = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), $order, array(), $admin);
header('Content-Type: text/csv');
//header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachement; filename="' . $tab . '_export_' . date('Y_m_d__H_i_s') . '.csv"');
if (headers_sent()) 
    die('Some data has already been output to browser, can\'t send the file');
$cols = array(
    __('Record ID'),
    __('Created on'),
    __('Created by'),
    __('Edited on'),
    __('Edited by'),
);
foreach ($tab_info as $v) {
    if (!$v['export']) 
        continue;
    $cols[] = _V($v['name']);
    if ($v['style'] == 'currency') 
        $cols[] = _V($v['name']) . ' - ' . __('Currency');
}
$f = fopen('php://output', 'w');
fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, $cols);
$currency_codes = DB::GetAssoc('SELECT symbol, code FROM utils_currency');

function rb_csv_export_format_currency_value($v, $symbol) {
    static $currency_decimal_signs = null;
    static $currency_thou_signs;
    if ($currency_decimal_signs === null) {
        $currency_decimal_signs = DB::GetAssoc('SELECT symbol, decimal_sign FROM utils_currency');
        $currency_thou_signs = DB::GetAssoc('SELECT symbol, thousand_sign FROM utils_currency');
    }
    $v = str_replace($currency_thou_signs[$symbol], '', $v);
    $v = str_replace($currency_decimal_signs[$symbol], '.', $v);
    return $v;
}
foreach ($records as $r) {
    $has_access = Utils_RecordBrowserCommon::get_access($tab, 'view', $r);
    if (!$has_access) 
        continue;
    $rec = array(
        $r['id'],
    );
    $details = Utils_RecordBrowserCommon::get_record_info($tab, $r['id']);
    $rec[] = $details['created_on'];
    $rec[] = Base_UserCommon::get_user_label($details['created_by'], true);
    $rec[] = $details['edited_on'];
    $rec[] = $details['edited_by'] ? Base_UserCommon::get_user_label($details['edited_by'], true) : '';
    foreach ($tab_info as $field_name => $v) {
        if (!$v['export']) 
            continue;
        ob_start();
        if (!isset($has_access[$v['id']]) || !$has_access[$v['id']]) 
            $val = '';
        else 
            $val = Utils_RecordBrowserCommon::get_val($tab, $field_name, $r, true, $v);
        ob_end_clean();
        $val = str_replace('&nbsp;', ' ', htmlspecialchars_decode(strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/', "\n", $val))));
        if ($v['style'] == 'currency') {
            $val = str_replace(' ', '_', $val);
            $val = explode(';', $val);
            if (isset($val[1])) {
                $final = array();
                foreach ($val as $v) {
                    $v = explode('_', $v);
                    if (isset($v[1])) 
                        $final[] = rb_csv_export_format_currency_value($v[0], $v[1]) . ' ' . $currency_codes[$v[1]];
                }
                $rec[] = implode('; ', $final);
                $rec[] = '---';
                continue;
            }
            $val = explode('_', $val[0]);
            $currency_symbol = '---';
            $last = end($val);
            $first = reset($val);
            if (isset($currency_codes[$first])) {
                $currency_symbol = array_shift($val);
            } elseif (isset($currency_codes[$last])) {
                $currency_symbol = array_pop($val);
            }
            $value = implode('', $val);
            if (isset($currency_codes[$currency_symbol])) {
                $rec[] = rb_csv_export_format_currency_value($value, $currency_symbol);
                $rec[] = $currency_codes[$currency_symbol];
            } else {
                $rec[] = $value;
                $rec[] = $currency_symbol;
            }
        } else {
            $rec[] = trim($val);
        }
    }
    fputcsv($f, $rec);
}

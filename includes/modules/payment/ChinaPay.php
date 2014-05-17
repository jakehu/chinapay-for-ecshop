<?php

/**
* ECSHOP 上海银联在线插件
* ============================================================================
* 版权所有 2005-2008 上海商派网络科技有限公司，并保留所有权利。
* 网站地址: http://www.ecshop.com；
* ----------------------------------------------------------------------------
* 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
* 使用；不允许对程序代码以任何形式任何目的的再发布。
* ============================================================================
 * jakehu
 * http://www.jakehu.me/
*/

if (!defined('IN_ECS'))
{
die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/ChinaPay.php';

include_once(ROOT_PATH ."includes/modules/payment/chinapay/netpayclient_config.php");
include_once(ROOT_PATH ."includes/modules/payment/chinapay/netpayclient.php");

if (file_exists($payment_lang))
{
global $_LANG;

include_once($payment_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
$i = isset($modules) ? count($modules) : 0;

/* 代码 */
$modules[$i]['code'] = basename(__FILE__, '.php');

/* 描述对应的语言项 */
$modules[$i]['desc'] = 'chinapay_desc';

/* 是否支持货到付款 */
$modules[$i]['is_cod'] = '0';

/* 是否支持在线支付 */
$modules[$i]['is_online'] = '1';

/* 支付费用 */
$modules[$i]['pay_fee'] = '0';

/* 作者 */
$modules[$i]['author'] = 'jakehu';

/* 网址 */
$modules[$i]['website'] = 'http://www.jakehu.me';

/* 版本号 */
$modules[$i]['version'] = '1.0.0';

/* 配置信息 */
$modules[$i]['config'] = array(
array('name' => 'chinapay_account', 'type' => 'text', 'value' => ''),
array('name' => 'chinapay_merkey_file', 'type' => 'text', 'value' => ''),
array('name' => 'chinapay_pubkey_file', 'type' => 'text', 'value' => '')
);

return;
}

/**
* 类
*/
class chinapay
{
/**
* 构造函数
*
* @access public
* @param
*
* @return void
*/
function __construct()
{
$this->chinapay();
}

function chinapay()
{
}

/**
* 生成支付代码
* @param array $order 订单信息
* @param array $payment 支付方式信息
*/
function get_code($order, $payment)
{
$MerId = trim($payment['chinapay_account']);
$OrdId = ecshopsn2chinapaysn($order['order_sn'], $MerId);
$TransAmt = formatamount($order['order_amount']);
$CuryId = '156'; 
$TransDate = date('Ymd',time());
$TransType = '0001'; 
$Version = '20070129';
$GateId = '';
$data_vreturnurl = return_url(basename(__FILE__, '.php'));
$Priv1 = ""; 
$merkey_file= trim($payment['chinapay_merkey_file']);
//导入私钥文件, 返回值即为您的商户号，长度15位
$merid = buildKey(ROOT_PATH . $merkey_file);
if(!$merid) {
echo "导入私钥文件失败！";
exit;
}
//按次序组合订单信息为待签名串
$plain = $merid . $OrdId . $TransAmt . $CuryId . $TransDate . $TransType . $Priv1;
//生成签名值，必填
$chkvalue = sign($plain);
if (!$chkvalue) {
	echo "签名失败！";
	exit;
}
$def_url = "<br /><form style='text-align:center;' method=post action='".REQ_URL_PAY."' target='_blank'>";
$def_url .= "<input type=HIDDEN name='MerId' value='".$MerId."'/>"; 
$def_url .= "<input type=HIDDEN name='OrdId' value='".$OrdId."'>";
$def_url .= "<input type=HIDDEN name='TransAmt' value='".$TransAmt."'>";
$def_url .= "<input type=HIDDEN name='CuryId' value='".$CuryId."'>"; 
$def_url .= "<input type=HIDDEN name='TransDate' value='".$TransDate."'>";
$def_url .= "<input type=HIDDEN name='TransType' value='".$TransType."'>";
$def_url .= "<input type=HIDDEN name='Version' value='".$Version."'>";
$def_url .= "<input type=HIDDEN name='BgRetUrl' value='".$data_vreturnurl."'>";
$def_url .= "<input type=HIDDEN name='PageRetUrl' value='".$data_vreturnurl."'>";
$def_url .= "<input type=HIDDEN name='GateId' value='".$GateId."'>";
$def_url .= "<input type=hidden name='Priv1' value='".$Priv1."'>"; 
$def_url .= "<input type=HIDDEN name='ChkValue' value='".$chkvalue."'>";
$def_url .= "<input type=submit value='" .$GLOBALS['_LANG']['pay_button']. "'>";
$def_url .= "</form>";
return $def_url;
}

/**
* 响应操作
*/
function respond()
{
//order_paid($v_oid);
//return true;
$payment = get_payment(basename(__FILE__, '.php'));

$merid = trim($_POST['merid']);
$orderno = trim($_POST['orderno']);
$transdate = trim($_POST['transdate']);
$amount = trim($_POST['amount']);
$currencycode = trim($_POST['currencycode']);
$transtype = trim($_POST['transtype']);
$status = trim($_POST['status']);
$checkvalue = trim($_POST['checkvalue']);
$v_gateid = trim($_POST['GateId']);
$v_Priv1 = trim($_POST['Priv1']);
/**
* 重新计算密钥的值
*/
$pubkey = $payment['chinapay_pubkey_file'];
$PGID = buildKey(ROOT_PATH . $pubkey);
if(!$PGID) {
echo "导入私钥文件失败！";
exit;
}
$verify = verifyTransResponse($merid, $orderno, $amount, $currencycode, $transdate, $transtype, $status, $checkvalue);
if (!$verify) {
echo "验证签名失败！";
exit;
}
/* 检查秘钥是否正确 */
if ($status == '1001')
{
$v_ordesn = chinapaysn2ecshopsn($orderno);
$order_id = get_order_id_by_sn($v_ordesn);
/* 改变订单状态 */
order_paid($order_id);
return true;
}
else
{
return false;
}
}
}


/*
*本地订单号转为银联订单号
*/
function ecshopsn2chinapaysn($order_sn, $vid){
if($order_sn && $vid){
$sub_vid = substr($vid, 10, 5);
$sub_start = substr($order_sn, 2, 4);
$sub_end = substr($order_sn, 6);
$temp = $pay_id;
return $sub_start . $sub_vid . $sub_end;
}
}

/*
*银联订单号转为本地订单号
*/
function chinapaysn2ecshopsn($chinapaysn){
if($chinapaysn){ 
$year = date('Y',time());

return substr($year,0,2) . substr($chinapaysn, 0, 4) . substr($chinapaysn, 9) ;
}
}

/*
*格式化交易金额，以分位单位的12位数字。
*/
function formatamount($amount){
if($amount){
if(!strstr($amount, ".")){
$amount = $amount.".00";
}
$amount = str_replace(".", "", $amount);
$temp = $amount;
for($i=0; $i< 12 - strlen($amount); $i++){
$temp = "0" . $temp;
}
return $temp;
}
}
?>
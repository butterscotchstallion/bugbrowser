<?php

$live = true;

if($live) {
    $root = "C:\Inetpub\wwwroot\bugbrowser\smarty_templates\\";
    $smarty_path = "C:\Inetpub\wwwroot\bugbrowser\inc\Smarty\Smarty.class.php";
} else {
    $root = "c:\\wamp\\smarty\\";
    $smarty_path = 'c:\wamp\www\bugbrowser\inc\Smarty\Smarty.class.php';
}

session_start();
ini_set('display_errors', 'on');
require($smarty_path);
//require('inc/connect.php');
require('inc/adodb.inc.php');
require('inc/misc.functions.php');
require('inc/account.functions.php');
require('inc/issue.functions.php');

$tpl = new Smarty();
$tpl->template_dir = $root . "templates";
$tpl->compile_dir = $root . "templates_c";
$tpl->cache_dir = $root . "cache";
$tpl->config_dir = $root . "configs";

//print_r($_SESSION);
?>
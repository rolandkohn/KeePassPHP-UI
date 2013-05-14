<?php

require_once "kphpdisplay/basic.php";
require_once "kphpdisplay/maindisplay.php";

define("MAX_FILE_SIZE", 100000);

function getFile($k)
{
		if(!isset($_FILES[$k]))
				return null;
		$f = $_FILES[$k];
		if($f['name'] == '' || $f['error'] == UPLOAD_ERR_NO_FILE || $f['tmp_name'] == '')
				return null;
		return $f;
}

function checkFile($k, $f, $display)
{
	if($f == null)
	{
		$display->setIfEmpty($k, MainDisplay::HI_EMPTY, 'warning');
		return null;
	}
	if($f['error'] == UPLOAD_ERR_INI_SIZE || $f['error'] == UPLOAD_ERR_FORM_SIZE
		|| $f['size'] > MAX_FILE_SIZE)
	{
		$display->setIfEmpty($k, MainDisplay::HI_FILETOOBIG, 'error');
		return null;
	}
	if($f['error'] != UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']))
	{
		$display->setIfEmpty($k, MainDisplay::HI_FILEERROR, 'error');
		return null;
	}
	return $f['tmp_name'];
}

$display = new MainDisplay();

$submitted = getString("submitted", $_POST);
if($submitted == "add")
{
	if(($dbid = getString("addDbid", $_POST)) == "")
		$display->setIfEmpty("addDbid", MainDisplay::HI_EMPTY, "warning");
	if(($mainPwd = getString("addMainPwd", $_POST)) == "")
		$display->setIfEmpty("addMainPwd", MainDisplay::HI_EMPTY, "warning");
	$kdbxFile = checkFile("addKdbxFile", getFile("addKdbxFile"), $display);
	$pwd1 = getString("addPwd1", $_POST);
	$keyfile = getFile("addFile1");
	if(!($usePwdForCK = (getString("addUsePwdForCK", $_POST, "") != "")) &&
		$pwd1 == "" && $keyfile == null)
	{
		$display->setIfEmpty("addUsePwdForCK", null, "error");
		$display->setIfEmpty("addPwd1", MainDisplay::HI_NOOTHERKEY, "error");
		$display->setIfEmpty("addFile1", MainDisplay::HI_ERROR, "error");
	}
	if(!$display->isError)
	{
		require_once "keepassphp/keepassphp.php";
		KeePassPHP::init(false);
		if(!KeePassPHP::exists($dbid) || KeePassPHP::checkPassword($dbid, $mainPwd))
		{
			$keys = $usePwdForCK ? array(array(KeePassPHP::KEY_PWD, $mainPwd)) : array();
			if($pwd1 != '')
				$keys[] = array(KeePassPHP::KEY_PWD, $pwd1);
			if($keyfile != null)
				if(($keyfile = checkFile("addFile1", $keyfile, $display)) != null)
					$keys[] = array(KeePassPHP::KEY_FILE, $keyfile);
			if(KeePassPHP::checkKeys($kdbxFile, $keys))
			{
				if(KeePassPHP::tryAdd($kdbxFile, $dbid, $mainPwd, $keys))
					$display->addSuccess = true;
			}
			else
			{
				if($usePwdForCK)
					$display->setIfEmpty("addMainPwd", MainDisplay::HI_BADPWD, "error");
				if($pwd1 != "")
					$display->setIfEmpty("addPwd1", MainDisplay::HI_BADPWD, "error");
				if($keyfile != null)
					$display->setIfEmpty ("addFile1", MainDisplay::HI_BADPWD, "error");
			}
		}
		else
		{
			$display->setIfEmpty("addDbid", MainDisplay::HI_IDEXISTS, "error");
			$display->setIfEmpty("addMainPwd", null, "error");
		}
		$display->addDebug(KeepassPHP::$errordump);
		if(KeePassPHP::$isError)
			$display->raiseError(KeePassPHP::$errordump);
	}
}

$display->display();

?>
<?php
//
// Codendi
// Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
// http://www.codendi.com
//
// 
//
// Originally written by Laurent Julliard 2004, Codendi Team, Xerox
//

$FILE_ACCEPTED = $GLOBALS['sys_custom_dir'].'/CODENDI_LICENSE_ACCEPTED';
$FILE_DECLINED = $GLOBALS['sys_custom_dir'].'/CODENDI_LICENSE_DECLINED';


/**
 * Says whether the license terms have already been displayed or not
 *
 *
 * @return boolean
 */
function license_already_displayed() {
    global $FILE_ACCEPTED, $FILE_DECLINED;
    return file_exists($FILE_ACCEPTED) || file_exists($FILE_DECLINED);
}

/**
 * Says whether the license terms have already been declined
 *
 *
 * @return boolean
 */
function license_already_declined() {
    global $FILE_DECLINED;
    return file_exists($FILE_DECLINED);
}

/**
 * The license terms are accepted so create the file that says it was accepted
 *
 * @return true
 */
function license_accepted() {
    global $FILE_ACCEPTED, $sys_lf, $Language;

    // Open the file and go to the end
    $fp = @fopen($FILE_ACCEPTED,'a+');

    if (!$fp) {
      exit_error($Language->getText('global','error'),$Language->getText('include_license','cannot_open_acc',$FILE_ACCEPTED));
    }

    // Write Date in file
    $msg = $Language->getText('include_license','license_accepted').' '.format_date($GLOBALS['Language']->getText('system', 'datefmt'),time()).$sys_lf;

    if (fwrite($fp, $msg) == FALSE) {
      exit_error($Language->getText('global','error'),$Language->getText('include_license','cannot_write_acc',$FILE_ACCEPTED));
   }

   fclose($fp);
   return true;
	
}

/**
 * The license terms were declined so remove the write it in the proper file
 *
 * @return true
 */
function license_declined() {
    global $FILE_DECLINED, $sys_lf, $Language;

    // Open the file and go to the end
    $fp = @fopen($FILE_DECLINED,'a+');

    if (!$fp) {
      exit_error($Language->getText('global','error'),$Language->getText('include_license','cannot_open_decl',$FILE_DECLINED));
    }

    // Write Date in file
    $msg = $Language->getText('include_license','license_declined')." ".
	format_date($GLOBALS['Language']->getText('system', 'datefmt'),time()).$sys_lf;

    if (fwrite($fp, $msg) == FALSE) {
      exit_error($Language->getText('global','error'),$Language->getText('include_license','cannot_write_decl',$FILE_DECLINED));
   }
   fclose($fp);

   return true;
}

function license_msg_accepted() {
    global $Language;
    return $Language->getText('include_license','msg_accept');
}

function license_msg_declined() {
    global $Language;
    return $Language->getText('include_license','msg_declined');
}

?>

<?php

if (!defined('_root')) {
    exit;
}

function _HCM_mailform($adresa = "", $predmet = null)
{
    // priprava
    $result = "";
    $_SESSION['hcm_' . Sunlight\Core::$hcmUid . '_mail_receiver'] = implode(",", _arrayRemoveValue(explode(";", trim($adresa)), ""));
    if (isset($predmet)) {
        $rsubject = " value='" . _e($predmet) . "'";
    } else {
        $rsubject = "";
    }
    $rcaptcha = _captchaInit();

    // zprava
    $msg = '';
    if (isset($_GET['hcm_mr_' . Sunlight\Core::$hcmUid])) {
        switch (_get('hcm_mr_' . Sunlight\Core::$hcmUid)) {
            case 1:
                $msg = _msg(_msg_ok, $GLOBALS['_lang']['hcm.mailform.msg.done']);
                break;
            case 2:
                $msg = _msg(_msg_warn, $GLOBALS['_lang']['hcm.mailform.msg.failure']);
                break;
            case 3:
                $msg = _msg(_msg_err, $GLOBALS['_lang']['global.emailerror']);
                break;
            case 4:
                $msg = _msg(_msg_err, $GLOBALS['_lang']['xsrf.msg']);
                break;
        }
    }

    // predvyplneni odesilatele
    if (_login) {
        $sender = _loginemail;
    } else {
        $sender = "&#64;";
    }

    $result .= $msg
        . _formOutput(
            array(
                'id' =>  'hcm_mform_' . Sunlight\Core::$hcmUid,
                'name' => 'mform' . Sunlight\Core::$hcmUid,
                'action' => _link('system/script/hcm/mform.php?_return=' . rawurlencode($GLOBALS['_index']['url'])),
                'submit_text' => $GLOBALS['_lang']['hcm.mailform.send'],
            ),
            array(
                array('label' => $GLOBALS['_lang']['hcm.mailform.sender'], 'content' => "<input type='text' class='inputsmall' name='sender' value='" . $sender . "'><input type='hidden' name='fid' value='" . Sunlight\Core::$hcmUid . "'>"),
                array('label' => $GLOBALS['_lang']['posts.subject'], 'content' => "<input type='text' class='inputsmall' name='subject'" . $rsubject . ">"),
                $rcaptcha,
                array('label' => $GLOBALS['_lang']['hcm.mailform.text'], 'content' => "<textarea class='areasmall' name='text' rows='9' cols='33'></textarea>", 'top' => true),
            )
        );

    return $result;
}

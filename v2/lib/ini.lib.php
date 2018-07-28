<?php

/* Tout ce qu'il faut pour Initialiser un membre */
/* require:
  lib/tpl.class.php et la variable $_tpl
  lib/vld.lib.php
  lib/session.class.php et la variable $_ses
 */

function mail_chg_pass($cond) { // envoyer un mail de changement de pass
    global $_tpl, $_ses;

    $mbr_array = Mbr::getInit($cond);
    if (!$mbr_array)
        return false;

    $key = genstring(GEN_LENGHT); // clé de validation aléatoire
    if (Vld::add($key, $mbr_array['mbr_mid'], 'edit')) {

        $pass = genstring(GEN_LENGHT); // générer un nouveau pass aléatoire
        $_tpl->set("vld_mail", $mbr_array['mbr_mail']);
        $_tpl->set("vld_pass", $_ses->crypt($mbr_array['mbr_login'], $pass));
        $_tpl->set("vld_pass2", $pass);
        $_tpl->set("vld_key", $key);
        $_tpl->set("vld_mid", $mbr_array['mbr_mid']);

        $objet = $_tpl->get("modules/inscr/mails/objet_edit.tpl", 1);
        $texte = $_tpl->get("modules/inscr/mails/text_edit.tpl", 1);
        if (mailto(SITE_WEBMASTER_MAIL, $mbr_array['mbr_mail'], $objet, $texte))
            return $mbr_array;
        else {
            if (SITE_DEBUG)
                echo $texte;
            return false;
        }
    } else
        return false; // impossible d'ajouter un nouveau changement (?)
}

function mail_init($mid, $login = '', $pass = false, $mail = '') {// envoyer un mail d'initialisation
    global $_tpl;

    $mid = protect($mid, 'uint');
    $login = protect($login, 'string');
    $pass = protect($pass, 'string');
    $mail = protect($mail, 'string');

    if (!$mid || !$pass)
        return false;
    if (!$login || !$mail) { // récupérer les infos manquantes
        $mbr_array = Mbr::getInit(array('mid' => $mid));
        if (!$mbr_array)
            return false;
        $login = $mbr_array['mbr_login'];
        $mail = $mbr_array['mbr_mail'];
    }

    $key = genstring(GEN_LENGHT);
    if (Vld::add($key, $mid, 'new')) {
        $_tpl->set("vld_key", $key);
        $_tpl->set("mbr_login", $login);
        $_tpl->set("mbr_pass", $pass);

        $sujet = $_tpl->get("modules/inscr/mails/objet_new.tpl", 1);
        $texte = $_tpl->get("modules/inscr/mails/text_new.tpl", 1);
        return mailto(SITE_WEBMASTER_MAIL, $mail, $sujet, $texte);
    } else
        return false;
}

function mail_del($mid, $mail = '') {// envoyer un mail pour suppression du compte
    global $_tpl;

    $mid = protect($mid, 'uint');
    $mail = protect($mail, 'string');
    if (!$mid)
        return false;
    if (!$mail) { // récupérer les infos manquantes
        $mbr_array = Mbr::getInit(array('mid' => $mid));
        if (!$mbr_array)
            return false;
        $mail = $mbr_array['mbr_mail'];
    }

    $key = genstring(GEN_LENGHT);
    if (Vld::add($key, $mid, 'del')) {// ajouter validation pour del
        $_tpl->set("vld_key", $key);
        $_tpl->set("mid", $mid);
        $_tpl->set("mail", $mail);

        $sujet = $_tpl->get("modules/member/mails/objet_del.tpl", 1);
        $texte = $_tpl->get("modules/inscr/mails/text_del.tpl", 1);
        return mailto(SITE_WEBMASTER_MAIL, $mail, $sujet, $texte);
    } else
        return false;
}

?>

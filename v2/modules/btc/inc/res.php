<?php

if (!defined("INDEX_BTC"))
    exit;

require_once("lib/res.lib.php");
require_once("lib/res.lib.php");
require_once("lib/src.lib.php");

if ($_sub == "cancel_res") {
    $researchId = request('rid', 'uint', 'get');
    $number = request('nb', 'uint', 'post');

    $_tpl->set('btc_act', 'cancel_res');
    $_tpl->set('btc_rid', $researchId);

    if (!$researchId)
        $_tpl->set('btc_no_rid', true);
    elseif (!$number)
        $_tpl->set('btc_no_nb', true);
    else {
        $infos = ResTodo::get($_user['mid'], ['rid' => $researchId]);

        if ($infos && $infos[0]['rtdo_nb'] >= $number) {
            $_tpl->set("btc_ok", true);

            $type = $infos[0]['rtdo_type'];
            ResTodo::cancel($_user['mid'], $researchId, $number);
            Res::mod($_user['mid'], $_ses->getConf("res", $type, "prix_res"), $number * 0.5);
        } else
            $_tpl->set("btc_ok", false);
    }
} else if ($_sub == "res") {
    $_tpl->set("btc_act", "res");

    $res_todo = ResTodo::get($_user['mid']);

    foreach ($res_todo as $id => $value) {
        if ($btc_type != $_ses->getConf("res", $value['rtdo_type'], "need_btc"))
            unset($res_todo[$id]);
    }

    $_tpl->set("res_todo", $res_todo);

    $conf_res = $_ses->getConf("res");
    $need_btc = array();
    $need_src = array();
    $need_res = array();

    foreach ($conf_res as $type => $value) {
        if (!isset($value['need_btc']) || $btc_type != $value['need_btc'])
            unset($conf_res[$type]);
        else if (isset($value['cron'])) /* virer les ressources en prod auto */
            unset($conf_res[$type]);
        else { /* un peu de ménage */
            if (isset($value['prix_res']))
                $need_res = array_merge(array_keys($value['prix_res']), $need_res);
            if (isset($value['need_src']))
                $need_src = array_merge($value['need_src'], $need_src);
            array_push($need_res, $type);
        }
    }

    $need_btc = $btc_type;
    $need_res = array_unique($need_res);
    $need_src = array_unique($need_src);
    asort($need_res);
    asort($need_src);

    $cache = array();
    $cache['btc'] = Btc::getNbActive($_user['mid'], [$need_btc]);
    $cache['src'] = Src::get($_user['mid'], $need_src);
    $cache['src'] = index_array($cache['src'], "src_type");
    $cache['res'] = Res::get($_user['mid'], $need_res);

    foreach ($res_todo as $value) {
        if (!isset($cache['res_todo'][$value['rtdo_type']]['rtdo_nb']))
            $cache['res_todo'][$value['rtdo_type']]['rtdo_nb'] = 0;

        $cache['res_todo'][$value['rtdo_type']]['rtdo_nb'] += $value['rtdo_nb'];
    }

    $res_tmp = array();

    foreach ($conf_res as $type => $value) {
        $res_tmp[$type]['bad'] = can_res($_user['mid'], $type, 1, $cache);
        $res_tmp[$type]['conf'] = $value;
    }

    $res_array = array();
    foreach ($res_tmp as $rid => $array) {
        if ($array['bad']['need_src'] || $array['bad']['need_btc'])
            continue;
        $res_array[$rid] = $array;
    }

    unset($res_tmp);

    $_tpl->set("res_dispo", $res_array);
    $_tpl->set("res_utils", $cache['res']);
    $_tpl->set("res_done", $cache['res']);
    $_tpl->set("res_conf", $conf_res);
}
//Nouvelle res
elseif ($_sub == "add_res") {
    $type = request("type", "uint", "post");
    $nb = request("nb", "uint", "post");

    $res_todo = ResTodo::get($_user['mid']);
    $res_todo_nb = 0;
    foreach ($res_todo as $value)
        $res_todo_nb += $value['rtdo_nb'];

    $_tpl->set("btc_act", "add_res");
    if (!$type || $_ses->getConf("res", $type, "need_btc") != $btc_type)
        $_tpl->set("btc_no_type", true);
    else if (!$nb)
        $_tpl->set("btc_no_nb", true);
    else if ($res_todo_nb + $nb > TODO_MAX_RES)
        $_tpl->set("btc_res_todo_max", TODO_MAX_RES);
    else {
        $array = can_res($_user['mid'], $type, $nb);

        if (isset($array['do_not_exist']))
            $_tpl->set("btc_no_type", true);
        else {
            $ok = !($array['need_src'] || $array['need_btc'] || $array['prix_res']);
            $_tpl->set("res_id", $type);
            $_tpl->set("btc_res_nb", $nb);
            $_tpl->set("res_infos", $array);
            $_tpl->set("btc_ok", $ok);
            if ($ok) {
                Res::mod($_user['mid'], $_ses->getConf("res", $type, "prix_res"), -1 * $nb);
                ResTodo::add($_user['mid'], [$type => $nb]);
            }
        }
    }
}
?>

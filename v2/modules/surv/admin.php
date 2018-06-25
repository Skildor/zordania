<?php
//Verif
if(defined("_INDEX_") and can_d(DROIT_ADM_MBR)){

require_once("lib/member.lib.php");
require_once("lib/alliances.lib.php");
require_once("lib/msg.lib.php");

$_tpl->set("module_tpl","modules/surv/admin.tpl");
$_tpl->set("admin_name","Membres surveill�s");
$_tpl->set("array_type", array(SURV_TYPE_ALY => "Alliance", SURV_TYPE_IP => "Adresse IP", SURV_TYPE_MP => "Messagerie", SURV_TYPE_FRM => "Forum", SURV_TYPE_ALL => "Totale"));
$_tpl->set('act',$_act);

switch($_act) {

case "view":
	$sid = request("sid", "uint", "get");
	$msg_id = request("msg_id", "uint", "get");
	$msg_mid = request("msg_mid", "uint", "get");
	if(!empty($sid)){
		$surv = Surv::getBySid($sid);
		$info_surv = array();
		$mbr_info = Mbr::get(['mid' => $surv[0]['surv_mid'], 'full' => true]);
                //get_mbr_gen(array('mid' => $surv[0]['surv_mid'], 'full' => true));
		switch ($surv[0]['surv_type'])
		{
			case SURV_TYPE_ALY:
				$info_surv[SURV_TYPE_ALY] = AlResLog::get($mbr_info[0]['ambr_aid'], 50, 0);
				$_tpl->set("info_ally", $info_surv[SURV_TYPE_ALY]);
				break;
			case SURV_TYPE_IP:
				//$_tpl->set("log_ip", get_log_ip($surv[0]['surv_mid'] , 0, 'full'));
				$_tpl->set("mbr_array",Mbr::getIps($mbr_info[0]['mbr_lip']));
				if($mbr_info[0]['mbr_lip'])
					$_tpl->set('log_ip', MbrLog::getByIp($mbr_info[0]['mbr_lip'], true));
				break;
			/*case SURV_TYPE_MP:
				$info_surv[SURV_TYPE_MP] = get_msg_env($surv[0]['surv_mid']);
				$_tpl->set("info_mp", $info_surv[SURV_TYPE_MP]);
				break;*/
			case SURV_TYPE_ALL:
				$info_surv[SURV_TYPE_ALY] = AlResLog::get($mbr_info[0]['ambr_aid'], 50, 0);
				$_tpl->set("mbr_array",Mbr::getIps($mbr_info[0]['mbr_lip']));
				if($mbr_info[0]['mbr_lip'])
					$_tpl->set('log_ip', MbrLog::getByIp($mbr_info[0]['mbr_lip'], true));
				$info_surv[SURV_TYPE_MP] = get_msg_env($surv[0]['surv_mid']);
				$_tpl->set("info_ally", $info_surv[SURV_TYPE_ALY]);
				//$_tpl->set("info_mp", $info_surv[SURV_TYPE_MP]);
				break;
			case SURV_TYPE_FRM:
				break;
		}
		$_tpl->set("srv_mid", $surv[0]['surv_mid']);
		$_tpl->set("type_surv", $surv[0]['surv_type']);
		$_tpl->set("view_surv", true);
	}
	elseif(!empty($msg_id) && !empty($msg_mid)){
		$array_msg = get_msg_env($msg_mid, $msg_id);
		$_tpl->set("view_mp", true);
		$_tpl->set("array_msg", $array_msg);
	}
	else
		$_tpl->set("no_sid", true);
break;

case 'new': // formulaire nouvelle surveillance
	$mid = request("mid", "uint", "get");
	if(!empty($mid)){
		$mbr_array = Mbr::get(['mid' => $mid]);
		$_tpl->set("mbr_array", $mbr_array[0]);
	}
break;

case 'add': // ajouter une surveillance
	$mid = trim(request("mid", "uint", "post"));
	$type = trim(request("type", "uint", "post"));
	$cause = trim(request("cause", "string", "post"));
	
	if(!empty($mid)){
		$mbr_infos = Mbr::get(['mid' => $mid]);
		if(Surv::get($mbr_infos[0]['mbr_mid']))
			$_tpl->set("all_assign", true);
		elseif(in_array($mbr_infos[0]['mbr_gid'], array(GRP_GARDE,GRP_ADM_DEV,GRP_DEMI_DIEU,GRP_DIEU)))
			$_tpl->set("no_surv_admin", true);
		else {
			Surv::add($mbr_infos[0]['mbr_mid'], $_user['mid'], $type, $cause);
			$_tpl->set("add_surv", true);
		}
	}
	else
		$_tpl->set("no_member", true);

	$_tpl->set("list_surv", Surv::getList());
	$_tpl->set("list_fin_surv", Surv::getFin());
	/* continue to default ! */

default: /* case 'close': */
	$sid = request("sid", "uint", "get");
	if($sid){ // fermer la surveillance
		Surv::close($sid);
		$_tpl->set("close_surv", true);
	}

	$_tpl->set("list_surv", Surv::getList());
	$_tpl->set("list_fin_surv", Surv::getFin());
} /* fin switch $_act */

} /* fin si droits ok */

?>

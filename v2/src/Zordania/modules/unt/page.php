<?php
if(!defined("_INDEX_")){ exit; }
if(!$_ses->canDo(DROIT_PLAY)){
	$_tpl->set("need_to_be_loged",true); 
}else{

$_tpl->set("module_tpl","modules/unt/unt.tpl");

// member: class helper
$mbr = new member($_user['mid']);
$unt_type = request("unt_type", "uint", "get");

if($_act == "pend") {
	$unt_nb = request("unt_nb", "uint", "post");

	if(!$unt_type || !$unt_nb || !$mbr->getConf("unt", $unt_type)) {
		$_act = false;
		$_tpl->set('unt_sub','error');
	} else if ($mbr->getConf('unt', $unt_type, 'role') == TYPE_UNT_HEROS) {
		$_act = false;
		$_tpl->set('no_heros',false);
	} else {
		$_tpl->set('unt_act','pend');
		if($unt_nb > $mbr->nb_unt_done($unt_type))
			$_tpl->set('unt_sub','paspossible');
		else  {
			Unt::editVlg($_user['mid'], array($unt_type => $unt_nb), -1);
			Mbr::edit($_user['mid'], array('population' => Leg::countUnt($_user['mid'])));
			// on rembourse 50% du prix de ressources
			Res::mod($_user['mid'], $mbr->getConf("unt", $unt_type, "prix_res"), 0.5 * $unt_nb);
			$_tpl->set('unt_sub','ok');

		}
	}
}

if(!$_act) {
	$nb = $mbr->getConf("race_cfg", "unt_nb");
	for($i = 1; $i <= $nb; ++$i)
		$unt_done['tot'][$i] = $unt_done['vlg'][$i] = $unt_done['btc'][$i] = 0;

        //unités au village et en légions
	foreach( $mbr->unt() as $value) {
		if($value['leg_etat'] == Leg::ETAT_VLG)
			$unt_done['vlg'][$value['unt_type']] = $value['unt_nb'];
		else
			$unt_done['btc'][$value['unt_type']] = $value['unt_nb'];

		if(!isset($unt_done['tot'][$value['unt_type']]))
			$unt_done['tot'][$value['unt_type']] = 0;
		$unt_done['tot'][$value['unt_type']] += $value['unt_nb'];
	}

	$_tpl->set("unt_done", $unt_done);
	
	// config des unités: vie/group/role/prix_res/in_btc/need_btc ...
	// calculer tout ce qu'on peut former ... ou pas
	foreach($mbr->getConf("unt") as $type => $value) {
            // requete pour 1 seul type d'unité
            if(!empty($unt_type) && $unt_type != $type)
                continue;
            $bad = $mbr->can_unt($type, 1);
            if(!empty($bad['need_src']) || !empty($bad['need_btc']))
                if($unt_done['tot'][$type] == 0)
                    continue;
            $unt_array[$type] = ['bad'=>$bad, 'conf'=>$value];
	}

	if(empty($unt_type)){
		// grouper les todo par bat et par type
		$unt_todo = array();
		foreach($mbr->unt_todo() as $val1){
			$typ = $val1['utdo_type'];
			$inbtc = $unt_array[$typ]['conf']['in_btc'][0];
			$unt_todo[$inbtc][$typ][] = $val1;
		}
		$_tpl->set('unt_todo', $unt_todo);
		$_tpl->set("unt_dispo",$unt_array);

	} else {
		if ($mbr->getConf('unt', $unt_type, 'role') == TYPE_UNT_HEROS)
			$_tpl->set('no_heros',false);
		else {
			$btc = $mbr->getConf("unt", $unt_type, "in_btc");
			if($btc) {
				$_tpl->set('unt_type',$unt_type);
				$_tpl->set('btc_type',$btc[0]);
			}
                $_tpl->set("unt_dispo",$unt_array[$unt_type]);
        }
    }
}

}
?>

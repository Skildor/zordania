﻿<?php
if(!defined("_INDEX_") || !$_ses->canDo(DROIT_ADM_MBR)){ exit; }

$_tpl->set("module_tpl","modules/member/admin.tpl");
if($_act == "del" || $_act == "edit") {
	if(!$_ses->canDo(DROIT_ADM_EDIT)) {
		$_act = ""; // de rien faire
		$_tpl->set('act_interdit', true);
	}
}

if($_act == "del") {
	$mid = request("mid", "uint", "get");
	$ok = request("ok", "bool", "post");
	
	$_tpl->set("mbr_act","del");
	$_tpl->set("mbr_mid", $mid);
	if(!$mid)
		$_tpl->set("mbr_no_mid",true);
	elseif($ok == "ok") {
		$array = Mbr::getFull($mid);
		if($array) {
			$array = $array[0];
			$race = $array['mbr_race'];
			$cid = $array['mbr_mapcid'];

			if(Mbr::cls($mid, $cid, $race))
				$_tpl->set("mbr_ok",true);
			else
				$_tpl->set("mbr_error",true);
		} else
			$_tpl->set("mbr_error",true);
	} else
		$_tpl->set("mbr_need_ok",true);

} elseif($_act == "edit") {
	
	$mid = request("mid", "uint", "get");
	
	if(!Mbr::count(['mid' => $mid])) {
		$_tpl->set("mbr_not_exist",true);
	} else {
		if($_sub == "edit") {
			$new = array();
			$array = Mbr::getFull($mid);
			$array = $array[0];

			$new['mail'] = request("mail", "string", "post");
			$new['decal'] = request("decal", "string", "post");
			$new['gid'] = request("gid", "uint", "post");
			$new['pseudo'] = request("pseudo", "string", "post");
			$new['vlg'] = request("village", "string", "post");
			$new['pass'] = request("admpass", "string", "post");

			// verifier l'unicite du pseudo
			$have_pseudo = Mbr::get(array('count'=>true, 'pseudo' => $new['pseudo'], 'mid_excl'=>$mid, 'op'=>'AND'));
			if($have_pseudo[0]['mbr_nb']>0)
				$_tpl->set('mbr_pseudo_exist', true);
			else{
				if($new['pass'])
					$new['pass'] = $_ses->crypt($array['mbr_login'], $new['pass']);

				$new['sign'] = Parser::parse(request("sign", "string", "post"), true);
				$new['descr'] = Parser::parse(request("descr", "string", "post"));
			
				foreach($new as $key => $val)
					if(!$val) unset($new[$key]);
			
				$_tpl->set("mbr_edit", Mbr::edit($mid, $new));
			}
		} elseif($_sub == "add_rec") {
			$rec_type = request("rec_type", "uint", "post");
			
			if($rec_type)
				$_tpl->set("mbr_edit", Rec::add($mid, $rec_type));
		} elseif($_sub == "del_rec") {
			$rec_type = request("rec_type", "uint", "post");
			
			if($rec_type)
				$_tpl->set("mbr_edit", Rec::del($mid, $rec_type));
		} elseif($_sub == "ren_leg") {
			$leg_name = request("leg_name", "string", "post");
			$lid = request("lid", "uint", "get");

			$_tpl->set("ren_leg_name", $leg_name);
			if(!$lid)
				$_tpl->set("ren_leg_not_exists", true);
			elseif(!Leg::canRename($mid,$lid,$leg_name))
				$_tpl->set("ren_leg_name_exists", true);
			else
				$_tpl->set("ren_leg_ok", Leg::edit($mid,$lid,array('name'=>$leg_name)));
		} elseif($_sub == "move_mbr") { /* déplacer village */
			$map_x = request("map_x", "uint", "post");
			$map_y = request("map_y", "uint", "post");
			$map_cid = request("map_cid", "uint", "post");

			if($map_x and $map_y) // chercher map_cid
				$map_cid = Map::getCid($map_x,$map_y);

			if($map_cid) { // vérifier que la destination est libre
				$arr_cid = Map::getGen($map_cid);
				if(!empty($arr_cid)) { // destination connue
					if($arr_cid['map_type'] == MAP_LIBRE) // emplacement libre
						$_tpl->set('depl_ok', 
                                                        DB::select('CALL move_member(?, ?)', [$mid, $map_cid]));
					else
						$_tpl->set('depl_ok', 'no_free');
				} else
					$_tpl->set('depl_ok', 'out');
			} else
				$_tpl->set('depl_ok', 'out');
		}
		$array = Mbr::getFull($mid);
		$array = $array[0];
		$_tpl->set("mbr_act","edit");

		$_tpl->set("mbr_mid",$mid);
		$_tpl->set("mbr_login",$array['mbr_login']);
		$_tpl->set("mbr_pseudo",$array['mbr_pseudo']);
		$_tpl->set("mbr_vlg",$array['mbr_vlg']);
		$_tpl->set("mbr_mail",$array['mbr_mail']);
		$_tpl->set("mbr_lang",$array['mbr_lang']);
		$_tpl->set("mbr_decal",$array['mbr_decal']);
		$_tpl->set("mbr_date",date("H:i:s"));
		$_tpl->set("mbr_race",$array['mbr_race']);
		$_tpl->set("mbr_gid",$array['mbr_gid']);
		$_tpl->set("mbr_descr", Parser::unparse($array['mbr_descr']));
		$_tpl->set("mbr_sign", Parser::unparse($array['mbr_sign']));

		// champs pour renommer les lÃ©gions
		require_once("lib/unt.lib.php");
		$_tpl->set('leg',Leg::getAll($mid));
	}
		
	
	$_tpl->set('rec_array', Rec::get($mid));
} elseif($_act == "liste" OR !$_act) {
	$_tpl->set('mbr_act','liste');

	/* gestion du where */
	$pseudo = request("pseudo", "string", "post");
	$ip = request("ip", "string", "post");
	
	$cond = array();
	$cond['op'] = "AND";
	
        $request = [];
	if($pseudo)
            $request[] = ['mbr_pseudo', 'LIKE', "%$pseudo%"];
            //$cond['pseudo'] = "%".$pseudo."%";
	if($ip )
            $request[] = ['mbr_ip', '=', $ip];
            //$cond['ip'] = $ip;

	$_tpl->set("mbr_pseudo", $pseudo);
	$_tpl->set("mbr_ip", $ip);	
	
	/* gestion du order by */
	$order = request("order", "uint", "get", request("order", "uint", "post"));
	$by = request("by", "string", "get", request("by", "string", "post"));
	if($by) {
		$_tpl->set("mbr_by",$by);
		$_tpl->set("mbr_order",$order);
		
		if($order == 2)
			$order = "DESC";
		else
			$order = "ASC";
		
		$order_by = array($order, $by);
	} else
		$order_by = array('DESC', 'points');
	
	$_tpl->set("mbr_order", $order_by[0]);
	$_tpl->set("mbr_by", $order_by[1]);
	
	
	$mbr_page = request("mbr_page", "int", "get", -1);
	
        $mbr_nb = Mbr::where($request)->count();
	
	$current_i = $mbr_page - LIMIT_NB_PAGE/2;
	$current_i = round($current_i < 0 ? 0 : $current_i)*LIMIT_MBR_PAGE;
	$nombre_page = ($mbr_nb / LIMIT_MBR_PAGE);
	$nombre_total = ceil($nombre_page);
	$nombre = $nombre_total - 1;
	
	$_tpl->set('limite_page',LIMIT_MBR_PAGE);
	$_tpl->set('limite_nb_page',LIMIT_NB_PAGE);
	$_tpl->set('current_i',$current_i);
	$_tpl->set("mbr_nb",$mbr_nb);
	
	if($mbr_page > 0)
		$limite_mysql = LIMIT_MBR_PAGE * $mbr_page;
	else
		$limite_mysql = 0;

	$mbr_array = Mbr::get(array_merge($cond, 
                ['limite1'=>$limite_mysql, 'limite2'=>LIMIT_MBR_PAGE, 'orderby'=>$order_by,'list'=>true]));
	
	$_tpl->set("mbr_array",$mbr_array);	
	$_tpl->set('mbr_page',$mbr_page);
	
} elseif($_act == "old") {
	$_tpl->set('mbr_act','old');

	$pseudo = request("pseudo", "string", "post");
	$ip = request("ip", "string", "post");
	$mid = request("mid", "uint", "post", request("mid", "uint", "get"));
	
	$cond = array();
	
	if($pseudo)
		$cond['pseudo'] = $pseudo;
	if($ip)
		$cond['ip'] = $ip;
	if($mid)
		$cond['mid'] = $mid;

	$_tpl->set("mbr_pseudo", $pseudo);
	$_tpl->set("mbr_ip", $ip);
	$_tpl->set("mbr_mid", $mid);
	
	$mbr_array = MbrOld::get($cond);
	$_tpl->set("mbr_array",$mbr_array);
} elseif($_act == "liste_online") {
	//liste online
	$_tpl->set("module_tpl","modules/member/liste.tpl");
	$_tpl->set("mbr_act","liste_online");
	
        $pg = new Paginator(Ses::getOnline());
        $pg->get = Mbr::canAtq($pg->get, $_user['pts_arm'],$_user['mid'],$_user['groupe'], $_user['alaid']);
	$_tpl->set('pager',$pg);

} else if($_act == "view") {
	$mid = request("mid", "uint", "get");
	$_tpl->set("mbr_act","view");

	$mbr_array = Mbr::getFull($mid);
	$mbr_staff = false;
	if($mbr_array){
		$mbr_array = $mbr_array[0];
		// bloquer cette page pour un compte du groupe staff :
		$grp_staff = array(GRP_GARDE, GRP_PRETRE, GRP_DEV, GRP_ADM_DEV, GRP_DEMI_DIEU, GRP_DIEU);
		if(in_array($mbr_array['mbr_gid'], $grp_staff) and $_user['groupe'] != GRP_DIEU)
			$mbr_staff = true;
	}

	if(!$mbr_array)
		$_tpl->set("mbr_not_exist", true);
	else if($mbr_staff)
		$_tpl->set("mbr_staff", $mbr_staff);
	else {
		$_tpl->set_ref("mbr_array",$mbr_array);
		$_tpl->set('log_ip', MbrLog::get($mid));
		
		if($mbr_array['mbr_etat'] == MBR_ETAT_OK || $mbr_array['mbr_etat'] == MBR_ETAT_ZZZ) {
                    
			$legions = new legions(['mid'=>$mid,
                            //'etat' => [LEG_ETAT_VLG, LEG_ETAT_BTC, LEG_ETAT_GRN, LEG_ETAT_DPL, LEG_ETAT_ATQ]
                            ], true, true);
                        
			//$cond = array('mid'=>$mid);
			//$legions = new legions($cond, true, true);
			$hro_array = array('hro_id'=>0, 'hro_type'=>0);
			foreach($legions->legs as $lid => $leg){
				if($leg->hid){
                                        // edit nrj et xp
                                        $nrj = request('hro_nrj', 'uint', 'post');
                                        $addNrj = request('hro_add_nrj', 'uint', 'post');
                                        $vie = request('hro_vie', 'uint', 'post');
                                        $xp = request('mbr_xp', 'uint', 'post');
                                        if($nrj){
                                            $leg->setHro('vie', $vie);
                                            if($addNrj)
                                                $leg->addHro('nrj',$addNrj);
                                            else
                                                $leg->setHro('nrj',$nrj);
                                            $leg->flush();
                                            edit_mbr($mid, ['xp' => $xp]);
                                        }
					$hro_array = $leg->infos;
					break;
				}
                        }
                            
			$_tpl->set("hro_array", $hro_array);

			// vider les ressources d'une légion
			$lid = request('leg_res_init','uint','get');
			if($lid) {
				if(!$_ses->canDo(DROIT_ADM_EDIT)) { // admin seulement
					$_tpl->set('act_interdit', true);
				} else if(isset($legions->legs[$lid])) {
					$res_array = $legions->legs[$lid]->get_res();
					foreach($res_array as $res => $nb)
						$res_array[$res] = $nb * -1;

					$legions->legs[$lid]->mod_res($res_array);
					Res::mod($mid, $res_array, -1);
					$_tpl->set("lres_ok", true);
				}
			}
			$btc_array = Btc::getNb($mid, array(), array(BTC_ETAT_OK,BTC_ETAT_BRU,BTC_ETAT_DES,BTC_ETAT_REP));

			$_tpl->set("res_leg", $legions->get_all_res());
			$_tpl->set("unt_leg", $legions->get_all_unts());
			$_tpl->set("leg_array", $legions->get_all_legs_infos());
                        if(isset($legions->legs[$legions->btc_lid])){
                            $leg_bat_reel = $legions->legs[$legions->btc_lid]->get_unt();
                            $_tpl->set("leg_bat_reel", $leg_bat_reel);
                            
                            $leg_bat_diff = array();
                            foreach($leg_bat_reel as $key => $nb)
                                    $leg_bat_diff[$key] = -$nb; /* initialiser la legion difference */

                            $leg_bat_th = array();
                            foreach ($btc_array as $btc) {
                                    /* compter la legion theorique des batiments */
                                    $prix_unt = Config::get($mbr_array['mbr_race'], 'btc', $btc['btc_type'], 'prix_unt');
                                    if ($prix_unt) {
                                            array_ksum($leg_bat_diff, $prix_unt, $btc['btc_nb']);/* difference = - existant + requis */
                                            array_ksum($leg_bat_th, $prix_unt, $btc['btc_nb']);/* requis dans les batiments */
                                    }
                            }

                            $_tpl->set('leg_bat_diff', $leg_bat_diff); /* leg theorique bat */
                            $_tpl->set('leg_bat_th', $leg_bat_th); /* leg theorique bat */
                        }
                        
                        $_tpl->set("leg_race", $mbr_array['mbr_race']);
			$_tpl->set('btc_done', $btc_array);
			$_tpl->set('btc_todo', Btc::get($mid, [], [BTC_ETAT_TODO]));
			$conf_btc = Config::get($mbr_array['mbr_race'], 'btc');
			$_tpl->set('conf_btc', $conf_btc);


			$res_array = Res::get($mid);
			// edit ressources du joueur
			$add_res = request('add_res', 'array', 'post');
			if(!empty($add_res)) {
				if(!$_ses->canDo(DROIT_ADM_EDIT)) { // admin seulement
					$_tpl->set('act_interdit', true);
				} else {
					foreach($add_res as $key=>$value)
						if(!is_numeric($value) || $value==0) // valeur ko
							unset($add_res[$key]);
						else if($res_array[$key] + $value < 0) // y'a pas assez
							unset($add_res[$key]);
						else
							$res_array[$key]+=$value; // ok
					if(Res::mod($mid, $add_res))
						$_tpl->set('edit_res', $add_res);
				}
			}

			$_tpl->set('res_done', $res_array);
			$_tpl->set('res_todo', ResTodo::get($mid));

			$_tpl->set('trn_done', Trn::get($mid));

			$_tpl->set('src_done', Src::get($mid));
			$_tpl->set('src_todo', SrcTodo::get($mid));

			$_tpl->set('unt_todo', UntTodo::get($mid));


			/* comptage des points */
			$src_array = Src::get($mid);
			$mbr_array['pts']['src']['nb'] = count($src_array);
			$mbr_array['pts']['src']['coef'] = Config::get($mbr_array['mbr_race'], "race_cfg", "src_nb");
			$mbr_array['pts']['src']['pts'] = count($src_array) 
				* 10000 / (Config::get($mbr_array['mbr_race'], "race_cfg", "src_nb") + 1);

			$btc_arr = Btc::get($mid);
			$btc_vie = 0;
			foreach($btc_arr as $value) $btc_vie += $value['btc_vie'];
			$mbr_array['pts']['btc']['nb'] = count($btc_array);
			$mbr_array['pts']['btc']['vie'] = $btc_vie ;
			$mbr_array['pts']['btc']['pts'] = $btc_vie / 3;

			$unt_array = Leg::get(['mid' => $mid, 'sum' => true]);
			$pts_armee = 0;
			foreach($unt_array as $key => $value){
				$unt_cfg = Config::get($mbr_array['mbr_race'], "unt", $value['unt_type']);

                                if ($unt_cfg['role'] == TYPE_UNT_CIVIL){
                                    $unt_array[$key]['pts'] = 0;
                                }else{
                                    $unt_array[$key]['pts'] = (isset($unt_cfg['def']) ? $unt_cfg['def'] : 0)
					 + (isset($unt_cfg['atq_unt']) ? $unt_cfg['atq_unt'] : 0 )
					 + ( isset($unt_cfg['atq_btc']) ? $unt_cfg['atq_btc']*1.8 : 0 )
					 + $unt_cfg['vie'];
                                }

				$unt_array[$key]['total'] = $unt_array[$key]['pts'] * $value['unt_sum'];
				$pts_armee += $unt_array[$key]['total'];
			}
			$_tpl->set('unt_done', $unt_array);
			$mbr_array['pts']['unt']['pts'] = $pts_armee;
			/* fin comptage des points */
		}
	}
} else if($_act == "liste_ip") {
	$_tpl->set("mbr_act","liste_ip");
	$ip = request('ip', 'string', 'get', '');
	$_tpl->set("mbr_array",Mbr::getIps($ip, $_user['groupe']));
	if($ip )
		$_tpl->set('log_ip', MbrLog::getByIp($ip, true, $_user['groupe']));
} else if ($_act == 'exp') 
{ // exporter membre
	$mid = request("mid", "uint", "get");
	require_once("lib/mysql.lib.php");
	
	// telecharger fichier sql
	header('Content-Type: text');
	header('Content-Disposition: attachment; filename="export.'.$mid.'.sql"');
	die(zrd_dump($mid));
	
	$_tpl->set("sql",htmlspecialchars(zrd_dump($mid)));	
	$_tpl->set("mbr_act","exp");
}

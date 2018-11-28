<?php
if(!defined("_INDEX_")){ exit; }
/*
if(!can_d(DROIT_PLAY)) {
	$_tpl->set("need_to_be_loged",true);
} else {
*/

if($_act == 'search' and $_display == 'ajax')
	$_tpl->set("module_tpl","modules/forum/search.tpl");
else
	$_tpl->set("module_tpl","modules/forum/forum.tpl");

$_tpl->set('frm_act',$_act);

$tid = request('tid','uint','get');
$pid = request('pid','uint','get');
$sub = request('sub', 'string','get');
$fid = request('fid', 'uint','get');
$pseudo = $_user['pseudo'];
$mid = $_user['mid'];	
$group = $_user['groupe'];
$is_modo = $_ses->canDo(DROIT_PUNBB_MOD);
$is_admin = $_ses->canDo(DROIT_PUNBB_ADMIN);
$_tpl->set('is_modo',$is_modo);
$_tpl->set('is_admin',$is_admin);
$_tpl->set('mid',$mid);


switch ($_act)  {

case 'post' : // valider le formulaire & créer le topic / message
	//on sépare tous les cas :

	// prévisualisation ajax
	if($_display == "ajax"){
		if($_ses->canDo(DROIT_PUNBB_GUEST))
			$post = array('poster_id' => 1, 'username' => 'guest', 'tid' => 0, 'message' => 'ACCES INTERDIT',
				'subject' => 'ACCES INTERDIT', 'mbr_gid' => 1, 'posted' => date('j-n-Y'));
		else{
			$pst_titre = request('pst_titre','string','post');
			$pst_msg = request('pst_msg','string','post');
			$post = array();
			$post['id'] = 'nvmsg';
			//on vérifie que le message n'est pas vide
			if($pst_titre == 'topic') $pst_titre == ''; // sinon bug URL!

			if (!$pst_msg)
				$_tpl->set('tpc_vide', true);
			else{
				$post['poster_id'] = $_user['mid'];
				$post['username'] = $_user['pseudo'];
				$post['tid'] = $tid;
				$post['message'] = Parser::parse($pst_msg);
				$post['subject'] = $pst_titre;
				$post['mbr_gid'] = $_user['groupe'];
				$post['posted'] = date('j-n-Y');
			}
		}

		$_tpl->set('tid', $tid);
		$_tpl->set('post', $post);
		$_tpl->set("module_tpl","modules/forum/post.tpl");
		break;
	}

	if($_ses->canDo(DROIT_PUNBB_GUEST)){
		$_tpl->set('cant_create', true);
		break;
	}
	//D'abord la création de topic :
	if ($sub == 'new' && $fid)
	{
		//on vérifie qu'on a le droit
		if (!FrmPerm::can($fid, $group, 'create'))
			$_tpl->set('cant_create', true);
		else
		{
			$pst_titre = request('pst_titre','string','post');
			$pst_msg = request('pst_msg','string','post');
			$last_usr_post = FrmPost::getLast($mid);
			if($pst_titre == 'topic') $pst_titre == ''; // sinon bug URL!
			//on vérifie que le message n'est pas vide				
			if (!$pst_msg || !$pst_titre)
				$_tpl->set('tpc_vide', true);
			else if ($last_usr_post && $last_usr_post['message'] == $pst_msg)
			// on regarde s'il n'y a pas eu du f5
				$_tpl->set('tpc_f5', true);
			else
			{
				$closed = $is_modo && isset($_POST['closed']) ? 1 : 0;
				$sticky = $is_modo && isset($_POST['sticky']) ? 1 : 0;
				//c'est bon, on peut ajouter le topic, ajouter le message
				$tid = FrmTopic::add($pseudo,$pst_titre,$fid, $closed, $sticky);
				$pid = FrmPost::add($mid,$pseudo,$_user["ip"],$pst_msg,$tid);
				FrmTopic::maj($pid,$pseudo,$tid,$fid,true,$pst_titre,$pst_msg);	

				header("Location: forum-post.html?pid=$pid#$pid");
				
				//si c'est une news on envoit sur discord
				// source https://gist.github.com/thoanny/df9acea3ffabfc8db32113502a0c3e93#file-php-to-discord-php
				if ($fid == ZORD_NEWS_FID)
				{
					//webhook
					$url = 'https://discordapp.com/api/webhooks/'.DISCORD_WEBHOOK.'';
					$data = array(
						'username' => 'Barnabé le Tavernier', // Remplacer le nom du webhook, à enlever si inutilisé
						'embeds' => array(
							array(
								'title' => $pst_titre, // Intitulé de la news
								'url' => 'http://zordania.fr/forum-post.html?pid='.$pid.'#'.$pid.'', // Adresse de la news									  
								'author' => array(
									'name' => 'Nouvelle News postée:', // texte annonce 
								  	),
								)
							),
						);

					$context = array(
					  'http' => array(
						'method' => 'POST',
						'header' => "Content-type: application/json\r\n",
						'content' => json_encode($data),
					  )
					);

					$context  = stream_context_create($context);
					$result = @file_get_contents($url, false, $context);

					if($result === false) {
					  return false;
						}

					return true;
				}
			}			
		}
	}
	//Ensuite toutes les opération porte sur un topic
	elseif ($tid || $pid)
	{
		//on obtient le tid si on a que le pid
		if (!$tid){
			$pst = FrmPost::getById( $pid);
			if($pst)
				$tid = $pst['tid'];
		}
		if(!$tid){$_tpl->set('empty', true);break;}

		$info = FrmTopic::getInfo($tid,$group);
		if(empty($info)){
                    $_tpl->set('empty', true);
                    break;
                }

		$edit = array();
		// marquer ce topic comme lu
		$_user['forum_lus'][$tid] = true;

		//création de message
		if ($sub == 'new' && $tid)
		{
			//on vérifie qu'on a le droit
			if ($info['post_replies'] == 0 || ($info['closed'] && !$is_modo))
				$_tpl->set('cant_post', true);
			else
			{
				//sinon, on a le droit de poster le message
				$pst_msg = request('pst_msg','string','post');
				$last_usr_post = FrmPost::getLast($mid);

				//on vérifie qu'il n'est pas vide
				if (!$pst_msg)
					$_tpl->set('pst_vide', true);
				else if ($last_usr_post && $last_usr_post['message'] == $pst_msg)
					 // on regarde s'il n'y a pas eu du f5
					$_tpl->set('post_f5', true);
				else
				{
					if($is_modo){// le modérateur peut modifier le topic en même temps qu'il répond
						$edit = array('tid' => $tid);
						$edit['closed'] = isset($_POST['closed']) ? 1:0;
						$edit['sticky'] = isset($_POST['sticky']) ? 1:0;
						if($edit['closed']!=$info['closed'] || $edit['sticky']!=$info['sticky'])
							FrmPost::edit($edit);
					}
					//on ajoute le message, et on met à jour la bdd
					$pid = FrmPost::add($mid,$pseudo,$_user["ip"],$pst_msg,$tid);
					FrmTopic::maj($pid,$pseudo,$tid,$info['forum_id'],false,$info['subject'],$pst_msg);
					header("Location: forum.html?pid=$pid#$pid");
				}
			}
		}
		//ensuite l'édition de message
		elseif ($sub == 'edit')
		{
			$pst_msg = request('pst_msg','string','post');

			//on vérifie qu'on a les droits
			if (!$is_modo && $pst['poster_id'] != $_user['mid'])
				$_tpl->set('cant_edit', true);
			else if (!$pst_msg) //on vérifie qu'il n'est pas vide
				$_tpl->set('pst_vide', true);
			else
			{
				$edit['pid'] = $pid;
				$edit['msg'] = $pst_msg;
				if($is_modo)
					$edit['closed'] = isset($_POST['closed']) ? 1:0;
				if($is_modo)
					$edit['sticky'] = isset($_POST['sticky']) ? 1:0;
				if($is_modo && isset($_POST['silent']))
					$edit['silent'] = true;
				$move = request('move', 'uint', 'post', -1);
				if($is_modo && $move >=0)
					$edit['fid'] = $move;
				$statut = request('statut', 'uint', 'post', -1);
				if($is_modo && $statut >=0)
					$edit['statut'] = $statut;
				$type = request('type', 'uint', 'post', -1);
				if($is_modo && $type >=0)
					$edit['type'] = $type;
				// possible d'éditer le sujet hormis modo ??
				$title = request('pst_titre', 'string', 'post');
				if($is_modo && $title)
					$edit['title'] = $title;
				if(isset($edit['title']) || isset($edit['fid']) || isset($edit['closed']) || isset($edit['sticky']))
					$edit['tid'] = $tid;// modifier aussi le topic concerné
				//on édite le message, et on met à jour la bdd
				FrmPost::edit($edit);
				header("Location: forum.html?pid=$pid#$pid");
			}
		}
		//panel de modération topic (fermer/postit/déplacer)
		elseif ($sub == 'modo' || $sub == 'stick' || $sub == 'unstick' || $sub == 'close' || $sub == 'open')
		{
			//on vérifie qu'on a les droits
			if (!$is_modo)
				$_tpl->set('cant', true);
			else
			{
				if(isset($_POST['modo'])){
					$edit['tid'] = $tid;

					$edit['closed'] = isset($_POST['close']) ? 1:0;
					if($edit['closed'] == $info['closed'])
						unset($edit['closed']);
					else
						$info['closed'] = $edit['closed'];

					$edit['sticky'] = isset($_POST['stick']) ? 1:0;
					if($edit['sticky'] == $info['sticky'])
						unset($edit['sticky']);
					else
						$info['sticky'] = $edit['sticky'];

					$move = request('move', 'int', 'post', -1);
					if($move >=0 and $move <> $info['forum_id']){
						$edit['fid'] = $move;
						$info['forum_id'] = $move;
					}
					
					$statut = request('statut', 'int', 'post', -1);
					if($statut){
						$edit['statut'] = $statut;
					}
					
					$type = request('type', 'int', 'post', -1);
					if($type){
						$edit['type'] = $type;
					}
					
					if(isset($edit['closed']) or isset($edit['sticky']) or isset($edit['fid']) or isset($edit['statut']) or isset($edit['type']))
					{ //on édite le message, et on met à jour la bdd
						FrmPost::edit($edit);
						$_tpl->set('edit_tpc',true);
					}
				}
				else if ($sub == 'stick' || $sub == 'unstick')
					$_tpl->set('stick', FrmPost::stick($tid, ($sub == 'stick'? 1:0)));
				else if ($sub == 'close' || $sub == 'open')
					$_tpl->set('close', FrmPost::close($tid, ($sub == 'close'? 1:0)));
			}
		}

		//Suppression de message : confirmation
		else if ($sub == 'conf')
		{
			$_tpl->set('conf',true);
			$_tpl->set('pid',$pid);
		}
		else if ($sub == 'del' && isset($_POST['Oui']))
		{
			if (($pst['poster_id'] <> $mid) && !$is_modo)//on vérifie qu'on a le droit
				$_tpl->set('cant_del', true);
			else
			{
				//si oui, on supprime le message
				$topic_del = FrmPost::del($pst, $info);
                                $_tpl->set('del',true);
				//et c'est tout si on a supprimé le topic (sinon affichage à la fin)
				if ($topic_del)
					break;
				//sinon on réalimente les infos
			}
		}

	}

	//et enfin l'affichage de message
	//on vérifie qu'on a le droit
		
	if (isset($info) && $info['read_forum'] == 1)
	{
		FrmTopic::view($tid);
		//dans ce cas, on envoie toutes les informations nécessaires au template
		$nbr_pages = ceil( ($info['num_replies']+1) / LIMIT_PAGE);
		if($nbr_pages > 1){
			if ($pid)
				$page = FrmPost::searchOffset($pid,$tid,0,LIMIT_PAGE);
			else
				$page = request('p','uint','get');	

			$page = ( $page <= 1 || $page > $nbr_pages) ? 1 : $page;
			$_tpl->set('arr_pge', get_list_page( $page, $nbr_pages));
			$_tpl->set('pge',$page);
			$deb = LIMIT_PAGE * ($page - 1);
		}else
			$deb = 0;
		$_tpl->set('messages',FrmPost::getMsg($tid,$deb,LIMIT_PAGE));
		$_tpl->set('tpc', $info);
		$_tpl->set('spec_title', $info['subject']);
		if ($is_modo)
			$_tpl->set('cat_array', Frm::getCat());
	}
	else
		$_tpl->set('cant_read',true);

	break;
	
case 'search': // recherche
	$action = request('action', 'string', 'get');
	$search_id = request('search_id', 'uint', 'get');
	$forum = request('forum', 'uint', 'get');
	$sort_dir = request('sort_dir', 'string', 'get');
	$sort_dir = ($sort_dir == 'DESC') ? 'DESC' : 'ASC';
	// liste des catégories & forums pour la liste de recherche
	$_tpl->set('cat_array',Frm::getCat());

	if ($search_id)// une recherche était fournie
	{
		if ($search_id < 1)
			{$_tpl->set('Bad_request', true); break;}
	}

	else if ($action == 'search')// recherche normale (mot clé et/ou autheur)
	{
		$show_as = request('show_as', 'string', 'get', 'posts');
		$sort_by = request('sort_by', 'uint', 'get', null);
		$search_in = request('search_in', 'string', 'get');
		$search_in = (!$search_in || $search_in == 'all') ? 0 : (($search_in == 'message') ? 1 : -1);

		$keywords = strtolower(trim(request('keywords', 'string', 'get', null)));
		$author = strtolower(trim(request('author', 'string', 'get', null)));

		if (preg_match('#^[\*%]+$#', $keywords) || strlen(str_replace(array('*', '%'), '', $keywords)) < 3)
			$keywords = '';

		if (preg_match('#^[\*%]+$#', $author) || strlen(str_replace(array('*', '%'), '', $author)) < 3)
			$author = '';

		if (!$keywords && !$author)
			{$_tpl->set('no_search', true); break;}

		if ($author)
			$author = str_replace('*', '%', $author);

	}
	// si recherche d'utilisateur (par id)
	else if ($action == 'show_user')
	{
		$user_id = request('user_id', 'uint', 'get');
		if ($user_id < 2) // car 1 = invité
			{$_tpl->set('Bad_request', true); break;}
	}
	else if ($action != 'show_new' && $action != 'show_24h' && $action != 'show_unanswered')
	{// aucune action : mauvaise idée
		if(!empty($action))
			$_tpl->set('Bad_request', true);
		break;
	}


	if (!$search_id)// traiter la recherche pour la mettre en cache
	{
		$keyword_results = $author_results = array();

		if (!empty($author) || !empty($keywords))
		{
			// recherche par mots clés
			if ($keywords)
				$keyword_results = search_keywords_results($keywords, $search_in);

			// recherche par auteur
			if ($author) // && strcasecmp($author, 'Guest'))
				$author_results = FrmPost::searchFrom($author);

			if ($author && $keywords)// intersection entre les résultats auteur & mots-clé
			{
				$search_ids = array_intersect($keyword_results, $author_results);
				unset($keyword_results, $author_results);
			}
			else if ($keywords)
				$search_ids = $keyword_results;
			else
				$search_ids = $author_results;

			$num_hits = count($search_ids);
			if (!$num_hits)
				$_tpl->set('no_hits', true);
			else  {
				$cond = array('pid_list'=>$search_ids);
				if($forum != -1) // filtre sur un forum ?
					$cond['fid'] = $forum;

				if ($show_as == 'topics'){ // rechercher les topics uniquement
					$cond['select'] = 'tid'; $cond['group'] = 'tid';
				}else // rechercher les posts
					$cond['select'] = 'pid';
			}
		}// fin de recherche par mots clés

		// nouveaux msgs depuis dernière connexion - msg des dernières 24h - msg d'un utilisateur - msg non répondu
		else if ($action == 'show_new' || $action == 'show_24h' || $action == 'show_user' || $action == 'show_unanswered'){
			$cond['select'] = 'tid';
			$sort_by = 4;// trier par le dernier post
			$show_as = 'topics';

			if ($action == 'show_user'){ // msg d'un utilisateur
				$cond['user'] = $user_id;
				//$cond['group'] = 'tid';
				$cond['select'] = 'pid';
				$show_as = 'posts';
			}
			else
				$cond[$action] = true;
		}
		if(!isset($cond)){
			break;
		}

		$result = FrmPost::get($cond)->get()->toArray(); // récupérer la liste des ids recherchés
		$search_ids = array();
		if(!empty($result)){// récupérer la 1ère valeur de $key
			foreach ($result[0] as $key => $row)
				break;
			foreach ($result as $row)// récupérer tous les résultats
				$search_ids[] = $row[$key];
		}

		$num_hits = count($search_ids);

		// résultat final de la recherche
		$search_results = implode(',', $search_ids);

		// enregistrer cette recherche en cache
		$temp['search_results'] = $search_results;
		$temp['num_hits'] = $num_hits;
		$temp['sort_by'] = $sort_by;
		$temp['sort_dir'] = $sort_dir;
		$temp['show_as'] = $show_as;
		$search_id = FrmCache::add( $temp);

		//if ($action != 'show_new' && $action != 'show_24h')// pkoi on ne redirige pas dans ces 2 cas la?
		//{
			// redirection vers la recherche en cache
			//header('Location: forum-search.html?search_id='.$search_id);
			//exit;
		//}

	}

	if($search_id){
		$temp = FrmCache::get( $search_id);
		if(!$temp){
			$_tpl->set('no_hits', true);
			break;
		}
		$search_results = $temp['search_results'];
		$search_ids = explode(',', $search_results);
		$num_hits = $temp['num_hits'];
		$sort_by = $temp['sort_by'];
		$sort_dir = $temp['sort_dir'];
		$show_as = $temp['show_as'];
	}

	// afficher le résultat de la recherche...
	if ($search_results != '')
	{
		$cond = array();
		$cond['sort_by'] = $sort_by;

		// Determiner le décalage de page (basé sur $_GET['p'])
		$num_pages = ceil($num_hits / LIMIT_PAGE);
		$p = request('p', 'uint', 'get');
		if($p <= 1 || $p > $num_pages)
			$p = 1;
		if($num_pages > 1){// pagination dans les résultats
			$_tpl->set('arr_pge', get_list_page( $p, $num_pages));
			$_tpl->set('pge',$p);
		}

		$cond['start'] = LIMIT_PAGE * ($p - 1);
		$cond['limit'] = LIMIT_PAGE;
		$cond['sort_dir'] = $sort_dir;

		$frm_array = Frm::get();
		$_tpl->set('frm_array', index_array($frm_array, 'fid'));
		if ($show_as == 'posts')
		{
			$cond['select'] = 'substr';
			$cond['pid_list'] = $search_ids;
			$_tpl->set('posts_array', FrmPost::get($cond));
		}
		else
		{
			$cond['tid_list'] = $search_ids;
			$cond['select'] = 'mbr';
			$_tpl->set('topic_array', FrmTopic::get($cond));
			$_tpl->set('frm_act',$_act);
		}
		$_tpl->set('action',empty($action) ? 'search': $action);
		$_tpl->set('search_id',$search_id);
		
	}else{
		$_tpl->set('no_hits', true);
	}
	break;


/*******************************/
case 'rep' : // formulaire de création & réponse

	$fid = request('fid', 'uint','get');
	$tid = request('tid','uint','get');
	$pid = request('pid','uint','get');
	$quote = request('qt','uint','get');
	$action = '';
	if($quote) // citation
		$_tpl->set('quote', FrmPost::getById($quote));

	if ($fid) // on créé un topic
	{
		$_tpl->set('form_url',"forum-post.html?sub=new&fid=$fid");
		$_tpl->set('new','topic');
		$action = 'topic'; // nv sujet
	}
	elseif ($tid) // on fait une réponse
	{
		$_tpl->set('form_url', "forum-post.html?sub=new&tid=$tid#lst_pst");
		$_tpl->set('new','post');
		$info = FrmTopic::getInfo($tid,$group);
		$_tpl->set('pst',$info);
		$fid = $info['forum_id'];
		$action = 'post'; // nouvelle réponse
		if (!isset($info['read_forum']) || $info['read_forum'])
			$_tpl->set('messages',FrmPost::getLastFromTopic($tid));
	}

	elseif ($pid) // on édite un message
	{
		$_tpl->set('form_url', "forum-post.html?sub=edit&pid=$pid#$pid");
		$_tpl->set('new','edit');
		$info = FrmPost::get(array('pid'=>$pid, 'select'=>'first_pid'))->get()->toArray();
		$action = 'edit';
		if($info){
			$fid = $info[0]['forum_id'];
			$info[0]['message'] = $info[0]['message'];
			$_tpl->set('pst',$info[0]);
		}
		if($is_modo) // liste des forums pour déplacement
			$_tpl->set('cat_array', Frm::getCat());
	}
	// récupérer les infos du forum
	$frm = Frm::get(0, $fid);
	if(empty($frm) or $_ses->canDo(DROIT_PUNBB_GUEST))// permission refusée ou catégorie introuvable
		$_tpl->set('no_perm', true);
	elseif((!$frm[0]['post_replies'] and $action=='post') or (!$frm[0]['post_topics'] and $action=='topic'))// permission refusée
		$_tpl->set('no_perm', true);
	else
		$_tpl->set('frm', $frm[0]);
	break;
	
/*******************************/
case 'topic' :	
	$frm = Frm::get(0, $fid);
	if ($fid)
	{
		$_tpl->set('frm', $frm[0]);
		$topics = FrmTopic::get(['fid'=>$fid, 'select'=>'mbr', 'order'=>$frm[0]['sort_by']]);
                $_tpl->set('pg', new Paginator($topics));

	}
	else
		$_tpl->set('bad_fid', true);
	break;

//************************//
default :

	// si on veut lire un topic (idem secion "post")
	if ($tid || $pid)
	{
		//on obtient le tid si on a que le pid
		if (!$tid){
			$pst = FrmPost::getById( $pid);
			if($pst)
				$tid = $pst['tid'];
		}
		if($tid){
			$info = FrmTopic::getInfo($tid,$group);
			if(!empty($info)){

				$_tpl->set('frm_act','post');
				$edit = array();
				// marquer ce topic comme lu
				$_user['forum_lus'][$tid] = true;

				//et enfin l'affichage de message
				//on vérifie qu'on a le droit
				if (isset($info) && $info['read_forum'] == 1)
				{
					FrmTopic::view($tid);
					//dans ce cas, on envoie toutes les informations nécessaires au template
                                        if ($pid){
                                            $page = FrmPost::searchOffset($pid,$tid,0,LIMIT_PAGE);
                                        }else{
                                            $page = 0;
                                        }
					$_tpl->set('messages',new Paginator(FrmPost::getMsg($tid), $page));
					$_tpl->set('tpc', $info);
					$_tpl->set('spec_title', $info['subject']);
					if ($is_modo)
						$_tpl->set('cat_array', Frm::getCat());
				}
				else
					$_tpl->set('cant_read',true);

			}
		}
	}

	$cid = request('cid', 'uint', 'get');
	$_tpl->set('cat_array', Frm::getCat($cid, 0, true));
	break;
}


//} // endif(!can_d(DROIT_PLAY)) 

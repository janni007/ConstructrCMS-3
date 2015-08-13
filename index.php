<?php

	session_start();

    $APP = require_once __DIR__.'/vendor/base.php';

	$CONSTRUCTR_CONFIG = file_get_contents(__DIR__.'/CONSTRUCTR-CMS/CONFIG/constructr_config.json');
	$CONSTRUCTR_CONFIG = json_decode($CONSTRUCTR_CONFIG, true);

	$APP->set('DATABASE_HOSTNAME',$CONSTRUCTR_CONFIG['DATABASE_HOSTNAME']);
	$APP->set('DATABASE_DATABASE',$CONSTRUCTR_CONFIG['DATABASE_DATABASE']);
	$APP->set('DATABASE_PORT',$CONSTRUCTR_CONFIG['DATABASE_PORT']);
	$APP->set('DATABASE_USERNAME',$CONSTRUCTR_CONFIG['DATABASE_USERNAME']);
	$APP->set('DATABASE_PASSWORD',$CONSTRUCTR_CONFIG['DATABASE_PASSWORD']);
 	$APP->set('CONSTRUCTR_VERSION',$CONSTRUCTR_CONFIG['CONSTRUCTR_VERSION']);
	$APP->set('CONSTRUCTR_USER_SALT',$CONSTRUCTR_CONFIG['CONSTRUCTR_USER_SALT']);
	$APP->set('CONSTRUCTR_BASE_URL',$CONSTRUCTR_CONFIG['CONSTRUCTR_BASE_URL']);
	$APP->set('CONSTRUCTR_REPLACE_BASE_URL',$CONSTRUCTR_CONFIG['CONSTRUCTR_REPLACE_BASE_URL']);
	$APP->set('ENCODING','utf-8');
    $APP->set('AUTOLOAD', __DIR__.'/CONSTRUCTR-CMS/CONTROLLER/');
	$APP->set('CONSTRUCTR_LOG', $CONSTRUCTR_LOG = new \Log('CONSTRUCTR-CMS/LOGFILES/'.date('Y-m-d').'-constructr.txt'));
	$APP->set('CONSTRUCTR_FE_CACHE', __DIR__.'/CONSTRUCTR-CMS/CACHE/');
	$APP->set('TEMPLATES',$APP->get('CONSTRUCTR_BASE_URL').'/THEMES/');
	$APP->set('CONSTRUCTR_CACHE',true);

    try{
    	$APP->set('DBCON',$DBCON=new DB\SQL('mysql:host='.$APP->get('DATABASE_HOSTNAME').';port='.$APP->get('DATABASE_PORT').';dbname='.$APP->get('DATABASE_DATABASE'),$APP->get('DATABASE_USERNAME'),$APP->get('DATABASE_PASSWORD')));
		$APP->set('DB_C',true);
    } catch (PDOException $e){
        $APP->set('DB_C',false);
    }

	if($APP->get('DB_C') == false){
		echo 'Setup ConstructrCMS <a href="'.((empty($_SERVER['HTTPS']))?'http://':'https://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'CONSTRUCTR-CMS-SETUP/">here</a>';
		die();
	}

    $REQUEST='http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $REQUEST=trim(str_replace($APP->get('CONSTRUCTR_REPLACE_BASE_URL'),'', $REQUEST));

    if (strpos($REQUEST, 'constructr') === false){
		if($APP->get('CONSTRUCTR_CACHE') == true){
			$UNIQUE=$APP->get('CONSTRUCTR_FE_CACHE').md5($REQUEST).'.html';

			if(file_exists($UNIQUE)){
				$CACHE_OUTPUT=@file_get_contents($APP->get('CONSTRUCTR_FE_CACHE').md5($REQUEST).'.html');
				echo $CACHE_OUTPUT;
				die();
			}
		}

		if($REQUEST == '/' || $REQUEST == ''){
	        $APP->set('ACT_PAGE', $APP->get('DBCON')->exec(
	                array('SELECT * FROM constructr_pages WHERE constructr_pages_order=:STARTPAGE_ORDER AND constructr_pages_nav_visible=1 LIMIT 1;'),
	                array(array(':STARTPAGE_ORDER'=>1))
	            )
	        );
		} else {
	        $APP->set('ACT_PAGE', $APP->get('DBCON')->exec(
	                array('SELECT * FROM constructr_pages WHERE constructr_pages_URL=:REQUEST AND constructr_pages_nav_visible=1 LIMIT 1;'),
	                array(array(':REQUEST'=>$REQUEST))
	            )
	        );
		}

		$APP->set('ACT_PAGE_COUNTR',0);
		$APP->set('ACT_PAGE_COUNTR',count($APP->get('ACT_PAGE')));

		if($APP->get('ACT_PAGE_COUNTR') == 1){
			$PAGE_EXT_URL=$APP->get('ACT_PAGE.0.constructr_pages_ext_url');

			if($PAGE_EXT_URL != ''){
				header('HTTP/1.1 301 Moved Permanently');
				header('Location: ' . $PAGE_EXT_URL);
				die();
			}

			$PAGE_ID=$APP->get('ACT_PAGE.0.constructr_pages_id');
			$PAGE_NAME=$APP->get('ACT_PAGE.0.constructr_pages_name');
			$PAGE_TEMPLATE=$APP->get('ACT_PAGE.0.constructr_pages_template');
			$PAGE_CSS=$APP->get('ACT_PAGE.0.constructr_pages_css');
			$PAGE_JS=$APP->get('ACT_PAGE.0.constructr_pages_js');
			$PAGE_TITLE=$APP->get('ACT_PAGE.0.constructr_pages_title');
			$PAGE_DESCRIPTION=$APP->get('ACT_PAGE.0.constructr_pages_description');
			$PAGE_KEYWORDS=$APP->get('ACT_PAGE.0.constructr_pages_keywords');
			$NAVIGATION='<ul><li><a href="'.$APP->get('CONSTRUCTR_BASE_URL').'">Home</a></li></ul>';
	        $APP->set('PAGES', $APP->get('DBCON')->exec(array('SELECT * FROM constructr_pages WHERE constructr_pages_nav_visible=1 ORDER BY constructr_pages_order ASC;'),array()));

			if($APP->get('PAGES')){
				$NAVIGATION = ConstructrBase::constructrNavGen($APP->get('CONSTRUCTR_BASE_URL'),$APP->get('PAGES'));
 			}

			$TEMPLATE=file_get_contents($APP->get('TEMPLATES').$PAGE_TEMPLATE);

			$APP->set('CONTENT', $APP->get('DBCON')->exec(
                    array('SELECT * FROM constructr_content WHERE constructr_content_page_id=:PAGE_ID AND constructr_content_visible=:VISIBILITY AND constructr_content_tpl_id_mapping=:NULLER ORDER BY constructr_content_order ASC;'),
                    array(
                        array(
                        	':PAGE_ID'=>$PAGE_ID,
                        	':NULLER'=>'',
                        	':VISIBILITY'=>1
                        )
                    )
                )
            );

			$CONTENT_COUNTR=count($APP->get('CONTENT'));

			if($CONTENT_COUNTR == 0){
				$PAGE_CONTENT_RAW='';
				$PAGE_CONTENT_HTML='';
			} else {
				$PAGE_CONTENT_HTML='';
				$PAGE_CONTENT_RAW='';

				foreach($APP->get('CONTENT') AS $CONTENT)
				{
					$PAGE_CONTENT_RAW.=$CONTENT['constructr_content_content_raw'];
					$PAGE_CONTENT_HTML.=$CONTENT['constructr_content_content_html'];
				}
			}

			$SEARCHR=array('{{@ CONSTRUCTR_BASE_URL @}}','{{@ PAGE_ID @}}','{{@ PAGE_TEMPLATE @}}','{{@ PAGE_NAME @}}','{{@ PAGE_CONTENT_RAW @}}','{{@ PAGE_CONTENT_HTML @}}','{{@ PAGE_CSS @}}','{{@ PAGE_JS @}}','{{@ PAGE_NAVIGATION_UL_LI @}}','{{@ CONSTRUCTR_PAGE_TITLE @}}','{{@ CONSTRUCTR_PAGE_KEYWORDS @}}','{{@ CONSTRUCTR_PAGE_DESCRIPTION @}}');
			$REPLACR=array($APP->get('CONSTRUCTR_BASE_URL'),$PAGE_ID,$PAGE_TEMPLATE,$PAGE_NAME,$PAGE_CONTENT_RAW,$PAGE_CONTENT_HTML,$PAGE_CSS,$PAGE_JS,$NAVIGATION,$PAGE_TITLE,$PAGE_DESCRIPTION,$PAGE_KEYWORDS);
			$TEMPLATE=str_replace($SEARCHR,$REPLACR,$TEMPLATE);

			$APP->set('MAPPING_CONTENT', $APP->get('DBCON')->exec(
                    array('SELECT * FROM constructr_content WHERE constructr_content_page_id=:PAGE_ID AND constructr_content_visible=:VISIBILITY AND constructr_content_tpl_id_mapping!=:NULLER ORDER BY constructr_content_order ASC;'),
                    array(
                        array(
                        	':PAGE_ID'=>$PAGE_ID,
                        	':NULLER'=>'',
                        	':VISIBILITY'=>1
                        )
                    )
                )
            );

			if(count($APP->get('MAPPING_CONTENT')) != 0){
				preg_match_all("/({{@ CONSTRUCTR_MAPPING\()+([\w-])+(\) @}})/", $TEMPLATE, $MATCH);
				$CONSTRUCTR_TPL_MAPPINGS = array();

				if($MATCH[0]){
					$i = 0;
					foreach($MATCH[0] AS $MATCHR){
						$CONSTRUCTR_TPL_MAPPINGS[$i] = $MATCHR;
						$i++;
					}

					if($CONSTRUCTR_TPL_MAPPINGS){
						$MAPPERS = array();

						foreach($APP->get('MAPPING_CONTENT') AS $KEY => $MAPPING_CONTENT){
							if(!isset($MAPPERS[$MAPPING_CONTENT['constructr_content_tpl_id_mapping']]) || $MAPPERS[$MAPPING_CONTENT['constructr_content_tpl_id_mapping']] == ''){
								$MAPPERS[$MAPPING_CONTENT['constructr_content_tpl_id_mapping']] = $MAPPING_CONTENT['constructr_content_content_html'];	
							} else {
								$MAPPERS[$MAPPING_CONTENT['constructr_content_tpl_id_mapping']] = $MAPPERS[$MAPPING_CONTENT['constructr_content_tpl_id_mapping']].$MAPPING_CONTENT['constructr_content_content_html'];	
							}
						}

						$MAPPERS = array_filter($MAPPERS,'strlen');

						foreach($CONSTRUCTR_TPL_MAPPINGS AS $MAP_NOW_KEY => $MAP_NOW_VALUE) {
							if(isset($MAPPERS[$MAP_NOW_VALUE])) {
								$TEMPLATE=str_replace($MAP_NOW_VALUE,$MAPPERS[$MAP_NOW_VALUE],$TEMPLATE);
							} else {
								$TEMPLATE=str_replace($MAP_NOW_VALUE,'',$TEMPLATE);
							}
						}
					}
				}
			} else {
				preg_match_all("/({{@ CONSTRUCTR_MAPPING\()+([\w-])+(\) @}})/", $TEMPLATE, $MATCH);

				foreach($MATCH[0] AS $MATCHR) {
					$TEMPLATE=str_replace($MATCHR,'',$TEMPLATE);
				}
			}

			$TEMPLATE .="\n<!-- ConstructrCMS Version ".$APP->get("CONSTRUCTR_VERSION")." / http://phaziz.com -->";

			if($APP->get('CONSTRUCTR_CACHE') == true){
				@file_put_contents($UNIQUE=$APP->get('CONSTRUCTR_FE_CACHE').md5($REQUEST).'.html',$TEMPLATE."\n".'<!-- ConstructrCache '.date('Y-m-d H:i:s').' -->');
			}

			echo $TEMPLATE;
			die();
		} else {
			$APP->get('CONSTRUCTR_LOG')->write('Frontend: 404');
			$APP->reroute($APP->get('CONSTRUCTR_BASE_URL'));
		}
	} else {
		if(!$APP->get('SESSION.login') || $APP->get('SESSION.login') == 'false'){
			$APP->set('SESSION.login','false');
			$APP->set('SESSION.username','');
		}

		$APP->set('NAVIGATION','./CONSTRUCTR-CMS/TEMPLATES/constructr_navigation.html');
		$APP->set('DEBUG',3);
	    $APP->set('CACHE',true);
		$APP->set('UPLOADS',__DIR__.'/UPLOADS/');

		require_once __DIR__.'/CONSTRUCTR-CMS/USER_RIGHTS/user_rights.php';
		require_once __DIR__.'/CONSTRUCTR-CMS/ROUTES/constructr_routes.php';

		$APP->set('ALL_CONSTRUCTR_USER_RIGHTS',$CONSTRUCTR_USER_RIGHTS);
	}

	$APP->set('levelIndicator',
	    function($LEVEL){
	    	$RET = '';

			for ($i = 1; $i <= $LEVEL; $i++){
				$RET .= '&#160;&#160;&#160;';
			}

			return $RET;
	    }
	);

	$APP->set('ONERROR', function ($APP){
        while (ob_get_level()){
            ob_end_clean();
        }

		$APP->get('CONSTRUCTR_LOG')->write($APP->get('ERROR.text') . ' - '. $APP->get('ERROR.code') . ': '. $APP->get('ERROR.status'));

		if($APP->get('ERROR.code') == '404'){
			$APP->reroute($APP->get('CONSTRUCTR_BASE_URL').'/constructr/404');
		} else {
			$APP->reroute($APP->get('CONSTRUCTR_BASE_URL').'/constructr/error');
		}
    });

    $APP->run();

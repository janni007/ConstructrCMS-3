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

    try {
    	$APP->set('DBCON', $DBCON = new DB\SQL('mysql:host=' . $APP->get('DATABASE_HOSTNAME') . ';port=' . $APP->get('DATABASE_PORT') . ';dbname=' . $APP->get('DATABASE_DATABASE'), $APP->get('DATABASE_USERNAME'), $APP->get('DATABASE_PASSWORD')));
		$APP->set('DB_CONNECTION', true);
    } catch (PDOException $e) {
        $APP->set('DB_CONNECTION', false);
    }

	if($APP->get('DB_CONNECTION') == false) {
	    function getCurrentUrl() {
	        $ACT_URL = ((empty($_SERVER['HTTPS'])) ? 'http://' : 'https://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	        return $ACT_URL;
	    }

		$CURRENT_URL = getCurrentUrl();
		echo 'Setup ConstructrCMS <a href="' . $CURRENT_URL . 'CONSTRUCTR-CMS-SETUP/">here</a>';

		die();
	}

	$APP->set('TEMPLATES',$APP->get('CONSTRUCTR_BASE_URL').'/THEMES/');

    $REQUEST = 'http://' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $REQUEST = trim(str_replace($APP->get('CONSTRUCTR_REPLACE_BASE_URL'), '', $REQUEST));

    if (strpos($REQUEST, 'constructr') === false) {
		if($REQUEST == '/' || $REQUEST == '') {
	        $APP->set('ACT_PAGE', $APP->get('DBCON')->exec(
	                array(
	                    'SELECT * FROM constructr_pages WHERE constructr_pages_order=:STARTPAGE_ORDER AND constructr_pages_nav_visible=1 LIMIT 1;'
	                ),
	                array(
	                    array(
	                    	':STARTPAGE_ORDER'=>1
	                    )
	                )
	            )
	        );
		} else {
	        $APP->set('ACT_PAGE', $APP->get('DBCON')->exec(
	                array(
	                    'SELECT * FROM constructr_pages WHERE constructr_pages_URL=:REQUEST AND constructr_pages_nav_visible=1 LIMIT 1;'
	                ),
	                array(
	                    array(
	                    	':REQUEST'=>$REQUEST
	                    )
	                )
	            )
	        );
		}

		$APP->set('ACT_PAGE_COUNTR',0);
		$APP->set('ACT_PAGE_COUNTR',count($APP->get('ACT_PAGE')));

		if($APP->get('ACT_PAGE_COUNTR') == 1) {
			$PAGE_ID=$APP->get('ACT_PAGE.0.constructr_pages_id');
			$PAGE_NAME=$APP->get('ACT_PAGE.0.constructr_pages_name');
			$PAGE_TEMPLATE=$APP->get('ACT_PAGE.0.constructr_pages_template');
			$PAGE_CSS=$APP->get('ACT_PAGE.0.constructr_pages_css');
			$PAGE_JS=$APP->get('ACT_PAGE.0.constructr_pages_js');
			$PAGE_TITLE=$APP->get('ACT_PAGE.0.constructr_pages_title');
			$PAGE_DESCRIPTION=$APP->get('ACT_PAGE.0.constructr_pages_description');
			$PAGE_KEYWORDS=$APP->get('ACT_PAGE.0.constructr_pages_keywords');
			$NAVIGATION = '';

	        $APP->set('PAGES', $APP->get('DBCON')->exec(
	                array(
	                    'SELECT * FROM constructr_pages WHERE constructr_pages_nav_visible=1 ORDER BY constructr_pages_order ASC;'
	                ),
	                array(
	                    array(
	                    )
	                )
	            )
	        );

			$PAGES = $APP->get('PAGES');

			if($PAGES) {
				$DOM = new domDocument();
				$DOM->validateOnParse = true;
				$DOM->loadHTML('<html>');
				$ROOT = $DOM->createElement('ul');
				$ROOT->setAttribute('class','constructr-menu');
				$DOM->appendChild($ROOT);

				foreach($PAGES as $KEY => $VALUE) {
				    $PID = $VALUE['constructr_pages_mother'];
				    $PARENT = $DOM->getElementById('constructr-li-'.$PID);

				    if($PARENT != null) {
				        $UL = $DOM->getElementById('ul-'.$PID);

				        if($UL == null) {
				            $UL = $DOM->createElement('ul');
				            $UL->setAttribute('id','constructr-ul-'.$PID);
				            $PARENT->appendChild($UL);
				        }

				        $TARGET=$UL;
				    }
				    else
				    {
				        $TARGET=$ROOT;
				    }

				    $LI = $DOM->createElement('li','<a href="' . $APP->get('CONSTRUCTR_BASE_URL') . '/' . $VALUE['constructr_pages_url'] . '">' . $VALUE['constructr_pages_name'] . '</a>');
				    $LI->setAttribute('id','constructr-li-'.$VALUE['constructr_pages_id']);
					$TARGET->appendChild($LI);
				}

				$NAVIGATION = $DOM->saveHTML($ROOT);
				$NAVIGATION = str_replace('&lt;','<',$NAVIGATION);
				$NAVIGATION = str_replace('&gt;','>',$NAVIGATION);
			}

			$TEMPLATE=file_get_contents($APP->get('TEMPLATES').$PAGE_TEMPLATE);
            $APP->set('CONTENT', $APP->get('DBCON')->exec(
                    array(
                        'SELECT * FROM constructr_content WHERE constructr_content_page_id=:PAGE_ID AND constructr_content_visible=1 ORDER BY constructr_content_order ASC;'
                    ),
                    array(
                        array(
                        	':PAGE_ID'=>$PAGE_ID
                        )
                    )
                )
            );

			$CONTENT_COUNTR=count($APP->get('CONTENT'));

			if($CONTENT_COUNTR == 0) {
				$PAGE_CONTENT_RAW='<p>Keine Inhalte vorhanden</p>';
				$PAGE_CONTENT_HTML='<p>Keine Inhalte vorhanden</p>';	
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
			$TEMPLATE .="\n<!-- ConstructrCMS Version ".$APP->get("CONSTRUCTR_VERSION")." / http://phaziz.com -->";

			echo $TEMPLATE;

			die();
		} else {
			$APP->get('CONSTRUCTR_LOG')->write('Frontend: 404');
			$APP->reroute($APP->get('CONSTRUCTR_BASE_URL').'/constructr/404');
		}
	} else {
		if(!$APP->get('SESSION.login') || $APP->get('SESSION.login') == 'false') {
			$APP->set('SESSION.login','false');
			$APP->set('SESSION.username','');
		}

		$APP->set('DEBUG',3);
	    $APP->set('CACHE',true);
		$APP->set('TEMP','folder='.__DIR__.'/CONSTRUCTR-CMS/CACHE/');
		$APP->set('BACKEND_CACHE','folder='.__DIR__.'/CONSTRUCTR-CMS/CACHE/');
		$APP->set('UPLOADS',__DIR__.'/UPLOADS/');
		$APP->set('CONSTRUCTR_LOG', $CONSTRUCTR_LOG = new \Log('CONSTRUCTR-CMS/LOGFILES/'.date('Y-m-d').'-constructr.txt'));	

		require_once __DIR__.'/CONSTRUCTR-CMS/USER_RIGHTS/user_rights.php';

		$APP->set('ALL_CONSTRUCTR_USER_RIGHTS',$CONSTRUCTR_USER_RIGHTS);

		require_once __DIR__.'/CONSTRUCTR-CMS/ROUTES/constructr_routes.php';
	}

	$APP->set('levelIndicator',
	    function($LEVEL) {
	    	$RET = '';

			for ($i = 1; $i <= $LEVEL; $i++) {
				$RET .= '&#160;&#160;&#160;';
			}

			return $RET;
	    }
	);

	$APP->set('ONERROR', function ($APP) {
        while (ob_get_level()) {
            ob_end_clean();
        }

		$APP->get('CONSTRUCTR_LOG')->write($APP->get('ERROR.text') . ' - '. $APP->get('ERROR.code') . ' '. $APP->get('ERROR.status'));

		if($APP->get('ERROR.code') == '404')
		{
			$APP->reroute($APP->get('CONSTRUCTR_BASE_URL').'/constructr/404');
		}
		else
		{
			$APP->reroute($APP->get('CONSTRUCTR_BASE_URL').'/constructr/error');
		}
    });

    $APP->run();

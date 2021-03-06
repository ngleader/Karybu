<?php
    /**
     * @class  installAdminController
     * @author Arnia (dev@karybu.org)
     * @brief admin controller class of the install module
     **/
    class installAdminController extends install {


        /**
         * @brief Initialization
         **/
        function init() {
        }

        /**
         * @brief Install the module
         **/
        function procInstallAdminInstall() {
            $module_name = Context::get('module_name');
            if(!$module_name) return new object(-1, 'invalid_request');

            $oInstallController = &getController('install');
            $oInstallController->installModule($module_name, './modules/'.$module_name);

            $this->setMessage('success_installed');
        }

        /**
         * @brief Upate the module
         **/
        function procInstallAdminUpdate() {
			set_time_limit(0);
            $module_name = Context::get('module_name');
            if(!$module_name) return new object(-1, 'invalid_request');

            $oModule = &getModule($module_name, 'class');
            if($oModule) $output = $oModule->moduleUpdate();
            else $output = new Object(-1, 'invalid_request');

            return $output;
        }

        /**
         * @brief Change settings
         **/
        function procInstallAdminSaveTimeZone() {
            $use_rewrite = Context::get('use_rewrite');
            if($use_rewrite!='Y') $use_rewrite = 'N';

            $use_sso = Context::get('use_sso');
            if($use_sso !='Y') $use_sso = 'N';

			$use_cdn = Context::get('use_cdn');
			if($use_cdn != 'Y') $use_cdn = 'N';

            $time_zone = Context::get('time_zone');

            $qmail_compatibility = Context::get('qmail_compatibility');
            if($qmail_compatibility!='Y') $qmail_compatibility = 'N';

            $use_db_session = Context::get('use_db_session');
            if($use_db_session!='Y') $use_db_session = 'N';

            $use_ssl = Context::get('use_ssl');
            if(!$use_ssl) $use_ssl = 'none';

			$use_html5 = Context::get('use_html5');
			if(!$use_html5) $use_html5 = 'N';

            $http_port = Context::get('http_port');
            $https_port = Context::get('https_port');

			$use_mobile_view = Context::get('use_mobile_view');
			if ($use_mobile_view != 'Y') $use_mobile_view = 'N';

			$admin_ip_list = Context::get('admin_ip_list');

			$admin_ip_list = preg_replace("/[\r|\n|\r\n]+/",",",$admin_ip_list);
			$admin_ip_list = preg_replace("/\s+/","",$admin_ip_list);
			if(preg_match('/(<\?|<\?php|\?>)/xsm', $admin_ip_list))
			{
				$admin_ip_list = '';
			}

			$db_info = Context::getDBInfo();
            $db_info->default_url = Context::get('default_url');
            if($db_info->default_url && !preg_match('/^(http|https):\/\//i', $db_info->default_url)) $db_info->default_url = 'http://'.$db_info->default_url;
            $db_info->time_zone = $time_zone;
            $db_info->qmail_compatibility = $qmail_compatibility;
            $db_info->use_db_session = $use_db_session;
            $db_info->use_rewrite = $use_rewrite;
            $db_info->use_sso = $use_sso;
            $db_info->use_ssl = $use_ssl;
			$db_info->use_cdn = $use_cdn;
			$db_info->use_html5 = $use_html5;
			$db_info->use_mobile_view = $use_mobile_view;
			$db_info->admin_ip_list = $admin_ip_list;

			if(!empty($http_port)) {
                $db_info->http_port = (int) $http_port;
            }
            else if(!empty($db_info->http_port)) {
                unset($db_info->http_port);
            }

            if(!empty($https_port)) {
                $db_info->https_port = (int) $https_port;
            }
            else if(!empty($db_info->https_port)) {
                unset($db_info->https_port);
            }

            unset($db_info->lang_type);
            Context::setDBInfo($db_info);

            $oInstallController = &getController('install');
            $oInstallController->makeConfigFile();
            $site_args = new stdClass();
            $site_args->site_srl = 0;
            $site_args->index_module_srl = Context::get('index_module_srl');
            $site_args->default_language = Context::get('change_lang_type');
            $site_args->domain = $db_info->default_url;
            $oModuleController = &getController('module');
            $oModuleController->updateSite($site_args);

			$this->setMessage('success_updated');
        }

        function procInstallAdminRemoveFTPInfo() {
            $ftp_config_file = Context::getFTPConfigFile();
            if(file_exists($ftp_config_file)) unlink($ftp_config_file);
            if($_SESSION['ftp_password']) unset($_SESSION['ftp_password']);
            $this->setMessage('success_deleted');
        }

        function procInstallAdminSaveFTPInfo() {
            $ftp_info = Context::getFTPInfo();
            if (!is_object($ftp_info)) {
                $ftp_info = new stdClass();
            }
            $ftp_info->ftp_user = Context::get('ftp_user');
            $ftp_info->ftp_port = Context::get('ftp_port');
            $ftp_info->ftp_host = Context::get('ftp_host');
			$ftp_info->ftp_pasv = Context::get('ftp_pasv');
			if ($ftp_info->ftp_pasv !== 'Y') {
                $ftp_info->ftp_pasv = "N";
            }
            $ftp_info->sftp = Context::get('sftp');

			$ftp_root_path = Context::get('ftp_root_path');
			if (substr($ftp_root_path, strlen($ftp_root_path)-1) == "/") {
				$ftp_info->ftp_root_path = $ftp_root_path;
			} else {
				$ftp_info->ftp_root_path = $ftp_root_path.'/';
			}

            if(ini_get('safe_mode')) {
                $ftp_info->ftp_password = Context::get('ftp_password');
            }

            $buff = '<?php if(!defined("__KARYBU__")) exit();'."\n";
            $buff .= '$ftp_info = new stdClass();' . "\n";
            foreach ($ftp_info as $key => $val) {
                if (!$val) continue;
				if (preg_match(
                    '/(<\?|<\?php|\?>|fputs|fopen|fwrite|fgets|fread|\/\*|\*\/|chr\()/xsm'
                    , preg_replace('/\s/', '', $val)
                )) continue;
                $buff .= sprintf("\$ftp_info->%s = '%s';\n", $key, str_replace("'","\\'",$val));
            }
            $config_file = Context::getFTPConfigFile();
            FileHandler::WriteFile($config_file, $buff);
            if(!empty($_SESSION['ftp_password'])) {
                unset($_SESSION['ftp_password']);
            }
            $this->setMessage('success_updated');
			$this->setRedirectUrl(Context::get('error_return_url'));
		}

		function procInstallAdminConfig(){
			$this->procInstallAdminSaveTimeZone();

			$selected_lang = Context::get('selected_lang');
			$this->saveLangSelected($selected_lang);
            $config = new stdClass();
			$config->thumbnail_type = Context::get('thumbnail_type');
			$config->htmlFooter = Context::get('htmlFooter');
			$this->setModulesConfig($config);

			$favicon = Context::get('favicon');
			$this->saveIcon($favicon,'favicon.ico');

			$mobicon = Context::get('mobicon');
			$this->saveIcon($mobicon,'mobicon.png');

			$this->setRedirectUrl(Context::get('error_return_url'));
		}

		//from procInstallAdminSaveTimeZone
		/**
         * @brief Supported languages (was procInstallAdminSaveLangSelected)
         **/
		function saveLangSelected($selected_lang){
			$langs = $selected_lang;

            $lang_supported = Context::loadLangSupported();
            $buff = null;
            for($i=0;$i<count($langs);$i++) {
                $buff .= sprintf("%s,%s\n", $langs[$i], $lang_supported[$langs[$i]]);

            }
            FileHandler::writeFile(_KARYBU_PATH_.'files/config/lang_selected.info', trim($buff));
			//$this->setMessage('success_updated');
		}

		function setModulesConfig($config) {
            $args = new stdClass();
			if(!$config->thumbnail_type || $config->thumbnail_type != 'ratio' ) {
                $args->thumbnail_type = 'crop';
            }
			else {
                $args->thumbnail_type = 'ratio';
            }

			$oModuleController = &getController('module');
			$oModuleController->insertModuleConfig('document',$args);
			
			unset($args);
			$args = new stdClass();
			$args->htmlFooter = $config->htmlFooter;
			$oModuleController->insertModuleConfig('module',$args);

			//return $output;
            return null;
		}

		function saveIcon($icon,$iconname) {
			$mobicon_size = array('57','114');
			$target_file = $icon['tmp_name'];
			$type = $icon['type'];
			$target_filename = _KARYBU_PATH_.'files/attach/xeicon/'.$iconname;

			list($width, $height, $type_no, $attrs) = @getimagesize($target_file);
			if($iconname == 'favicon.ico' && preg_match('/^.*(icon).*$/',$type)){
				$fitHeight = $fitWidth = '16';
			} else if($iconname == 'mobicon.png' && preg_match('/^.*(png).*$/',$type) && in_array($height,$mobicon_size) && in_array($width,$mobicon_size)) {
				$fitHeight = $fitWidth = $height;
			} else{
				return false;
			}
			//FileHandler::createImageFile($target_file, $target_filename, $fitHeight, $fitWidth, $ext);
			FileHandler::copyFile($target_file, $target_filename);
		}

        /**
         * handle debug save settings
         *
         * @access public
         * @return void
         */
        function procInstallAdminDebug() {
            $env = Context::get('debug_env');
            //validate environment
            try{
                $environment = \Karybu\Environment\Environment::getEnvironment($env);
                $allowedSettings = array(
                    'level',
                    'toolbar',
                    'slow_queries_threshold',
                    'handlers');
                $values = array();
                $values['imports'][0]['resource'] = '../../config/config_'.$env.'.base.yml';
                $values['debug'] = array();
                foreach ($allowedSettings as $setting){
                    if (!is_null(Context::get($setting))){
                        $value = null;
                        switch ($setting){
                            case 'level':
                                $value = Context::get($setting);
                                break;
                            case 'toolbar':
                                $_val = Context::get($setting);
                                if ($_val === 'false' || !($_val)){
                                    $value = false;
                                }
                                else{
                                    $value = true;
                                }
                                break;
                            case 'slow_queries_threshold':
                                $value = (int)(Context::get($setting));
                                break;
                            case 'handlers' :
                                $value = Context::get($setting);
                                break;
                        }
                        if (!is_null($value)){
                            $values['debug'][$setting] = $value;
                        }
                    }
                }
                $dumper = new \Symfony\Component\Yaml\Dumper();
                $yaml = $dumper->dump($values, 2);
                FileHandler::writeFile(_KARYBU_PATH_.'files/config/config_'.$env.'.yml', $yaml);
                $this->setMessage('success_updated', 'info');
            }
            catch (Exception $e){
                $this->setMessage($e->getMessage(), 'error');
            }
            $this->setRedirectUrl(Context::get('error_return_url'));
        }
        function procInstallAdminEnvironment() {
            $env = Context::get('environment');
            $environments = \Karybu\Environment\Environment::getEnvironments();
            if (isset($environments[$env])){
                $filename = _KARYBU_PATH_.'files/config/environment.txt';
                FileHandler::writeFile($filename, $env);
                $this->setMessage('success_updated', 'info');
            }
            $this->setRedirectUrl(Context::get('error_return_url'));
        }
    }
?>

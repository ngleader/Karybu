<?php
/**
 * @class  moduleModel
 * @author Arnia (dev@karybu.org)
 * @brief Model class of module module
 **/

class moduleModel extends module
{

    /**
     * @brief Initialization
     **/
    function init()
    {
    }

    /**
     * @brief Check if mid, vid are available
     **/
    function isIDExists($id, $site_srl = 0)
    {
        if (!preg_match('/^[a-z]{1}([a-z0-9_]+)$/i', $id)) {
            return true;
        }
        // directory and rss/atom/api reserved checking, etc.
        $dirs = FileHandler::readDir(_KARYBU_PATH_);
        $dirs[] = 'rss';
        $dirs[] = 'atom';
        $dirs[] = 'api';
        if (in_array($id, $dirs)) {
            return true;
        }
        // mid test
        $args = new stdClass();
        $args->mid = $id;
        $args->site_srl = $site_srl;
        $output = executeQuery('module.isExistsModuleName', $args);
        if ($output->data->count) {
            return true;
        }
        // vid test (check mid != vid if site_srl=0, which means it is not a virtual site)
        if (!$site_srl) {
            $site_args = new stdClass();
            $site_args->domain = $id;
            $output = executeQuery('module.isExistsSiteDomain', $site_args);
            if ($output->data->count) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Get site information
     **/
    function getSiteInfo($site_srl, $columnList = array())
    {
        $args = new stdClass();
        $args->site_srl = $site_srl;
        $output = executeQuery('module.getSiteInfo', $args, $columnList);
        return $output->data;
    }

    function getSiteInfoByDomain($domain, $columnList = array())
    {
        $args = new stdClass();
        $args->domain = $domain;
        $output = executeQuery('module.getSiteInfoByDomain', $args, $columnList);
        return $output->data;
    }

    /**
     * @brief Get module information with document_srl
     * In this case, it is unable to use the cache file
     **/
    function getModuleInfoByDocumentSrl($document_srl)
    {
        $args = new stdClass();
        $args->document_srl = $document_srl;
        $output = executeQuery('module.getModuleInfoByDocument', $args);
        return $this->addModuleExtraVars($output->data);
    }

    /**
     * @brief Get the defaul mid according to the domain
     **/
    function getDefaultMid()
    {
        $default_url = preg_replace('/\/$/', '', Context::getDefaultUrl());
        $request_url = preg_replace('/\/$/', '', Context::getRequestUri());
        $default_url_parse = parse_url($default_url);
        $request_url_parse = parse_url($request_url);
        $vid = Context::get('vid');
        $mid = Context::get('mid');
        // Set up
        // test.xe.com
        $domain = '';
        if ($default_url && $default_url_parse['host'] != $request_url_parse['host']) {
            $url_info = parse_url($request_url);
            $hostname = $url_info['host'];
            $path = preg_replace('/\/$/', '', $url_info['path']);
            $domain = sprintf(
                '%s%s%s',
                $hostname,
                $url_info['port'] && $url_info['port'] != 80 ? ':' . $url_info['port'] : '',
                $path
            );
        }
        // xe.com/blog
        if ($domain === '') {
            if (!$vid) {
                $vid = $mid;
            }
            if ($vid) {
                $domain = $vid;
            }
        }

        $oCacheHandler = & CacheHandler::getInstance('object');
        $args = new stdClass();
        // If domain is set, look for subsite
        if ($domain !== '') {
            if ($oCacheHandler->isSupport()) {
                $output = $oCacheHandler->get('domain_' . $domain);
            }
            if (!isset($output)) {
                $args->domain = $domain;
                $output = executeQuery('module.getSiteInfoByDomain', $args);
                if ($oCacheHandler->isSupport() && $output->data) {
                    $oCacheHandler->put('domain_' . $domain, $output);
                }
            }
            if ($output->toBool() && $output->data && $vid) {
                Context::set('vid', $output->data->domain, true);
                if (strtolower($mid) == strtolower($output->data->domain)) {
                    Context::set(
                        'mid',
                        $output->data->mid,
                        true
                    );
                }
            }
            if (!$output || !$output->data) {
                $domain = '';
                unset($output);
            }
        }
        // If no virtual website was found, get default website
        if ($domain === '') {
            if ($oCacheHandler->isSupport()) {
                $output = $oCacheHandler->get('default_site');
            }
            if (!isset($output)) {
                $args->site_srl = 0;
                $output = executeQuery('module.getSiteInfo', $args);
                // Update the related informaion if there is no default site info
                if (!$output->data) {
                    // Create a table if sites table doesn't exist
                    $oDB = DB::getInstance();
                    if (!$oDB->isTableExists('sites')) {
                        $oDB->createTableByXmlFile(
                            _KARYBU_PATH_ . 'modules/module/schemas/sites.xml'
                        );
                    }
                    if (!$oDB->isTableExists('sites')) {
                        return;
                    }
                    // Get mid, language
                    $mid_output = $oDB->executeQuery('module.getDefaultMidInfo', $args);
                    $db_info = Context::getDBInfo();
                    $domain = Context::getDefaultUrl();
                    $url_info = parse_url($domain);
                    $domain = $url_info['host'] . ((!empty($url_info['port']) && $url_info['port'] != 80) ? ':' . $url_info['port'] : '') . $url_info['path'];
                    $site_args = new stdClass();
                    $site_args->site_srl = 0;
                    $site_args->index_module_srl = $mid_output->data->module_srl;
                    $site_args->domain = $domain;
                    $site_args->default_language = $db_info->lang_type;

                    if ($output->data && !$output->data->index_module_srl) {
                        $output = executeQuery('module.updateSite', $site_args);
                    } else {
                        $output = executeQuery('module.insertSite', $site_args);
                        if (!$output->toBool()) {
                            return $output;
                        }
                    }
                    $output = executeQuery('module.getSiteInfo', $args);
                }
                if ($oCacheHandler->isSupport()) {
                    $oCacheHandler->put('default_site', $output);
                }
            }
        }
        $module_info = $output->data;
        if (!$module_info->module_srl) {
            return $module_info;
        }
        if (is_array($module_info) && $module_info->data[0]) {
            $module_info = $module_info[0];
        }
        return $this->addModuleExtraVars($module_info);
    }

    /**
     * @brief Get module information by mid
     **/
    function getModuleInfoByMid($mid, $site_srl = 0, $columnList = array())
    {
        if (!$mid) {
            return;
        }
        $args = new stdClass();
        $args->mid = $mid;
        $args->site_srl = (int)$site_srl;
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $cache_key = 'object:' . $mid . '_' . $site_srl;
            $module_srl = $oCacheHandler->get($cache_key);
            if ($module_srl) {
                $cache_key = 'object_module_info:' . $module_srl;
                $output = $oCacheHandler->get($cache_key);
            }
        }
        if (!isset($output)) {
            $output = executeQuery('module.getMidInfo', $args);
            if ($oCacheHandler->isSupport()) {
                $cache_key = 'object:' . $mid . '_' . $site_srl;
                $oCacheHandler->put($cache_key, $output->data->module_srl);
                $cache_key = 'object_module_info:' . $output->data->module_srl;
                $oCacheHandler->put($cache_key, $output);
            }
        }

        $module_info = $output->data;
        if (!$module_info->module_srl && $module_info->data[0]) {
            $module_info = $module_info->data[0];
        }
        return $this->addModuleExtraVars($module_info);
    }

    /**
     * @brief Get module information corresponding to module_srl
     **/
    function getModuleInfoByModuleSrl($module_srl, $columnList = array())
    {
        // Get data
        $args = new stdClass();
        $output = null;
        $args->module_srl = $module_srl;
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $cache_key = 'object_module_info:' . $module_srl;
            $output = $oCacheHandler->get($cache_key);
        }
        if (!$output) {
            $output = executeQuery('module.getMidInfo', $args);
            if (!$output->data) {
                return;
            }
            if ($oCacheHandler->isSupport()) {
                $oCacheHandler->put($cache_key, $output);
            }
        }
        $module_info = new stdClass();
        if (count($columnList)) {
            foreach ($output->data as $key => $item) {
                if (in_array($key, $columnList)) {
                    $module_info->$key = $item;
                }
            }
        } else {
            $module_info = $output->data;
        }
        return $this->addModuleExtraVars($module_info);
    }

    /**
     * @brief Get module information corresponding to layout_srl
     **/
    function getModulesInfoByLayout($layout_srl, $columnList = array())
    {
        // Imported data
        $args->layout_srl = $layout_srl;
        $output = executeQueryArray('module.getModulesByLayout', $args, $columnList);

        $count = count($output->data);

        $modules = array();
        for ($i = 0; $i < $count; $i++) {
            $modules[] = $output->data[$i];
        }
        return $this->addModuleExtraVars($modules);
    }

    /**
     * @brief Get module information corresponding to multiple module_srls
     **/
    function getModulesInfo($module_srls, $columnList = array())
    {
        if (is_array($module_srls)) {
            $module_srls = implode(',', $module_srls);
        }
        $args = new stdClass();
        $args->module_srls = $module_srls;
        $output = executeQueryArray('module.getModulesInfo', $args, $columnList);
        if (!$output->toBool()) {
            return;
        }
        return $this->addModuleExtraVars($output->data);
    }

    /**
     * @brief Add extra vars to the module basic information
     **/
    function addModuleExtraVars($module_info)
    {
        // Process although one or more module informaion is requested
        if (!is_array($module_info)) {
            $target_module_info = array($module_info);
        } else {
            $target_module_info = $module_info;
        }
        // Get module_srl
        $module_srls = array();
        foreach ($target_module_info as $key => $val) {
            $module_srl = isset($val->module_srl) ? $val->module_srl : null;
            if (!$module_srl) {
                continue;
            }
            $module_srls[] = $val->module_srl;
        }
        // Extract extra information of the module and skin
        $extra_vars = $this->getModuleExtraVars($module_srls);
        if (!count($module_srls) || !count($extra_vars)) {
            return $module_info;
        }

        foreach ($target_module_info as $key => $val) {
            if (!$extra_vars[$val->module_srl] || !count($extra_vars[$val->module_srl])) {
                continue;
            }
            foreach ($extra_vars[$val->module_srl] as $k => $v) {
                if (isset($target_module_info[$key]->{$k})) {
                    continue;
                }
                $target_module_info[$key]->{$k} = $v;
            }
        }
        if (is_array($module_info)) {
            return $target_module_info;
        }
        return $target_module_info[0];
    }

    /**
     * @brief Get a complete list of mid, which is created in the DB
     **/
    function getMidList($args = null, $columnList = array())
    {
        $output = executeQuery('module.getMidList', $args, $columnList);
        if (!$output->toBool()) {
            return $output;
        }

        $list = $output->data;
        if (!$list) {
            return;
        }

        if (!is_array($list)) {
            $list = array($list);
        }

        foreach ($list as $val) {
            $mid_list[$val->mid] = $val;
        }
        return $mid_list;
    }

    /**
     * @brief Get a complete list of module_srl, which is created in the DB
     **/
    function getModuleSrlList($args = null, $columnList = array())
    {
        $output = executeQueryArray('module.getMidList', $args, $columnList);
        if (!$output->toBool()) {
            return $output;
        }

        $list = $output->data;
        if (!$list) {
            return;
        }

        return $list;
    }

    /**
     * @brief Return an array of module_srl corresponding to a mid list
     **/
    function getModuleSrlByMid($mid)
    {
        if ($mid && !is_array($mid)) {
            $mid = explode(',', $mid);
        }
        if (is_array($mid)) {
            $mid = "'" . implode("','", $mid) . "'";
        }

        $site_module_info = Context::get('site_module_info');
        $args = new stdClass();
        $args->mid = $mid;
        if ($site_module_info) {
            $args->site_srl = $site_module_info->site_srl;
        }
        $output = executeQuery('module.getModuleSrlByMid', $args);
        if (!$output->toBool()) {
            return $output;
        }

        $list = $output->data;
        if (!$list) {
            return;
        }
        if (!is_array($list)) {
            $list = array($list);
        }

        foreach ($list as $key => $val) {
            $module_srl_list[] = $val->module_srl;
        }

        return $module_srl_list;
    }

    /**
     * @brief Get forward value by the value of act
     **/
    function getActionForward($act, $module = "")
    {
        $args = new stdClass();
        $args->act = $act;
        $args->module = ($module) ? $module : null;
        if (strlen($args->module) > 0) {
            $output = executeQuery('module.getActionForwardWithModule', $args);
        } else {
            $output = executeQuery('module.getActionForward', $args);
        }
        return $output->data;
    }

    /**
     * @brief Get a list of all triggers on the trigger_name
     **/
    function getTriggers($trigger_name, $called_position)
    {
        // cache controll
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $cache_key = 'object:' . $trigger_name . '_' . $called_position;
            $output = $oCacheHandler->get($cache_key);
        }
        if (!isset($output)) {
            $args = new stdClass();
            $args->trigger_name = $trigger_name;
            $args->called_position = $called_position;
            $output = executeQueryArray('module.getTriggers', $args);
            if ($oCacheHandler->isSupport()) {
                $oCacheHandler->put($cache_key, $output);
            }
        }
        return $output->data;
    }

    /**
     * @brief Get specific triggers from the trigger_name
     **/
    function getTrigger($trigger_name, $module, $type, $called_method, $called_position)
    {
        $args = new stdClass();
        $args->trigger_name = $trigger_name;
        $args->module = $module;
        $args->type = $type;
        $args->called_method = $called_method;
        $args->called_position = $called_position;
        $output = executeQuery('module.getTrigger', $args);
        return !empty($output->data) ? $output->data : null;
    }

    /**
     * @brief Get module extend
     **/
    function getModuleExtend($parent_module, $type, $kind = '')
    {
        $key = $parent_module . '.' . $kind . '.' . $type;

        $module_extend_info = $this->loadModuleExtends();
        if (array_key_exists($key, $module_extend_info)) {
            return $module_extend_info[$key];
        }

        return false;
    }

    /**
     * @brief Get all the module extend
     **/
    function loadModuleExtends()
    {
        $cache_file = './files/config/module_extend.php';
        $cache_file = FileHandler::getRealPath($cache_file);

        if (!isset($GLOBALS['__MODULE_EXTEND__'])) {

            // check pre install
            if (file_exists(FileHandler::getRealPath('./files')) && !file_exists($cache_file)) {
                $arr = array();
                $output = executeQueryArray('module.getModuleExtend');
                if ($output->data) {
                    foreach ($output->data as $v) {
                        $arr[] = sprintf(
                            "'%s.%s.%s' => '%s'",
                            $v->parent_module,
                            $v->kind,
                            $v->type,
                            $v->extend_module
                        );
                    }
                }

                $str = '<?PHP return array(%s); ?>';
                $str = sprintf($str, join(',', $arr));

                FileHandler::writeFile($cache_file, $str);
            }


            if (file_exists($cache_file)) {
                $GLOBALS['__MODULE_EXTEND__'] = include($cache_file);
            } else {
                $GLOBALS['__MODULE_EXTEND__'] = array();
            }
        }

        return $GLOBALS['__MODULE_EXTEND__'];
    }

    /**
     * @brief Get information from conf/info.xml
     **/
    function getModuleInfoXml($module)
    {
        // Get a path of the requested module. Return if not exists.
        $module_path = ModuleHandler::getModulePath($module);
        if (!$module_path) {
            return;
        }
        // Read the xml file for module skin information
        $xml_file = sprintf("%s/conf/info.xml", $module_path);
        if (!file_exists($xml_file)) {
            return;
        }

        $oXmlParser = new XmlParser();
        $tmp_xml_obj = $oXmlParser->loadXmlFile($xml_file);
        $xml_obj = $tmp_xml_obj->module;

        if (!$xml_obj) {
            return;
        }
        // Module Information
        if ($xml_obj->version && $xml_obj->attrs->version == '0.2') {
            // module format 0.2
            $module_info = new stdClass();
            if (isset($xml_obj->title->body)) {
                $module_info->title = $xml_obj->title->body;
            }
            else {
                $module_info->title = null;
            }
            if (isset($xml_obj->description->body)) {
                $module_info->description = $xml_obj->description->body;
            }
            else {
                $module_info->description = null;
            }
            if (isset($xml_obj->version->body)) {
                $module_info->version = $xml_obj->version->body;
            }
            else {
                $module_info->version = null;
            }
            if (isset($xml_obj->link->body)) {
                $module_info->homepage = $xml_obj->link->body;
            }
            else {
                $module_info->homepage = null;
            }
            if (isset($xml_obj->category->body)) {
                $module_info->category = $xml_obj->category->body;
            }
            else {
                $module_info->category = 'service';
            }
            if (isset($xml_obj->date->body)) {
                list ($y, $m, $d) = sscanf($xml_obj->date->body, '%d-%d-%d');
                $module_info->date = sprintf('%04d%02d%02d', $y, $m, $d);
            }
            else {
                $module_info->date = null;
            }

            if (isset($xml_obj->license->body)) {
                $module_info->license = $xml_obj->license->body;
            }
            else {
                $module_info->license = null;
            }
            if (isset($xml_obj->license->attrs->link)) {
                $module_info->license_link = $xml_obj->license->attrs->link;
            }
            else{
                $module_info->license_link = null;
            }
            if (isset($xml_obj->author)) {
                if (!is_array($xml_obj->author)) {
                    $author_list[] = $xml_obj->author;
                } else {
                    $author_list = $xml_obj->author;
                }
            }
            else {
                $author_list = array();
            }

            foreach ($author_list as $author) {
                unset($author_obj);
                $author_obj = new stdClass();
                if (isset($author->name->body)) {
                    $author_obj->name = $author->name->body;
                }
                else {
                    $author_obj->name = null;
                }

                if (isset($author->attrs->email_address)) {
                    $author_obj->email_address = $author->attrs->email_address;
                }
                else {
                    $author_obj->email_address = null;
                }

                if (isset($author->attrs->link)) {
                    $author_obj->homepage = $author->attrs->link;
                }
                else {
                    $author_obj->homepage = null;
                }
                $module_info->author[] = $author_obj;
            }

            // history
            if (isset($xml_obj->history)) {
                if (!is_array($xml_obj->history)) {
                    $history[] = $xml_obj->history;
                } else {
                    $history = $xml_obj->history;
                }

                foreach ($history as $item) {
                    unset($obj);
                    $obj = new stdClass();
                    if (isset($item->author)) {
                        if (!is_array($item->author)) {
                            $obj->author_list[] = $item->author;
                        }
                        else{
                            $obj->author_list = $item->author;
                        }
                        foreach ($obj->author_list as $author) {
                            unset($author_obj);
                            $author_obj = new stdClass();
                            if (isset($author->name->body)) {
                                $author_obj->name = $author->name->body;
                            }
                            else {
                                $author_obj->name = null;
                            }
                            if (isset($author->attrs->email_address)) {
                                $author_obj->email_address = $author->attrs->email_address;
                            }
                            else {
                                $author_obj->email_address = null;
                            }
                            if (isset($author->attrs->link)) {
                                $author_obj->homepage = $author->attrs->link;
                            }
                            $obj->author[] = $author_obj;
                        }
                    }
                    if (isset($item->name->body)) {
                        $obj->name = $item->name->body;
                    }
                    else {
                        $obj->name = null;
                    }

                    if (isset($item->attrs->email_address)) {
                        $obj->email_address = $item->attrs->email_address;
                    }
                    else {
                        $obj->email_address = null;
                    }

                    if (isset($item->attrs->link)) {
                        $obj->homepage = $item->attrs->link;
                    }
                    else {
                        $obj->homepage = null;
                    }

                    if (isset($item->attrs->version)) {
                        $obj->version = $item->attrs->version;
                    }
                    else {
                        $obj->version = null;
                    }

                    if (isset($item->attrs->date)) {
                        $obj->date = $item->attrs->date;
                    }
                    else {
                        $obj->date = null;
                    }

                    if (isset($item->description->body)) {
                        $obj->description = $item->description->body;
                    }
                    else {
                        $obj->description = null;
                    }

                    if (isset($item->log)) {
                        $obj->log = array();
                        if (!is_array($item->log)){
                            $obj->log[] = $item->log;
                        }
                        else {
                            $obj->log = $item->log;
                        }
                        foreach ($obj->log as $log) {
                            unset($logs_obj);
                            $logs_obj = new stdClass();
                            if (isset($log->body)) {
                                $logs_obj->text = $log->body;
                            }
                            else {
                                $logs_obj->text = null;
                            }
                            if (isset($log->attrs->link)) {
                                $logs_obj->link = $log->attrs->link;
                            }
                            else {
                                $logs_obj->link = null;
                            }
                            $obj->logs[] = $logs_obj;
                        }
                    }

                    $module_info->history[] = $obj;
                }
            }


        } else {
            // module format 0.1
            if (isset($xml_obj->title->body)) {
                $module_info->title = $xml_obj->title->body;
            }
            else {
                $module_info->title = null;
            }
            if (isset($xml_obj->author->description->body)) {
                $module_info->description = $xml_obj->author->description->body;
            }
            else {
                $module_info->description = null;
            }

            if (isset($xml_obj->attrs->version)) {
                $module_info->version = $xml_obj->attrs->version;
            }
            if (isset($xml_obj->attrs->category)) {
                $module_info->category = $xml_obj->attrs->category;
            }
            else {
                $module_info->category = 'service';
            }

            if (isset($xml_obj->author->attrs->date)) {
                list ($y, $m, $d) = sscanf($xml_obj->author->attrs->date, '%d. %d. %d');
                $module_info->date = sprintf('%04d%02d%02d', $y, $m, $d);
            }
            else {
                $module_info->date = null;
            }
            if (isset($xml_obj->author->name->body)) {
                $author_obj->name = $xml_obj->author->name->body;
            }
            else {
                $author_obj->name = null;
            }
            if (isset($xml_obj->author->attrs->email_address)) {
                $author_obj->email_address = $xml_obj->author->attrs->email_address;
            }
            else {
                $author_obj->email_address = null;
            }

            if (isset($xml_obj->author->attrs->link)) {
                $author_obj->homepage = $xml_obj->author->attrs->link;
            }
            else {
                $author_obj->homepage = null;
            }
            $module_info->author[] = $author_obj;
        }
        // Add admin_index by using action information
        $action_info = $this->getModuleActionXml($module);
        if (isset($action_info->admin_index_act)) {
            $module_info->admin_index_act = $action_info->admin_index_act;
        }
        else {
            $module_info->admin_index_act = null;
        }
        if (isset($action_info->default_index_act)) {
            $module_info->default_index_act = $action_info->default_index_act;
        }
        else {
            $module_info->default_index_act = null;
        }

        if (isset($action_info->setup_index_act)) {
            $module_info->setup_index_act = $action_info->setup_index_act;
        }
        else {
            $module_info->setup_index_act = null;
        }
        return $module_info;
    }

    /**
     * @brief Return permisson and action data by conf/module.xml in the module
     * Cache it because it takes too long to parse module.xml file
     * When caching, add codes so to include it directly
     * This is apparently good for performance, but not sure about its side-effects
     **/
    function getModuleActionXml($module)
    {
        // Get a path of the requested module. Return if not exists.
        $class_path = ModuleHandler::getModulePath($module);
        if (!$class_path) {
            return null;
        }
        // Check if module.xml exists in the path. Return if not exist
        $xml_file = sprintf("%sconf/module.xml", $class_path);
        if (!file_exists($xml_file)) {
            return null;
        }
        // Check if cached file exists
        $cache_file = sprintf(
            "./files/cache/module_info/%s.%s.%s.php",
            $module,
            Context::getLangType(),
            __KARYBU_VERSION__
        );
        // Update if no cache file exists or it is older than xml file
        if (!file_exists($cache_file) || filemtime($cache_file) < filemtime($xml_file)) {

            $buff = ""; // /< Set buff variable to use in the cache file

            $parser = new XmlParser();
            $xml_obj = $parser->loadXmlFile($xml_file); // /< Read xml file and convert it to xml object

            if (!count($xml_obj->module)) {
                return null;
            } // /< Error occurs if module tag doesn't included in the xml

            $grants = isset($xml_obj->module->grants->grant) ? $xml_obj->module->grants->grant : null; // /< Permission information
            $permissions = isset($xml_obj->module->permissions->permission) ? $xml_obj->module->permissions->permission : null; // /<  Acting permission
            $menus = isset($xml_obj->module->menus->menu) ? $xml_obj->module->menus->menu : null;
            $actions = isset($xml_obj->module->actions->action) ? $xml_obj->module->actions->action : null; // /< Action list (required)

            $info = new stdClass();
            $default_index = $admin_index = '';
            // Arrange permission information
            if ($grants) {
                if (is_array($grants)) {
                    $grant_list = $grants;
                } else {
                    $grant_list[] = $grants;
                }

                $buff .= '$info->grant = new stdClass;';
                foreach ($grant_list as $grant) {
                    $name = isset($grant->attrs->name) ? $grant->attrs->name : null;
                    $default = !empty($grant->attrs->default) ? $grant->attrs->default : 'guest';
                    $title = isset($grant->title->body) ? $grant->title->body : null;
                    $info->grant = new stdClass();
                    $info->grant->{$name} = new stdClass();
                    $info->grant->{$name}->title = $title;
                    $info->grant->{$name}->default = $default;
                    $buff .= sprintf('$info->grant->%s=new stdClass;', $name);
                    $buff .= sprintf('$info->grant->%s->title=\'%s\';', $name, $title);
                    $buff .= sprintf('$info->grant->%s->default=\'%s\';', $name, $default);
                }
            }
            // Permissions to grant
            if ($permissions) {
                if (is_array($permissions)) {
                    $permission_list = $permissions;
                } else {
                    $permission_list[] = $permissions;
                }

                $buff .= '$info->permission = new stdClass;';
                foreach ($permission_list as $permission) {
                    $action = $permission->attrs->action;
                    $target = $permission->attrs->target;

                    $info->permission = new stdClass();
                    $info->permission->{$action} = $target;

                    $buff .= sprintf('$info->permission->%s = \'%s\';', $action, $target);
                }
            }
            $info->menu = new stdClass();
            // for admin menus
            if ($menus) {
                if (is_array($menus)) {
                    $menu_list = $menus;
                } else {
                    $menu_list[] = $menus;
                }

                $buff .= '$info->menu = new stdClass;';
                foreach ($menu_list as $menu) {
                    $menu_name = $menu->attrs->name;
                    $menu_title = is_array($menu->title) ? $menu->title[0]->body : $menu->title->body;
                    $menu_type = $menu->attrs->type;

                    if (isset($menu_title)) {
                        $info->menu->{$menu_name} = new stdClass();
                        $info->menu->{$menu_name}->title = $menu_title;
                    }
                    $info->menu->{$menu_name}->acts = array();
                    $info->menu->{$menu_name}->type = $menu_type;

                    $buff .= sprintf('$info->menu->%s=new stdClass;', $menu_name, $menu_title);
                    $buff .= sprintf('$info->menu->%s->title=\'%s\';', $menu_name, $menu_title);
                    $buff .= sprintf('$info->menu->%s->type=\'%s\';', $menu_name, $menu_type);
                }
            }

            // actions
            if ($actions) {
                if (is_array($actions)) {
                    $action_list = $actions;
                } else {
                    $action_list[] = $actions;
                }

                $output = new stdClass();
                $info->action = new stdClass();
                $buff .= '$info->action = new stdClass;';
                foreach ($action_list as $action) {
                    $name = $action->attrs->name;

                    $type = $action->attrs->type;
                    $grant = $action->attrs->grant ? $action->attrs->grant : 'guest';
                    $standalone = $action->attrs->standalone == 'true' ? 'true' : 'false';
                    $ruleset = $action->attrs->ruleset ? $action->attrs->ruleset : '';

                    $index = $action->attrs->index;
                    $admin_index = $action->attrs->admin_index;
                    $setup_index = $action->attrs->setup_index;
                    $menu_index = $action->attrs->menu_index;

                    $output->action = new stdClass();
                    $output->action->{$name} = new stdClass();
                    $output->action->{$name}->type = $type;
                    $output->action->{$name}->grant = $grant;
                    $output->action->{$name}->standalone = $standalone;

                    $info->action->{$name} = new stdClass();
                    $info->action->{$name}->type = $type;
                    $info->action->{$name}->grant = $grant;
                    $info->action->{$name}->standalone = $standalone == 'true' ? true : false;
                    $info->action->{$name}->ruleset = $ruleset;
                    if ($action->attrs->menu_name) {
                        if ($menu_index == 'true') {
                            $info->menu->{$action->attrs->menu_name}->index = $name;
                            $buff .= sprintf('$info->menu->%s->index=\'%s\';', $action->attrs->menu_name, $name);
                        }
                        if (is_array($info->menu->{$action->attrs->menu_name}->acts)) {
                            @array_push($info->menu->{$action->attrs->menu_name}->acts, $name);
                            $currentKey = @array_search($name, $info->menu->{$action->attrs->menu_name}->acts);
                        }

                        $buff .= sprintf(
                            '$info->menu->%s->acts[%d]=\'%s\';',
                            $action->attrs->menu_name,
                            $currentKey,
                            $name
                        );
                    }

                    $buff .= sprintf('$info->action->%s=new stdClass;', $name);
                    $buff .= sprintf('$info->action->%s->type=\'%s\';', $name, $type);
                    $buff .= sprintf('$info->action->%s->grant=\'%s\';', $name, $grant);
                    $buff .= sprintf('$info->action->%s->standalone=%s;', $name, $standalone);
                    $buff .= sprintf('$info->action->%s->ruleset=\'%s\';', $name, $ruleset);

                    if ($index == 'true') {
                        $default_index_act = $name;
                        $info->default_index_act = $name;
                    }
                    if ($admin_index == 'true') {
                        $admin_index_act = $name;
                        $info->admin_index_act = $name;
                    }
                    if ($setup_index == 'true') {
                        $setup_index_act = $name;
                        $info->setup_index_act = $name;
                    }
                }
            }
            if (!isset($default_index_act)) {
                $default_index_act = '';
            }
            if (!isset($setup_index_act)) {
                $setup_index_act = '';
            }
            if (!isset($admin_index_act)) {
                $admin_index_act = '';
            }
            $buff = sprintf(
                '<?php if(!defined("__KARYBU__")) exit(); $info = new stdClass; $info->default_index_act = \'%s\';$info->setup_index_act=\'%s\';$info->admin_index_act = \'%s\';%s?>',
                $default_index_act,
                $setup_index_act,
                $admin_index_act,
                $buff
            );

            FileHandler::writeFile($cache_file, $buff);

            return $info;
        }

        include($cache_file);

        return $info;
    }


    /**
     * @brief Get a list of skins for the module
     * Return file analysis of skin and skin.xml
     **/
    function getSkins($path, $dir = 'skins')
    {
        $skin_path = sprintf("%s/%s/", $path, $dir);
        $list = FileHandler::readDir($skin_path);
        if (!count($list)) {
            return;
        }

        natcasesort($list);

        foreach ($list as $skin_name) {
            unset($skin_info);
            $skin_info = $this->loadSkinInfo($path, $skin_name, $dir);
            if (empty($skin_info)) {
                $skin_info = new stdClass();
                $skin_info->title = $skin_name;
            }

            $skin_list[$skin_name] = $skin_info;
        }

        return $skin_list;
    }

    /**
     * @brief Get skin information on a specific location
     **/
    function loadSkinInfo($path, $skin, $dir = 'skins')
    {
        // Read xml file having skin information
        if (substr($path, -1) != '/') {
            $path .= '/';
        }
        $skin_xml_file = sprintf("%s%s/%s/skin.xml", $path, $dir, $skin);
        if (!file_exists($skin_xml_file)) {
            return;
        }
        // Create XmlParser object
        $oXmlParser = new XmlParser();
        $_xml_obj = $oXmlParser->loadXmlFile($skin_xml_file);
        // Return if no skin information is
        if (!$_xml_obj->skin) {
            return;
        }
        $xml_obj = $_xml_obj->skin;
        // Skin Name
        $skin_info = new stdClass();
        $skin_info->title = isset($xml_obj->title->body) ? $xml_obj->title->body : null;
        // Author information
        if ($xml_obj->version && $xml_obj->attrs->version == '0.2') {
            // skin format v0.2
            $skin_info->version = isset($xml_obj->version->body) ? $xml_obj->version->body : null;
            if (!empty($xml_obj->date->body)) {
                list($y, $m, $d) = sscanf($xml_obj->date->body, '%d-%d-%d');
                $skin_info->date = sprintf('%04d%02d%02d', $y, $m, $d);
            }
            else{
                $skin_info->date = null;
            }

            $skin_info->homepage = isset($xml_obj->link->body) ? $xml_obj->link->body : null;
            $skin_info->license = isset($xml_obj->license->body) ? $xml_obj->license->body : null;
            $skin_info->license_link = isset($xml_obj->license->attrs->link) ? $xml_obj->license->attrs->link : null;
            $skin_info->description = isset($xml_obj->description->body) ? $xml_obj->description->body : null;
            if (isset($xml_obj->author)) {
                if (!is_array($xml_obj->author)) {
                    $author_list[] = $xml_obj->author;
                } else {
                    $author_list = $xml_obj->author;
                }
            }
            else{
                $author_list = array();
            }
            if (count($author_list)) {
                $skin_info->author = array();
            }
            foreach ($author_list as $author) {
                unset($author_obj);
                $author_obj = new stdClass();
                $author_obj->name = isset($author->name->body) ? $author->name->body: null;
                $author_obj->email_address = isset($author->attrs->email_address) ? $author->attrs->email_address : null;
                $author_obj->homepage = isset($author->attrs->link) ? $author->attrs->link : null;
                $skin_info->author[] = $author_obj;
            }
            // List extra vars
            if ($xml_obj->extra_vars) {
                $extra_var_groups = $xml_obj->extra_vars->group;
                if (!$extra_var_groups) {
                    $extra_var_groups = $xml_obj->extra_vars;
                }
                if (!is_array($extra_var_groups)) {
                    $extra_var_groups = array($extra_var_groups);
                }

                foreach ($extra_var_groups as $group) {
                    $extra_vars = $group->var;
                    if (!$extra_vars) {
                        continue;
                    }
                    if (!is_array($group->var)) {
                        $extra_vars = array($group->var);
                    }

                    foreach ($extra_vars as $key => $val) {
                        unset($obj);
                        if (empty($val->attrs->type)) {
                            $val->attrs->type = 'text';
                        }
                        $obj = new stdClass();
                        $obj->group = isset($group->title->body) ? $group->title->body : null;
                        $obj->name = isset($val->attrs->name) ? $val->attrs->name : null;
                        $obj->title = isset($val->title->body) ? $val->title->body : null;
                        $obj->type = isset($val->attrs->type) ? $val->attrs->type : null;
                        $obj->description = isset($val->description->body) ? $val->description->body : null;
                        if (isset($obj->name) && isset($extra_vals->{$obj->name})) {
                            $obj->value = $extra_vals->{$obj->name};
                        }
                        else {
                            $obj->value = null;
                        }
                        $obj->default = isset($val->attrs->default) ? $val->attrs->default : null;
                        if (strpos($obj->value, '|@|') != false) {
                            $obj->value = explode('|@|', $obj->value);
                        }
                        if ($obj->type == 'mid_list' && !is_array($obj->value)) {
                            $obj->value = array($obj->value);
                        }
                        // Get an option list from 'select'type
                        if (is_array($val->options)) {
                            $option_count = count($val->options);
                            $obj->options = array();
                            for ($i = 0; $i < $option_count; $i++) {
                                $obj->options[$i] = new stdClass();
                                $obj->options[$i]->title = isset($val->options[$i]->title->body) ? $val->options[$i]->title->body : null;
                                $obj->options[$i]->value = isset($val->options[$i]->attrs->value) ? $val->options[$i]->attrs->value : null;
                            }
                        } else {
                            $obj->options = array();
                            $obj->options[0] = new stdClass();
                            $obj->options[0]->title = isset($val->options->title->body) ? $val->options->title->body : null;
                            $obj->options[0]->value = isset($val->options->attrs->value) ? $val->options->attrs->value : null;
                        }

                        $skin_info->extra_vars[] = $obj;
                    }
                }
            }

            // history
            if ($xml_obj->history) {
                if (!is_array($xml_obj->history)) {
                    $history[] = $xml_obj->history;
                } else {
                    $history = $xml_obj->history;
                }

                foreach ($history as $item) {
                    unset($obj);

                    if ($item->author) {
                        (!is_array(
                            $item->author
                        )) ? $obj->author_list[] = $item->author : $obj->author_list = $item->author;

                        foreach ($obj->author_list as $author) {
                            unset($author_obj);
                            $author_obj->name = $author->name->body;
                            $author_obj->email_address = $author->attrs->email_address;
                            $author_obj->homepage = $author->attrs->link;
                            $obj->author[] = $author_obj;
                        }
                    }

                    $obj->name = $item->name->body;
                    $obj->email_address = $item->attrs->email_address;
                    $obj->homepage = $item->attrs->link;
                    $obj->version = $item->attrs->version;
                    $obj->date = $item->attrs->date;
                    $obj->description = $item->description->body;

                    if ($item->log) {
                        (!is_array($item->log)) ? $obj->log[] = $item->log : $obj->log = $item->log;

                        foreach ($obj->log as $log) {
                            unset($log_obj);
                            $log_obj->text = $log->body;
                            $log_obj->link = $log->attrs->link;
                            $obj->logs[] = $log_obj;
                        }
                    }

                    $skin_info->history[] = $obj;
                }
            }


        } else {

            // skin format v0.1
            if (isset($xml_obj->maker->attrs->date)) {
                list ($y, $m, $d) = sscanf($xml_obj->maker->attrs->date, '%d-%d-%d');
                $skin_info->date = sprintf('%04d%02d%02d', $y, $m, $d);
            }
            else {
                $skin_info->date = null;
            }

            $skin_info->version = isset($xml_obj->version->body) ? $xml_obj->version->body : null;

            $skin_info->homepage = isset($xml_obj->link->body) ? $xml_obj->link->body : null;
            $skin_info->license = isset($xml_obj->license->body) ? $xml_obj->license->body : null;
            $skin_info->license_link = isset($xml_obj->license->attrs->link) ? $xml_obj->license->attrs->link : null;
            $skin_info->description = isset($xml_obj->maker->description->body) ? $xml_obj->maker->description->body : null;
            $skin_info->author = array();
            $skin_info->author[0] = new stdClass();
            $skin_info->author[0]->name = isset($xml_obj->maker->name->body) ? $xml_obj->maker->name->body : null;
            $skin_info->author[0]->email_address = isset($xml_obj->maker->attrs->email_address) ? $xml_obj->maker->attrs->email_address : null;
            $skin_info->author[0]->homepage = isset($xml_obj->maker->attrs->link) ? $xml_obj->maker->attrs->link : null;
            // Variables used in the skin
            $extra_var_groups = isset($xml_obj->extra_vars->group) ? $xml_obj->extra_vars->group : null;
            if (!$extra_var_groups) {
                $extra_var_groups = isset($xml_obj->extra_vars) ? $xml_obj->extra_vars : array();
            }
            if (!is_array($extra_var_groups)) {
                $extra_var_groups = array($extra_var_groups);
            }

            foreach ($extra_var_groups as $group) {
                $extra_vars = $group->var;

                if ($extra_vars) {

                    if (!is_array($extra_vars)) {
                        $extra_vars = array($extra_vars);
                    }

                    foreach ($extra_vars as $var) {
                        unset($obj);
                        unset($options);

                        $group = $group->title->body;
                        $name = $var->attrs->name;
                        $type = $var->attrs->type;
                        $title = $var->title->body;
                        $description = $var->description->body;
                        // Get an option list from 'select'type.
                        if (is_array($var->default)) {
                            $option_count = count($var->default);

                            for ($i = 0; $i < $option_count; $i++) {
                                $options[$i]->title = $var->default[$i]->body;
                                $options[$i]->value = $var->default[$i]->body;
                            }
                        } else {
                            $options[0]->title = $var->default->body;
                            $options[0]->value = $var->default->body;
                        }

                        $width = $var->attrs->width;
                        $height = $var->attrs->height;

                        unset($obj);
                        $obj->group = $group;
                        $obj->title = $title;
                        $obj->description = $description;
                        $obj->name = $name;
                        $obj->type = $type;
                        $obj->options = $options;
                        $obj->width = $width;
                        $obj->height = $height;
                        $obj->default = $options[0]->value;

                        $skin_info->extra_vars[] = $obj;
                    }
                }
            }
        }

        // colorset
        $colorset = isset($xml_obj->colorset->color) ? $xml_obj->colorset->color : null;
        if ($colorset) {
            if (!is_array($colorset)) {
                $colorset = array($colorset);
            }

            foreach ($colorset as $color) {
                $name = $color->attrs->name;
                $title = $color->title->body;
                $screenshot = $color->attrs->src;
                if ($screenshot) {
                    $screenshot = sprintf("%s%s/%s/%s", $path, $dir, $skin, $screenshot);
                    if (!file_exists($screenshot)) {
                        $screenshot = "";
                    }
                } else {
                    $screenshot = "";
                }

                unset($obj);
                $obj = new stdClass();
                $obj->name = $name;
                $obj->title = $title;
                $obj->screenshot = $screenshot;
                $skin_info->colorset[] = $obj;
            }
        }
        // Menu type (settings for layout)
        if (!empty($xml_obj->menus->menu)) {
            $menus = $xml_obj->menus->menu;
            if (!is_array($menus)) {
                $menus = array($menus);
            }

            $menu_count = count($menus);
            $skin_info->menu_count = $menu_count;
            for ($i = 0; $i < $menu_count; $i++) {
                unset($obj);

                $obj->name = $menus[$i]->attrs->name;
                if ($menus[$i]->attrs->default == "true") {
                    $obj->default = true;
                }
                $obj->title = $menus[$i]->title->body;
                $obj->maxdepth = $menus[$i]->maxdepth->body;

                $skin_info->menu->{$obj->name} = $obj;
            }
        }

        $thumbnail = sprintf("%s%s/%s/thumbnail.png", $path, $dir, $skin);
        $skin_info->thumbnail = (file_exists($thumbnail)) ? $thumbnail : null;
        return $skin_info;
    }

    /**
     * @brief Return the number of modules which are registered on a virtual site
     **/
    function getModuleCount($site_srl, $module = null)
    {
        $args->site_srl = $site_srl;
        if (!is_null($module)) {
            $args->module = $module;
        }
        $output = executeQuery('module.getModuleCount', $args);
        return $output->data->count;
    }

    /**
     * @brief Return module configurations
     * Global configuration is used to manage board, member and others
     **/
    function getModuleConfig($module, $site_srl = 0)
    {
        // cache controll
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $cache_key = 'object:module_config:module_' . $module . '_site_srl_' . $site_srl;
            $config = $oCacheHandler->get($cache_key);
        }
        if (!isset($config)) {
            if (!isset($GLOBALS['__ModuleConfig__'][$site_srl][$module])) {
                $args = new stdClass();
                $args->module = $module;
                $args->site_srl = $site_srl;
                $output = executeQuery('module.getModuleConfig', $args);
                if (isset($output->data->config)) {
                    $config = unserialize($output->data->config);
                }
                //insert in cache
                if ($oCacheHandler->isSupport()) {
                    if (isset($config)) {
                        $oCacheHandler->put($cache_key, $config);
                    }
                }
                if (isset($config)) {
                    $GLOBALS['__ModuleConfig__'][$site_srl][$module] = $config;
                }
            }
            if (isset($GLOBALS['__ModuleConfig__'][$site_srl][$module])) {
                return $GLOBALS['__ModuleConfig__'][$site_srl][$module];
            }
        }
        if (isset($config)) {
            return $config;
        }
    }

    /**
     * @brief Return the module configuration of mid
     * Manage mid configurations which depend on module
     **/
    function getModulePartConfig($module, $module_srl)
    {
        // cache controll
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $cache_key = 'object_module_part_config:' . $module . '_' . $module_srl;
            $config = $oCacheHandler->get($cache_key);
        }
        if (!isset($config)) {
            if (!isset($GLOBALS['__ModulePartConfig__'][$module][$module_srl])) {
                $args = new stdClass();
                $args->module = $module;
                $args->module_srl = $module_srl;
                $config = null;
                $output = executeQuery('module.getModulePartConfig', $args);
                if (isset($output->data->config)) {
                    $config = unserialize($output->data->config);
                }
                //insert in cache
                if ($oCacheHandler->isSupport()) {
                    if ($config) {
                        $oCacheHandler->put($cache_key, $config);
                    }
                }
                $GLOBALS['__ModulePartConfig__'][$module][$module_srl] = $config;
            }
            return $GLOBALS['__ModulePartConfig__'][$module][$module_srl];
        }

        return $config;

    }

    /**
     * @brief Get all of module configurations for each mid
     **/
    function getModulePartConfigs($module, $site_srl = 0)
    {
        $args = new stdClass();
        $args->module = $module;
        if ($site_srl) {
            $args->site_srl = $site_srl;
        }
        $output = executeQueryArray('module.getModulePartConfigs', $args);
        if (!$output->toBool() || !$output->data) {
            return array();
        }

        foreach ($output->data as $key => $val) {
            $result[$val->module_srl] = unserialize($val->config);
        }
        return $result;
    }


    /**
     * @brief Get a list of module category
     **/
    function getModuleCategories($moduleCategorySrl = array())
    {
        $args = new stdClass();
        $args->moduleCategorySrl = $moduleCategorySrl;
        // Get data from the DB
        $output = executeQuery('module.getModuleCategories', $args);
        if (!$output->toBool()) {
            return $output;
        }
        $list = $output->data;
        if (!$list) {
            return;
        }
        if (!is_array($list)) {
            $list = array($list);
        }

        foreach ($list as $val) {
            $category_list[$val->module_category_srl] = $val;
        }
        return $category_list;
    }

    /**
     * @brief Get content from the module category
     **/
    function getModuleCategory($module_category_srl)
    {
        // Get data from the DB
        $args = new stdClass();
        $args->module_category_srl = $module_category_srl;
        $output = executeQuery('module.getModuleCategory', $args);
        if (!$output->toBool()) {
            return $output;
        }
        return $output->data;
    }

    /**
     * @brief Get xml information of the module
     **/
    function getModulesXmlInfo()
    {
        // Get a list of downloaded and installed modules
        $searched_list = FileHandler::readDir('./modules');
        $searched_count = count($searched_list);
        if (!$searched_count) {
            return;
        }
        sort($searched_list);

        for ($i = 0; $i < $searched_count; $i++) {
            // Module name
            $module_name = $searched_list[$i];

            $path = ModuleHandler::getModulePath($module_name);
            // Get information of the module
            $info = $this->getModuleInfoXml($module_name);
            unset($obj);

            $info->module = $module_name;
            $info->created_table_count = null;
            $info->table_count = null;
            $info->path = $path;
            $info->admin_index_act = $info->admin_index_act;
            $list[] = $info;
        }
        return $list;
    }

    function checkNeedInstall($module_name)
    {
        $oDB = DB::getInstance();
        $info = null;

        $moduledir = ModuleHandler::getModulePath($module_name);
        if (file_exists(FileHandler::getRealPath($moduledir . "schemas"))) {
            $tmp_files = FileHandler::readDir($moduledir . "schemas", '/(\.xml)$/');
            $table_count = count($tmp_files);
            // Check if the table is created
            $created_table_count = 0;
            for ($j = 0; $j < count($tmp_files); $j++) {
                list($table_name) = explode(".", $tmp_files[$j]);
                if ($oDB->isTableExists($table_name)) {
                    $created_table_count++;
                }
            }
            // Check if DB is installed
            if ($table_count > $created_table_count) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    function checkNeedUpdate($module_name)
    {
        // Check if it is upgraded to module.class.php on each module
        $oDummy = & getModule($module_name, 'class');
        if ($oDummy && method_exists($oDummy, "checkUpdate")) {
            return $oDummy->checkUpdate();
        }
        return false;
    }

    /**
     * @brief Get a type and information of the module
     **/
    function getModuleList()
    {
        // Create DB Object
        $oDB = DB::getInstance();
        // Get a list of downloaded and installed modules
        $searched_list = FileHandler::readDir('./modules', '/^([a-zA-Z0-9_-]+)$/');
        sort($searched_list);

        $searched_count = count($searched_list);
        if (!$searched_count) {
            return;
        }

        for ($i = 0; $i < $searched_count; $i++) {
            // module name
            $module_name = $searched_list[$i];

            $path = ModuleHandler::getModulePath($module_name);
            if (!is_dir(FileHandler::getRealPath($path))) {
                continue;
            }

            // Get the number of xml files to create a table in schemas
            $tmp_files = FileHandler::readDir($path . 'schemas', '/(\.xml)$/');
            $table_count = count($tmp_files);
            // Check if the table is created
            $created_table_count = 0;
            for ($j = 0; $j < $table_count; $j++) {
                list($table_name) = explode('.', $tmp_files[$j]);
                if ($oDB->isTableExists($table_name)) {
                    $created_table_count++;
                }
            }
            // Get information of the module
            $info = null;
            $info = $this->getModuleInfoXml($module_name);

            $info->module = $module_name;
            $info->category = $info->category;
            $info->created_table_count = $created_table_count;
            $info->table_count = $table_count;
            $info->path = $path;
            $info->admin_index_act = $info->admin_index_act;
            // Check if DB is installed
            if ($table_count > $created_table_count) {
                $info->need_install = true;
            } else {
                $info->need_install = false;
            }
            // Check if it is upgraded to module.class.php on each module
            $oDummy = null;
            $oDummy = & getModule($module_name, 'class');
            if ($oDummy && method_exists($oDummy, "checkUpdate")) {
                $info->need_update = $oDummy->checkUpdate();
            } else {
                continue;
            }

            $list[] = $info;
        }
        return $list;
    }

    /**
     * @brief Combine module_srls with domain of sites
     * Because Karybu DBHandler doesn't support left outer join,
     * it should be as same as $Output->data[]->module_srl.
     **/
    function syncModuleToSite(&$data)
    {
        if (!$data) {
            return;
        }

        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $module_srls[] = $val->module_srl;
            }
            if (!count($module_srls)) {
                return;
            }
        } else {
            $module_srls[] = $data->module_srl;
        }
        $args = new stdClass();
        $args->module_srls = implode(',', $module_srls);
        $output = executeQueryArray('module.getModuleSites', $args);
        if (!$output->data) {
            return array();
        }
        foreach ($output->data as $key => $val) {
            $modules[$val->module_srl] = $val;
        }

        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key]->domain = $modules[$val->module_srl]->domain;
            }
        } else {
            $data->domain = $modules[$data->module_srl]->domain;
        }
    }

    /**
     * @brief Check if it is an administrator of site_module_info
     **/
    function isSiteAdmin($member_info, $site_srl = null)
    {
        if (empty($member_info->member_srl)) {
            return false;
        }
        if (isset($member_info->is_admin) && $member_info->is_admin == 'Y') {
            return true;
        }
        $args = new stdClass();
        if (!isset($site_srl)) {
            $site_module_info = Context::get('site_module_info');
            if (!$site_module_info) {
                return;
            }
            $args->site_srl = $site_module_info->site_srl;
        } else {
            $args->site_srl = $site_srl;
        }

        $args->member_srl = $member_info->member_srl;
        $output = executeQuery('module.isSiteAdmin', $args);
        if (isset($output->data->member_srl) && $output->data->member_srl == $args->member_srl) {
            return true;
        }
        return false;

    }

    /**
     * @brief Get admin information of the site
     **/
    function getSiteAdmin($site_srl)
    {
        $args->site_srl = $site_srl;
        $output = executeQueryArray('module.getSiteAdmin', $args);
        return $output->data;
    }

    /**
     * @brief Get admin ID of the module
     **/
    function getAdminId($module_srl)
    {
        $obj = new stdClass();
        $obj->module_srl = $module_srl;
        $output = executeQueryArray('module.getAdminID', $obj);
        if (!$output->toBool() || !$output->data) {
            return;
        }

        return $output->data;
    }

    /**
     * @brief Get extra vars of the module
     * Extra information, not in the modules table
     **/
    function getModuleExtraVars($module_srl)
    {
        if (is_array($module_srl)) {
            $module_srl = implode(',', $module_srl);
        }
        // cache controll
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $cache_key = 'object:module_extra_vars_' . $module_srl;
            $vars = $oCacheHandler->get($cache_key);
        }
        if (!isset($vars)) {
            $args = new stdClass();
            $args->module_srl = $module_srl;
            $output = executeQueryArray('module.getModuleExtraVars', $args);
            if (!$output->toBool() || !$output->data) {
                if ($oCacheHandler->isSupport()) {
                    $oCacheHandler->put($cache_key, 'empty_extra_vars');
                }
                return;
            }
            $vars = array();
            foreach ($output->data as $key => $val) {
                if (in_array($val->name, array('mid', 'module')) || $val->value == 'Array') {
                    continue;
                }
                if (!isset($vars[$val->module_srl])) {
                    $vars[$val->module_srl] = new stdClass();
                }
                $vars[$val->module_srl]->{$val->name} = $val->value;
            }
            if ($oCacheHandler->isSupport()) {
                $oCacheHandler->put($cache_key, $vars);
            }
        } elseif ($vars == 'empty_extra_vars') {
            return;
        }
        return $vars;
    }

    /**
     * @brief Get skin information of the module
     **/
    function getModuleSkinVars($module_srl)
    {
        $args = new stdClass();
        $args->module_srl = $module_srl;
        $output = executeQueryArray('module.getModuleSkinVars', $args);
        if (!$output->toBool() || !$output->data) {
            return;
        }

        $skin_vars = array();
        foreach ($output->data as $val) {
            $skin_vars[$val->name] = $val;
        }
        return $skin_vars;
    }

    /**
     * @brief Combine skin information with module information
     **/
    function syncSkinInfoToModuleInfo(&$module_info)
    {
        if (!isset($module_info->module_srl) || !$module_info->module_srl) {
            return;
        }

        if (Mobile::isFromMobilePhone()) {
            $cache_key = 'object_module_mobile_skin_vars:' . $module_info->module_srl;
            $query = 'module.getModuleMobileSkinVars';
        } else {
            $cache_key = 'object_module_skin_vars:' . $module_info->module_srl;
            $query = 'module.getModuleSkinVars';
        }

        // cache controll
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $output = $oCacheHandler->get($cache_key);
        }
        if (!isset($output)) {
            $args = new stdClass();
            $args->module_srl = $module_info->module_srl;
            $output = executeQueryArray($query, $args);
            //insert in cache
            if ($oCacheHandler->isSupport()) {
                $oCacheHandler->put($cache_key, $output);
            }
        }
        if (!$output->toBool() || !$output->data) {
            return;
        }

        foreach ($output->data as $val) {
            if (isset($module_info->{$val->name})) {
                continue;
            }
            $module_info->{$val->name} = $val->value;
        }
    }

    /**
     * Get mobile skin information of the module
     * @param $module_srl Sequence of module
     * @return array
     **/
    function getModuleMobileSkinVars($module_srl)
    {
        $args = new stdClass();
        $args->module_srl = $module_srl;
        $output = executeQueryArray('module.getModuleMobileSkinVars', $args);
        if (!$output->toBool() || !$output->data) {
            return;
        }

        $skin_vars = array();
        foreach ($output->data as $val) {
            $skin_vars[$val->name] = $val;
        }
        return $skin_vars;
    }

    /**
     * Combine skin information with module information
     * @param $module_info Module information
     **/
    function syncMobileSkinInfoToModuleInfo(&$module_info)
    {
        if (!$module_info->module_srl) {
            return;
        }
        // cache controll
        $oCacheHandler = & CacheHandler::getInstance('object');
        if ($oCacheHandler->isSupport()) {
            $cache_key = 'object_module_mobile_skin_vars:' . $module_info->module_srl;
            $output = $oCacheHandler->get($cache_key);
        }
        if (!$output) {
            $args->module_srl = $module_info->module_srl;
            $output = executeQueryArray('module.getModuleMobileSkinVars', $args);
            //insert in cache
            if ($oCacheHandler->isSupport()) {
                $oCacheHandler->put($cache_key, $output);
            }
        }
        if (!$output->toBool() || !$output->data) {
            return;
        }

        foreach ($output->data as $val) {
            if (isset($module_info->{$val->name})) {
                continue;
            }
            $module_info->{$val->name} = $val->value;
        }
    }

    /**
     * @brief Return permission by using module info, xml info and member info
     **/
    function getGrant($module_info, $member_info, $xml_info = '')
    {
        if (!$xml_info) {
            $module = isset($module_info->module) ? $module_info->module : null;
            $xml_info = $this->getModuleActionXml($module);
        }
        // Set variables to grant group permission
        if (isset($module_info->module_srl)){
            $module_srl = $module_info->module_srl;
        }
        else{
            $module_srl = null;
        }
        if (isset($xml_info->grant)) {
            $grant_info = $xml_info->grant;
        }
        if (!empty($member_info->member_srl)) {
            if (is_array($member_info->group_list)) {
                $group_list = array_keys($member_info->group_list);
            } else {
                $group_list = array();
            }
        } else {
            $group_list = array();
        }
        // If module_srl doesn't exist(if unable to set permissions)
        $grant = new stdClass();
        if (!$module_srl) {
            $grant->access = true;
            if ($this->isSiteAdmin(
                $member_info,
                isset($module_info->site_srl) ? $module_info->site_srl : null
            )
            ) {
                $grant->access = $grant->is_admin = $grant->manager = $grant->is_site_admin = true;
            } else {
                $grant->is_admin = (isset($member_info->is_admin) &&  $member_info->is_admin== 'Y') ? true : false;
                $grant->manager = $grant->is_admin;
            }
            // If module_srl exists
        } else {
            // Get a type of granted permission
            $grant->is_site_admin = (isset($member_info->is_admin) && $member_info->is_admin == 'Y' || $this->isSiteAdmin(
                $member_info,
                $module_info->site_srl
            )) ? true : false;
            $grant->access = $grant->is_site_admin;
            $grant->is_admin = $grant->is_site_admin;
            $grant->manager = $grant->is_site_admin;

            // If a just logged-in member is, check if the member is a module administrator
            if (empty($grant->manager) && !empty($member_info->member_srl)) {
                $args = new stdClass();
                $args->module_srl = $module_srl;
                $args->member_srl = $member_info->member_srl;
                $output = executeQuery('module.getModuleAdmin', $args);
                if ($output->data && $output->data->member_srl == $member_info->member_srl) {
                    $grant->manager = $grant->is_admin = true;
                }
            }
            // If not an administrator, get information from the DB and grant manager privilege.
            if (!$grant->manager) {
                $args = new stdClass();
                // If planet, get permission settings from the planet home
                if ($module_info->module == 'planet') {
                    $output = executeQueryArray('module.getPlanetGrants', $args);
                } else {
                    $args->module_srl = $module_srl;
                    $output = executeQueryArray('module.getModuleGrants', $args);
                }

                $grant_exists = $granted = array();

                if ($output->data) {
                    // Arrange names and groups who has privileges
                    foreach ($output->data as $val) {
                        $grant_exists[$val->name] = true;
                        if (!empty($granted[$val->name])) {
                            continue;
                        }
                        // Log-in member only
                        if ($val->group_srl == -1) {
                            $granted[$val->name] = true;
                            if ($member_info->member_srl) {
                                $grant->{$val->name} = true;
                            }
                            // Site-joined member only
                        } elseif ($val->group_srl == -2) {
                            $granted[$val->name] = true;
                            // Do not grant any permission for non-logged member
                            if (!$member_info->member_srl) {
                                $grant->{$val->name} = false;
                            } // Log-in member
                            else {
                                $site_module_info = Context::get('site_module_info');
                                // Permission granted if no information of the currently connected site exists
                                if (!$site_module_info->site_srl) {
                                    $grant->{$val->name} = true;
                                } // Permission is not granted if information of the currently connected site exists
                                elseif (count($group_list)) {
                                    $grant->{$val->name} = true;
                                }
                            }
                            // All of non-logged members
                        } elseif ($val->group_srl == 0) {
                            $granted[$val->name] = true;
                            $grant->{$val->name} = true;
                            // If a target is a group
                        } else {
                            if ($group_list && count($group_list) && in_array($val->group_srl, $group_list)) {
                                $grant->{$val->name} = true;
                                $granted[$val->name] = true;
                            }
                        }
                    }
                }
                // Separate processing for the virtual group access
                if (empty($grant_exists['access'])) {
                    $grant->access = true;
                }
                if (isset($grant_info) && count($grant_info)) {
                    foreach ($grant_info as $grant_name => $grant_item) {
                        if (!empty($grant_exists[$grant_name])) {
                            continue;
                        }
                        switch ($grant_item->default) {
                            case 'guest' :
                                $grant->{$grant_name} = true;
                                break;
                            case 'member' :
                                if ($member_info->member_srl) {
                                    $grant->{$grant_name} = true;
                                } else {
                                    $grant->{$grant_name} = false;
                                }
                                break;
                            case 'site' :
                                $site_module_info = Context::get('site_module_info');
                                if ($member_info->member_srl && (($site_module_info->site_srl && count(
                                    $group_list
                                )) || !$site_module_info->site_srl)
                                ) {
                                    $grant->{$grant_name} = true;
                                } else {
                                    $grant->{$grant_name} = false;
                                }
                                break;
                            case 'manager' :
                            case 'root' :
                                if ($member_info->is_admin == 'Y') {
                                    $grant->{$grant_name} = true;
                                } else {
                                    $grant->{$grant_name} = false;
                                }
                                break;
                        }
                    }
                }
            }
            // Set true to grant all privileges if an administrator is
            if ($grant->manager) {
                $grant->access = true;
                if (isset($grant_info)) {
                    if (count($grant_info)) {
                        foreach ($grant_info as $key => $val) {
                            $grant->{$key} = true;
                        }
                    }
                }
            }

        }
        return $grant;
    }

    function getModuleFileBox($module_filebox_srl)
    {
        $args->module_filebox_srl = $module_filebox_srl;
        return executeQuery('module.getModuleFileBox', $args);
    }

    function getModuleFileBoxList()
    {
        $oModuleModel = & getModel('module');
        $args = new stdClass();
        $args->page = Context::get('page');
        $args->list_count = 6;
        $args->page_count = 6;
        $output = executeQuery('module.getModuleFileBoxList', $args);
        $output = $oModuleModel->unserializeAttributes($output);
        return $output;
    }

    function unserializeAttributes($module_filebox_list)
    {
        if (is_array($module_filebox_list)) {
            foreach ($module_filebox_list->data as $item) {
                $attributes = explode(';', $item->comment);
                foreach ($attributes as $attribute) {
                    $values = explode(':', $attribute);
                    if ((count($values) % 2) == 1) {
                        for ($i = 2; $i < count($values); $i++) {
                            $values[1] .= ":" . $values[$i];
                        }
                    }
                    $atts[$values[0]] = $values[1];
                }
                $item->attributes = $atts;
                unset($atts);
            }
        }
        return $module_filebox_list;
    }

    function getFileBoxListHtml()
    {
        $logged_info = Context::get('logged_info');
        if ($logged_info->is_admin != 'Y' && !$logged_info->is_site_admin) {
            return new Object(-1, 'msg_not_permitted');
        }
        $link = parse_url($_SERVER["HTTP_REFERER"]);
        $link_params = isset($link['query']) ? explode('&', $link['query']) : array();
        foreach ($link_params as $param) {
            $param = explode("=", $param);
            if ($param[0] == 'selected_widget') {
                $selected_widget = $param[1];
            }
        }
        $oWidgetModel = & getModel('widget');
        if (!empty($selected_widget)) {
            $widget_info = $oWidgetModel->getWidgetInfo($selected_widget);
        }
        if (isset($widget_info->extra_var->images->allow_multiple)) {
            Context::set('allow_multiple', $widget_info->extra_var->images->allow_multiple);
        }
        else {
            Context::set('allow_multiple', null);
        }

        $oModuleModel = & getModel('module');
        $output = $oModuleModel->getModuleFileBoxList();
        Context::set('filebox_list', $output->data);

        $page = Context::get('page');
        if (!$page) {
            $page = 1;
        }
        Context::set('page', $page);
        Context::set('page_navigation', $output->page_navigation);

        $security = new Security();
        $security->encodeHTML('filebox_list..comment');

        $oTemplate = & TemplateHandler::getInstance();
        $html = $oTemplate->compile('./modules/module/tpl/', 'filebox_list_html');

        $this->add('html', $html);
    }

    function getModuleFileBoxPath($module_filebox_srl)
    {
        return sprintf("./files/attach/filebox/%s", getNumberingPath($module_filebox_srl, 3));
    }

    /**
     * @brief Return ruleset cache file path
     * @param module, act
     **/
    function getValidatorFilePath($module, $ruleset, $mid = null)
    {
        // load dynamic ruleset xml file
        if (strpos($ruleset, '@') !== false) {
            $rulsetFile = str_replace('@', '', $ruleset);
            $xml_file = sprintf('./files/ruleset/%s.xml', $rulsetFile);
            return FileHandler::getRealPath($xml_file);
        } else {
            if (strpos($ruleset, '#') !== false) {
                $rulsetFile = str_replace('#', '', $ruleset) . '.' . $mid;
                $xml_file = sprintf('./files/ruleset/%s.xml', $rulsetFile);
                if (is_readable($xml_file)) {
                    return FileHandler::getRealPath($xml_file);
                } else {
                    $ruleset = str_replace('#', '', $ruleset);
                }

            }
        }
        // Get a path of the requested module. Return if not exists.
        $class_path = ModuleHandler::getModulePath($module);
        if (!$class_path) {
            return;
        }

        // Check if module.xml exists in the path. Return if not exist
        $xml_file = sprintf("%sruleset/%s.xml", $class_path, $ruleset);
        if (!file_exists($xml_file)) {
            return;
        }

        return $xml_file;
    }

    function getLangListByLangcodeForAutoComplete()
    {
        $keyword = Context::get('search_keyword');

        $requestVars = Context::getRequestVars();
        $args = new stdClass();
        $args->site_srl = isset($requestVars->site_srl) ? (int)$requestVars->site_srl : 0;
        $args->page = 1; // /< Page
        $args->list_count = 100; // /< the number of posts to display on a single page
        $args->page_count = 5; // /< the number of pages that appear in the page navigation
        $args->sort_index = 'name';
        $args->order_type = 'asc';
        $args->search_keyword = Context::get('search_keyword'); // /< keyword to search*/

        $output = executeQueryArray('module.getLangListByLangcode', $args);

        $list = array();

        if ($output->toBool()) {
            foreach ((array)$output->data as $code_info) {
                unset($codeInfo);
                $codeInfo = array('name' => '$user_lang->' . $code_info->name, 'value' => $code_info->value);
                $list[] = $codeInfo;
            }
        }
        $this->add('results', $list);
    }

    /**
     * @brief already instance created module list
     **/
    function getModuleListByInstance($site_srl = 0, $columnList = array())
    {
        $args = new stdClass();
        $args->site_srl = $site_srl;
        $output = executeQueryArray('module.getModuleListByInstance', $args, $columnList);
        return $output;
    }

    function getLangByLangcode()
    {
        $langCode = Context::get('langCode');
        if (!$langCode) {
            return;
        }

        $oModuleController = & getController('module');
        $oModuleController->replaceDefinedLangCode($langCode);

        $this->add('lang', $langCode);
    }
}
?>

<?php
    /**
     * @class  widgetModel
     * @author Arnia (dev@karybu.org)
     * @version 0.1
     * @brief Model class for widget modules
     **/

    class widgetModel extends widget {

        /**
         * @brief Initialization
         **/
        function init() {
        }

        /**
         * @brief Wanted widget's path
         **/
        function getWidgetPath($widget_name) {
            $path = sprintf('./widgets/%s/', $widget_name);
            if(is_dir($path)) return $path;

            return "";
        }


        /**
         * @brief Wanted widget style path
         **/
        function getWidgetStylePath($widgetStyle_name) {
            $path = sprintf('./widgetstyles/%s/', $widgetStyle_name);
            if(is_dir($path)) return $path;

            return "";
        }

        /**
         * @brief Wanted widget style path
         **/
        function getWidgetStyleTpl($widgetStyle_name) {
            $path = $this->getWidgetStylePath($widgetStyle_name);
            $tpl = sprintf('%swidgetstyle.html', $path);
            return $tpl;
        }

        /**
         * @brief Wanted photos of the type and information
         * Download a widget with type (generation and other means)
         **/
        function getDownloadedWidgetList() {
			$oAutoinstallModel = &getModel('autoinstall');

            // 've Downloaded the widget and the widget's list of installed Wanted
            $searched_list = FileHandler::readDir('./widgets');
            $searched_count = count($searched_list);
            if(!$searched_count) return;
            sort($searched_list);
            // D which pertain to the list of widgets loop spins return statement review the information you need
            for($i=0;$i<$searched_count;$i++) {
                // The name of the widget
                $widget = $searched_list[$i];
                // Wanted information on the Widget
                $widget_info = $this->getWidgetInfo($widget);

				// get easyinstall remove url
				$packageSrl = $oAutoinstallModel->getPackageSrlByPath($widget_info->path);
				$widget_info->remove_url = $oAutoinstallModel->getRemoveUrlByPackageSrl($packageSrl);

				// get easyinstall need update
				$package = $oAutoinstallModel->getInstalledPackages($packageSrl);
				$widget_info->need_update = !empty($package[$packageSrl]->need_update) ? $package[$packageSrl]->need_update : null;

				// get easyinstall update url
				if ($widget_info->need_update == 'Y')
				{
					$widget_info->update_url = $oAutoinstallModel->getUpdateUrlByPackageSrl($packageSrl);
				}

                $list[] = $widget_info;
            }
            return $list;
        }

        /**
         * @brief Wanted photos of the type and information
         * Download a widget with type (generation and other means)
         **/
        function getDownloadedWidgetStyleList() {
            // 've Downloaded the widget and the widget's list of installed Wanted
            $searched_list = FileHandler::readDir('./widgetstyles');
            $searched_count = count($searched_list);
            if(!$searched_count) return;
            sort($searched_list);
            // D which pertain to the list of widgets loop spins return statement review the information you need
            for($i=0;$i<$searched_count;$i++) {
                // The name of the widget
                $widgetStyle = $searched_list[$i];
                // Wanted information on the Widget
                $widgetStyle_info = $this->getWidgetStyleInfo($widgetStyle);

                $list[] = $widgetStyle_info;
            }
            return $list;
        }


        /**
         * @brief Modules conf/info.xml wanted to read the information
         * It uses caching to reduce time for xml parsing ..
         **/
        function getWidgetInfo($widget) {
            // Get a path of the requested module. Return if not exists.
            $widget_path = $this->getWidgetPath($widget);
            if(!$widget_path) {
                return;
            }
            // Read the xml file for module skin information
            $xml_file = sprintf("%sconf/info.xml", $widget_path);
            if(!file_exists($xml_file)) {
                return;
            }
            // If the problem by comparing the cache file and include the return variable $widget_info
            $cache_file = sprintf('./files/cache/widget/%s.%s.cache.php', $widget, Context::getLangType());


            if(file_exists($cache_file)&&filemtime($cache_file)>filemtime($xml_file)) {
                include($cache_file);
                return $widget_info;
            }
            // If no cache file exists, parse the xml and then return the variable.
            $oXmlParser = new XmlParser();
            $tmp_xml_obj = $oXmlParser->loadXmlFile($xml_file);
            $xml_obj = $tmp_xml_obj->widget;
            if(!$xml_obj) {
                return;
            }

            $buff = '';
            $buff .= sprintf('$widget_info = new stdClass;');
            if($xml_obj->version && $xml_obj->attrs->version == '0.2') {
                // Title of the widget, version
                $buff .= sprintf('$widget_info->widget = "%s";', $widget);
                $buff .= sprintf('$widget_info->path = "%s";', $widget_path);
                $buff .= sprintf('$widget_info->title = "%s";', isset($xml_obj->title->body) ? $xml_obj->title->body : '');
                $buff .= sprintf('$widget_info->description = "%s";', isset($xml_obj->description->body) ? $xml_obj->description->body : '');
                $buff .= sprintf('$widget_info->version = "%s";', isset($xml_obj->version->body) ? $xml_obj->version->body : '');
                if (isset($xml_obj->date->body)) {
                    list ($y, $m, $d) = sscanf($xml_obj->date->body, '%d-%d-%d');
                    $date = sprintf('%04d%02d%02d', $y, $m, $d);
                }
                else {
                    $date = '';
                }
                $buff .= sprintf('$widget_info->date = "%s";', $date);
                $buff .= sprintf('$widget_info->homepage = "%s";', isset($xml_obj->link->body) ? $xml_obj->link->body : '');
                $buff .= sprintf('$widget_info->license = "%s";', isset($xml_obj->license->body) ? $xml_obj->license->body : '');
                $buff .= sprintf('$widget_info->license_link = "%s";', isset($xml_obj->license->attrs->link) ? $xml_obj->license->attrs->link : '');
                //$buff .= sprintf('$widget_info->widget_srl = "%s";', $widget_srl);
                //$buff .= sprintf('$widget_info->widget_title = "%s";', $widget_title);
                // Author information
                if(!is_array($xml_obj->author)) {
                    $author_list[] = $xml_obj->author;
                }
                else {
                    $author_list = $xml_obj->author;
                }
                $buff .= sprintf('$widget_info->author = array();');
                for($i=0; $i < count($author_list); $i++) {
                    $buff .= sprintf('$widget_info->author['.$i.'] = new stdClass;');
                    $buff .= sprintf('$widget_info->author['.$i.']->name = "%s";', isset($author_list[$i]->name->body) ? $author_list[$i]->name->body : '');
                    $buff .= sprintf('$widget_info->author['.$i.']->email_address = "%s";', isset($author_list[$i]->attrs->email_address) ? $author_list[$i]->attrs->email_address : null);
                    $buff .= sprintf('$widget_info->author['.$i.']->homepage = "%s";', isset($author_list[$i]->attrs->link) ? $author_list[$i]->attrs->link : '');
                }

                // history
                if(!empty($xml_obj->history)) {
                    if(!is_array($xml_obj->history)) {
                        $history_list[] = $xml_obj->history;
                    }
                    else {
                        $history_list = $xml_obj->history;
                    }
                    $buff .= sprintf('$widget_info->history = array();');
                    for($i=0; $i < count($history_list); $i++) {
                        $buff .= sprintf('$widget_info->history['.$i.'] = new stdClass;');
                        if (isset($history_list[$i]->attrs->date)) {
                            list ($y, $m, $d) = sscanf($history_list[$i]->attrs->date, '%d-%d-%d');
                            $date = sprintf('%04d%02d%02d', $y, $m, $d);
                        }
                        else {
                            $date = '';
                        }
                        $buff .= sprintf('$widget_info->history['.$i.']->description = "%s";', isset($history_list[$i]->description->body) ? $history_list[$i]->description->body : '');
                        $buff .= sprintf('$widget_info->history['.$i.']->version = "%s";', isset($history_list[$i]->attrs->version) ? $history_list[$i]->attrs->version : '');
                        $buff .= sprintf('$widget_info->history['.$i.']->date = "%s";', $date);

                        if(!empty($history_list[$i]->author)) {
                            $obj = new stdClass();
                            if(!is_array($history_list[$i]->author)) {
                                $obj->author_list[] = $history_list[$i]->author;
                            }
                            else {
                                $obj->author_list = $history_list[$i]->author;
                            }
                            $buff .= sprintf('$widget_info->history['.$i.']->author = array();');
                            for($j=0; $j < count($obj->author_list); $j++) {
                                $buff .= sprintf('$widget_info->history['.$i.']->author['.$j.'] = new stdClass;');
                                $buff .= sprintf('$widget_info->history['.$i.']->author['.$j.']->name = "%s";', isset($obj->author_list[$j]->name->body) ? $obj->author_list[$j]->name->body : '');
                                $buff .= sprintf('$widget_info->history['.$i.']->author['.$j.']->email_address = "%s";', isset($obj->author_list[$j]->attrs->email_address) ? $obj->author_list[$j]->attrs->email_address : '');
                                $buff .= sprintf('$widget_info->history['.$i.']->author['.$j.']->homepage = "%s";', isset($obj->author_list[$j]->attrs->link) ? $obj->author_list[$j]->attrs->link : '');
                            }
                        }

                        if(!empty($history_list[$i]->log)) {
                            $obj = new stdClass();
                            if (!is_array($history_list[$i]->log)) {
                                $obj->log_list[] = $history_list[$i]->log;
                            } else {
                                $obj->log_list = $history_list[$i]->log;
                            }
                            $buff .= sprintf('$widget_info->history['.$i.']->logs = array();');
                            for($j=0; $j < count($obj->log_list); $j++) {
                                $buff .= sprintf('$widget_info->history['.$i.']->logs['.$j.'] = new stdClass;');
                                $buff .= sprintf('$widget_info->history['.$i.']->logs['.$j.']->text = "%s";', isset($obj->log_list[$j]->body) ? $obj->log_list[$j]->body : '');
                                $buff .= sprintf('$widget_info->history['.$i.']->logs['.$j.']->link = "%s";', isset($obj->log_list[$j]->attrs->link) ? $obj->log_list[$j]->attrs->link : '');
                            }
                        }
                    }
                }

            } else {
                // Title of the widget, version
                $buff .= sprintf('$widget_info->widget = "%s";', $widget);
                $buff .= sprintf('$widget_info->path = "%s";', $widget_path);
                $buff .= sprintf('$widget_info->title = "%s";', $xml_obj->title->body);
                $buff .= sprintf('$widget_info->description = "%s";', $xml_obj->author->description->body);
                $buff .= sprintf('$widget_info->version = "%s";', $xml_obj->attrs->version);
                sscanf($xml_obj->author->attrs->date, '%d. %d. %d', $date_obj->y, $date_obj->m, $date_obj->d);
                $date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
                $buff .= sprintf('$widget_info->date = "%s";', $date);
                $buff .= sprintf('$widget_info->widget_srl = $widget_srl;');
                $buff .= sprintf('$widget_info->widget_title = $widget_title;');
                // Author information
                $buff .= sprintf('$widget_info->author = array();');
                $buff .= sprintf('$widget_info->author[0] = new stdClass;');
                $buff .= sprintf('$widget_info->author[0]->name = "%s";', $xml_obj->author->name->body);
                $buff .= sprintf('$widget_info->author[0]->email_address = "%s";', $xml_obj->author->attrs->email_address);
                $buff .= sprintf('$widget_info->author[0]->homepage = "%s";', $xml_obj->author->attrs->link);
            }
            // Extra vars (user defined variables to use in a template)
            $extra_var_groups = isset($xml_obj->extra_vars->group) ? $xml_obj->extra_vars->group : null;
            if(!$extra_var_groups) {
                $extra_var_groups = isset($xml_obj->extra_vars) ? $xml_obj->extra_vars : array();
            }
            if(!is_array($extra_var_groups)) {
                $extra_var_groups = array($extra_var_groups);
            }
            foreach($extra_var_groups as $group){
                $extra_vars = $group->var;
                if(!is_array($group->var)) {
                    $extra_vars = array($group->var);
                }

                if(!empty($extra_vars[0]->attrs->id) || !empty($extra_vars[0]->attrs->name)) {
                    $extra_var_count = count($extra_vars);

                    $buff .= sprintf('$widget_info->extra_var_count = "%s";', $extra_var_count);
                    $buff .= sprintf('$widget_info->extra_var = new stdClass;');
                    for($i=0;$i<$extra_var_count;$i++) {
                        unset($var);
                        unset($options);
                        $var = $extra_vars[$i];
                        if (!empty($var->attrs->id)) {
                            $id = $var->attrs->id;
                        }
                        elseif (!empty($var->attrs->name)) {
                            $id = $var->attrs->name;
                        }
                        else {
                            $id = null;
                        }

                        if (!empty($var->name->body)) {
                            $name = $var->name->body;
                        }
                        elseif (!empty($var->title->body)) {
                            $name = $var->title->body;
                        }
                        else {
                            $name = null;
                        }

                        if (!empty($var->attrs->type)) {
                            $type = $var->attrs->type;
                        }
                        elseif (!empty($var->title->body)) {
                            $type = $var->type->body;
                        }
                        else {
                            $type = null;
                        }
                        if (!is_null($id)){
                            $buff .= sprintf('$widget_info->extra_var->%s = new stdClass;', $id);
                            if($type =='filebox') {
                                $buff .= sprintf('$widget_info->extra_var->%s->filter = "%s";', $id, $var->type->attrs->filter);
                            }
                            if($type =='filebox') {
                                $buff .= sprintf('$widget_info->extra_var->%s->allow_multiple = "%s";', $id, $var->type->attrs->allow_multiple);
                            }

                            $buff .= sprintf('$widget_info->extra_var->%s->group = "%s";', $id, isset($group->title->body) ? $group->title->body : '');
                            $buff .= sprintf('$widget_info->extra_var->%s->name = "%s";', $id, $name);
                            $buff .= sprintf('$widget_info->extra_var->%s->type = "%s";', $id, $type);
                            $buff .= sprintf('$widget_info->extra_var->%s->value = "%s";', $id, isset($vars->$id) ? $vars->$id : '');
                            $buff .= sprintf('$widget_info->extra_var->%s->description = "%s";', $id, isset($var->description->body) ? str_replace('"','\"',$var->description->body) : '');
                        }

                        $options = $var->options;
                        if(!$options) {
                            continue;
                        }

                        if(!is_array($options)) $options = array($options);
                        $options_count = count($options);
                        for($j=0;$j<$options_count;$j++) {
                            $buff .= sprintf('$widget_info->extra_var->%s->options["%s"] = "%s";', $id, isset($options[$j]->value->body) ? $options[$j]->value->body  : '', isset($options[$j]->name->body) ? $options[$j]->name->body : '');

                            if(!empty($options[$j]->attrs->default) && $options[$j]->attrs->default=='true'){
                                $buff .= sprintf('$widget_info->extra_var->%s->default_options["%s"] = true;', $id, isset($options[$j]->value->body) ? $options[$j]->value->body : '');
                            }

                            if(!empty($options[$j]->attrs->init) && $options[$j]->attrs->init=='true'){
                                $buff .= sprintf('$widget_info->extra_var->%s->init_options["%s"] = true;', $id, $options[$j]->value->body);
                            }
                        }

                    }
                }
            }

            $buff = '<?php if(!defined("__KARYBU__")) exit(); '.$buff.' ?>';
            FileHandler::writeFile($cache_file, $buff);

            if(file_exists($cache_file)) include($cache_file);
            return $widget_info;
        }



        /**
         * @brief Modules conf/info.xml wanted to read the information
         * It uses caching to reduce time for xml parsing ..
         **/
        function getWidgetStyleInfo($widgetStyle) {

            $widgetStyle_path = $this->getWidgetStylePath($widgetStyle);
            if(!$widgetStyle_path) return;
            $xml_file = sprintf("%sskin.xml", $widgetStyle_path);
            if(!file_exists($xml_file)) return;
            // If the problem by comparing the cache file and include the return variable $widgetStyle_info
            $cache_file = sprintf('./files/cache/widgetstyles/%s.%s.cache.php', $widgetStyle, Context::getLangType());

            if(file_exists($cache_file)&&filemtime($cache_file)>filemtime($xml_file)) {
                include($cache_file);
                return $widgetStyle_info;
            }
            // If no cache file exists, parse the xml and then return the variable.
            $oXmlParser = new XmlParser();
            $tmp_xml_obj = $oXmlParser->loadXmlFile($xml_file);
            $xml_obj = $tmp_xml_obj->widgetstyle;
            if(!$xml_obj) return;

            $buff = '';
            // Title of the widget, version
            $buff .= sprintf('$widgetStyle_info->widgetStyle = "%s";', $widgetStyle);
            $buff .= sprintf('$widgetStyle_info->path = "%s";', $widgetStyle_path);
            $buff .= sprintf('$widgetStyle_info->title = "%s";', $xml_obj->title->body);
            $buff .= sprintf('$widgetStyle_info->description = "%s";', $xml_obj->description->body);
            $buff .= sprintf('$widgetStyle_info->version = "%s";', $xml_obj->version->body);
            sscanf($xml_obj->date->body, '%d-%d-%d', $date_obj->y, $date_obj->m, $date_obj->d);
            $date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
            $buff .= sprintf('$widgetStyle_info->date = "%s";', $date);
            $buff .= sprintf('$widgetStyle_info->homepage = "%s";', $xml_obj->link->body);
            $buff .= sprintf('$widgetStyle_info->license = "%s";', $xml_obj->license->body);
            $buff .= sprintf('$widgetStyle_info->license_link = "%s";', $xml_obj->license->attrs->link);

            // preview
            if(!$xml_obj->preview->body) $xml_obj->preview->body = 'preview.jpg';
            $preview_file = sprintf("%s%s", $widgetStyle_path,$xml_obj->preview->body);
            if(file_exists($preview_file)) $buff .= sprintf('$widgetStyle_info->preview = "%s";', $preview_file);
            // Author information
            if(!is_array($xml_obj->author)) $author_list[] = $xml_obj->author;
            else $author_list = $xml_obj->author;

            for($i=0; $i < count($author_list); $i++) {
                $buff .= sprintf('$widgetStyle_info->author['.$i.']->name = "%s";', $author_list[$i]->name->body);
                $buff .= sprintf('$widgetStyle_info->author['.$i.']->email_address = "%s";', $author_list[$i]->attrs->email_address);
                $buff .= sprintf('$widgetStyle_info->author['.$i.']->homepage = "%s";', $author_list[$i]->attrs->link);
            }

            // history
            if($xml_obj->history) {
                if(!is_array($xml_obj->history)) $history_list[] = $xml_obj->history;
                else $history_list = $xml_obj->history;

                for($i=0; $i < count($history_list); $i++) {
                    sscanf($history_list[$i]->attrs->date, '%d-%d-%d', $date_obj->y, $date_obj->m, $date_obj->d);
                    $date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
                    $buff .= sprintf('$widgetStyle_info->history['.$i.']->description = "%s";', $history_list[$i]->description->body);
                    $buff .= sprintf('$widgetStyle_info->history['.$i.']->version = "%s";', $history_list[$i]->attrs->version);
                    $buff .= sprintf('$widgetStyle_info->history['.$i.']->date = "%s";', $date);

                    if($history_list[$i]->author) {
                        (!is_array($history_list[$i]->author)) ? $obj->author_list[] = $history_list[$i]->author : $obj->author_list = $history_list[$i]->author;

                        for($j=0; $j < count($obj->author_list); $j++) {
                            $buff .= sprintf('$widgetStyle_info->history['.$i.']->author['.$j.']->name = "%s";', $obj->author_list[$j]->name->body);
                            $buff .= sprintf('$widgetStyle_info->history['.$i.']->author['.$j.']->email_address = "%s";', $obj->author_list[$j]->attrs->email_address);
                            $buff .= sprintf('$widgetStyle_info->history['.$i.']->author['.$j.']->homepage = "%s";', $obj->author_list[$j]->attrs->link);
                        }
                    }

                    if($history_list[$i]->log) {
                        (!is_array($history_list[$i]->log)) ? $obj->log_list[] = $history_list[$i]->log : $obj->log_list = $history_list[$i]->log;

                        for($j=0; $j < count($obj->log_list); $j++) {
                            $buff .= sprintf('$widgetStyle_info->history['.$i.']->logs['.$j.']->text = "%s";', $obj->log_list[$j]->body);
                            $buff .= sprintf('$widgetStyle_info->history['.$i.']->logs['.$j.']->link = "%s";', $obj->log_list[$j]->attrs->link);
                        }
                    }
                }
            }
            // Extra vars (user defined variables to use in a template)
            $extra_var_groups = $xml_obj->extra_vars->group;
            if(!$extra_var_groups) $extra_var_groups = $xml_obj->extra_vars;
            if(!is_array($extra_var_groups)) $extra_var_groups = array($extra_var_groups);
            foreach($extra_var_groups as $group){
                $extra_vars = $group->var;
                if(!is_array($group->var)) $extra_vars = array($group->var);

                if($extra_vars[0]->attrs->id || $extra_vars[0]->attrs->name) {
                    $extra_var_count = count($extra_vars);

                    $buff .= sprintf('$widgetStyle_info->extra_var_count = "%s";', $extra_var_count);
                    for($i=0;$i<$extra_var_count;$i++) {
                        unset($var);
                        unset($options);
                        $var = $extra_vars[$i];

                        $id = $var->attrs->id?$var->attrs->id:$var->attrs->name;
                        $name = $var->name->body?$var->name->body:$var->title->body;
                        $type = $var->attrs->type?$var->attrs->type:$var->type->body;


                        $buff .= sprintf('$widgetStyle_info->extra_var->%s->group = "%s";', $id, $group->title->body);
                        $buff .= sprintf('$widgetStyle_info->extra_var->%s->name = "%s";', $id, $name);
                        $buff .= sprintf('$widgetStyle_info->extra_var->%s->type = "%s";', $id, $type);
                        if($type =='filebox') $buff .= sprintf('$widgetStyle_info->extra_var->%s->filter = "%s";', $id, $var->attrs->filter);
                        if($type =='filebox') $buff .= sprintf('$widgetStyle_info->extra_var->%s->allow_multiple = "%s";', $id, $var->attrs->allow_multiple);
                        $buff .= sprintf('$widgetStyle_info->extra_var->%s->value = $vars->%s;', $id, $id);
                        $buff .= sprintf('$widgetStyle_info->extra_var->%s->description = "%s";', $id, str_replace('"','\"',$var->description->body));

                        $options = $var->options;
                        if(!$options) continue;

                        if(!is_array($options)) $options = array($options);
                        $options_count = count($options);
                        for($j=0;$j<$options_count;$j++) {
                            $buff .= sprintf('$widgetStyle_info->extra_var->%s->options["%s"] = "%s";', $id, $options[$j]->value->body, $options[$j]->name->body);
                        }

                    }
                }
            }

            $buff = '<?php if(!defined("__KARYBU__")) exit(); '.$buff.' ?>';
            FileHandler::writeFile($cache_file, $buff);

            if(file_exists($cache_file)) include($cache_file);
            return $widgetStyle_info;
        }

    }
?>
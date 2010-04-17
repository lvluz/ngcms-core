<?php

//
// Copyright (C) 2006-2008 Next Generation CMS (http://ngcms.ru/)
// Name: extrainst.inc.php
// Description: Functions required for plugin managment scripts
// Author: Vitaly Ponomarev
//

// Protect against hack attempts
if (!defined('NGCMS')) die ('HAL');


// automatic config screen generator

/*
params:
 array of arrays with variables:
	name = parameter name
	title = parameter title (showed in html)
	descr = description (small symbols show)
	type  = input / select / text
	value = default filled value
	values = array of possible values (for select)
	html_flags = additional html flags for parameter
	validate = array with validation parameters, several lines may be applied
		: type = int
			: min, max = define minimum and maximum values
		: type = regex
			: match = define regex that shoud be matched

		: type = integer
		:

*/

function generate_config_page($module, $params, $values = array()) {
  global $tpl, $lang;

  function mkParamLine($param) {
  	global $tpl;
	if ($param['type'] == 'flat') {
		return $param['input'];
	}

	$tvars['vars'] = array(
		'name'	=> $param['name'],
		'title' => $param['title'],
		'descr' => $param['descr'],
		'error' => '',
		'input' => '');

	if ($param['descr']) {
		$tvars['vars']['[descr]'] = "";
		$tvars['vars']['[/descr]'] = "";
	} else {
		$tvars['regx']["'\\[descr\\].*?\\[/descr\\]'si"] = "";
	}

	if ($param['error']) {
		$tvars['vars']['error'] = str_replace('%error%',$param['error'],$lang['param_error']);
	}
	if ($values[$param['name']]) {
		$param['value'] = $values[$param['name']];
	}

	if ($param['type'] == 'text') {
		$tvars['vars']['input'] = '<textarea name="'.$param['name'].'" title="'.$param['title'].'" '.$param['html_flags'].'>'.$param['value'].'</textarea>';
	} else if ($param['type'] == 'input') {
		$tvars['vars']['input'] = '<input name="'.$param['name'].'" type="text" title="'.$param['title'].'" '.$param['html_flags'].' value="'.$param['value'].'" />';
	} else if ($param['type'] == 'checkbox') {
		$tvars['vars']['input'] = '<input name="'.$param['name'].'" type="checkbox" title="'.$param['title'].'" '.$param['html_flags'].' value="1"'.($param['value']?' checked':'').' />';
	} else if ($param['type'] == 'hidden') {
		$tvars['vars']['input'] = '<input name="'.$param['name'].'" type=hidden value="'.$param['value'].'" />';
	} else if ($param['type'] == 'select') {
		$tvars['vars']['input'] = '<select name="'.$param['name'].'" '.$param['html_flags'].'>';
		foreach ($param['values'] as $oid => $oval) {
			$tvars['vars']['input'].= '<option value="'.$oid.'"'.($param['value']==$oid?' selected':'').'>'.$oval.'</option>';
		}
		$tvars['vars']['input'].='</select>';
	} else if ($param['type'] == 'manual') {
		$tvars['vars']['input'] = $param['input'];
	}

	$tpl -> vars('entries', $tvars);
	return $tpl -> show('entries');
  }

  // Prepare
  $tpl -> template('group', tpl_actions.'extra-config');
  $tpl -> template('entries', tpl_actions.'extra-config');

  // For each param do
  foreach($params as $param) {
  	if ($param['mode'] == 'group') {
  		$line = '';
  		// Lets' group parameters into one block
  		foreach ($param['entries'] as $entry) {
  			$line .= mkParamLine($entry);
  		}
  		$tvars['vars'] = array('title' => $param['title'], 'entries' => $line);
  		$tpl -> vars('group', $tvars);
  		$entries .= $tpl -> show('group', $tvars);
  		//$entries .= $line;
  	} else {
  		$entries .= mkParamLine($param);
  	}
  }

  $tpl -> template('table', tpl_actions.'extra-config');
  $tvars['vars'] = array('entries' => $entries, 'plugin' => $module, 'php_self' => $PHP_SELF);
  $tpl -> vars('table', $tvars);
  echo $tpl -> show('table');
}


// Automatic save values into module parameters DB
function commit_plugin_config_changes($module, $params) {

	// Load cofig
	plugins_load_config();

	// For each param do save data
	foreach($params as $param) {
		// Validate parameter if needed
	        if ($param['mode'] == 'group') {
	        	if (is_array($param['entries'])) {
	        		foreach ($param['entries'] as $gparam) {
					if ($gparam['name'] && (!$gparam['nosave'])) {
						extra_set_param($module, $gparam['name'], $_POST[$gparam['name']].'');
					}
	        		}
	        	}
	        } else if ($param['name'] && (!$param['nosave'])) {
			extra_set_param($module, $param['name'], $_POST[$param['name']].'');
		}
	}

	// Save config
	extra_commit_changes();
}

// Load params sent by POST request in plugin configuration
function load_commit_params($cfg, $outparams) {

	foreach ($cfg as $param) {
		if ($param['name']) {
			$outparams[$param['name']] = $_POST[$param['name']];
		}
	}
	return $outparams;
}

// Priint page with config change complition notification
function print_commit_complete($plugin) {
	global $tpl;

	$tpl -> template('done', tpl_actions.'extra-config');
	$tvars['vars'] = array('plugin' => $plugin, 'php_self' => $PHP_SELF);
	$tpl -> vars('done', $tvars);
	echo $tpl -> show('done');
}


// check if table exists
function mysql_table_exists($table) {
	global $config, $mysql;

	if (is_array($mysql->record("show tables like ".db_squote($table)))) {
		return 1;
	}
	return 0;
}

// check field params
function get_mysql_field_type($table, $field) {
	$result = mysql_query("SELECT * FROM $table limit 0");
	$fields = mysql_num_fields($result);
	for ($i=0; $i < $fields; $i++) {
	        if (mysql_field_name($result, $i) == $field) {
	        	$ft = mysql_field_type($result, $i);
	        	$fl = mysql_field_len($result, $i);
	        	if ($ft == 'string') { $ft = 'char'; }
	        	if ($ft == 'blob') { $ft = 'text'; $fl = ''; }
	        	$res = $ft.($fl?' ('.$fl.')':'');
	        	return $res;
		}
	}
	return '';
}

// Database update during install
function fixdb_plugin_install($module, $params, $mode='install', $silent = false) {
	global $lang, $tpl, $mysql;

	// Load config
	plugins_load_config();

	$publish = array();
	if ($mode == 'install') {
		array_push($publish, array('title' => '<b>'.$lang['idbc_process'].'</b>', 'descr' => '', 'result' => ''));
	} else {
		array_push($publish, array('title' => '<b>'.$lang['ddbc_process'].'</b>', 'descr' => '', 'result' => ''));
	}
	// For each params do update DB
	foreach($params as $table) {
		$error = 0;
		$publish_title  = '';
		$publish_descr  = '';
		$publish_result = '';
		$publish_error  = 0;

		$create_mode = 0;

		if (!$table['table']) {
			$publish_result = 'No table name specified';
			$publish_error  = 1;
			break;
		}
		if (($table['action'] != 'create')&&
		    ($table['action'] != 'cmodify')&&
		    ($table['action'] != 'modify')&&
		    ($table['action'] != 'drop')) {
		        $publish_title = 'Table operations';
			$publish_result = 'Unknown action type specified ['.$table['action'].']';
			$publish_error  = 1;
		    	break;
		}


                if ($table['action'] == 'drop') {
			$publish_title = $lang['idbc_tdrop'];
			$publish_title = str_replace('%table%', $table['table'], $publish_title);

			if (!mysql_table_exists(prefix."_".$table['table'])) {
		                $publish_result = $lang['idbc_tnoexists'];
				$publish_error = 1;
				break;
			}

                	$query = "drop table ".prefix."_".$table['table'];
                	$mysql->query($query);

			array_push($publish, array('title' => $publish_title, 'descr' => "SQL: [$query]", 'result' => ($publish_result?$publish_result:($error?$lang['idbc_fail']:$lang['idbc_ok']))));
			continue;
		}

		if (!is_array($table['fields'])) {
			$publish_result = 'Field list should be specified';
			$publish_error  = 1;
			break;
		}

                if ($table['action'] == 'modify') {
			$publish_title = $lang['idbc_tmodify'];
			$publish_title = str_replace('%table%', $table['table'], $publish_title);

			if (!mysql_table_exists(prefix."_".$table['table'])) {
		                $publish_result = $lang['idbc_tnoexists'];
				$publish_error = 1;
				break;
			}
		}

		if ($table['action'] == 'create') {
			$publish_title = $lang['idbc_tcreate'];
			$publish_title = str_replace('%table%', $table['table'], $publish_title);

			if (mysql_table_exists(prefix."_".$table['table'])) {
		                $publish_result = $lang['idbc_t_alreadyexists'];
				$publish_error = 1;
				break;
			}
			$create_mode = 1;
		}

		if ($table['action'] == 'cmodify') {
			$publish_title = $lang['idbc_tcmodify'];
			$publish_title = str_replace('%table%', $table['table'], $publish_title);
			if (!mysql_table_exists(prefix."_".$table['table'])) {
				$create_mode = 1;
			}
		}


		// Now we can perform field creation
		if ($create_mode) {
			$fieldlist = array();
			foreach ($table['fields'] as $field) {
				if (!$field['name']) {
					$publish_result = 'Field name should be specified';
					$publish_error  = 1;
					break;
				}
				if (($field['action'] == 'create')||($field['action'] == 'cmodify')||($field['action'] == 'cleave')) {
					if (!$field['type']) {
						$publish_result = 'Field type should be specified';
						$publish_error  = 1;
						break;
					}
					array_push($fieldlist, $field['name']." ".$field['type']." ".$field['params']);
				} else if ($field['action'] != 'drop') {
					$publish_result = 'Unknown action';
					$publish_error  = 1;
					break;
				}
			}

			// Check if different character set are supported [ version >= 4.1.1 ]
			$charset = is_array($mysql->record("show variables like 'character_set_client'"))?' DEFAULT CHARSET=CP1251':'';

			$query = "create table ".prefix.'_'.$table['table']." (".implode(', ',$fieldlist).($table['key']?', '.$table['key']:'').")".$charset.($table['engine']?' engine='.$table['engine']:'');
			$mysql->query($query);
			array_push($publish, array('title' => $publish_title, 'descr' => "SQL: [$query]", 'result' => ($publish_result?$publish_result:($error?$lang['idbc_fail']:$lang['idbc_ok']))));
		} else {
			foreach ($table['fields'] as $field) {
				if (!$field['name']) {
					$publish_result = 'Field name should be specified';
					$publish_error  = 1;
					break;
				}
				if (($field['action'] == 'create')||($field['action'] == 'cmodify')||($field['action'] == 'cleave')) {
					if (!$field['type']) {
						$publish_result = 'Field type should be specified';
						$publish_error  = 1;
						break;
					}
				} else if ($field['action'] != 'drop') {
					$publish_result = 'Unknown action';
					$publish_error  = 1;
					break;
				}

				$ft = get_mysql_field_type(prefix.'_'.$table['table'], $field['name']);

				if ($field['action'] == 'drop') {
					$publish_title = $lang['idbc_drfield'];
					$publish_title = str_replace('%field%', $field['name'], $publish_title);
					$publish_title = str_replace('%table%', $table['table'], $publish_title);
					if (!$ft) {
						$publish_result = $lang['idbc_fnoexists'];
						$publish_error = 1;
						break;
					}
					$query = "alter table ".prefix.'_'.$table['table']." drop column `".$field['name']."`";
					$mysql->query($query);
					array_push($publish, array('title' => $publish_title, 'descr' => "SQL: [$query]", 'result' => ($publish_result?$publish_result:($error?$lang['idbc_fail']:$lang['idbc_ok']))));
				}
				if ($field['action'] == 'create') {
					$publish_title = $lang['idbc_amfield'];
					$publish_title = str_replace('%field%', $field['name'], $publish_title);
					$publish_title = str_replace('%type%', $field['type'], $publish_title);
					$publish_title = str_replace('%table%', $table['table'], $publish_title);
					if ($ft) {
						$publish_result = $lang['idbc_f_alreadyexists'];
						$publish_error = 1;
						break;
					}
					$query = "alter table ".prefix."_".$table['table']." add column `".$field['name']."` ".$field['type']." ".$field['params'];
					$mysql->query($query);
					array_push($publish, array('title' => $publish_title, 'descr' => "SQL: [$query]", 'result' => ($publish_result?$publish_result:($error?$lang['idbc_fail']:$lang['idbc_ok']))));
					continue;
				}
				if ($field['action'] == 'cmodify') {
					if (!$ft) {
						$query = "alter table ".prefix."_".$table['table']." add column `".$field['name']."` ".$field['type']." ".$field['params'];
					} else {
						$query = "alter table ".prefix."_".$table['table']." change column `".$field['name']."` `".$field['name']."` ".$field['type']." ".$field['params'];
					}
					$mysql->query($query);
					array_push($publish, array('title' => $publish_title, 'descr' => "SQL: [$query]", 'result' => ($publish_result?$publish_result:($error?$lang['idbc_fail']:$lang['idbc_ok']))));
					continue;

				}

			}
			if ($publish_error) { break; }
			$publish_title = '';

		}

	}

	// Scan for messages
	if ($publish_title && $publish_error) {
		array_push($publish, array('title' => $publish_title, 'descr' => $publish_descr, 'error' => $publish_error, 'result' => ($publish_result?$publish_result:($publish_error?$lang['idbc_fail']:$lang['idbc_ok']))));
	}

	$tpl -> template('install-entries', tpl_actions.'extra-config');


	// Write an info
	foreach ($publish as $v) {
		$tvars['vars'] = $v;
		if ($tvars['vars']['error']) { $tvars['vars']['result'] = '<font color="red">'.$tvars['vars']['result'].'</font>'; }
		$tpl -> vars('install-entries', $tvars);
		$entries .= $tpl -> show('install-entries');
	}


	$tpl -> template('install-process', tpl_actions.'extra-config');
	$tvars['vars'] = array(
		'entries' => $entries,
		'plugin' => $module,
		'php_self' => $PHP_SELF,
		'mode_text' => ($mode=='install')?$lang['install_text']:$lang['deinstall_text'],
		'msg' => ($mode=='install'?($publish_error?$lang['ibdc_ifail']:$lang['idbc_iok']):($publish_error?$lang['dbdc_ifail']:$lang['ddbc_iok']))
	);
	$tpl -> vars('install-process', $tvars);
	if (!$silent) {
		print $tpl -> show('install-process');
	}

	if ($publish_error) { return 0; }
	return 1;
}

// Create install page
function generate_install_page($plugin, $text, $stype = 'install') {
	global $tpl, $lang;

	$tpl -> template('install', tpl_actions.'extra-config');
	$tvars['vars'] = array(
		'plugin' => $plugin,
		'stype' => $stype,
		'install_text' => $text,
		'mode_text' => ($stype == 'install')?$lang['install_text']:$lang['deinstall_text'],
		'mode_commit' => ($stype == 'install')?$lang['commit_install']:$lang['commit_deinstall'],
		'php_self' => $PHP_SELF
	);
	$tpl -> vars('install', $tvars);
	echo $tpl -> show('install');

}


?>
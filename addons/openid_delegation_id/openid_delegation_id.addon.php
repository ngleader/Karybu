<?php
if(!defined('__KARYBU__')) exit();

/**
 * @file openid_delegation_id.addon.php
 * @author Arnia (dev@karybu.org)
 * @brief OpenID Delegation ID Add-on
 *
 * This enables to use openID as user's homepage or blog url.
 * Enter your open ID service information on the configuration.
 **/
// Execute only wen called_position is before_module_init
if($called_position != 'before_module_init') return;
// Get add-on settings(openid_delegation_id)
if(!$addon_info->server||!$addon_info->delegate||!$addon_info->xrds) return;

$header_script = sprintf(
	'<link rel="openid.server" href="%s" />'."\n".
	'<link rel="openid.delegate" href="%s" />'."\n".
	'<meta http-equiv="X-XRDS-Location" content="%s" />',
	$addon_info->server,
	$addon_info->delegate,
	$addon_info->xrds
);

Context::addHtmlHeader($header_script);
?>
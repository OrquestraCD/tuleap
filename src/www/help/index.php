<?php
//
// SourceForge: Breaking Down the Barriers to Open Source Development
// Copyright 1999-2000 (c) The SourceForge Crew
// http://sourceforge.net
//
// $Id: index.php 1418 2005-04-08 13:17:03Z schneide $

require_once('pre.php');
$Language->loadLanguageMsg('help/help');

$HTML->header(array(title=>$Language->getText('help_index','welcome',$GLOBALS['sys_name'])));

print '<p>'.$Language->getText('help_index','page_info');

$HTML->footer(array());

?>

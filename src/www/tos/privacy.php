<?php
//
// SourceForge: Breaking Down the Barriers to Open Source Development
// Copyright 1999-2000 (c) The SourceForge Crew
// http://sourceforge.net
//
// $Id: privacy.php 1491 2005-05-09 07:18:09Z ljulliar $

require_once('pre.php');    // Initial db and session library, opens session
$HTML->header(array(title=>"Privacy Policy"));

include($Language->getContent('tos/privacy'));

$HTML->footer(array());

?>

<?php
/**
* Copyright (c) Xerox Corporation, CodeX Team, 2001-2005. All rights reserved
* 
* $Id$
*
* Docman_SettingsDao
*/

require_once('common/dao/include/DataAccessObject.class.php');

class Docman_SettingsDao extends DataAccessObject {
    
    function searchByGroupId($group_id) {
        $sql = sprintf('SELECT * FROM plugin_docman_project_settings WHERE group_id = %d', $group_id);
        return $this->retrieve($sql);
    }

    function searchViewByGroupId($group_id) {
        $sql = 'SELECT view FROM plugin_docman_project_settings WHERE group_id = '. $this->da->quoteSmart($group_id);
        return $this->retrieve($sql);
    }
    
    function create($group_id, $view, $use_obsolescence_date=0, $use_status=0) {
        $sql = sprintf('INSERT INTO plugin_docman_project_settings('.
                       'group_id, view, use_obsolescence_date, use_status'.
                       ') VALUES ('.
                       '%d, %s, %d, %d'.
                       ')',
                       $group_id,
                       $this->da->quoteSmart($view),
                       $use_obsolescence_date,
                       $use_status);
         return $this->update($sql);
    }

    function updateViewForGroupId($group_id, $view) {
        $sql = 'UPDATE plugin_docman_project_settings SET view = '. $this->da->quoteSmart($view) .' WHERE group_id = '. $this->da->quoteSmart($group_id);
        return $this->update($sql);
    }

    function updateMetadataUsageForGroupId($group_id, $label, $useIt) {
        $sql = sprintf('UPDATE plugin_docman_project_settings'.
                       ' SET use_%s = %d'.
                       ' WHERE group_id = %d',
                       $label,
                       $useIt,
                       $group_id);
        return $this->update($sql);
    }
}

?>
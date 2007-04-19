<?php

/**
* Copyright (c) Xerox Corporation, CodeX Team, 2001-2005. All rights reserved
* 
* $Id$
*
* Docman_View_NewFolder
*/

require_once('Docman_View_New.class.php');
require_once('Docman_View_GetFieldsVisitor.class.php');
require_once(dirname(__FILE__).'/../Docman_MetadataFactory.class.php');

class Docman_View_NewFolder extends Docman_View_New {
    
    function _getTitle($params) {
        return $GLOBALS['Language']->getText('plugin_docman', 'new_folder');
    }
    
    function _getAction() {
        return 'createFolder';
    }
    function _getActionText() {
        return $GLOBALS['Language']->getText('plugin_docman', 'new_folder_action');
    }
    function _getGeneralProperties($params) {
        $html = '';

        $mdFactory = new Docman_MetadataFactory($params['group_id']);

        if(isset($params['force_item'])) {
            $new_folder =& $params['force_item'];            

            // append MD list
            $mdFactory->appendAllListOfValuesToItem($new_folder);
        }
        else {
            $new_folder = new Docman_Folder();
            $mdFactory->appendItemMetadataList($new_folder);
        }

        $metadataToSkip = $mdFactory->getMetadataLabelToSkipCreation();
        $get_fields = new Docman_View_GetFieldsVisitor($metadataToSkip);
        $fields     = $new_folder->accept($get_fields, array('form_name'  => $params['form_name'],
                                                             'theme_path' => $params['theme_path']));
        foreach($fields as $field) {
            $html .= '<p>';
            $html .= '<label>'. $field->getLabel().'</label>';
            $html .= $field->getField();
            $html .= '</p>';
        }        
        $html .= '<input type="hidden" name="item[item_type]" value="'. PLUGIN_DOCMAN_ITEM_TYPE_FOLDER .'" />';
        return $html;
    }
}

?>

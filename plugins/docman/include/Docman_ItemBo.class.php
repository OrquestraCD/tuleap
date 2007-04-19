<?php
/**
 * Copyright (c) STMicroelectronics, 2006. All Rights Reserved.
 *
 * Originally written by Manuel Vacelet, 2006
 *
 * This file is a part of CodeX.
 *
 * CodeX is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * CodeX is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CodeX; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * $Id$
 */
require_once('common/dao/CodexDataAccess.class.php');
require_once('Docman_ItemDao.class.php');
require_once('Docman_ItemFactory.class.php');
require_once('Docman_PermissionsManager.class.php');
require_once('Docman_SubItemsRemovalVisitor.class.php');

class Docman_ItemBo {
    var $groupId;
    var $dao;
    
    function Docman_ItemBo($groupId=0) {
        $this->groupId = (int) $groupId;
        $this->dao = new Docman_ItemDao(CodexDataAccess::instance());
        $this->permCache = array();
    }       

    /**
     * Retreive list of collapsed items for given user
     *
     * This function retreive collapsed folders from user preferences 
     *
     * @param $parentId Id of the "current" root node (cannot be excluded).
     * @param $userId Id of current user.
     * @return Array List of items to exclude for a search
     **/
    function &_getExpandedUserPrefs($parentId, $userId) {           
        $collapsedItems = array();     
        // Retreive the list of collapsed folders in prefs        
        $dar = $this->dao->searchExpandedUserPrefs($this->groupId, 
                                                   $userId);
        while($dar->valid()) {
            $row =& $dar->current();
            $tmp = explode('_', $row['preference_name']);
            if ($tmp[4] != $parentId) {
                $collapsedItems[] = (int) $tmp[4];
            }
            $dar->next();
        }               
        
        return $collapsedItems;
    }

    function userHasPermission(&$user, &$item) {
        $dPm =& Docman_PermissionsManager::instance($this->groupId);
        return $dPm->userCanRead($user, $item->getId());
    }

    /**
     * Build a subtree from with the list of items
     * 
     * @param $parentId int Id of tree root.
     * @return Item 
     */
    function &getItemSubTree($parentId, $params = null) {
        $_parentId = (int) $parentId;
               
        $user =& $params['user'];

        // {{1}} Exclude collapsed items      
        $expandedFolders = array();
        if (!isset($params['ignore_collapse']) 
            || !$params['ignore_collapse']) {
            $expandedFolders =& $this->_getExpandedUserPrefs($_parentId, 
                                                             user_getid());
        }

        // Prepare filters if any
        $filter = null;
        if(isset($params['filter'])) {
            $filter =& $params['filter'];
        }  

        $dar = $this->dao->searchByGroupId($this->groupId, $filter);
        
        // Preload perms
        $objectsIds = array();
        while($dar->valid()) {
            $row = $dar->current();
            $objectsIds[] = $row['item_id'];
            
            $dar->next();
        }
        //$pm =& PermissionsManager::instance();
        //$ptype = array('"PLUGIN_DOCMAN_READ"', '"PLUGIN_DOCMAN_WRITE"', '"PLUGIN_DOCMAN_MANAGE"');
        //$pm->_retrievePermissionsArray($objectsIds, $ptype, $user->getUgroups($this->groupId, array()));

        $dpm =& Docman_PermissionsManager::instance($this->groupId);
        $dpm->retreiveReadPermissionsForItems($objectsIds, $user);

        $itemFactory =& new Docman_ItemFactory();

        $parentIdList = array();
        $itemList = array();
        $first = true;
        $dar->rewind();
        while(count($parentIdList) > 0 || $first) {
            if(!$first) {
                $dar = $this->dao->searchByIdList($parentIdList);
            }
            else {
                $first = false;
            }
               
            $tmpParentIdList = array();

            while($dar->valid()) {
                $row =& $dar->current();

                $item =& $itemFactory->getItemFromRow($row);
                if($item && $this->userHasPermission($user, $item)) {
                    $insert = false;
                    $type = $itemFactory->getItemTypeForItem($item);
                    if ($type == PLUGIN_DOCMAN_ITEM_TYPE_FILE 
                        || $type == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
                        // For items with history, we retreive all versions
                        // of the item. The following keep only the more
                        // recent version of the item
                        if(isset($itemList[$item->getId()])) {
                            $oldVer =& $itemList[$item->getId()]->getCurrentVersion();
                            $newVer =& $item->getCurrentVersion();
                            if($oldVer->getDate() < $newVer->getDate()) {
                                $insert = true;
                            }
                        }
                        else {
                            $insert = true;
                        }
                    }
                    else {
                        $insert = true;
                    }

                    if($insert) {
                        $itemList[$item->getId()] =& $item;
                        if($item->getId() != $_parentId) {
                            $tmpParentIdList[] = $item->getParentId();
                        }
                    }
                }
                
                $dar->next();
            }
            
            $parentIdList = array();
            foreach($tmpParentIdList as $id) {
                if(!array_key_exists($id, $itemList) 
                   && !in_array($id, $parentIdList)) {
                    $parentIdList[] = $id;
                }
            }
         }
     
        // Note: use foreach with keys to ensure we only deal with
        // references (foreach $itemList loose references)

        // Build hierarchie
        $keys = array_keys($itemList);
        foreach($keys as $i) {
            if(isset($itemList[$itemList[$i]->getParentId()])) {
                $itemList[$itemList[$i]->getParentId()]->addItem($itemList[$i]);
            }
        }        
        
        // @todo: Tailor empty folders      

        // Tailor expanded folders
        if (!isset($params['ignore_collapse']) || !$params['ignore_collapse']) {
            $keys = array_keys($itemList);
            $remove_subitems =& new Docman_SubItemsRemovalVisitor();
            foreach($keys as $i) {
                $item =& $itemList[$i];
                if(!in_array($item->getId(), $expandedFolders) && $item->getParentId() && $item->getId() != $parentId) {
                    // @todo: Delete all childrens
                    $item->accept($remove_subitems, array());
                }
                unset($item);
            }
        }

        // If nothing to output, output root (?)
        if(!isset($itemList[$_parentId])) {
            $item =& $this->findById($_parentId);
            if($item && $this->userHasPermission($user, $item)) {
                $itemList[$_parentId] =& $item;
            }
        }

        return $itemList[$_parentId];     
    }

    /**
     *
     */
    function &getItemSubTreeAsList($parentId, $params = null) {
        $_parentId = (int) $parentId;
               
        $user =& $params['user'];

        // Prepare filters if any
        $filter = null;
        if(isset($params['filter'])) {
            $filter =& $params['filter'];
        }

        $itemFactory =& new Docman_ItemFactory();
        $itemList = array();
        $itemLocationInList = array();

        $dar = $this->dao->searchByGroupId($this->groupId, $filter);
        $i = 0;
        while($dar->valid()) {
            $row =& $dar->current();

            $item =& $itemFactory->getItemFromRow($row);
            if($this->userHasPermission($user, $item)) {
                $insert = false;
                $type = $itemFactory->getItemTypeForItem($item);
                if ($type == PLUGIN_DOCMAN_ITEM_TYPE_FILE 
                    || $type == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
                    // For items with history, we retreive all versions
                    // of the item. The following keep only the more
                    // recent version of the item
                    if(isset($itemLocationInList[$item->getId()])) {
                        $itemOffset = $itemLocationInList[$item->getId()];
                        $oldVer =& $itemList[$itemOffset]->getCurrentVersion();
                        $newVer =& $item->getCurrentVersion();
                        if($oldVer->getDate() < $newVer->getDate()) {
                            $itemList[$itemOffset] =& $item;
                            $insert = false;
                        }
                    }
                    else {
                        $insert = true;
                    }
                }
                else {
                    $insert = true;
                }
                
                if($insert) {
                    $itemLocationInList[$item->getId()] = $i;
                    $itemList[$i] =& $item;
                    $i++;
                }
            }
            unset($item);

            $dar->next();
        }

        $itemIterator =& new ArrayIterator($itemList);

        // Compute Item location in tree
        // And delete items that do not belong to $_parentId subtree
        // And delete items that have parents unreadble by user.
        $itemToDelete = array();

        $itemIterator->rewind();
        while($itemIterator->valid()) {
            $item =& $itemIterator->current();

            $pid = $item->getParentId();
            $locationTitle = array();
            $locationId    = array();
            $i = 0;
            $deleteCurrentItem = false;
            while(!$deleteCurrentItem && $pid != $_parentId && $pid != 0) {
                // @todo: on possible improvment is to check here the perms
                // and that current pid is not in itemToDelete array.
                if(!isset($itemLocationInList[$pid])) {
                    // @todo: register in DB path to avoid such crapy "on the
                    // fly" item search.
                    $parentItem =& $this->findById($pid);
                    if($parentItem !== null) {
                        $k = count($itemLocationInList);
                        $itemLocationInList[$parentItem->getId()] = $k;
                        $itemList[$k] =& $parentItem;
                    unset($parentItem);
                    }
                    else {
                        // Found a parent that doesn't exist (probably deleted)
                        $itemToDelete[] = $itemList[$itemOffset]->getId();
                        $deleteCurrentItem = true;
                    }
                }

                if(!$deleteCurrentItem) {
                    $itemOffset = $itemLocationInList[$pid];

                    if($this->userHasPermission($user, $itemList[$itemOffset])) {
                        $locationTitle[$i] = $itemList[$itemOffset]->getTitle();
                        $locationId[$i]    = $itemList[$itemOffset]->getId();
                        $i++;
                        $pid = $itemList[$itemOffset]->getParentId();
                    }
                    else {
                        // If current user don't have the right to read item's
                        // parent: delete it and delete the parent.
                        $itemToDelete[] = $itemList[$itemOffset]->getId();
                        $deleteCurrentItem = true;
                    }
                }
            }
            
            if($deleteCurrentItem || ($pid != $_parentId)) {
                // Usuly it implies that $pid == 0.
                // that mean we go until the root of the docman without
                // crossing the current $_parentId. It implies that the current
                // item is not a part of current $_parentId subtree so we can
                // delete it.
                $itemToDelete[] = $item->getId();
            }
            else {
                if($pid != 0) {
                    if(!isset($itemLocationInList[$pid])) {
                        // @todo: register in DB path to avoid such crapy "on the
                        // fly" item search.
                        $parentItem =& $this->findById($pid);
                        $k = count($itemLocationInList);
                        $itemLocationInList[$parentItem->getId()] = $k;
                        $itemList[$k] =& $parentItem;
                        unset($parentItem);
                    }
                    $itemOffset = $itemLocationInList[$pid];
                    $locationTitle[$i] = $itemList[$itemOffset]->getTitle();
                    $locationId[$i]    = $itemList[$itemOffset]->getId();
                }
                $item->setPathTitle(array_reverse($locationTitle));
                $item->setPathId(array_reverse($locationId));
            }

            $itemIterator->next();
        }

        // Delete unneeded items
        foreach($itemToDelete as $id) {
            $itemOffset = $itemLocationInList[$id];
            unset($itemList[$itemOffset]);
        }        

        $i = new ArrayIterator($itemList);
        return $i;
    }

    // STOP: note for childrens, this is a big messy hack !
    // this 'else' statement only happend a fake child to a node
    // that contains child but for whom child was not fetched from
    // database. We need to add a fake child to be able to detect
    // this case on display to add a clickable 'plus'.
    function &getParentList() {
        // Fetch list of items that contains that are parent of other items.
        $dar = $this->dao->searchAllParent($this->groupId);
        $fakeParentList = array();
        while($dar->valid()) {
            $row =& $dar->current();
            $fakeParentList[] = $row['parent_id'];
            $dar->next();
        }

        return $fakeParentList;
    }


    /**
     * Build a tree from with the list of items
     *
     * @return ItemNode
     */
    function &getItemTree($id = 0, $params = null) {
        if (!$id) {
            $id = $this->dao->searchRootIdForGroupId($this->groupId);
        }
        return $this->getItemSubTree($id, $params);
    }

    /**
     * Build a list of items
     *
     * @return ItemNode
     */
    function &getItemList($id = 0, $params = null) {
        if (!$id) {
            $id = $this->dao->searchRootIdForGroupId($this->groupId);
        }
        return $this->getItemSubTreeAsList($id, $params);
    } 

    function &getDocumentsIterator() {
        $itemFactory =& new Docman_ItemFactory();

        $filters = null;
        $dar = $this->dao->searchByGroupId($this->groupId, $filters);
        $itemList = array();
        while($dar->valid()) {
            $row = $dar->current();

            $item =& $itemFactory->getItemFromRow($row);
            $type = $itemFactory->getItemTypeForItem($item);
            if($type != PLUGIN_DOCMAN_ITEM_TYPE_FOLDER) {
                if(!isset($itemList[$item->getId()])) {
                    $itemList[$item->getId()] =& $item;
                }
            }

            $dar->next();
        }

        $i = new ArrayIterator($itemList);
        return $i;
    }

    /**
    * @return Item
    */
    function &findById($id, $params = array()) {
        $item_factory =& $this->_getItemFactory();
        $item =& $item_factory->getItemFromDb($id);
        if (is_a($item, 'Docman_Folder') && isset($params['recursive']) && $params['recursive']) {
            $item =& $this->getItemSubTree($item->getId(), $params);
        }
        return $item;
    }
    var $item_factory;
    function &_getItemFactory() {
        if (!$this->item_factory) {
            $this->item_factory =& new Docman_ItemFactory();
        }
        return $this->item_factory;
    }

    function findByTitle($user, $title) {
        $ia = array();

        $itemFactory = new Docman_ItemFactory();

        $dar = $this->dao->searchByTitle($title);
        $dar->rewind();
        while($dar->valid()) {
            $row = $dar->current();

            $item = $itemFactory->getItemFromRow($row);
            if($this->userHasPermission($user, $item)) {
                $parentItem = $this->findById($item->getParentId());
                if($this->userHasPermission($user, $parentItem)) {
                    $ia[] = $item;
                }
            }

            $dar->next();
        }

        $ii = new ArrayIterator($ia);

        return $ii;
    }
}

?>

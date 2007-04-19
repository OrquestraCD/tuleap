<?php
require_once('MultiMap.class.php');
require_once('PrioritizedList.class.php');
/**
 * Copyright (c) Xerox Corporation, CodeX Team, 2001-2005. All rights reserved
 * 
 * $Id:PrioritizedMultiMap.class.php 4446 2006-12-08 16:18:48 +0000 (Fri, 08 Dec 2006) ahardyau $
 *
 * An object that maps key to values. 
 * A multi-map can contain duplicate keys; each key can map to more than one value.
 */
class PrioritizedMultiMap extends MultiMap{
    
    function PrioritizedMultiMap() {
        $this->MultiMap();
        $this->collection_class_name = "PrioritizedList";
    }
    
    /**
     * Associates the specified value with the specified key in this map
     */
    function put(&$key, &$value, $priority = 0) {
        $col =& $this->_getCollection($key);
        $col->add($value, $priority);
    }
}
?>
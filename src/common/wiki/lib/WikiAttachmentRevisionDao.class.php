<?php
/*
 * Copyright STMicroelectronics, 2006
 * Originally written by Manuel VACELET, STMicroelectronics, 2006 
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
 *
 */

require_once('common/dao/include/DataAccessObject.class.php');

class WikiAttachmentRevisionDao extends DataAccessObject {

    /**
     * Constructs WikiAttachmentRevisionDao
     * @param $da instance of the DataAccess class
     */
    function WikiAttachmentRevisionDao(&$da) {
        DataAccessObject::DataAccessObject($da);
    }

    /**
     * Create a new attachment revision
     *
     * @return boolean success or failure
     */
    function create($attachmentId, $ownerId, $date, $revision, $type, $size) {
        $sql = sprintf('INSERT INTO wiki_attachment_revision SET'
                       .'  attachment_id = %d'
                       .', user_id       = %d'
                       .', date          = %d'
                       .', revision      = %d'
                       .', mimetype      = "%s"'
                       .', size          = %d',
                       $attachmentId,
                       $ownerId,
                       $date,
                       $revision,
                       $this->da->quoteSmart($type),
                       $size);

        $inserted = $this->update($sql);
        return $inserted;
    }

    function log($attachmentId, $revision, $groupId, $userId, $date) {
        $sql = sprintf('INSERT INTO wiki_attachment_log SET'
                       .'  user_id                     = %d'
                       .', group_id                    = %d'
                       .', wiki_attachment_id          = %d'
                       .', wiki_attachment_revision_id = %d'
                       .', time                        = %d',
                       $userId,
                       $groupId,
                       $attachmentId,
                       $revision,
                       $date);
        
        $inserted = $this->update($sql);
        return $inserted;
    }

    /**
     * Get one revision
     */
    function &getRevision($attachmentId, $revision) {
        $sql = sprintf('SELECT * FROM wiki_attachment_revision'
                       .' WHERE attachment_id=%d'
                       .' AND revision=%d',
                       $attachmentId,
                       $revision);

         return $this->retrieve($sql);
    }
    
    /**
     * Fetch all revisions of a given attachment
     */
    function &getAllRevisions($id) {
        $sql = sprintf('SELECT * FROM wiki_attachment_revision'
                       .' WHERE attachment_id=%d'
                       .' ORDER BY date DESC',
                       $id);
        
        return $this->retrieve($sql);        
    }

    

}

?>

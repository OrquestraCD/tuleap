<?php

namespace Tuleap\Git\GitPHP;

/**
 * GitPHP Head
 *
 * Represents a single head
 *
 */

/**
 * Head class
 *
 */
class Head extends Ref
{

    /**
     * commit
     *
     * Stores the commit internally
     *
     * @access protected
     */
    protected $commit;

    /**
     * __construct
     *
     * Instantiates head
     *
     * @access public
     * @param mixed $project the project
     * @param string $head head name
     * @param string $headHash head hash
     * @return mixed head object
     * @throws \Exception exception on invalid head or hash
     */
    public function __construct($project, $head, $headHash = '')
    {
        parent::__construct($project, 'heads', $head, $headHash);
    }

    /**
     * GetCommit
     *
     * Gets the commit for this head
     *
     * @access public
     * @return mixed commit object for this tag
     */
    public function GetCommit() // @codingStandardsIgnoreLine
    {
        if (!$this->commit) {
            $this->commit = $this->project->GetCommit($this->GetHash());
        }

        return $this->commit;
    }

    /**
     * CompareAge
     *
     * Compares two heads by age
     *
     * @access public
     * @static
     * @param mixed $a first head
     * @param mixed $b second head
     * @return integer comparison result
     */
    public static function CompareAge($a, $b) // @codingStandardsIgnoreLine
    {
        $aObj = $a->GetCommit();
        $bObj = $b->GetCommit();
        return Commit::CompareAge($aObj, $bObj);
    }
}

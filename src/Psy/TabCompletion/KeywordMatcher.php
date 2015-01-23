<?php

namespace Psy\TabCompletion;
use Psy\Context;

/**
 * Class KeywordMatcher
 * @package Psy\TabCompletion
 */
class KeywordMatcher extends AbstractMatcher
{
    protected $keywords = array(
        'array', 'clone', 'declare', 'die', 'echo', 'empty', 'eval', 'exit', 'include',
        'include_once', 'isset', 'list', 'print',  'require', 'require_once', 'unset',
    );

    /**
     * @return array
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * @param $keyword
     * @return bool
     */
    public function isKeyword($keyword)
    {
        return in_array($keyword, $this->keywords);
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        return array_filter($this->keywords, function ($keyword) use ($input) {
            return $this->startsWith($input, $keyword);
        });
    }
}

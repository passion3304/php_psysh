<?php

namespace Psy\Util;

/**
 * A docblock representation.
 *
 * Based on PHP-DocBlock-Parser by Paul Scott:
 *
 * {@link http://www.github.com/icio/PHP-DocBlock-Parser}
 *
 * @author Paul Scott <paul@duedil.com>
 * @author Justin Hileman <justin@justinhileman.info>
 */
class Docblock
{
    /**
     * Tags in the docblock that have a whitepace-delimited number of parameters
     * (such as `@param type var desc` and `@return type desc`) and the names of
     * those parameters.
     *
     * @type Array
     */
    public static $vectors = array(
        'throws'     => array('type', 'desc'),
        'param'      => array('type', 'var', 'desc'),
        'return'     => array('type', 'desc'),
    );

    /**
     * The description of the symbol
     *
     * @type String
     */
    public $desc;

    /**
     * The tags defined in the docblock.
     *
     * The array has keys which are the tag names (excluding the @) and values
     * that are arrays, each of which is an entry for the tag.
     *
     * In the case where the tag name is defined in {@see DocBlock::$vectors} the
     * value within the tag-value array is an array in itself with keys as
     * described by {@see DocBlock::$vectors}.
     *
     * @type Array
     */
    public $tags;

    /**
     * The entire DocBlock comment that was parsed.
     *
     * @type String
     */
    public $comment;

    /**
     * Docblock constructor.
     *
     * @param String $comment The text of the docblock
     */
    public function __construct($comment = null)
    {
        if ($comment) {
            $this->setComment($comment);
        }
    }

    /**
     * Set and parse the docblock comment.
     *
     * @param String $comment The docblock
     */
    public function setComment($comment)
    {
        $this->desc    = '';
        $this->tags    = array();
        $this->comment = $comment;

        $this->parseComment($comment);
    }

    /**
     * Find the length of the docblock prefix.
     *
     * @param array $lines
     *
     * @return int Prefix length
     */
    protected static function prefixLength(array $lines)
    {
        // find only lines with interesting things
        $lines = array_filter($lines, function($line) {
            return substr($line, strspn($line, "* \t\n\r\0\x0B"));
        });

        // if we sort the lines, we only have to compare two items
        sort($lines);

        $first = reset($lines);
        $last  = end($lines);

        // find the longest common substring
        $count = min(strlen($first), strlen($last));
        for ($i = 0; $i < $count; $i++) {
            if ($first[$i] !== $last[$i]) {
                return $i;
            }
        }

        return $count;
    }

    /**
     * Parse the comment into the component parts and set the state of the object.
     *
     * @param  String $comment The docblock
     */
    protected function parseComment($comment)
    {
        // Strip the opening and closing tags of the docblock
        $comment = substr($comment, 3, -2);


        // Split into arrays of lines
        $comment = array_filter(preg_split('/\r?\n\r?/', $comment));

        // Trim asterisks and whitespace from the beginning and whitespace from the end of lines
        $prefixLength = self::prefixLength($comment);
        $comment = array_map(function($line) use ($prefixLength) {
            return rtrim(substr($line, $prefixLength));
        }, $comment);

        // Group the lines together by @tags
        $blocks = array();
        $b = -1;
        foreach ($comment as $line) {
            if (self::isTagged($line)) {
                $b++;
                $blocks[] = array();
            } else if($b == -1) {
                $b = 0;
                $blocks[] = array();
            }
            $blocks[$b][] = $line;
        }

        // Parse the blocks
        foreach ($blocks as $block => $body) {
            $body = trim(implode("\n", $body));

            if ($block == 0 && !self::isTagged($body)) {
                // This is the description block
                $this->desc = $body;
            } else {
                // This block is tagged
                $tag  = substr(self::strTag($body), 1);
                $body = ltrim(substr($body, strlen($tag)+2));

                if (isset(self::$vectors[$tag])) {
                    // The tagged block is a vector
                    $count = count(self::$vectors[$tag]);
                    if ($body) {
                        $parts = preg_split('/\s+/', $body, $count);
                    } else {
                        $parts = array();
                    }

                    // Default the trailing values
                    $parts = array_pad($parts, $count, null);

                    // Store as a mapped array
                    $this->tags[$tag][] = array_combine(self::$vectors[$tag], $parts);
                } else {
                    // The tagged block is only text
                    $this->tags[$tag][] = $body;
                }
            }
        }
    }

    /**
     * Whether or not a docblock contains a given @tag.
     *
     * @param  String $tag The name of the @tag to check for
     *
     * @return bool
     */
    public function hasTag($tag)
    {
        return is_array($this->tags) && array_key_exists($tag, $this->tags);
    }

    /**
     * The value of a tag
     *
     * @param  String $tag
     *
     * @return Array
     */
    public function tag($tag)
    {
        return $this->hasTag($tag) ? $this->tags[$tag] : null;
    }

    /**
     * The value of a tag (concatenated for multiple values)
     *
     * @param  String $tag
     * @param  string $sep The seperator for concatenating
     *
     * @return String
     */
    public function tagImplode($tag, $sep = ' ')
    {
        return $this->hasTag($tag) ? implode($sep, $this->tags[$tag]) : null;
    }

    /**
     * The value of a tag (merged recursively)
     * @param  String $tag
     * @return Array
     */
    public function tagMerge($tag)
    {
        return $this->hasTag($tag) ? array_merge_recursive($this->tags[$tag]) : null;
    }

    /**
     * Whether or not a string begins with a @tag
     *
     * @param  String $str
     *
     * @return bool
     */
    public static function isTagged($str)
    {
        return isset($str[1]) && $str[0] == '@' && ctype_alpha($str[1]);
    }

    /**
     * The tag at the beginning of a string
     *
     * @param  String $str
     *
     * @return String|null
     */
    public static function strTag($str)
    {
        if (preg_match('/^@[a-z0-9_]+/', $str, $matches)) {
            return $matches[0];
        }
    }
}

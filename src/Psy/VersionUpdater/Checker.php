<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\VersionUpdater;

use Psy\Shell;

class Checker
{
    const URL = 'https://api.github.com/repos/bobthecow/psysh/releases/latest';

    private $latest;

    /**
     * @return bool
     */
    public function isLatest()
    {
        return version_compare(Shell::VERSION, $this->getVersionFromTag(), '>=');
    }

    /**
     * @return string
     */
    public function getLatest()
    {
        return $this->latest;
    }

    /**
     * @param string $version
     */
    public function setLatest($version)
    {
        $this->latest = $version;
    }

    /**
     * @return string|null
     */
    private function getVersionFromTag()
    {
        $contents = $this->downloadFromGitHub();
        if (!$contents || !isset($contents->tag_name)) {
            throw new \InvalidArgumentException('Unable to check for updates');
        }
        $this->setLatest($contents->tag_name);

        return $this->getLatest();
    }

    /**
     * Set to public to make testing easier.
     *
     * @return mixed
     */
    public function downloadFromGitHub()
    {
        $context = stream_context_create(array('http' => array('user_agent' => 'PsySH/' . Shell::VERSION)));

        return json_decode(@file_get_contents(self::URL, false, $context));
    }
}

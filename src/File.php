<?php

namespace Laminas\Uri;

use function preg_match;
use function str_replace;
use function strpos;

/**
 * File URI handler
 *
 * The 'file:...' scheme is loosely defined in RFC-1738
 */
class File extends Uri
{
    /** @var array<int,string> */
    protected static $validSchemes = ['file'];

    /**
     * Check if the URI is a valid File URI
     *
     * This applies additional specific validation rules beyond the ones
     * required by the generic URI syntax.
     *
     * @see    Uri::isValid()
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->query) {
            return false;
        }

        return parent::isValid();
    }

    /**
     * User Info part is not used in file URIs
     *
     * @see    Uri::setUserInfo()
     *
     * @param  string $userInfo
     */
    public function setUserInfo($userInfo): static
    {
        return $this;
    }

    /**
     * Fragment part is not used in file URIs
     *
     * @see    Uri::setFragment()
     *
     * @param  string $fragment
     */
    public function setFragment($fragment): static
    {
        return $this;
    }

    /**
     * Convert a UNIX file path to a valid file:// URL
     *
     * @param  string $path
     */
    public static function fromUnixPath($path): static
    {
        $url = new static('file:');
        if (str_starts_with($path, '/')) {
            $url->setHost('');
        }

        $url->setPath($path);
        return $url;
    }

    /**
     * Convert a Windows file path to a valid file:// URL
     *
     * @param  string $path
     */
    public static function fromWindowsPath($path): static
    {
        $url = new static('file:');

        // Convert directory separators
        $path = str_replace(['/', '\\'], ['%2F', '/'], $path);

        // Is this an absolute path?
        if (preg_match('|^([a-zA-Z]:)?/|', $path)) {
            $url->setHost('');
        }

        $url->setPath($path);
        return $url;
    }
}

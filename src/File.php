<?php

declare (strict_types=1);
namespace Laminas\Uri;

use function preg_match;
use function str_replace;
/**
 * File URI handler
 *
 * The 'file:...' scheme is loosely defined in RFC-1738
 */
class File extends Uri
{
    /** @var array<int,string> */
    protected static $valid_schemes = ['file'];
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
    public function is_valid()
    {
        if ($this->query) {
            return false;
        }
        return parent::is_valid();
    }
    /**
     * User Info part is not used in file URIs
     *
     * @see    Uri::setUserInfo()
     *
     * @param  string $userInfo
     */
    public function set_user_info($user_info): static
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
    public function set_fragment($fragment): static
    {
        return $this;
    }
    /**
     * Convert a UNIX file path to a valid file:// URL
     *
     * @param  string $path
     */
    public static function from_unix_path($path): static
    {
        $url = new static('file:');
        if (str_starts_with($path, '/')) {
            $url->set_host('');
        }
        $url->set_path($path);
        return $url;
    }
    /**
     * Convert a Windows file path to a valid file:// URL
     *
     * @param  string $path
     */
    public static function from_windows_path($path): static
    {
        $url = new static('file:');
        // Convert directory separators
        $path = str_replace(['/', '\\'], ['%2F', '/'], $path);
        // Is this an absolute path?
        if (preg_match('|^([a-zA-Z]:)?/|', $path)) {
            $url->set_host('');
        }
        $url->set_path($path);
        return $url;
    }
}
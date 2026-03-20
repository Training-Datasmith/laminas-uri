<?php

declare (strict_types=1);
namespace Laminas\Uri;

use function array_key_exists;
use function explode;
/**
 * HTTP URI handler
 */
class Http extends Uri
{
    /**
     * @see Uri::$validSchemes
     *
     * @var array<int,string>
     */
    protected static $valid_schemes = ['http', 'https'];
    /**
     * @see Uri::$defaultPorts
     *
     * @var array<string,int>
     */
    protected static $default_ports = ['http' => 80, 'https' => 443];
    /**
     * @see Uri::$validHostTypes
     *
     * @var int
     */
    protected $valid_host_types = self::HOST_DNS_OR_IPV4_OR_IPV6_OR_REGNAME;
    /**
     * User name as provided in authority of URI
     *
     * @var null|string
     */
    protected $user;
    /**
     * Password as provided in authority of URI
     *
     * @var null|string
     */
    protected $password;
    /**
     * Get the username part (before the ':') of the userInfo URI part
     *
     * @return string|null
     */
    public function get_user()
    {
        return $this->user;
    }
    /**
     * Get the password part (after the ':') of the userInfo URI part
     *
     * @return string|null
     */
    public function get_password()
    {
        return $this->password;
    }
    /**
     * Get the User-info (usually user:password) part
     *
     * @return string|null
     */
    public function get_user_info()
    {
        return $this->user_info;
    }
    /**
     * Set the username part (before the ':') of the userInfo URI part
     *
     * @param string|null $user
     */
    public function set_user($user): static
    {
        $this->user = null === $user ? null : (string) $user;
        $this->build_user_info();
        return $this;
    }
    /**
     * Set the password part (after the ':') of the userInfo URI part
     *
     * @param  string $password
     */
    public function set_password($password): static
    {
        $this->password = null === $password ? null : (string) $password;
        $this->build_user_info();
        return $this;
    }
    /**
     * Set the URI User-info part (usually user:password)
     *
     * @param  string|null $userInfo
     * @throws Exception\InvalidUriPartException If the schema definition does not have this part.
     */
    public function set_user_info($user_info): static
    {
        $this->user_info = null === $user_info ? null : (string) $user_info;
        $this->parse_user_info();
        return $this;
    }
    /**
     * Validate the host part of an HTTP URI
     *
     * This overrides the common URI validation method with a DNS or IP only
     * default. Users may still enforce allowing other host types.
     *
     * @param  string  $host
     * @param  int $allowed
     * @return bool
     */
    public static function validate_host($host, $allowed = self::HOST_DNS_OR_IPV4_OR_IPV6)
    {
        return parent::validate_host($host, $allowed);
    }
    /**
     * Parse the user info into username and password segments
     *
     * Parses the user information into username and password segments, and
     * then sets the appropriate values.
     *
     * @return void
     */
    protected function parse_user_info()
    {
        // No user information? we're done
        if (null === $this->user_info) {
            $this->set_user(null);
            $this->set_password(null);
            return;
        }
        // If no ':' separator, we only have a username
        if (!str_contains($this->user_info, ':')) {
            $this->set_user($this->user_info);
            $this->set_password(null);
            return;
        }
        // Split on the ':', and set both user and password
        [$this->user, $this->password] = explode(':', $this->user_info, 2);
    }
    /**
     * Build the user info based on user and password
     *
     * Builds the user info based on the given user and password values
     *
     * @return void
     */
    protected function build_user_info()
    {
        if (null !== $this->password) {
            $this->user_info = $this->user . ':' . $this->password;
        } else {
            $this->user_info = $this->user;
        }
    }
    /**
     * Return the URI port
     *
     * If no port is set, will return the default port according to the scheme
     *
     * @see    Laminas\Uri\Uri::getPort()
     *
     * @return int
     */
    public function get_port()
    {
        if (empty($this->port)) {
            $scheme = $this->scheme ?? '';
            if (array_key_exists($scheme, static::$default_ports)) {
                return static::$default_ports[$this->scheme];
            }
        }
        return $this->port;
    }
    /**
     * Parse a URI string
     *
     * @param  string $uri
     */
    public function parse($uri): static
    {
        parent::parse($uri);
        if (empty($this->path)) {
            $this->path = '/';
        }
        return $this;
    }
}
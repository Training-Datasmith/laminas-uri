<?php

declare (strict_types=1);
namespace Laminas\Uri;

use function array_intersect_assoc;
use function array_pop;
use function array_unshift;
use Exception as PhpException;
use function explode;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use Laminas\Escaper\Escaper;
use Laminas\Validator;
use function parse_str;
use function preg_match;
use function preg_replace_callback;
use function preg_split;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;
use function rawurldecode;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
/**
 * Generic URI handler
 */
class Uri implements Uri_Interface
{
    /**
     * Character classes defined in RFC-3986
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    public const CHAR_GEN_DELIMS = ':\/\?#\[\]@';
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    public const CHAR_RESERVED = ':\/\?#\[\]@!\$&\'\(\)\*\+,;=';
    /**
     * Not in the spec - those characters have special meaning in urlencoded query parameters
     */
    public const CHAR_QUERY_DELIMS = '!\$\'\(\)\*\,';
    /**
     * Host part types represented as binary masks
     * The binary mask consists of 5 bits in the following order:
     * <RegName> | <DNS> | <IPvFuture> | <IPv6> | <IPv4>
     * Place 1 or 0 in the different positions for enable or disable the part.
     * Finally use a hexadecimal representation.
     */
    public const HOST_IPV4 = 0x1;
    //00001
    public const HOST_IPV6 = 0x2;
    //00010
    public const HOST_IPVFUTURE = 0x4;
    //00100
    public const HOST_IPVANY = 0x7;
    //00111
    public const HOST_DNS = 0x8;
    //01000
    public const HOST_DNS_OR_IPV4 = 0x9;
    //01001
    public const HOST_DNS_OR_IPV6 = 0xa;
    //01010
    public const HOST_DNS_OR_IPV4_OR_IPV6 = 0xb;
    //01011
    public const HOST_DNS_OR_IPVANY = 0xf;
    //01111
    public const HOST_REGNAME = 0x10;
    //10000
    public const HOST_DNS_OR_IPV4_OR_IPV6_OR_REGNAME = 0x1b;
    //11011
    public const HOST_ALL = 0x1f;
    //11111
    /**
     * URI scheme
     *
     * @var string|null
     */
    protected $scheme;
    /**
     * URI userInfo part (usually user:password in HTTP URLs)
     *
     * @var string|null
     */
    protected $user_info;
    /**
     * URI hostname
     *
     * @var string|null
     */
    protected $host;
    /**
     * URI port
     *
     * @var int|null
     */
    protected $port;
    /**
     * URI path
     *
     * @var string|null
     */
    protected $path;
    /**
     * URI query string
     *
     * @var string|null
     */
    protected $query;
    /**
     * URI fragment|null
     *
     * @var string
     */
    protected $fragment;
    /**
     * Which host part types are valid for this URI?
     *
     * @var int
     */
    protected $valid_host_types = self::HOST_ALL;
    /**
     * Array of valid schemes.
     *
     * Subclasses of this class that only accept specific schemes may set the
     * list of accepted schemes here. If not empty, when setScheme() is called
     * it will only accept the schemes listed here.
     *
     * @var array
     */
    protected static $valid_schemes = [];
    /**
     * List of default ports per scheme
     *
     * Inheriting URI classes may set this, and the normalization methods will
     * automatically remove the port if it is equal to the default port for the
     * current scheme
     *
     * @var array
     */
    protected static $default_ports = [];
    /** @var Escaper */
    protected static $escaper;
    /**
     * Create a new URI object
     *
     * @param  Uri|string|null $uri
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($uri = null)
    {
        if (is_string($uri)) {
            $this->parse($uri);
        } elseif ($uri instanceof Uri_Interface) {
            // Copy constructor
            $this->set_scheme($uri->get_scheme());
            $this->set_user_info($uri->get_user_info());
            $this->set_host($uri->get_host());
            $this->set_port($uri->get_port());
            $this->set_path($uri->get_path());
            $this->set_query($uri->get_query());
            $this->set_fragment($uri->get_fragment());
        } elseif ($uri !== null) {
            throw new Exception\InvalidArgumentException(sprintf('Expecting a string or a URI object, received "%s"', get_debug_type($uri)));
        }
    }
    /**
     * Set Escaper instance
     */
    public static function set_escaper(Escaper $escaper): void
    {
        static::$escaper = $escaper;
    }
    /**
     * Retrieve Escaper instance
     *
     * Lazy-loads one if none provided
     *
     * @return Escaper
     */
    public static function get_escaper()
    {
        if (null === static::$escaper) {
            static::set_escaper(new Escaper());
        }
        return static::$escaper;
    }
    /**
     * Check if the URI is valid
     *
     * Note that a relative URI may still be valid
     */
    public function is_valid(): bool
    {
        if ($this->host) {
            if (null !== $this->path && strlen($this->path) > 0 && !str_starts_with($this->path, '/')) {
                return false;
            }
            return true;
        }
        if ($this->user_info || $this->port) {
            return false;
        }
        if ($this->path) {
            // Check path-only (no host) URI
            if (str_starts_with($this->path, '//')) {
                return false;
            }
            return true;
        }
        if (!($this->query || $this->fragment)) {
            // No host, path, query or fragment - this is not a valid URI
            return false;
        }
        return true;
    }
    /**
     * Check if the URI is a valid relative URI
     */
    public function is_valid_relative(): bool
    {
        if ($this->scheme || $this->host || $this->user_info || $this->port) {
            return false;
        }
        if ($this->path) {
            // Check path-only (no host) URI
            if (str_starts_with($this->path, '//')) {
                return false;
            }
            return true;
        }
        if (!($this->query || $this->fragment)) {
            // No host, path, query or fragment - this is not a valid URI
            return false;
        }
        return true;
    }
    /**
     * Check if the URI is an absolute or relative URI
     */
    public function is_absolute(): bool
    {
        return $this->scheme !== null;
    }
    /**
     * Reset URI parts
     */
    protected function reset()
    {
        $this->set_scheme(null);
        $this->set_port(null);
        $this->set_user_info(null);
        $this->set_host(null);
        $this->set_path(null);
        $this->set_fragment(null);
        $this->set_query(null);
    }
    /**
     * Parse a URI string
     *
     * @param  string $uri
     */
    public function parse($uri): static
    {
        $this->reset();
        // Capture scheme
        if (($scheme = self::parse_scheme($uri)) !== null) {
            $this->set_scheme($scheme);
            $uri = substr($uri, strlen($scheme) + 1) ?: '';
        }
        // Capture authority part
        if (preg_match('|^//([^/\?#]*)|', $uri, $match)) {
            $authority = $match[1];
            $uri = substr($uri, strlen($match[0]));
            // Split authority into userInfo and host
            if (str_contains($authority, '@')) {
                // The userInfo can also contain '@' symbols; split $authority
                // into segments, and set it to the last segment.
                $segments = explode('@', $authority);
                $authority = array_pop($segments);
                $user_info = implode('@', $segments);
                unset($segments);
                $this->set_user_info($user_info);
            }
            $n_matches = preg_match('/:[\d]{0,5}$/', $authority, $matches);
            if ($n_matches === 1) {
                $port_length = strlen($matches[0]);
                $port = substr($matches[0], 1);
                // If authority ends with colon, port will be empty string.
                // Remove the colon from authority, but keeps port null
                if ($port) {
                    $this->set_port((int) $port);
                }
                $authority = substr($authority, 0, -$port_length);
            }
            $this->set_host($authority);
        }
        if (!$uri) {
            return $this;
        }
        // Capture the path
        if (preg_match('|^[^\?#]*|', $uri, $match)) {
            $this->set_path($match[0]);
            $uri = substr($uri, strlen($match[0]));
        }
        if (!$uri) {
            return $this;
        }
        // Capture the query
        if (preg_match('|^\?([^#]*)|', $uri, $match)) {
            $this->set_query($match[1]);
            $uri = substr($uri, strlen($match[0]));
        }
        if (!$uri) {
            return $this;
        }
        // All that's left is the fragment
        if ($uri && str_starts_with($uri, '#')) {
            $this->set_fragment(substr($uri, 1));
        }
        return $this;
    }
    /**
     * Compose the URI into a string
     *
     * @throws Exception\InvalidUriException
     */
    public function to_string(): string
    {
        if (!$this->is_valid()) {
            if ($this->is_absolute() || !$this->is_valid_relative()) {
                throw new Exception\Invalid_Uri_Exception('URI is not valid and cannot be converted into a string');
            }
        }
        $uri = '';
        if ($this->scheme) {
            $uri .= $this->scheme . ':';
        }
        if ($this->host !== null) {
            $uri .= '//';
            if ($this->user_info) {
                $uri .= $this->user_info . '@';
            }
            $uri .= $this->host;
            if ($this->port) {
                $uri .= ':' . $this->port;
            }
        }
        if ($this->path) {
            $uri .= static::encode_path($this->path);
        } elseif ($this->host && ($this->query || $this->fragment)) {
            $uri .= '/';
        }
        if ($this->query) {
            $uri .= '?' . static::encode_query_fragment($this->query);
        }
        if ($this->fragment) {
            $uri .= '#' . static::encode_query_fragment($this->fragment);
        }
        return $uri;
    }
    /**
     * Normalize the URI
     *
     * Normalizing a URI includes removing any redundant parent directory or
     * current directory references from the path (e.g. foo/bar/../baz becomes
     * foo/baz), normalizing the scheme case, decoding any over-encoded
     * characters etc.
     *
     * Eventually, two normalized URLs pointing to the same resource should be
     * equal even if they were originally represented by two different strings
     */
    public function normalize(): static
    {
        if ($this->scheme) {
            $this->scheme = static::normalize_scheme($this->scheme);
        }
        if ($this->host) {
            $this->host = static::normalize_host($this->host);
        }
        if ($this->port) {
            $this->port = static::normalize_port($this->port, $this->scheme);
        }
        if ($this->path) {
            $this->path = static::normalize_path($this->path);
        }
        if ($this->query) {
            $this->query = static::normalize_query($this->query);
        }
        if ($this->fragment) {
            $this->fragment = static::normalize_fragment($this->fragment);
        }
        // If path is empty (and we have a host), path should be '/'
        // Isn't this valid ONLY for HTTP-URI?
        if ($this->host && empty($this->path)) {
            $this->path = '/';
        }
        return $this;
    }
    /**
     * Convert a relative URI into an absolute URI using a base absolute URI as
     * a reference.
     *
     * This is similar to merge() - only it uses the supplied URI as the
     * base reference instead of using the current URI as the base reference.
     *
     * Merging algorithm is adapted from RFC-3986 section 5.2
     * (@link http://tools.ietf.org/html/rfc3986#section-5.2)
     *
     * @param  Uri|string $baseUri
     * @throws Exception\InvalidArgumentException
     */
    public function resolve($base_uri): static
    {
        // Ignore if URI is absolute
        if ($this->is_absolute()) {
            return $this;
        }
        if (is_string($base_uri)) {
            $base_uri = new static($base_uri);
        } elseif (!$base_uri instanceof Uri) {
            throw new Exception\InvalidArgumentException('Provided base URI must be a string or a Uri object');
        }
        // Merging starts here...
        if ($this->get_host()) {
            $this->set_path(static::remove_path_dot_segments($this->get_path()));
        } else {
            $base_path = $base_uri->get_path();
            $rel_path = $this->get_path();
            if (!$rel_path) {
                $this->set_path($base_path);
                if (!$this->get_query()) {
                    $this->set_query($base_uri->get_query());
                }
            } else if (str_starts_with($rel_path, '/')) {
                $this->set_path(static::remove_path_dot_segments($rel_path));
            } else {
                if ($base_uri->get_host() && !$base_path) {
                    $merged_path = '/';
                } else {
                    $merged_path = substr((string) $base_path, 0, strrpos((string) $base_path, '/') + 1);
                }
                $this->set_path(static::remove_path_dot_segments($merged_path . $rel_path));
            }
            // Set the authority part
            $this->set_user_info($base_uri->get_user_info());
            $this->set_host($base_uri->get_host());
            $this->set_port($base_uri->get_port());
        }
        $this->set_scheme($base_uri->get_scheme());
        return $this;
    }
    /**
     * Convert the link to a relative link by substracting a base URI
     *
     *  This is the opposite of resolving a relative link - i.e. creating a
     *  relative reference link from an original URI and a base URI.
     *
     *  If the two URIs do not intersect (e.g. the original URI is not in any
     *  way related to the base URI) the URI will not be modified.
     *
     * @param  Uri|string $baseUri
     */
    public function make_relative($base_uri): static
    {
        // Copy base URI, we should not modify it
        $base_uri = new static($base_uri);
        $this->normalize();
        $base_uri->normalize();
        $host = $this->get_host();
        $base_host = $base_uri->get_host();
        if ($host && $base_host && $host !== $base_host) {
            // Not the same hostname
            return $this;
        }
        $port = $this->get_port();
        $base_port = $base_uri->get_port();
        if ($port && $base_port && $port !== $base_port) {
            // Not the same port
            return $this;
        }
        $scheme = $this->get_scheme();
        $base_scheme = $base_uri->get_scheme();
        if ($scheme && $base_scheme && $scheme !== $base_scheme) {
            // Not the same scheme (e.g. HTTP vs. HTTPS)
            return $this;
        }
        // Remove host, port and scheme
        $this->set_host(null)->set_port(null)->set_scheme(null);
        // Is path the same?
        if ($this->get_path() === $base_uri->get_path()) {
            $this->set_path('');
            return $this;
        }
        $path_parts = preg_split('|(/)|', $this->get_path() ?? '', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $base_parts = preg_split('|(/)|', $base_uri->get_path() ?? '', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        // Get the intersection of existing path parts and those from the
        // provided URI
        $matching_parts = array_intersect_assoc($path_parts, $base_parts);
        // Loop through the matches
        foreach ($matching_parts as $index => $segment) {
            // If we skip an index at any point, we have parent traversal, and
            // need to prepend the path accordingly
            if ($index && !isset($matching_parts[$index - 1])) {
                array_unshift($path_parts, '../');
                continue;
            }
            // Otherwise, we simply unset the given path segment
            unset($path_parts[$index]);
        }
        // Reset the path by imploding path segments
        $this->set_path(implode('', $path_parts));
        return $this;
    }
    /**
     * Get the scheme part of the URI
     *
     * @return string|null
     */
    public function get_scheme()
    {
        return $this->scheme;
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
     * Get the URI host
     *
     * @return string|null
     */
    public function get_host()
    {
        return $this->host;
    }
    /**
     * Get the URI port
     *
     * @return int|null
     */
    public function get_port()
    {
        return $this->port;
    }
    /**
     * Get the URI path
     *
     * @return string|null
     */
    public function get_path()
    {
        return $this->path;
    }
    /**
     * Get the URI query
     *
     * @return string|null
     */
    public function get_query()
    {
        return $this->query;
    }
    /**
     * Return the query string as an associative array of key => value pairs
     *
     * This is an extension to RFC-3986 but is quite useful when working with
     * most common URI types
     */
    public function get_query_as_array(): array
    {
        $query = [];
        if ($this->query) {
            parse_str($this->query, $query);
        }
        return $query;
    }
    /**
     * Get the URI fragment
     *
     * @return string|null
     */
    public function get_fragment()
    {
        return $this->fragment;
    }
    /**
     * Set the URI scheme
     *
     * If the scheme is not valid according to the generic scheme syntax or
     * is not acceptable by the specific URI class (e.g. 'http' or 'https' are
     * the only acceptable schemes for the Laminas\Uri\Http class) an exception
     * will be thrown.
     *
     * You can check if a scheme is valid before setting it using the
     * validateScheme() method.
     *
     * @param  string|null $scheme
     * @throws Exception\InvalidUriPartException
     */
    public function set_scheme($scheme): static
    {
        if ($scheme !== null && !self::validate_scheme($scheme)) {
            throw new Exception\Invalid_Uri_Part_Exception(sprintf('Scheme "%s" is not valid or is not accepted by %s', $scheme, static::class), Exception\Invalid_Uri_Part_Exception::INVALID_SCHEME);
        }
        $this->scheme = $scheme;
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
        $this->user_info = $user_info;
        return $this;
    }
    /**
     * Set the URI host
     *
     * Note that the generic syntax for URIs allows using host names which
     * are not necessarily IPv4 addresses or valid DNS host names. For example,
     * IPv6 addresses are allowed as well, and also an abstract "registered name"
     * which may be any name composed of a valid set of characters, including,
     * for example, tilda (~) and underscore (_) which are not allowed in DNS
     * names.
     *
     * Subclasses of Uri may impose more strict validation of host names - for
     * example the HTTP RFC clearly states that only IPv4 and valid DNS names
     * are allowed in HTTP URIs.
     *
     * @param  string|null $host
     * @throws Exception\InvalidUriPartException
     */
    public function set_host($host): static
    {
        if ($host !== '' && $host !== null && !self::validate_host($host, $this->valid_host_types)) {
            throw new Exception\Invalid_Uri_Part_Exception(sprintf('Host "%s" is not valid or is not accepted by %s', $host, static::class), Exception\Invalid_Uri_Part_Exception::INVALID_HOSTNAME);
        }
        if ($host !== null) {
            $host = strtolower($host);
        }
        $this->host = $host;
        return $this;
    }
    /**
     * Set the port part of the URI
     *
     * @param  int|null $port
     */
    public function set_port($port): static
    {
        $this->port = $port;
        return $this;
    }
    /**
     * Set the path
     *
     * @param  string|null $path
     */
    public function set_path($path): static
    {
        $this->path = $path;
        return $this;
    }
    /**
     * Set the query string
     *
     * If an array is provided, will encode this array of parameters into a
     * query string. Array values will be represented in the query string using
     * PHP's common square bracket notation.
     *
     * @param  string|array|null $query
     */
    public function set_query($query): static
    {
        if (is_array($query)) {
            // We replace the + used for spaces by http_build_query with the
            // more standard %20.
            $query = str_replace('+', '%20', http_build_query($query));
        }
        $this->query = $query;
        return $this;
    }
    /**
     * Set the URI fragment part
     *
     * @param  string|null $fragment
     * @throws Exception\InvalidUriPartException If the schema definition does not have this part.
     */
    public function set_fragment($fragment): static
    {
        $this->fragment = $fragment;
        return $this;
    }
    /**
     * Magic method to convert the URI to a string
     */
    public function __toString(): string
    {
        try {
            return $this->to_string();
        } catch (Php_Exception) {
            return '';
        }
    }
    /**
     * Encoding and Validation Methods
     */
    /**
     * Check if a scheme is valid or not
     *
     * Will check $scheme to be valid against the generic scheme syntax defined
     * in RFC-3986. If the class also defines specific acceptable schemes, will
     * also check that $scheme is one of them.
     *
     * @param  string $scheme
     * @return bool
     */
    public static function validate_scheme($scheme)
    {
        if (!empty(static::$valid_schemes) && !in_array(strtolower($scheme), static::$valid_schemes)) {
            return false;
        }
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9\-\.+]*$/', $scheme);
    }
    /**
     * Check that the userInfo part of a URI is valid
     *
     * @param  string $userInfo
     */
    public static function validate_user_info($user_info): bool
    {
        $regex = '/^(?:[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':]+|%[A-Fa-f0-9]{2})*$/';
        return (bool) preg_match($regex, $user_info);
    }
    /**
     * Validate the host part
     *
     * Users may control which host types to allow by passing a second parameter
     * with a bitmask of HOST_* constants which are allowed. If not specified,
     * all address types will be allowed.
     *
     * Note that the generic URI syntax allows different host representations,
     * including IPv4 addresses, IPv6 addresses and future IP address formats
     * enclosed in square brackets, and registered names which may be DNS names
     * or even more complex names. This is different (and is much more loose)
     * from what is commonly accepted as valid HTTP URLs for example.
     *
     * @param  string  $host
     * @param  int $allowed bitmask of allowed host types
     */
    public static function validate_host($host, $allowed = self::HOST_ALL): bool
    {
        /*
         * "first-match-wins" algorithm (RFC 3986):
         * If host matches the rule for IPv4address, then it should be
         * considered an IPv4 address literal and not a reg-name
         */
        if ($allowed & self::HOST_IPVANY) {
            if (static::is_valid_ip_address($host, $allowed)) {
                return true;
            }
        }
        if ($allowed & self::HOST_REGNAME) {
            if (static::is_valid_reg_name($host)) {
                return true;
            }
        }
        if ($allowed & self::HOST_DNS) {
            if (static::is_valid_dns_hostname($host)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Validate the port
     *
     * Valid values include numbers between 1 and 65535, and empty values
     *
     * @param  int $port
     */
    public static function validate_port($port): bool
    {
        if ($port === 0) {
            return false;
        }
        if ($port) {
            $port = (int) $port;
            if ($port < 1 || $port > 0xffff) {
                return false;
            }
        }
        return true;
    }
    /**
     * Validate the path
     *
     * @param  string $path
     */
    public static function validate_path($path): bool
    {
        $pchar = '(?:[' . self::CHAR_UNRESERVED . ':@&=\+\$,]+|%[A-Fa-f0-9]{2})*';
        $segment = $pchar . "(?:;{$pchar})*";
        $regex = "/^{$segment}(?:\\/{$segment})*\$/";
        return (bool) preg_match($regex, $path);
    }
    /**
     * Check if a URI query or fragment part is valid or not
     *
     * Query and Fragment parts are both restricted by the same syntax rules,
     * so the same validation method can be used for both.
     *
     * You can encode a query or fragment part to ensure it is valid by passing
     * it through the encodeQueryFragment() method.
     *
     * @param  string $input
     */
    public static function validate_query_fragment($input): bool
    {
        $regex = '/^(?:[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':@\/\?]+|%[A-Fa-f0-9]{2})*$/';
        return (bool) preg_match($regex, $input);
    }
    /**
     * URL-encode the user info part of a URI
     *
     * @param  string $userInfo
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public static function encode_user_info($user_info): ?string
    {
        if (!is_string($user_info)) {
            throw new Exception\InvalidArgumentException(sprintf('Expecting a string, got %s', get_debug_type($user_info)));
        }
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:]|%(?![A-Fa-f0-9]{2}))/';
        $escaper = static::get_escaper();
        $replace = fn($match) => $escaper->escape_url($match[0]);
        return preg_replace_callback($regex, $replace, $user_info);
    }
    /**
     * Encode the path
     *
     * Will replace all characters which are not strictly allowed in the path
     * part with percent-encoded representation
     *
     * @param  string $path
     * @throws Exception\InvalidArgumentException
     * @return string
     */
    public static function encode_path($path): ?string
    {
        if (!is_string($path)) {
            throw new Exception\InvalidArgumentException(sprintf('Expecting a string, got %s', get_debug_type($path)));
        }
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . ')(:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/';
        $escaper = static::get_escaper();
        $replace = fn($match) => $escaper->escape_url($match[0]);
        return preg_replace_callback($regex, $replace, $path);
    }
    /**
     * URL-encode a query string or fragment based on RFC-3986 guidelines.
     *
     * Note that query and fragment encoding allows more unencoded characters
     * than the usual rawurlencode() function would usually return - for example
     * '/' and ':' are allowed as literals.
     *
     * @param  string $input
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public static function encode_query_fragment($input): ?string
    {
        if (!is_string($input)) {
            throw new Exception\InvalidArgumentException(sprintf('Expecting a string, got %s', get_debug_type($input)));
        }
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/';
        $escaper = static::get_escaper();
        $replace = fn($match) => $escaper->escape_url($match[0]);
        return preg_replace_callback($regex, $replace, $input);
    }
    /**
     * Extract only the scheme part out of a URI string.
     *
     * This is used by the parse() method, but is useful as a standalone public
     * method if one wants to test a URI string for it's scheme before doing
     * anything with it.
     *
     * Will return the scheme if found, or NULL if no scheme found (URI may
     * still be valid, but not full)
     *
     * @param  string $uriString
     * @throws Exception\InvalidArgumentException
     * @return string|null
     */
    public static function parse_scheme($uri_string)
    {
        if (!is_string($uri_string)) {
            throw new Exception\InvalidArgumentException(sprintf('Expecting a string, got %s', get_debug_type($uri_string)));
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9\.\+\-]*):/', $uri_string, $match)) {
            return $match[1];
        }
    }
    /**
     * Remove any extra dot segments (/../, /./) from a path
     *
     * Algorithm is adapted from RFC-3986 section 5.2.4
     * (@link http://tools.ietf.org/html/rfc3986#section-5.2.4)
     *
     * @todo   consider optimizing
     * @param  string $path
     */
    public static function remove_path_dot_segments($path): string
    {
        $output = '';
        while ($path) {
            if ($path === '..' || $path === '.') {
                break;
            }
            switch (true) {
                case $path === '/.':
                    $path = '/';
                    break;
                case $path === '/..':
                    $path = '/';
                    $last_slash_pos = strrpos($output, '/', -1);
                    if (false === $last_slash_pos) {
                        break;
                    }
                    $output = substr($output, 0, $last_slash_pos);
                    break;
                case str_starts_with($path, '/../'):
                    $path = '/' . substr($path, 4);
                    $last_slash_pos = false;
                    if ($output !== '') {
                        $last_slash_pos = strrpos($output, '/', -1);
                    }
                    if (false === $last_slash_pos) {
                        break;
                    }
                    $output = substr($output, 0, $last_slash_pos);
                    break;
                case str_starts_with($path, '/./'):
                case str_starts_with($path, './'):
                    $path = substr($path, 2);
                    break;
                case str_starts_with($path, '../'):
                    $path = substr($path, 3);
                    break;
                default:
                    $slash = strpos($path, '/', 1);
                    if ($slash === false) {
                        $seg = $path;
                    } else {
                        $seg = substr($path, 0, $slash);
                    }
                    $output .= $seg;
                    $path = substr($path, strlen($seg));
                    break;
            }
        }
        return $output;
    }
    /**
     * Merge a base URI and a relative URI into a new URI object
     *
     * This convenience method wraps ::resolve() to allow users to quickly
     * create new absolute URLs without the need to instantiate and clone
     * URI objects.
     *
     * If objects are passed in, none of the passed objects will be modified.
     *
     * @param  Uri|string $baseUri
     * @param  Uri|string $relativeUri
     * @return Uri
     */
    public static function merge($base_uri, $relative_uri)
    {
        $uri = new static($relative_uri);
        return $uri->resolve($base_uri);
    }
    /**
     * Check if a host name is a valid IP address, depending on allowed IP address types
     *
     * @param  string  $host
     * @param  int $allowed allowed address types
     * @return bool
     */
    protected static function is_valid_ip_address($host, $allowed)
    {
        $validator_params = ['allowipv4' => (bool) ($allowed & self::HOST_IPV4), 'allowipv6' => false, 'allowipvfuture' => false, 'allowliteral' => false];
        // Test only IPv4
        $validator_ip_v4 = new Validator\Ip($validator_params);
        $return = $validator_ip_v4->is_valid($host);
        if ($return) {
            return true;
        }
        // IPv6 & IPvLiteral must be in literal format
        $validator_params = ['allowipv4' => false, 'allowipv6' => (bool) ($allowed & self::HOST_IPV6), 'allowipvfuture' => (bool) ($allowed & self::HOST_IPVFUTURE), 'allowliteral' => true];
        static $regex = '/^\[.*\]$/';
        $validator_ip_v6 = new Validator\Ip($validator_params);
        return preg_match($regex, $host) && $validator_ip_v6->is_valid($host);
    }
    /**
     * Check if an address is a valid DNS hostname
     *
     * @param  string $host
     * @return bool
     */
    protected static function is_valid_dns_hostname($host)
    {
        $validator = new Validator\Hostname(['allow' => Validator\Hostname::ALLOW_DNS | Validator\Hostname::ALLOW_LOCAL]);
        return $validator->is_valid($host);
    }
    /**
     * Check if an address is a valid registered name (as defined by RFC-3986) address
     *
     * @param  string $host
     */
    protected static function is_valid_reg_name($host): bool
    {
        $regex = '/^(?:[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':@\/\?]+|%[A-Fa-f0-9]{2})+$/';
        return (bool) preg_match($regex, $host);
    }
    /**
     * Part normalization methods
     *
     * These are called by normalize() using static::_normalize*() so they may
     * be extended or overridden by extending classes to implement additional
     * scheme specific normalization rules
     */
    /**
     * Normalize the scheme
     *
     * Usually this means simply converting the scheme to lower case
     *
     * @param  string $scheme
     */
    protected static function normalize_scheme($scheme): string
    {
        return strtolower($scheme);
    }
    /**
     * Normalize the host part
     *
     * By default this converts host names to lower case
     *
     * @param  string $host
     */
    protected static function normalize_host($host): string
    {
        return strtolower($host);
    }
    /**
     * Normalize the port
     *
     * If the class defines a default port for the current scheme, and the
     * current port is default, it will be unset.
     *
     * @param  int $port
     * @param  string  $scheme
     * @return int|null
     */
    protected static function normalize_port($port, $scheme = null)
    {
        if ($scheme && isset(static::$default_ports[$scheme]) && $port === static::$default_ports[$scheme]) {
            return;
        }
        return $port;
    }
    /**
     * Normalize the path
     *
     * This involves removing redundant dot segments, decoding any over-encoded
     * characters and encoding everything that needs to be encoded and is not
     *
     * @param  string $path
     * @return string
     */
    protected static function normalize_path($path)
    {
        return self::encode_path(self::decode_url_encoded_chars(self::remove_path_dot_segments($path), '/[' . self::CHAR_UNRESERVED . ':@&=\+\$,\/;%]/'));
    }
    /**
     * Normalize the query part
     *
     * This involves decoding everything that doesn't need to be encoded, and
     * encoding everything else
     *
     * @param  string $query
     * @return string
     */
    protected static function normalize_query($query)
    {
        return self::encode_query_fragment(self::decode_url_encoded_chars($query, '/[' . self::CHAR_UNRESERVED . self::CHAR_QUERY_DELIMS . ':@\/\?]/'));
    }
    /**
     * Normalize the fragment part
     *
     * Currently this is exactly the same as normalizeQuery().
     *
     * @param  string $fragment
     * @return string
     */
    protected static function normalize_fragment($fragment)
    {
        return self::encode_query_fragment(self::decode_url_encoded_chars($fragment, '/[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]/'));
    }
    /**
     * Decode all percent encoded characters which are allowed to be represented literally
     *
     * Will not decode any characters which are not listed in the 'allowed' list
     *
     * @param string $input
     * @param string $allowed Pattern of allowed characters
     */
    protected static function decode_url_encoded_chars($input, $allowed = ''): ?string
    {
        $decode_cb = function ($match) use ($allowed) {
            $char = rawurldecode((string) $match[0]);
            if (preg_match($allowed, $char)) {
                return $char;
            }
            return strtoupper((string) $match[0]);
        };
        return preg_replace_callback('/%[A-Fa-f0-9]{2}/', $decode_cb, $input);
    }
}
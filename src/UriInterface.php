<?php

declare (strict_types=1);
namespace Laminas\Uri;

/**
 * Interface defining a URI
 */
interface Uri_Interface
{
    /**
     * Create a new URI object
     *
     * @param  Uri|string|null $uri
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($uri = null);
    /**
     * Check if the URI is valid
     *
     * Note that a relative URI may still be valid
     *
     * @return bool
     */
    public function is_valid();
    /**
     * Check if the URI is a valid relative URI
     *
     * @return bool
     */
    public function is_valid_relative();
    /**
     * Check if the URI is an absolute or relative URI
     *
     * @return bool
     */
    public function is_absolute();
    /**
     * Parse a URI string
     *
     * @param  string $uri
     * @return Uri
     */
    public function parse($uri);
    /**
     * Compose the URI into a string
     *
     * @return string
     * @throws Exception\InvalidUriException
     */
    public function to_string();
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
     *
     * @return Uri
     */
    public function normalize();
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
     * @return Uri
     */
    public function make_relative($base_uri);
    /**
     * Get the scheme part of the URI
     *
     * @return string|null
     */
    public function get_scheme();
    /**
     * Get the User-info (usually user:password) part
     *
     * @return string|null
     */
    public function get_user_info();
    /**
     * Get the URI host
     *
     * @return string|null
     */
    public function get_host();
    /**
     * Get the URI port
     *
     * @return int|null
     */
    public function get_port();
    /**
     * Get the URI path
     *
     * @return string|null
     */
    public function get_path();
    /**
     * Get the URI query
     *
     * @return string|null
     */
    public function get_query();
    /**
     * Return the query string as an associative array of key => value pairs
     *
     * This is an extension to RFC-3986 but is quite useful when working with
     * most common URI types
     *
     * @return array
     */
    public function get_query_as_array();
    /**
     * Get the URI fragment
     *
     * @return string|null
     */
    public function get_fragment();
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
     * @param  string $scheme
     * @throws Exception\InvalidUriPartException
     * @return Uri
     */
    public function set_scheme($scheme);
    /**
     * Set the URI User-info part (usually user:password)
     *
     * @param  string $userInfo
     * @return Uri
     * @throws Exception\InvalidUriPartException If the schema definition does not have this part.
     */
    public function set_user_info($user_info);
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
     * @param  string $host
     * @throws Exception\InvalidUriPartException
     * @return Uri
     */
    public function set_host($host);
    /**
     * Set the port part of the URI
     *
     * @param  int $port
     * @return Uri
     */
    public function set_port($port);
    /**
     * Set the path
     *
     * @param  string $path
     * @return Uri
     */
    public function set_path($path);
    /**
     * Set the query string
     *
     * If an array is provided, will encode this array of parameters into a
     * query string. Array values will be represented in the query string using
     * PHP's common square bracket notation.
     *
     * @param  string|array $query
     * @return Uri
     */
    public function set_query($query);
    /**
     * Set the URI fragment part
     *
     * @param  string $fragment
     * @return Uri
     * @throws Exception\InvalidUriPartException If the schema definition does not have this part.
     */
    public function set_fragment($fragment);
    /**
     * Magic method to convert the URI to a string
     *
     * @return string
     */
    public function __toString();
}
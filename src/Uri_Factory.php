<?php

declare (strict_types=1);
namespace Laminas\Uri;

use function is_string;
use function sprintf;
use function strtolower;
/**
 * URI Factory Class
 *
 * The URI factory can be used to generate URI objects from strings, using a
 * different URI subclass depending on the input URI scheme. New scheme-specific
 * classes can be registered using the registerScheme() method.
 *
 * Note that this class contains only static methods and should not be
 * instantiated
 */
// phpcs:ignore WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix
abstract class Uri_Factory
{
    /**
     * Registered scheme-specific classes
     *
     * @var array
     */
    protected static $scheme_classes = ['http' => Http::class, 'https' => Http::class, 'mailto' => Mailto::class, 'file' => File::class, 'urn' => Uri::class, 'tag' => Uri::class];
    /**
     * Register a scheme-specific class to be used
     *
     * @param string $scheme
     * @param string $class
     */
    public static function register_scheme($scheme, $class): void
    {
        $scheme = strtolower($scheme);
        static::$scheme_classes[$scheme] = $class;
    }
    /**
     * Unregister a scheme
     *
     * @param string $scheme
     */
    public static function unregister_scheme($scheme): void
    {
        $scheme = strtolower($scheme);
        if (isset(static::$scheme_classes[$scheme])) {
            unset(static::$scheme_classes[$scheme]);
        }
    }
    /**
     * Get the class name for a registered scheme
     *
     * If provided scheme is not registered, will return NULL
     *
     * @param  string $scheme
     * @return string|null
     */
    public static function get_registered_scheme_class($scheme)
    {
        if (!isset(static::$scheme_classes[$scheme])) {
            return null;
        }
        return static::$scheme_classes[$scheme];
    }
    /**
     * Create a URI from a string
     *
     * @param  string $uriString
     * @param  string $defaultScheme
     * @throws Exception\InvalidArgumentException
     * @return Uri
     */
    public static function factory($uri_string, $default_scheme = null)
    {
        if (!is_string($uri_string)) {
            throw new Exception\InvalidArgumentException(sprintf('Expecting a string, received "%s"', get_debug_type($uri_string)));
        }
        $uri = new Uri($uri_string);
        $scheme = strtolower($uri->get_scheme() ?? '');
        if (!$scheme && $default_scheme) {
            $scheme = $default_scheme;
        }
        if ($scheme && !isset(static::$scheme_classes[$scheme])) {
            throw new Exception\InvalidArgumentException(sprintf('no class registered for scheme "%s"', $scheme));
        }
        if ($scheme && isset(static::$scheme_classes[$scheme])) {
            $class = static::$scheme_classes[$scheme];
            $uri = new $class($uri);
            if (!$uri instanceof Uri_Interface) {
                throw new Exception\InvalidArgumentException(sprintf('class "%s" registered for scheme "%s" does not implement Laminas\Uri\UriInterface', $class, $scheme));
            }
        }
        return $uri;
    }
}
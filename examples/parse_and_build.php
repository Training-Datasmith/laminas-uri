<?php

declare(strict_types=1);

/**
 * Example: parsing and building URIs with laminas-uri.
 *
 * Run from the laminas-uri project root:
 *   php examples/parse_and_build.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Laminas\Uri\UriFactory;
use Laminas\Uri\Uri;
use Laminas\Uri\Http as HttpUri;

// --- Parse an HTTP URI ---
$uri = UriFactory::factory('https://user:pass@example.com:8080/path/to/page?foo=bar&baz=1#section');

echo "Scheme:   " . $uri->get_scheme()    . "\n";
echo "Host:     " . $uri->get_host()      . "\n";
echo "Port:     " . $uri->get_port()      . "\n";
echo "User:     " . $uri->get_user()      . "\n";
echo "Path:     " . $uri->get_path()      . "\n";
echo "Query:    " . $uri->get_query()     . "\n";
echo "Fragment: " . $uri->get_fragment()  . "\n";
echo "Valid:    " . ($uri->is_valid() ? 'yes' : 'no') . "\n\n";

// --- Modify URI components ---
$uri->set_path('/new/path');
$uri->set_query_parameters(['page' => '2', 'sort' => 'asc']);
echo "Modified URI: " . $uri->to_string() . "\n\n";

// --- Build from scratch ---
$built = new HttpUri();
$built->set_scheme('https');
$built->set_host('api.example.com');
$built->set_path('/v1/users');
$built->set_query('limit=10&offset=0');

echo "Built URI: " . $built->to_string() . "\n";
echo "Valid:     " . ($built->is_valid() ? 'yes' : 'no') . "\n\n";

// --- Relative URI resolution ---
$base = UriFactory::factory('https://example.com/base/page.html');
$relative = new Uri('../images/photo.jpg');
$resolved = $relative->resolve($base);
echo "Resolved: " . $resolved . "\n";

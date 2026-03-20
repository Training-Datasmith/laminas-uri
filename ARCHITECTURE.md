# Architecture: laminas-uri

## Purpose
A PHP URI manipulation and validation library. Parses, builds, and validates URIs (HTTP/HTTPS, file://, mailto:) with strict RFC 3986 compliance.

## Directory Structure
```
src/
  Uri.php             # Base URI class — parse, validate, build all URI components
  Uri_Interface.php   # Contract for all URI types
  Uri_Factory.php     # Creates the correct URI subclass based on scheme
  Http.php            # HTTP/HTTPS URI with host validation and port normalization
  File.php            # file:// URI
  Mailto.php          # mailto: URI
  Exception/
    Invalid_Uri_Exception.php
    Invalid_Uri_Part_Exception.php
```

## Key Design Decisions
- **Factory pattern** — `Uri_Factory` examines the scheme and returns the appropriate typed URI instance (`Http`, `File`, `Mailto`, or the base `Uri`).
- **Lazy validation** — URI components are stored as-set; `isValid()` performs the full validation only when called, allowing incremental construction.
- **Scheme-specific subclasses** — `Http` enforces HTTP-specific rules (valid port range, no `mailto`-style path); `Mailto` enforces email address format.

## Extension Points
- Extend `Uri` to add a custom URI scheme.
- Register the custom scheme in `Uri_Factory` for automatic dispatch.

## Dependency Flow
```
Uri_Factory::factory('https://example.com/path?q=1')
  └─ new Http('https://example.com/path?q=1')
       └─ parse_url() → components
            └─ isValid() → RFC 3986 checks

Uri::toString()
  └─ assemble scheme + authority + path + query + fragment
```

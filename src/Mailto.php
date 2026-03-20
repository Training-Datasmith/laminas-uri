<?php

declare (strict_types=1);
namespace Laminas\Uri;

use Laminas\Validator\Email_Address as EmailValidator;
use Laminas\Validator\Validator_Interface;
/**
 * "Mailto" URI handler
 *
 * The 'mailto:...' scheme is loosely defined in RFC-1738
 */
class Mailto extends Uri
{
    /** @var array<int,string> */
    protected static $valid_schemes = ['mailto'];
    /**
     * Validator for use when validating email address
     *
     * @var ValidatorInterface
     */
    protected $email_validator;
    /**
     * Check if the URI is a valid Mailto URI
     *
     * This applies additional specific validation rules beyond the ones
     * required by the generic URI syntax
     *
     * @see    Uri::isValid()
     *
     * @return bool
     */
    public function is_valid()
    {
        if ($this->host || $this->user_info || $this->port) {
            return false;
        }
        if (empty($this->path)) {
            return false;
        }
        if (str_starts_with($this->path, '/')) {
            return false;
        }
        $validator = $this->get_validator();
        return $validator->is_valid($this->path);
    }
    /**
     * Set the email address
     *
     * This is in fact equivalent to setPath() - but provides a more clear interface
     *
     * @param  string $email
     * @return Mailto
     */
    public function set_email($email)
    {
        return $this->set_path($email);
    }
    /**
     * Get the email address
     *
     * This is infact equivalent to getPath() - but provides a more clear interface
     *
     * @return string
     */
    public function get_email()
    {
        return $this->get_path();
    }
    /**
     * Set validator to use when validating email address
     */
    public function set_validator(Validator_Interface $validator): static
    {
        $this->email_validator = $validator;
        return $this;
    }
    /**
     * Retrieve validator for use with validating email address
     *
     * If none is currently set, an EmailValidator instance with default options
     * will be used.
     *
     * @return ValidatorInterface
     */
    public function get_validator()
    {
        if (null === $this->email_validator) {
            $this->set_validator(new Email_Validator());
        }
        return $this->email_validator;
    }
}
<?php

namespace Logstop;

use Monolog\Processor\ProcessorInterface;

class Processor implements ProcessorInterface
{
    private const FILTERED_STR = '[FILTERED]';
    private const FILTERED_URL_STR = '\1[FILTERED]\2';

    private const CREDIT_CARD_REGEX = '/\b[3456]\d{15}\b/';
    private const CREDIT_CARD_REGEX_DELIMITERS = '/\b[3456]\d{3}[\s+-]\d{4}[\s+-]\d{4}[\s+-]\d{4}\b/';
    private const EMAIL_REGEX = '/\b[\w]([\w+.-]|%2B)+(?:@|%40)[a-z\d-]+(?:\.[a-z\d-]+)*\.[a-z]+\b/i';
    private const IP_REGEX = '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/';
    private const PHONE_REGEX = '/\b(?:\+\d{1,2}\s)?\(?\d{3}\)?[\s+.-]\d{3}[\s+.-]\d{4}\b/';
    private const E164_PHONE_REGEX = '/(?:\+|%2B)[1-9]\d{6,14}\b/';
    private const SSN_REGEX = '/\b\d{3}[\s+-]\d{2}[\s+-]\d{4}\b/';
    private const URL_PASSWORD_REGEX = '/((?:\/\/|%2F%2F)\S+(?::|%3A))\S+(@|%40)/';
    private const MAC_REGEX = '/\b[0-9a-f]{2}(?:(?::|%3A)[0-9a-f]{2}){5}\b/i';

    // TODO make private in 0.2.0
    public $ip;
    public $mac;
    public $urlPassword;
    public $email;
    public $creditCard;
    public $phone;
    public $ssn;
    public $maxDepth;

    public function __construct($ip = false, $mac = false, $urlPassword = true, $email = true, $creditCard = true, $phone = true, $ssn = true, $maxDepth = 20)
    {
        $this->ip = $ip;
        $this->mac = $mac;
        $this->urlPassword = $urlPassword;
        $this->email = $email;
        $this->creditCard = $creditCard;
        $this->phone = $phone;
        $this->ssn = $ssn;

        if ($maxDepth <= 0) {
            throw new \InvalidArgumentException('maxDepth must be greater than 0');
        }
        $this->maxDepth = $maxDepth;
    }

    public function __invoke($record)
    {
        $message = $this->scrub($record->message);
        $context = $this->scrubContext($record->context);
        return $record->with(message: $message, context: $context);
    }

    private function scrub($message)
    {
        // order filters are applied is important
        if ($this->urlPassword) {
            $message = preg_replace(self::URL_PASSWORD_REGEX, self::FILTERED_URL_STR, $message);
        }

        if ($this->email) {
            $message = preg_replace(self::EMAIL_REGEX, self::FILTERED_STR, $message);
        }

        if ($this->creditCard) {
            $message = preg_replace(self::CREDIT_CARD_REGEX, self::FILTERED_STR, $message);
            $message = preg_replace(self::CREDIT_CARD_REGEX_DELIMITERS, self::FILTERED_STR, $message);
        }

        if ($this->phone) {
            $message = preg_replace(self::E164_PHONE_REGEX, self::FILTERED_STR, $message);
            $message = preg_replace(self::PHONE_REGEX, self::FILTERED_STR, $message);
        }

        if ($this->ssn) {
            $message = preg_replace(self::SSN_REGEX, self::FILTERED_STR, $message);
        }

        if ($this->ip) {
            $message = preg_replace(self::IP_REGEX, self::FILTERED_STR, $message);
        }

        if ($this->mac) {
            $message = preg_replace(self::MAC_REGEX, self::FILTERED_STR, $message);
        }

        return $message;
    }

    private function scrubContext($context, $depth = 1)
    {
        if ($depth > $this->maxDepth) {
            return '[logstop] Max depth exceeded';
        }

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->scrubContext($value, $depth + 1);
            } else {
                $context[$key] = $this->scrub($value);
            }
        }

        return $context;
    }
}

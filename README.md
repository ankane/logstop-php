# Logstop PHP

:fire: Keep personal data out of your logs

```php
$logger->info('Hi test@example.org!');
# Hi [FILTERED]!
```

By default, scrubs:

- email addresses
- phone numbers
- credit card numbers
- Social Security numbers (SSNs)
- passwords in URLs

Works with context as well

```php
$logger->info('Hi', ['email' => 'test@example.org']);
# Hi {"email":"[FILTERED]"}
```

Works even when sensitive data is URL-encoded with plus encoding

[![Build Status](https://github.com/ankane/logstop-php/actions/workflows/build.yml/badge.svg)](https://github.com/ankane/logstop-php/actions)

## Installation

Run:

```sh
composer require ankane/logstop
```

And add it to your [Monolog](https://github.com/Seldaek/monolog) logger:

```php
$logger->pushProcessor(new Logstop\Processor());
```

## Options

To scrub IP addresses (IPv4), use:

```php
new Logstop\Processor(ip: true);
```

To scrub MAC addresses, use:

```php
new Logstop\Processor(mac: true);
```

Disable default rules with:

```php
new Logstop\Processor(
    email: false,
    phone: false,
    creditCard: false,
    ssn: false,
    urlPassword: false
);
```

Change context limits with:

```php
new Logstop\Processor(
    maxDepth: 10,
    maxCount: 100
);
```

## Notes

- To scrub existing log files, check out [scrubadub](https://github.com/datascopeanalytics/scrubadub)
- To scan for unencrypted personal data in your database, check out [pdscan](https://github.com/ankane/pdscan)

## History

View the [changelog](CHANGELOG.md)

## Contributing

Everyone is encouraged to help improve this project. Here are a few ways you can help:

- [Report bugs](https://github.com/ankane/logstop-php/issues)
- Fix bugs and [submit pull requests](https://github.com/ankane/logstop-php/pulls)
- Write, clarify, or fix documentation
- Suggest or add new features

To get started with development:

```sh
git clone https://github.com/ankane/logstop-php.git
cd logstop-php
composer install
composer test
```

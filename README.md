TwitchPHP
====

A chat self-bot with an optional Helix API built with ReactPHP for the official [Twitch TV](https://www.twitch.tv) Internet Relay Chat (IRC) interface.

## Before you start

Before you start using this Library, you **need** to know how PHP works, you need to know the language and you need to know how Event Loops and Promises work. This is a fundamental requirement before you start. Without this knowledge, you will only suffer.

## FAQ

1. Can I run TwitchPHP on a webserver (e.g. Apache, nginx)?
    - No, TwitchPHP will only run in CLI. If you want to have an interface for your bot you can integrate [react/http](https://github.com/ReactPHP/http) with your bot and run it through CLI.
2. PHP is running out of memory?
	- Try increase your memory limit using `ini_set('memory_limit', '-1');`.

## Getting Started

### Requirements

- PHP 7.4.13
	- Technically the library can run on any PHP7 version or higher, however, no support will be given for any version lower than 7.4.13.
	- Despite the description above, this library is being built with PHP8 support in mind. There will come a time where PHP7 is no longer supported.
- Composer

### Windows and SSL

Unfortunately PHP on Windows does not have access to the Windows Certificate Store. This is an issue because TLS gets used and as such certificate verification gets applied (turning this off is **not** an option).

You will notice this issue by your script exiting immediately after one loop turn without any errors. Unfortunately there is for some reason no error or exception.

As such users of this library need to download a [Certificate Authority extract](https://curl.haxx.se/docs/caextract.html) from the cURL website.<br>
The path to the caextract must be set in the [`php.ini`](https://secure.php.net/manual/en/openssl.configuration.php) for `openssl.cafile`.

#### Recommended Extensions

- The latest PHP version.
- One of `ext-uv` (preferred), `ext-libev` or `evt-event` for a faster, and more performant event loop.
- `ext-mbstring` if handling non-english characters.

### Installing TwitchPHP

TwitchPHP is installed using [Composer](https://getcomposer.org).

1. Run `composer require VZGCoders/TwitchPHP`. This will install the lastest release.
2. Include the Composer autoload file at the top of your main file:
	- `include __DIR__.'/vendor/autoload.php';`
3. Make a bot!

## Configuration

1. Add the required "secret" and "nick" values.
2. Customize your commands and responses.

### Basic Example

[Link](run.php)

## Documentation

Raw documentation can be found in-line in the code.

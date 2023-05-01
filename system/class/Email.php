<?php

namespace Sunlight;

abstract class Email
{
    /**
     * Send an email
     *
     * @param string $to recipient
     * @param string $subject subject (automatically formatted for UTF-8)
     * @param string $message message content
     * @param array $headers associative array with headers
     */
    static function send(string $to, string $subject, string $message, array $headers = []): bool
    {
        // map defined headers
        $definedHeaderMap = [];

        foreach (array_keys($headers) as $headerName) {
            $definedHeaderMap[strtolower($headerName)] = true;
        }

        // add default headers
        if (Settings::get('mailerusefrom') && !isset($definedHeaderMap['from'])) {
            $headers['From'] = Settings::get('sysmail');
        }

        if (!isset($definedHeaderMap['content-type'])) {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        }

        if (!isset($definedHeaderMap['x-mailer'])) {
            $headers['X-Mailer'] = sprintf('PHP/%d', PHP_MAJOR_VERSION);
        }

        // extend
        $result = null;
        Extend::call('mail.send', [
            'to' => &$to,
            'subject' => &$subject,
            'message' => &$message,
            'headers' => &$headers,
            'result' => &$result,
        ]);

        if ($result !== null) {
            // handled by a plugin
            return $result;
        }

        // subject
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        // process headers
        $headerString = '';

        foreach ($headers as $headerName => $headerValue) {
            $headerString .= sprintf("%s: %s\n", $headerName, strtr($headerValue, ["\r" => '', "\n" => '']));
        }

        // send
        return @mail(
            $to,
            $subject,
            $message,
            $headerString
        );
    }

    /**
     * Define an e-mail sender header according to system settings
     */
    static function defineSender(array &$headers, string $sender, ?string $name = null): void
    {
        if (Settings::get('mailerusefrom')) {
            $headerName = 'From';
        } else {
            $headerName = 'Reply-To';
        }

        if ($name !== null) {
            $headerValue = sprintf('%s <%s>', $name, $sender);
        } else {
            $headerValue = $sender;
        }

        $headers[$headerName] = $headerValue;
    }

    /**
     * Validate an e-mail address
     *
     * @param bool $checkDns check DNS for the given domain (disabled in debug) 1/0
     */
    static function validate(string $email, bool $checkDns = true): bool
    {
        if (mb_strlen($email) > 255) {
            return false;
        }

        $atIndex = mb_strrpos($email, '@');

        if ($atIndex === false) {
            return false;
        }

        $domain = mb_substr($email, $atIndex + 1);
        $local = mb_substr($email, 0, $atIndex);
        $localLen = mb_strlen($local);
        $domainLen = mb_strlen($domain);

        if ($localLen < 1 || $localLen > 64) {
            return false; // local part length exceeded
        }

        if ($domainLen < 1 || $domainLen > 255) {
            return false; // domain part length exceeded
        }

        if ($local[0] == '.' || $local[$localLen - 1] == '.') {
            return false; // local part starts or ends with '.'
        }

        if (preg_match('{\\.\\.}', $local)) {
            return false; // local part has two consecutive dots
        }

        if (!preg_match('{[A-Za-z0-9\\-\\.]+$}AD', $domain)) {
            return false; // character not valid in domain part
        }

        if (preg_match('{\\.\\.}', $domain)) {
            return false; // domain part has two consecutive dots
        }

        if (!preg_match('{[A-Za-z0-9\\-\\._]+$}AD', $local)) {
            return false; // character not valid in local part
        }

        if ($checkDns && !self::checkDns($domain)) {
            return false; // no DNS record for the given domain
        }

        return true;
    }

    /**
     * Render a mailto link with some basic anti-spam protection
     */
    static function link(string $email): string
    {
        if (Settings::get('atreplace') !== '') {
            $email = str_replace('@', Settings::get('atreplace'), $email);
        }

        return '<a href="#" onclick="return Sunlight.mai_lto(this);">' . _e($email) . '</a>';
    }

    private static function checkDns(string $domain): bool
    {
        if (Core::$debug || !function_exists('checkdnsrr')) {
            return true;
        }

        return @checkdnsrr($domain . '.');
    }
}

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
     */
    static function validate(string $email): bool
    {
        $isValid = true;
        $atIndex = mb_strrpos($email, '@');
        if (mb_strlen($email) > 255) {
            $isValid = false;
        } elseif (is_bool($atIndex) && !$atIndex) {
            $isValid = false;
        } else {
            $domain = mb_substr($email, $atIndex + 1);
            $local = mb_substr($email, 0, $atIndex);
            $localLen = mb_strlen($local);
            $domainLen = mb_strlen($domain);
            if ($localLen < 1 || $localLen > 64) {
                // local part length exceeded
                $isValid = false;
            } elseif ($domainLen < 1 || $domainLen > 255) {
                // domain part length exceeded
                $isValid = false;
            } elseif ($local[0] == '.' || $local[$localLen - 1] == '.') {
                // local part starts or ends with '.'
                $isValid = false;
            } elseif (preg_match('{\\.\\.}', $local)) {
                // local part has two consecutive dots
                $isValid = false;
            } elseif (!preg_match('{[A-Za-z0-9\\-\\.]+$}AD', $domain)) {
                // character not valid in domain part
                $isValid = false;
            } elseif (preg_match('{\\.\\.}', $domain)) {
                // domain part has two consecutive dots
                $isValid = false;
            } elseif (!preg_match('{[A-Za-z0-9\\-\\._]+$}AD', $local)) {
                // character not valid in local part
                $isValid = false;
            }
            if (!Core::$debug && function_exists('checkdnsrr') && $isValid && !checkdnsrr($domain . '.', 'ANY')) {
                // no DNS record for the given domain
                $isValid = false;
            }
        }

        return $isValid;
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
}

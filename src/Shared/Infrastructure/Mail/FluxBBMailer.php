<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Mail;

use Psr\Log\LoggerInterface;

/**
 * Production mailer — sends emails via Symfony Mailer (when available)
 * with native PHP mail() fallback.
 *
 * @see https://symfony.com/doc/current/mailer.html
 */
class FluxBBMailer
{
    private const string DSN_PATTERN = '/^(smtp|sendmail|native|mail):\/\//';

    public function __construct(
        private readonly string $dsn,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Send an email.
     *
     * @param string $to Recipient email
     * @param string $subject Subject (will be prefixed with board name)
     * @param string $body HTML body
     * @param string $altBody Plain-text alternative
     * @return bool True if sent successfully
     */
    public function send(string $to, string $subject, string $body, string $altBody = ''): bool
    {
        // Attempt Symfony Mailer if class exists
        if (class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            return $this->sendSymfony($to, $subject, $body, $altBody);
        }

        // Fallback to native mail()
        return $this->sendNative($to, $subject, $body, $altBody);
    }

    private function sendSymfony(string $to, string $subject, string $body, string $altBody): bool
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            return $this->sendNative($to, $subject, $body, $altBody);
        }

        try {
            /** @psalm-suppress UndefinedClass, MixedMethodCall */
            $transport = \Symfony\Component\Mailer\Transport::fromDsn($this->dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            /** @psalm-suppress UndefinedClass, MixedMethodCall */
            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($this->fromAddress, $this->fromName))
                ->to($to)
                ->subject($subject)
                ->html($body);

            if ($altBody !== '') {
                $email->text($altBody);
            }

            $mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('Mailer failed: ' . $e->getMessage());
            // Fallback to native on failure
            return $this->sendNative($to, $subject, $body, $altBody);
        }
    }

    private function sendNative(string $to, string $subject, string $body, string $altBody): bool
    {
        $headers = [
            'From' => sprintf('%s <%s>', $this->fromName, $this->fromAddress),
            'MIME-Version' => '1.0',
            'Content-Type' => $altBody !== ''
                ? 'multipart/alternative; boundary="fluxbb-alt-boundary"'
                : 'text/html; charset="UTF-8"',
        ];

        if ($altBody !== '') {
            $body = sprintf(
                "--fluxbb-alt-boundary\r\nContent-Type: text/plain; charset=\"UTF-8\"\r\n\r\n%s\r\n"
                . "--fluxbb-alt-boundary\r\nContent-Type: text/html; charset=\"UTF-8\"\r\n\r\n%s\r\n"
                . "--fluxbb-alt-boundary--",
                $altBody,
                $body
            );
        }

        $headerStr = '';
        foreach ($headers as $name => $value) {
            $headerStr .= "$name: $value\r\n";
        }

        $result = mail($to, sprintf('=?UTF-8?B?%s?=', base64_encode($subject)), $body, $headerStr);
        if (!$result) {
            $this->logger?->error('Native mail() failed to send to ' . $to);
        }
        return $result;
    }
}
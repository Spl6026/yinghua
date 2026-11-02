<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use PHPMailer\PHPMailer\PHPMailer;
use Yinghua\Config;
use Yinghua\Contracts\Mailer;

/** SMTP 套件只在這個 Adapter 內使用；本機開發時信件一律送到 Mailpit。 */
class PhpMailerAdapter implements Mailer
{
    public function __construct(private readonly Config $config) {}

    public function send(string $recipient, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->config->string('SMTP_HOST', 'mailpit');
        $mail->Port = $this->config->integer('SMTP_PORT', 1025);
        $mail->SMTPAuth = $this->config->string('SMTP_USERNAME') !== '';
        $mail->Username = $this->config->string('SMTP_USERNAME');
        $mail->Password = $this->config->string('SMTP_PASSWORD');
        $encryption = $this->config->string('SMTP_ENCRYPTION', 'none');
        $mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        $mail->SMTPAutoTLS = $encryption !== 'none';
        $mail->Timeout = 5;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            $this->config->string('MAIL_FROM_ADDRESS', 'no-reply@example.test'),
            $this->config->string('MAIL_FROM_NAME', '映畫竹塹'),
        );
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    }
}

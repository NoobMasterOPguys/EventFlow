<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BrevoTransactionalMailer
{
    public function sendPasswordReset(User $user, string $token): void
    {
        $apiKey = config('services.brevo.api_key');
        $senderEmail = config('services.brevo.sender_email');
        $senderName = config('services.brevo.sender_name', 'EventFlow');

        if (
            ! is_string($apiKey) || trim($apiKey) === '' ||
            ! is_string($senderEmail) || trim($senderEmail) === ''
        ) {
            throw new RuntimeException(
                'Brevo API configuration is incomplete.'
            );
        }

        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]);

        $name = trim((string) $user->name);
        $greeting = $name !== ''
            ? 'Hello ' . e($name) . ','
            : 'Hello,';

        $safeUrl = e($resetUrl);

        $htmlContent = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reset your EventFlow password</title>
        </head>
        <body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 30px;">
            <div style="max-width: 560px; margin: auto; background: #ffffff; padding: 32px; border-radius: 10px;">
                <h1 style="color: #54202a;">EventFlow</h1>

                <h2>Password Reset Request</h2>

                <p>{$greeting}</p>

                <p>We received a request to reset your EventFlow account password.</p>

                <p>Click the button below to choose a new password:</p>

                <p style="margin: 30px 0;">
                    <a href="{$safeUrl}"
                       style="background: #54202a; color: #ffffff; padding: 14px 24px; text-decoration: none; border-radius: 6px;">
                        Reset My Password
                    </a>
                </p>

                <p>If the button does not work, copy this link into your browser:</p>

                <p style="word-break: break-all;">{$safeUrl}</p>

                <p>If you did not request a password reset, you can ignore this email.</p>

                <p style="color: #777777; font-size: 13px;">
                    For security reasons, this link expires according to EventFlow's password-reset policy.
                </p>

                <hr>

                <p style="color: #777777; font-size: 12px;">
                    This is an automated message from EventFlow. Please do not reply.
                </p>
            </div>
        </body>
        </html>
        HTML;

        $textContent = "Hello {$name},\n\n"
            . "We received a request to reset your EventFlow password.\n\n"
            . "Use this link to reset your password:\n"
            . $resetUrl . "\n\n"
            . "If you did not request this, you can ignore this email.";

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([
                    'api-key' => $apiKey,
                ])
                ->connectTimeout(5)
                ->timeout(10)
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $senderName,
                        'email' => $senderEmail,
                    ],
                    'to' => [
                        [
                            'email' => $user->getEmailForPasswordReset(),
                            'name' => $name,
                        ],
                    ],
                    'subject' => 'Reset Your EventFlow Password',
                    'htmlContent' => $htmlContent,
                    'textContent' => $textContent,
                ]);
        } catch (Throwable $exception) {
            Log::error('Brevo password reset request failed.', [
                'user_id' => $user->getKey(),
                'exception_class' => get_class($exception),
            ]);

            throw new RuntimeException(
                'Unable to send the password reset email.',
                0,
                $exception
            );
        }

        if (! $response->successful()) {
            Log::error('Brevo rejected a password reset email.', [
                'user_id' => $user->getKey(),
                'http_status' => $response->status(),
            ]);

            throw new RuntimeException(
                'Brevo rejected the password reset email request.'
            );
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a plain test email so SMTP settings can be verified after configuring
 * them in .env. Reports the active mailer and a clear pass/fail.
 */
class MailTestCommand extends Command
{
    protected $signature = 'mail:test {to : Recipient email address}';

    protected $description = 'Send a test email to verify the mail (SMTP) configuration';

    public function handle(): int
    {
        $to = $this->argument('to');
        $mailer = config('mail.default');
        $from = config('mail.from.address');

        $this->info("Mailer: {$mailer}  |  From: ".($from ?: '(not set!)')."  |  To: {$to}");

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER is still "log" — the email will be written to the app log, not actually sent.');
        }
        if (empty($from)) {
            $this->error('MAIL_FROM_ADDRESS is empty — set it in .env, then run `php artisan config:clear`.');

            return self::FAILURE;
        }

        try {
            Mail::raw('This is a test email from '.config('app.name').'. If you received it, your SMTP settings work.',
                fn ($m) => $m->to($to)->subject(config('app.name').' — mail test'));
        } catch (\Throwable $e) {
            $this->error('Send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent without error.'.($mailer === 'log' ? ' (Check storage/logs or the container log.)' : ' Check the inbox (and spam).'));

        return self::SUCCESS;
    }
}

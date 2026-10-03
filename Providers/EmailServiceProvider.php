<?php

namespace Extensions\Servers\Virtfusion\Providers;

use Illuminate\Support\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Register the email templates of this extension so admins can edit them under Emails.
     */
    public function boot(): void
    {
        config(['email_events' => array_merge(config('email_events', []), [
            'server.virtfusion.account_created' => [
                'name' => 'VirtFusion account created',
                'group' => 'Servers',
                'description' => 'Sent when a VirtFusion panel account is created for an order.',
                'subject' => 'Panel Account Created',
                'body' => <<<'BODY'
Your account has been created on the VPS panel.
You can login using the following details:
Email: {{panel_email}}
Password: {{panel_password}}
BODY,
                'button_text' => 'VPS Panel',
                'placeholders' => [
                    'panel_email' => 'Panel login email',
                    'panel_password' => 'Panel login password',
                ],
            ],
        ])]);
    }
}

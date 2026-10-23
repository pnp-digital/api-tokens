# generic-mail

### Adds a generic mail templating facility to laravel projects with logging and one-line email sending.

#### Adding the package to your laravel project

1. Add the following to `composer.json`:
    ```json
        "repositories": [
            {
                "type": "vcs",
                "url":  "git@github.com:pnp-digital/generic-mail.git",
                "no-api": true
            }
        ],
    ```
2. Ensure you have a public key added which can access this SSH repository
3. Issue a composer require command: ```composer require pnp-digital/generic-mail:dev-main```

### The GenericMailable class

All mail templates you send must inherit from the `GenericMailable` class, which itself derives from
`Illuminate\Mail\Mailable`. The `GenericMailable` class holds details of the sender address, subject,
which view blade view renders its content, and any attachments. As a bare minimum you should override
at least the `envelope()`, `emailView`, `VARIABLES` and `NAME` values. This class __must__ be under the
`App\Mail` namespace:

```php
<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Envelope;
use Pipeandpiper\Mail\Mail\GenericMailable;

class NewUserInvite extends GenericMailable
{
    protected static string $emailView = 'email_templates.new_user_invite';
    public const VARIABLES = [
        'user_name',
        'url',
    ];

    public const NAME = "New User Created";

    /**
     * @inheritDoc
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome To Homera!',
        );
    }
}
```

### Mailable views

Once you have implemented a mailable class you must also then implement the view connected to the mailable. To aid in
rapid development you can extend the base `generic-mailable::base` view provided by the package, and implement the
`content` section:

```html
@extends('generic-mailable::base')

@section('content')

    <div class="info">
        {{ $user_name }},
        <br /><br />
        Welcome to Homera! Please download the app below. This email is under construction.
        <br /><br />
    </div>

    <a href="{{ $url }}" style="text-decoration: none">
        <img src="@embed_image('apple.png')" height="auto" width="300" />
    </a>
    <a href="{{ $url }}" style="text-decoration: none">
        <img src="@embed_image('android.png')" height="auto" width="300" />
    </a>

    <div class="sincerely">Sincerely, The Homera Team</div>

@endsection
```

### Sending the mail

To send mail, you must add the `MailTemplateTrait` to whatever class you wish to send email from. This trait can be
attached to any laravel class such as a model or controller. You will now have two new methods:

```php
    /**
     * Return an array mapping GenericMailable derived classes in the App\Mail namespace to template names.
     *
     * @return array
     */
    public function getMailables(): array;
```

This function returns an array of all possible mailables keyed by the "slug" name. The slug name is the name of the view.

```php
    /**
     * API endpoint to send an email given its slug name and a list of replacement variables.
     * Replacement variables and slug name are validated before calling any blade view rendering on it.
     *
     * @param string $toAddress
     * @param array|null $ccAddresses
     * @param string $slug email template slug name
     * @param array $replacements
     * @return ?string error message on failure, null on success
     */
    public function sendEmail(string $toAddress, ?array $ccAddresses, string $slug, array $replacements): ?string;
```

Use this method to send email. The slug must be an existing slug which has a mailable class and a view, the replacements
array must have the exact keys specified by the view. If there are more, or less keys than specified in the view, or any
keys are missing, the email will not send and an error will be returned.

### The embed_image blade directive and helper

This package implements both a global helper function called `embed_image` and a blade directive called `@embed_image`:

```php
    /**
     * Given a file which resides in resources/img, encode it as base64 and prefix it
     * so that it can be used in an img tag or similar within an email, without having to
     * worry about attaching it as an attachment.
     *
     * @param string $filename Filename to embed
     * @return string base64 encoded image file
     */
    function embed_image(string $filename): string;
```

You can use this helper/blade directive whenever you want to embed any image from the resources directory as a base64
blob, rather than having to add it as an attachment.

### Integrating with a CSS inliner
You may optionally install `fedeisas/laravel-mail-css-inliner` into your laravel project alongside this package and they
will operate together correctly. `laravel-mail-css-inliner` will take configured CSS files and embed them inline into sent
emails sent via mailable, turning css rules into explicit `style` attributes on the HTML tags. This is especially important
in many older email clients such as Outlook. An example configuration in `config/css-inliner.php` might be:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Css Files
    |--------------------------------------------------------------------------
    |
    | Css file of your style for your emails
    | The content of these files will be added directly into the inliner
    | Use absolute paths, ie. public_path('css/main.css')
    |
    */

    'css-files' => [
        resource_path('css/email.css')
    ],

];
```

### Configuring logging

This package will attempt to log all email activity to a separate log channel called `mailables`. You can configure this
log channel, for example to output to a file in the `storage/logs` folder by configuring an entry in the `config/logging.php`
file as follows:

```php
        'mailables' => [
            'driver' => 'single',
            'path' => storage_path('logs/mail.log'),
        ],
```

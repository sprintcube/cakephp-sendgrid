# SendGrid Plugin for CakePHP

[![CI](https://github.com/sprintcube/cakephp-sendgrid/workflows/CI/badge.svg?branch=master)](https://github.com/sprintcube/cakephp-sendgrid/actions)
[![codecov](https://codecov.io/gh/sprintcube/cakephp-sendgrid/branch/master/graph/badge.svg)](https://codecov.io/gh/sprintcube/cakephp-sendgrid)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Latest Stable Version](https://poser.pugx.org/sprintcube/cakephp-sendgrid/v/stable)](https://packagist.org/packages/sprintcube/cakephp-sendgrid)
[![Total Downloads](https://poser.pugx.org/sprintcube/cakephp-sendgrid/downloads)](https://packagist.org/packages/sprintcube/cakephp-sendgrid)

This plugin provides email delivery using [SendGrid](https://sendgrid.com/).

This branch is for use with CakePHP 5.0+. For CakePHP 4, please use cake-4.x branch.

## Requirements

This plugin has the following requirements:

* CakePHP 5.0 or greater.
* PHP 7.2 or greater.

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

```
composer require sprintcube/cakephp-sendgrid
```

After installation, [Load the plugin](http://book.cakephp.org/3.0/en/plugins.html#loading-a-plugin)
```php
Plugin::load('SendGrid');
```
Or, you can load the plugin using the shell command
```sh
$ bin/cake plugin load SendGrid
```

## Setup

Set your SendGrid Api key in `EmailTransport` settings in app.php

```php
'EmailTransport' => [
...
  'sendgrid' => [
      'className' => 'SendGrid.SendGrid',
      'apiKey' => 'your-api-key' // your api key
  ]
]
```
And create new delivery profile in `Email` settings.

```php
'Email' => [
    'default' => [
        'transport' => 'default',
        'from' => 'you@localhost',
        //'charset' => 'utf-8',
        //'headerCharset' => 'utf-8',
    ],
    'sendgrid' => [
        'transport' => 'sendgrid'
    ]
]
```

## Usage

You can now simply use the CakePHP `Email` to send an email via SendGrid.

```php
$email = new SendGridMailer();
$email->setFrom(['you@yourdomain.com' => 'CakePHP SendGrid'])
    ->setTo('foo@example.com.com')
    ->addTo('bar@example.com')
    ->addCc('john@example.com')
    ->setSubject('Email from CakePHP SendGrid plugin')
    ->deliver('Message from CakePHP SendGrid plugin');
```

That is it.

## Advance Use
You can also use few more options to send email via SendGrid APIs. To do so, just call the appropriate methods before sending the email.

### Custom Headers
You can pass your own headers. It must be prefixed with "X-". Use the default `Email::setHeaders` method like,

```php
$email = new SendGridMailer();
$email->setFrom(['you@yourdomain.com' => 'CakePHP SendGrid'])
    ->setTo('foo@example.com.com')
    ->setHeaders([
        'X-Custom' => 'headervalue',
        'X-MyHeader' => 'myvalue'
    ])
    ->setSubject('Email from CakePHP SendGrid plugin')
    ->deliver('Message from CakePHP SendGrid plugin');
```

> When sending request, `X-` will be removed from header name e.g. X-MyHeader will become MyHeader

### Attachments
Set your attachments using `Email::setAttachments` method.

```php
$email = new SendGridMailer();
$email->setFrom(['you@yourdomain.com' => 'CakePHP SendGrid'])
    ->setTo('foo@example.com.com')
    ->setSubject('Email from CakePHP SendGrid plugin')
    ->setAttachments([
        'cake_icon1.png' => Configure::read('App.imageBaseUrl') . 'cake.icon.png',
        'cake_icon2.png' => ['file' => Configure::read('App.imageBaseUrl') . 'cake.icon.png'],
        WWW_ROOT . 'favicon.ico'
    ])
    ->deliver('Message from CakePHP SendGrid plugin');
```

> To send inline attachment, use `contentId` parameter while setting attachment.

### Template
You can use the template created in SendGrid backend. Get the template id by either using their API or from the URL.
Set the template id using `setTemplate` method.

```php
$email = new SendGridMailer();
$email->setTo('foo@example.com.com')
    ->setTemplate('d-xxxxxx')
    ->deliver();
```

### Schedule
You can schedule the email to be sent in future date. You can set upto 72 hours in future as per SendGrid documentation. You need to pass a unix timestamp value.

```php
$email = new SendGridMailer();
$email->setTo('foo@example.com.com')
    ->setSendAt(1649500630)
    ->deliver();
```
## Webhooks
You can receive status events from SendGrid. This allows you ensure that SendGrid was able to send the email recording bounces etc. 

### Webhook Config
You will require a Table in the database to record the emails sent. You can use the lorenzo/cakephp-email-queue plugin to queue the emails and in that case you would 
use the email_queue table. However you can create your own table/Model as long as it has at least three columns. They can be called anything but they must have the correct types.

When you send the email the deliver function will return an array with a 'messageId' element if it successfully connected to SendGrid. This needs to be recorded in the status_id field.

* status_id VARCHAR(100)
* status VARCHAR(100)
* status_message TEXT

You need to map this table and these fields in you app_local.php config

```php

    'sendgridWebhook' => [
        'tableClass' => 'EmailQueue', // The table name that stores email data
        'uniqueIdField' => 'status_id', // The field name that stores the unique message ID VARCHAR(100)
        'statusField' => 'status', // The field name that stores the status of the email status VARCHAR(100)
        'statusMessageField' => 'status_message', // The field name that stores the status messages TEXT
        'debug' => 'true', // write incoming requests to debug log
        'secure' => 'true', // enable SendGrid signed webhook security. You should enable this in production
        'verification_key' => '<YOUR VERIFICATION KEY>', // The verification key from SendGrid
    ],

```

You will need to login to your SendGrid Account and configure your domain and the events that you want to track

 https://app.sendgrid.com/settings/mail_settings/webhook_settings

The return url needs to be set to 
* https://YOUR DOMAIN/send-grid/webhook


The CSRF protection middleware needs to allow posts to the webhooks controller in Application.php
Remove the current CSRF protection middleware and replace it with the following. If you already have CSRF exceptions then add the Webhooks one
  
  ```php
    $csrf = new CsrfProtectionMiddleware();

    $csrf->skipCheckCallback(function ($request) {
           // Skip token check for API URLs.
          if ($request->getParam('controller') === 'Webhooks') {
             return true;
            }
    });
 
      // Ensure routing middleware is added to the queue before CSRF protection middleware.
    $middlewareQueue->add($csrf);
 
    return $middlewareQueue;
  
  ```

If the authentication plugin (https://book.cakephp.org/authentication/3/en/index.html) is used for authentication the webhook action should work OK. If you have a different authentication method then you will need to add an exception for the webhook action. /send-grid/webhooks/index 

#### Webhook Signature Verification
SendGrid allows you to sign the webhook requests. This is a good idea in production to keep the webhook secure. You will need to enable this in your SendGrid account and then set secure to true and add your verification key to your app_local.php config file.

https://docs.sendgrid.com/for-developers/tracking-events/getting-started-event-webhook-security-features. Enable signed event webhook and follow the instructions to get the verification key.

## Reporting Issues

If you have a problem with this plugin or any bug, please open an issue on [GitHub](https://github.com/sprintcube/cakephp-sendgrid/issues).

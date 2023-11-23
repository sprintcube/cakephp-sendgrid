<?php

declare(strict_types=1);

// Docs
//https://docs.sendgrid.com/for-developers/tracking-events/event
//Config
//https://app.sendgrid.com/settings/mail_settings

/**
 * 
 * The CSRF protection middleware needs to allow posts to the webhooks controller
 * in Application.php
 * 
 * Remove the current CSRF protection middleware and replace it with the following. If you already have CSRF exceptions then add the Webhooks one
 * 
 *    $csrf = new CsrfProtectionMiddleware();
 *
 *      
 *    $csrf->skipCheckCallback(function ($request) {
 *          // Skip token check for API URLs.
 *          Log::write('debug', json_encode($request->getParam('controller')));
 *         if ($request->getParam('controller') === 'Webhooks') {
 *            return true;
 *           }
 *    });
 *
 *     // Ensure routing middleware is added to the queue before CSRF protection middleware.
 *    $middlewareQueue->add($csrf);
 *
 *   return $middlewareQueue;
 * 
 * test curl -X POST http://localhost:8765/send-grid/webhooks -H 'Content-Type: application/json' -d '{"login":"my_login","password":"my_password"}'
 */

namespace SendGrid\Controller;

use SendGrid\Controller\AppController;
use Cake\Log\Engine\FileLog;


class WebHooksController extends AppController

{
    public function index()
    {
        //debug($this->request->getData());
        $this->viewBuilder()->setLayout('ajax');

        // Log the incoming data
        $this->log(json_encode($this->request->getParam('controller')), 'debug');
    }
}

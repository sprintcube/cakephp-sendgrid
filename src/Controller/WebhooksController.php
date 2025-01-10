<?php

declare(strict_types=1);

// Docs
//https://docs.sendgrid.com/for-developers/tracking-events/event
//Config
//https://app.sendgrid.com/settings/mail_settings
//Key Verification
//https://docs.sendgrid.com/for-developers/tracking-events/event#security-features

/**
 * test 
 * curl -X POST http://localhost:8765/send-grid/webhooks -H 'Content-Type: application/json' -d '[{"timestamp": 1700762652,  "event": "processed", "sg_message_id": "14c5d75ce93.dfd.64b469.filter0001.16648.5515E0B88.0"}]'
 *
 * security test 
 * curl -X POST http://localhost:8765/send-grid/webhooks -H 'Content-Type: application/json' -H 'X-Twilio-Email-Event-Webhook-Signature: MFk..........2mg==' -d '[{"timestamp": 1700762652,  "event": "processed", "sg_message_id": "14c5d75ce93.dfd.64b469.filter0001.16648.5515E0B88.0"}]'
 * 
 * 
 */

/**
 * SendGrid Plugin for CakePHP
 * Copyright (c) SprintCube (https://www.sprintcube.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.md
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) SprintCube (https://www.sprintcube.com)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 * @link      https://github.com/sprintcube/cakephp-sendgrid
 * @since     5.0.0
 */

namespace SendGrid\Controller;

use SendGrid\Controller\AppController;

use Cake\View\JsonView;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use SendGrid\Util\EllipticCurve\Ecdsa;
use SendGrid\Util\EllipticCurve\PublicKey;
use SendGrid\Util\EllipticCurve\Signature;

class WebHooksController extends AppController
{

    const SIGNATURE = "X-Twilio-Email-Event-Webhook-Signature";
    const TIMESTAMP = "X-Twilio-Email-Event-Webhook-Timestamp";
    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
        // If the authentication plugin is loaded then open up the index action
        if (isset($this->Authentication)) {
            $this->Authentication->allowUnauthenticated(['index']);
        }
    }

    public function viewClasses(): array
    {
        return [JsonView::class];
    }


    public function index()
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName("Json");
        $result = $this->request->getData();

        $this->set('result', $result);
        $config = Configure::read('sendgridWebhook');

        if (isset($config['debug']) && $config['debug'] == 'true') {
            Log::debug(json_encode($result));
            Log::debug(json_encode($this->request->getHeaders()));
        }

        if (isset($config['secure']) && $config['secure'] == 'true') {
            $this->request->getBody()->rewind();
            $payload = $this->request->getBody()->getContents();
            // Log::debug($payload);

            if (!isset($config['verification_key'])) {
                if (isset($config['debug']) && $config['debug'] == 'true') {
                    Log::debug("Verfication Failed: Webhook Signature verification key not set in app_local.php");
                }
                $this->set('error', "Invalid Signature");
                $this->viewBuilder()->setOption('serialize', "error");
                return;
            }

            $publicKey = PublicKey::fromString($config['verification_key']);

            $timestampedPayload = $this->request->getHeaderLine($this::TIMESTAMP) . $payload;
            $decodedSignature = Signature::fromBase64($this->request->getHeaderLine($this::SIGNATURE));

            if (!Ecdsa::verify($timestampedPayload, $decodedSignature, $publicKey)) {
                if (isset($config['debug']) && $config['debug'] == 'true') {
                    Log::debug("Verfication Failed: Webhook Signature does not verify against the verification key");
                }
                $this->set('error', "Invalid Signature");
                $this->viewBuilder()->setOption('serialize', "error");
                return;
            }
        }

        $emailTable = $this->getTableLocator()->get($config['tableClass']);
        $count = 0;
        foreach ($result as $event) {
            $message_id = explode(".", $event['sg_message_id'])[0];
            $email_record = $emailTable->find('all')->select([
                "id",
                $config['uniqueIdField'],
                $config['statusField'],
                $config['statusMessageField'],
            ])->where([$config['uniqueIdField'] => $message_id])->first();
            if ($email_record) {
                $count++;
                $email_record->set($config['statusMessageField'], $email_record->get($config['statusMessageField']) . (new DateTime())->format('d/m/Y H:i:s') . ": " . $event['event'] . " " .
                    (isset($event['response']) ? $event['response'] : "") . " " .
                    (isset($event['reason']) ? $event['reason'] : "<br>"));
                $email_record->set($config['statusField'], $event['event']);
                $emailTable->save($email_record);
            }
        }
        if (isset($config['debug']) && $config['debug'] == 'true') {
            Log::debug("Updated $count Email records");
        }
        $this->set('OK', "OK");
        $this->viewBuilder()->setOption('serialize', "OK");
    }
}

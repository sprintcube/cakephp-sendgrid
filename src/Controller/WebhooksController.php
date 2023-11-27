<?php

declare(strict_types=1);

// Docs
//https://docs.sendgrid.com/for-developers/tracking-events/event
//Config
//https://app.sendgrid.com/settings/mail_settings

/**
 * test 
 * 
 curl -X POST http://localhost:8765/send-grid/webhooks -H 'Content-Type: application/json' -d '{"timestamp": 1700762652,  "event": "processed", "sg_message_id": "14c5d75ce93.dfd.64b469.filter0001.16648.5515E0B88.0"}'
 */

namespace SendGrid\Controller;

use SendGrid\Controller\AppController;

use Cake\View\JsonView;
use Cake\Core\Configure;
use Cake\I18n\DateTime;

use Cake\Log\Log;

class WebHooksController extends AppController

{

    public function viewClasses(): array
    {
        return [JsonView::class];
    }


    public function index()
    {

        $this->viewBuilder()->setClassName("Json");

        $result = $this->request->getData();
        $this->set('result', $result);

        $config = Configure::read('sendgridWebhook');

        if (isset($config['debug']) && $config['debug'] == 'true') {
            Log::debug(json_encode($result));
        }

        $emailTable = $this->getTableLocator()->get($config['tableClass']);

        foreach ($result as $event) {
            $message_id = explode(".", $event['sg_message_id'])[0];
            $email_record = $emailTable->find('all')->select(["id",
                $config['uniqueIdField'],
                $config['statusField'],
                $config['statusMessageField'],
            ])->where([$config['uniqueIdField'] => $message_id])->first();
            if ($email_record) {
                $email_record->set($config['statusMessageField'],$email_record->get($config['statusMessageField']). "<br>" . (new DateTime())->format('d/m/Y H:i:s') . ": " . $event['event'] . " " . 
                (isset($event['response'])? $event['response']:""). " " . 
                (isset($event['reason'])? $event['reason']:""));
                $email_record->set($config['statusField'],$event['event']);
                $emailTable->save($email_record);
            }
        }
        $ok = "OK";
        $this->viewBuilder()->setOption('serialize', $ok);
    }
}

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
use Cake\Log\Engine\FileLog;
use Cake\View\JsonView;
use Cake\Core\Configure;
use Cake\I18n\DateTime;

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
       $this->set('result',$result);

       $config = Configure::read('sendgridWebhook');

     $emailTable = $this->getTableLocator()->get($config['tableClass']);        

        $email_record = $emailTable->find('all')->contain([
            $config['uniqueIdField'],
            $config['statusField'],
            $config['statusMessageField'],
        ])->where([$config['uniqueIdField']=>$result->sg_message_id])->first();

        $email_record->$config['statusMessageField'] .= "<br>".(new DateTime())->format('d/m/Y H:i:s').":".$result->event." ".$result->response??" ".$result->reason??" ";
        $email_record->$config['statusField'] = $result->event;
        $emailTable->save($email_record);

        $ok = "OK";
        $this->viewBuilder()->setOption('serialize', $ok);
    }
}

<?php
/**
 * SendGrid Plugin for CakePHP 3
 * Copyright (c) SprintCube (https://www.sprintcube.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.md
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) SprintCube (https://www.sprintcube.com)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 * @link      https://github.com/sprintcube/cakephp-sprintcube-email
 * @since     1.0.0
 */

namespace SendGrid\Mailer\Transport;

use Cake\Http\Client;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Email;
use SendGrid\Mailer\Exception\SendGridApiException;

/**
 * Send mail using SendGrid API
 */
class SendGridTransport extends AbstractTransport
{

    /**
     * Default config for this class
     *
     * @var array
     */
    protected $_defaultConfig = [
        'apiEndpoint' => 'https://api.sendgrid.com/v3',
        'apiKey' => ''
    ];

    /**
     * API request parameters
     *
     * @var array
     */
    protected $_reqParams = [];

    /**
     * API Endpoint URL
     *
     * @var string
     */
    protected $_apiEndpoint = 'https://api.sendgrid.com/v3';

    /**
     * Prefix for setting custom headers
     *
     * @var string
     */
    protected $_customHeaderPrefix = 'X-';

    /**
     * Send mail
     *
     * @param \Cake\Mailer\Email $email Cake Email
     * @return array An array with api response and email parameters
     */
    public function send(Email $email)
    {
        if (empty($this->getConfig('apiKey'))) {
            throw new SendGridApiException(['Api Key for SendGrid could not found.']);
        }

        $this->_prepareEmailAddresses($email);
        $this->_reqParams['subject'] = $email->getSubject();
        $emailFormat = $email->getEmailFormat();
        if ('both' == $emailFormat || 'text' == $emailFormat) {
            $this->_reqParams['content'][] = (object)[
                    'type' => 'text/plain',
                    'value' => trim($email->message(Email::MESSAGE_TEXT))
            ];
        }
        $this->_reqParams['content'][] = (object)[
                'type' => 'text/html',
                'value' => trim($email->message(Email::MESSAGE_HTML))
        ];

        $customHeaders = $email->getHeaders(['_headers']);
        if (!empty($customHeaders)) {
            $headers = [];
            foreach ($customHeaders as $header => $value) {
                if (0 === strpos($header, $this->_customHeaderPrefix) && !empty($value)) {
                    $headers[substr($header, strlen($this->_customHeaderPrefix))] = $value;
                }
            }
            if (!empty($headers)) {
                $this->_reqParams['headers'] = (object)$headers;
            }
        }

        $attachments = $email->getAttachments();
        if (!empty($attachments)) {
            foreach ($attachments as $name => $file) {
                $this->_reqParams['attachments'][] = (object)[
                        'content' => base64_encode(file_get_contents($file['file'])),
                        'filename' => $name,
                        'disposition' => (!empty($file['contentId'])) ? 'inline' : 'attachment',
                        'content_id' => (!empty($file['contentId'])) ? $file['contentId'] : ''
                ];
            }
        }

        $apiRsponse = $this->_sendEmail();
        $res = [
            'apiResponse' => $apiRsponse,
            'reqParams' => $this->_reqParams
        ];
        $this->_reset();

        return $res;
    }

    /**
     * Returns the parameters for API request.
     *
     * @return array
     */
    public function getRequestParams()
    {
        return $this->_reqParams;
    }

    /**
     * Sets template id
     *
     * This will set template to use in email. Template can be created
     * in SendGrid dashboard.
     *
     * Example
     * ```
     *  $email = new Email('sendgrid');
     *  $emailInstance = $email->getTransport();
     *  $emailInstance->setTemplte(123);
     *
     *  $email->send();
     * ```
     *
     * @param string $id ID of template
     * @return $this
     */
    public function setTemplate($id = null)
    {
        if (!empty($id)) {
            $this->_reqParams['template_id'] = $id;
        }

        return $this;
    }

    /**
     * Sets the timestamp in the future this email should be sent
     *
     * Timestamp can be up to 72 hours.
     *
     * Example
     * ```
     *  $email = new Email('sendgrid');
     *  $emailInstance = $email->getTransport();
     *  $emailInstance->setScheduleTime(1537958411);
     *
     *  $email->send();
     * ```
     *
     * @param array $timestamp Unix timestamp
     * @return $this
     */
    public function setScheduleTime($timestamp = null)
    {
        if (is_numeric($timestamp)) {
            $this->_reqParams['send_at'] = $timestamp;
        }

        return $this;
    }

    /**
     * Prepares the from, to and sender email addresses
     *
     * @param \Cake\Mailer\Email $email Cake Email instance
     * @return void
     */
    protected function _prepareEmailAddresses(Email $email)
    {
        $from = $email->getFrom();
        if (key($from) != $from[key($from)]) {
            $this->_reqParams['from'] = (object)['email' => key($from), 'name' => $from[key($from)]];
        } else {
            $this->_reqParams['from'] = (object)['email' => key($from)];
        }

        $emails = [];
        $to = $email->getTo();
        foreach ($to as $toEmail => $toName) {
            $emails['to'][] = [
                'email' => $toEmail,
                'name' => $toName
            ];
        }

        $cc = $email->getCc();
        foreach ($cc as $ccEmail => $ccName) {
            $emails['cc'][] = [
                'email' => $ccEmail,
                'name' => $ccName
            ];
        }

        $bcc = $email->getBcc();
        foreach ($bcc as $bccEmail => $bccName) {
            $emails['bcc'][] = [
                'email' => $bccEmail,
                'name' => $bccName
            ];
        }

        $this->_reqParams['personalizations'][] = (object)$emails;
    }

    /**
     * Make an API request to send email
     *
     * @return mixed JSON Response from SendGrid API
     */
    protected function _sendEmail()
    {
        $headers = ['type' => 'json'];
        $http = new Client(['headers' => ['Authorization' => 'Bearer ' . $this->getConfig('apiKey')]]);
        $response = $http
            ->post("{$this->getConfig('apiEndpoint')}/mail/send", json_encode($this->_reqParams), $headers);

        return $response->getJson();
    }

    /**
     * Resets the parameters
     *
     * @return void
     */
    protected function _reset()
    {
        $this->_reqParams = [];
    }
}

<?php

namespace FluentMail\App\Services\Mailer\Providers\SendGrid;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\SendGrid\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 202;

    protected $url = 'https://api.sendgrid.com/v3/mail/send';

    public function send()
    {
        if ($this->preSend()) {
            return $this->postSend();
        }

        $this->handleFailure(new Exception('Something went wrong!', 0));
    }

    public function postSend()
    {
        $body = [
            'from' => $this->getFrom(),
            'personalizations' => $this->getRecipients(),
            'subject' => $this->getSubject(),
            'content' => $this->getBody(),
            'headers' => $this->getCustomEmailHeaders()
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['reply_to'] = $replyTo;
        }


        if (isset($this->params['attachments'])) {
            $body['attachments'] = $this->getAttachments();
        }

        $params = [
            'body' => json_encode($body),
            'headers' => $this->getRequestHeaders()
        ];

        $params = array_merge($params, $this->getDefaultParams());

        $this->response = wp_safe_remote_post($this->url, $params);

        return $this->handleResponse($this->response);
    }

    protected function getFrom()
    {
        return $this->getParam('from');
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            return reset($replyTo);
        }

    }

    protected function getRecipients()
    {
        $recipients = [
            'to' => $this->getTo(),
            'cc' => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy(),
        ];

        $recipients = array_filter($recipients);

        foreach ($recipients as $key => $recipient) {
            $array = array_map(function($recipient) {
                return isset($recipient['name'])
                ? $recipient['name'] . ' <' . $recipient['email'] . '>'
                : $recipient['email'];
           }, $recipient);

            $this->params['formatted'][$key] = implode(', ', $array);
        }

        return [$recipients];
    }

    protected function getTo()
    {
        return $this->getParam('to');
    }

    protected function getCarbonCopy()
    {
        return $this->getParam('headers.cc');
    }

    protected function getBlindCarbonCopy()
    {
       return $this->getParam('headers.bcc');
    }

    protected function getBody()
    {
        return [
            [
                'value' => $this->getParam('body'),
                'type' => $this->getParam('headers.content-type')
            ]
        ];
    }

    protected function getAttachments()
    {
        $data = [];

        foreach ($this->getParam('attachments') as $attachment) {
            $file = false;

            try {
                if (is_file($attachment[0]) && is_readable($attachment[0])) {
                    $fileName = basename($attachment[0]);
                    $contentId = wp_hash($attachment[0]);
                    $file = file_get_contents($attachment[0]);
                    $mimeType = mime_content_type($attachment[0]);
                    $filetype = str_replace(';', '', trim($mimeType));
                }
            } catch (\Exception $e) {
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $data[] = [
                'type' => $filetype,
                'filename' => $fileName,
                'disposition' => 'attachment',
                'content_id'  => $contentId,
                'content' => base64_encode($file)
            ];
        }

        return $data;
    }

    protected function getCustomEmailHeaders()
    {
        return [
            'X-Mailer' => 'FluentMail - SendGrid'
        ];
    }

    protected function getRequestHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getSetting('api_key')
        ];
    }

    public function isEmailSent()
    {
        $isSent = wp_remote_retrieve_response_code($this->response) == $this->emailSentCode;

        if (
            $isSent &&
            isset($this->response['response']) &&
            $this->response['response']['message'] != 'Accepted'
        ) {
            return false;
        }

        return $isSent;
    }

    protected function handleSuccess()
    {
        $response = $this->response['response'];

        return $this->processResponse(['response' => $response], true);
    }

    protected function handleFailure()
    {
        $response = $this->getResponseError();

        $this->processResponse(['response' => $response], false);

        $this->fireWPMailFailedAction($response);
    }

    public function getResponseError()
    {
        $response = $this->response;

        $body = (array) wp_remote_retrieve_body($response);

        $body = json_decode($body[0], true);

        $responseErrors = [];
        
        if (!empty($body['errors'])) {
            $responseErrors = $body['errors'];
        } elseif (!empty($body['error'])) {
            $responseErrors = $body['error'];
        }

        $errors = [];

        if (!empty($responseErrors)) {

            foreach ($responseErrors as $error) {

                if (array_key_exists('message', $error)) {
                    $extra = '';

                    if (array_key_exists('field', $error) && !empty($error['field'])) {
                        $extra .= $error['field'] . '.';
                    }

                    if (array_key_exists('help', $error) && !empty($error['help'])) {
                        $extra .= $error['help'];
                    }

                    $errors[] = $error['message'] . (!empty($extra) ? ' - ' . $extra : '');
                }
            }
        }

        $errors = array_map('esc_textarea', $errors);

        return [
            'message' => $response['response']['message'],
            'code' => $response['response']['code'],
            'errors' => $errors
        ];
    }
}
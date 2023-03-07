<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Mail;

use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\Mail as Mailer;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Send extends \Gsnowhawk\Mail
{
    public const DEFAULT_TEMPLATE = 'mail/send.tpl';
    public const SEND_TEMPLATE = 'mail/sendmail.tpl';
    public const REPLY_TEMPLATE = 'mail/reply.tpl';

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => '送信完了', 'id' => 'tms-mail-send', 'class' => 'tms-mail']
        );
    }

    /**
     * Default view.
     */
    public function init()
    {
        $this->app->execPlugin('beforeInit');

        $template = self::DEFAULT_TEMPLATE;
        $post = $this->request->post();
        $this->view->bind('post', $post);

        $noform = false;
        if (!empty($post['prev'])) {
            $template = Form::DEFAULT_TEMPLATE;
            $this->view->bind(
                'header',
                ['title' => 'メールフォーム', 'id' => 'tms-mail-form', 'class' => 'tms-mail']
            );
        } else {
            $valid = $this->validation();

            $return_from_plugin = $this->app->execPlugin('beforeSendmail', $valid);
            $result = true;
            foreach ($return_from_plugin as $return) {
                if ($return === false) {
                    $result = false;
                    $this->app->err['vl_plugin'] = 1;
                }
            }

            if (false === $valid
                || false === $result
                || false === $this->sendmail()
                || false === $this->reply()
            ) {
                $template = Confirm::DEFAULT_TEMPLATE;
                $this->view->bind(
                    'header',
                    ['title' => '入力内容確認', 'id' => 'tms-mail-confirm', 'class' => 'tms-mail']
                );
                $error_count = count(array_filter($this->app->err ?? [], function ($value) {
                    return $value !== 0;
                }));
                $this->view->bind('error_count', $error_count);
                $this->view->bind('err', $this->app->err);
            } else {
                $this->session->clear('ticket');
                $noform = true;
            }
        }

        if (false === $noform) {
            $form = $this->view->param('form');
            $form['action'] = parse_url(Environment::server('request_uri'), PHP_URL_PATH);
            $this->view->bind('form', $form);
        }

        $this->view->render($template);
    }

    /**
     * Sending mail.
     *
     * @return bool
     */
    private function sendmail()
    {
        $bcc = null;
        $cc = null;

        $extraheaders = [];
        $view = clone $this->view;
        $source = $view->render(self::SEND_TEMPLATE, true);
        list($headers, $message) = Mailer::parseEmailSource($source);
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                switch (strtolower($key)) {
                    case 'bcc':
                        $bcc = $value;
                        break;
                    case 'cc':
                        $cc = $value;
                        break;
                    case 'from':
                        $from = $value;
                        break;
                    case 'subject':
                        $subject = $value;
                        break;
                    case 'to':
                        $to = $value;
                        break;
                    default:
                        $extraheaders[$key] = $value;
                        break;
                }
            }
        }

        if (!empty($mail_to = $this->app->cnf('mail:mail_to'))) {
            $to = $mail_to;
        }
        if (!empty($mail_cc = $this->app->cnf('mail:mail_cc'))) {
            $cc = $mail_cc;
        }
        if (!empty($mail_bcc = $this->app->cnf('mail:mail_bcc'))) {
            $bcc = $mail_bcc;
        }
        if (!empty($mail_subject = $this->app->cnf('mail:mail_subject'))) {
            $subject = $mail_subject;
        }

        if (!empty($this->request->param('email'))) {
            $extraheaders['Reply-To'] = $this->request->param('email');
        }

        if (!empty($this->request->param('s1_subject'))) {
            $subject = $this->request->param('s1_subject');
        }

        $from = Mailer::noreplyAt();
        $envfrom = $from;

        return $this->mail($from, $to, $subject, $message, $bcc, $cc, $envfrom, $extraheaders);
    }

    /**
     * Auto reply.
     *
     * @return bool
     */
    private function reply()
    {
        $bcc = '';
        $cc = '';
        $extraheaders = [];

        $view = clone $this->view;
        if (!$view->exists(self::REPLY_TEMPLATE)) {
            return true;
        }

        $source = $view->render(self::REPLY_TEMPLATE, true);
        list($headers, $message) = Mailer::parseEmailSource($source);
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                switch (strtolower($key)) {
                    case 'bcc':
                        $bcc = $value;
                        break;
                    case 'cc':
                        $cc = $value;
                        break;
                    case 'from':
                        $from = $value;
                        break;
                    case 'subject':
                        $subject = $value;
                        break;
                    case 'to':
                        $to = $value;
                        break;
                    default:
                        $extraheaders[$key] = $value;
                        break;
                }
            }
        }

        if (!empty($reply_from = $this->app->cnf('mail:reply_from'))) {
            $from = $reply_from;
        }
        if (empty($from)) {
            return true;
        }
        $envfrom = $from;

        if (!empty($reply_subject = $this->app->cnf('mail:reply_subject'))) {
            $subject = $reply_subject;
        }
        if (!empty($this->request->param('email'))) {
            $to = $this->request->param('email');
        }

        return $this->mail($from, $to, $subject, $message, $bcc, $cc, $envfrom, $extraheaders);
    }

    /**
     * Execute send mail.
     *
     * @param string $from
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param string $bcc
     * @param string $cc
     * @param string $envfrom
     *
     * @return bool
     */
    private function mail($from, $to, $subject, $message, $bcc = '', $cc = '', $envfrom = '', array $extraheaders = [])
    {
        $host = $this->app->cnf('mail:smtp_host');
        if (is_null($host)) {
            $host = 'localhost';
        }
        $port = $this->app->cnf('mail:smtp_port');
        if (is_null($port)) {
            $port = '';
        }
        $user = $this->app->cnf('mail:smtp_user');
        if (is_null($user)) {
            $user = '';
        }
        $pass = $this->app->cnf('mail:smtp_pass');
        if (is_null($pass)) {
            $pass = '';
        }
        $mail = new Mailer($host, $port, $user, $pass);

        $mail->from($from);
        $mail->to($to);

        if (!empty($bcc)) {
            $mail->bcc($bcc);
        }
        if (!empty($cc)) {
            $mail->cc($cc);
        }
        if (!empty($envfrom)) {
            $mail->envfrom($envfrom);
        }

        foreach ($extraheaders as $key => $value) {
            $mail->setHeader($key, $value);
        }

        $mail->subject($subject);
        $mail->message($message);

        return $mail->send();
    }

    /**
     * Post data validation.
     *
     * @reutrn bool
     */
    private function validation(): bool
    {
        $validation_map = $this->request->param('validation_map');
        if (empty($validation_map)) {
            return true;
        }

        if (false === stream_resolve_include_path($validation_map)) {
            return true;
        }

        $post = [];
        foreach ($this->request->param() as $key => $value) {
            if (in_array($key, ['stub'])) {
                continue;
            }
            $post[$key] = $value;
        }

        $valid = [];
        include_once $validation_map;

        return $this->validate($valid);
    }
}

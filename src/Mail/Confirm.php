<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Mail;

use Gsnowhawk\Common\Environment;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Confirm extends \Gsnowhawk\Mail
{
    public const DEFAULT_TEMPLATE = 'mail/confirm.tpl';

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => '入力内容確認', 'id' => 'tms-mail-confirm', 'class' => 'tms-mail']
        );
    }

    /**
     * Default view.
     *
     * @return void
     */
    public function init()
    {
        $this->app->execPlugin('beforeInit');

        $template = self::DEFAULT_TEMPLATE;

        if (false === $this->validation()) {
            $template = Form::DEFAULT_TEMPLATE;
        }

        $form = $this->view->param('form');
        $form['action'] = parse_url(Environment::server('request_uri'), PHP_URL_PATH);
        $this->view->bind('form', $form);

        $post = $this->request->post();
        $this->view->bind('post', $post);

        $error_count = count(array_filter($this->app->err ?? [], function ($value) {
            return $value !== 0;
        }));
        $this->view->bind('error_count', $error_count);

        $this->view->bind('err', $this->app->err);
        $this->view->render($template);
    }

    /**
     * Post data validation.
     *
     * @reutrn bool
     */
    private function validation()
    {
        $valid = [];
        if (false !== stream_resolve_include_path($this->validation_map)) {
            $post = [];
            foreach ($this->request->param() as $key => $value) {
                if (in_array($key, ['stub'])) {
                    continue;
                }
                $post[$key] = $value;
            }
            include_once $this->validation_map;
        }

        return $this->validate($valid);
    }
}

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
class Form extends \Gsnowhawk\Mail
{
    public const DEFAULT_TEMPLATE = 'mail/form.tpl';

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => 'メールフォーム', 'id' => 'tms-mail-form', 'class' => 'tms-mail']
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

        $form = $this->view->param('form');
        $form['action'] = parse_url(Environment::server('request_uri'), PHP_URL_PATH);
        $this->view->bind('form', $form);

        $error_count = count(array_filter($this->app->err ?? [], function ($value) {
            return $value !== 0;
        }));
        $this->view->bind('error_count', $error_count);

        $this->view->bind('err', $this->app->err);
        $this->view->render(self::DEFAULT_TEMPLATE);
    }
}

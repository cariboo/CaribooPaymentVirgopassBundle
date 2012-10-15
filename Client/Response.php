<?php

namespace Cariboo\Payment\VirgopassBundle\Client;

use Symfony\Component\HttpFoundation\ParameterBag;

/*
 * Copyright 2012 Stephane Decleire <sdecleire@cariboo-networks.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Response
{
    public $body;

    public function __construct($response)
    {
        $this->body = new ParameterBag($this->parse($response));
    }

    public function isSuccess()
    {
        $ack = $this->body->get('error_code');

        return '0' === $ack;
    }

    public function isError()
    {
        $ack = $this->body->get('error_code');

        return '0' !== $ack;
    }

    public function getError()
    {
        $error = array(
            'code' => $this->body->get('error_code'),
            'description' => $this->body->get('error_desc'),
        );

        return $error;
    }

    public function __toString()
    {
        if ($this->isError()) {
            $str = 'Debug-Token: '.$this->body->get('session_id')."\n";
            $str .= "{$error['code']}: {$error['description']}\n";
        }
        else {
            $str = var_export($this->body->all(), true);
        }

        return $str;
    }

    private function parse($txt)
    {
        $ret = array();
        $cl = preg_split('/\n/', $txt);
        foreach($cl as $k => $v )
        {
            if( ! empty($v))
            {
                list($key,$val) = explode(': ', $v, 2);
                $ret[$key] = $val;
            }
        }
        return $ret;
    }
}
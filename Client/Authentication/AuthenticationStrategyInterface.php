<?php

namespace Cariboo\Payment\VirgopassBundle\Client\Authentication;

use JMS\Payment\CoreBundle\BrowserKit\Request;

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

interface AuthenticationStrategyInterface
{
    /**
     * Return the URL to call on the Virgopass API based on the function to execute.
     *
     * @param   string  $version version of the Virgopass API
     * @param   string  $method method to call on the Virgopass API
     * @param   string  $isDebug if true, the Virgopass Sandbox is called instead of the production API
     * @return  Response
     */
    function getApiEndpoint($version, $method, $isDebug);

    function authenticate(Request $request);
}
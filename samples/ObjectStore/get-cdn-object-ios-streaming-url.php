<?php
/**
 * Copyright 2012-2014 Rackspace US, Inc.
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

//
// Pre-requisites:
// * Prior to running this script, you must setup the following environment variables:
//   * RAX_USERNAME: Your Rackspace Cloud Account Username, and
//   * RAX_API_KEY:  Your Rackspace Cloud Account API Key
// * There exists a container named 'logos' in your Object Store. Run
//   create-container.php if you need to create one first.
// * The 'logos' container is CDN-enabled. Run enable-container-cdn.php if
//   you need to CDN-enable the container first.
// * The 'logos' container contains an object named 'php-elephant.jpg'. Run
//   upload-object.php if you need to create it first.
//

require __DIR__ . '/../../vendor/autoload.php';
use OpenCloud\Rackspace;
use OpenCloud\ObjectStore\Constants\UrlType;

// 1. Instantiate a Rackspace client.
$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
    'username' => getenv('RAX_USERNAME'),
    'apiKey'   => getenv('RAX_API_KEY')
));

// 2. Obtain an Object Store service object from the client.
$region = 'DFW';
$objectStoreService = $client->objectStoreService(null, $region);

// 3. Get container.
$container = $objectStoreService->getContainer('logos');

// 4. Get object.
$objectName = 'php-elephant.jpg';
$object = $container->getObject($objectName);

// 5. Get object's publicly-accessible iOS streaming URL.
$iosStreamingUrl = $object->getPublicUrl(UrlType::IOS_STREAMING);

printf("Object's publicly accessible iOS streaming URL: %s\n", $iosStreamingUrl);

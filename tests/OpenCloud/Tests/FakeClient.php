<?php

/**
 * @copyright Copyright 2012-2013 Rackspace US, Inc. 
  See COPYING for licensing information.
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0
 * @version   1.5.9
 * @author    Glen Campbell <glen.campbell@rackspace.com>
 * @author    Jamie Hannaford <jamie.hannaford@rackspace.com>
 */

namespace OpenCloud\Tests;

use OpenCloud\OpenStack;
use OpenCloud\Common\Http\Message\EntityEnclosingRequest;

/**
 * Description of FakeClient
 * 
 * @link 
 */
class FakeClient extends OpenStack
{
    private $url;
    private $requests;
    private $responseDir;
    
    public function send($requests) 
	{   
        return new Response(200);
        
        $this->responseDir = __DIR__ . 'Response' . DIRECTORY_SEPARATOR;
        $this->url = $requests->getUrl();
        $this->requests = $requests;
        
        $response = $this->intercept(); 
        $requests->setResponse($response);
		return $response;
	}
    
	private function urlContains($substring)
	{
		return strpos($this->url, $substring) !== false;
	}

	private function covertToRegex($array)
	{
		$regex = array();
        array_walk($array, function($config, $urlTemplate) use ($regex) {
            $trans = array(
                '{d}' => '(\d)+',
                '{s}' => '(\s)+',
                '{w}' => '(\w|\-|\.|\{|\})+',
                '/'   => '\/'
            );
            $regex[strtr($urlTemplate, $trans)] = $config;
        });
		return $regex;
	}

	private function matchUrlToArray($array)
	{
		foreach ($array as $key => $item) {
            if (preg_match("#{$key}$#", $this->url)) {
				return $item;
			}
		}
	}

    private function getBodyPath($path)
    {
        // Set to ./Response/Body/ by default
        $path = $this->responseDir . 'Body' . DIRECTORY_SEPARATOR;
        // Strip 'rax:' prefix from type - rax:autoscale becomes Autoscale
        $path .= ucfirst(str_replace('rax:', '', $this->getServiceType()));
        // Append file path
        $path .= DIRECTORY_SEPARATOR . $path . '.json';
        
        return $path;
    }
    
    private function findServiceArray($array) 
    {
        $type = $this->getServiceType();
        
        if (!array_key_exists($type, $array)) {
            throw new Exception(sprintf(
                '%s service was not found in the response array template.',
                $type
            ));
        }
        return $array[$type];
    }
    
	public function intercept()
	{
		$array = include $this->responseDir . strtoupper($this->requests->getMethod()) . '.php';

        // Retrieve second-level array from service type
        $serviceArray = $this->findServiceArray($array);
        
        // Now find the config array based on the path
        if (!$config = $this->matchUrlToArray($this->covertToRegex($serviceArray))) {
            // If not found, assume a 404
            return new Response(404);
        }
        
        // Retrieve config from nested array structure
        $config = $this->parseConfig($config);
        
        // Set response parameters to defaults if necessary
        $body = $config['body'];
        $status = $config['status'] ?: $this->defaults('status');
        $headers = $config['headers'] ?: $this->defaults('headers');
        
        return new Response($status, $headers, $body);
	}
    
    private function parseConfig($input)
    {
        $body    = null;
        $status  = null;
        $headers = null;
        
        if (is_string($input)) {
            // A string can act as a filepath or as the actual body itself
            $bodyPath = $this->getBodyPath($input);
            
            if (file_exists($bodyPath)) {
                // Load external file contents
                $body = include $bodyPath;
            } else{
                // Set body to string literal
                $body = $input;
            }
            
        } elseif (is_array($input)) {          
            
            if (!empty($input['path']) || !empty($input['body'])) {
                
                // Only one response option for this URL path
                if (!empty($input['body'])) {
                    $body = $input['body'];
                } else {  
                    $bodyPath = $this->getBodyPath($input['path']);
                    if (!file_exists($bodyPath)) {
                        throw new Exception(sprintf('No response file found: %s', $bodyPath));
                    }
                    $body = include $bodyPath;
                }
                
                if (!empty($input['status'])) {
                    $status = $input['status'];
                }
                
                if (!empty($input['headers'])) {
                    $headers = $input['headers'];
                }
                
            } elseif ($this->getRequest() instanceof EntityEnclosingRequest) {
                // If there are multiple response options for this URL path, you
                // need to do a pattern search on the request to differentiate
                $request = $this->getRequest()->toString();
                foreach ($input as $possibility) {
                    if (preg_match($request, $possibility['pattern'])) {
                        return $this->parseConfig($possibility);
                    }
                }    
            }
        }
        
        return array(
            'body'    => $body,
            'headers' => $headers,
            'status'  => $status
        );
    }
    
    private function defaults($key)
    {
        $config = array();
        
        switch ($this->requests->getMethod()) {
            case 'POST':
            case 'PUT':
                $config['status'] = 200;
                $config['headers'] = array();
                break;
            
            case 'GET':
                $config['status'] = 200;
                $config['headers'] = array();
                break;
            
            case 'DELETE':
                $config['status'] = 202;
                $config['headers'] = array();
                break;
            
            case 'HEAD':
                $config['status'] = 204;
                $config['headers'] = array();
                break;
            
            case 'PATCH':
                $config['status'] = 204;
                $config['headers'] = array();
                break;
            
            default:
                break;
        }
        
        return isset($config[$key]) ? $config[$key] : null;
    }
    
}
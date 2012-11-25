<?php

/*
 * This file is part of the WeatherUndergroundBundle.
 *
 * (c) Nikolay Ivlev <nikolay.kotovsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SunCat\WeatherUndergroundBundle\API;

use Buzz\Client\FileGetContents;
use Buzz\Message\Request as BuzzRequest;
use Buzz\Message\Response as BuzzResponse;

/**
 * Weather Underground Api Data Features
 * 
 * @author suncat2000 <nikolay.kotovsky@gmail.com>
 */
class DataFeatures
{
    private $apiKey;
    private $method;
    private $host;
    private $features;
    private $settings;
    private $query;
    private $format;

    private $availFeatures = array(
        'alerts',
        'almanac',
        'astronomy',
        'conditions',
        'currenthurricane',
        'forecast',
        'forecast10day',
        'geolookup',
        'history',
        'hourly',
        'hourly10day',
        'planner',
        'rawtide',
        'tide',
        'webcams',
        'yesterday'
    );
    private $availSettings = array(
        'lang',
        'pws',
        'bestfct'
    );
    private $availMethods = array(
        'GET'
    );

    /**
     * Constructor
     * 
     * @param type $apiKey Api key
     * @param type $format Format
     * @param type $host   Host
     */
    public function __construct($apiKey, $format = 'json', $host = 'http://api.wunderground.com')
    {
        $this->apiKey = $apiKey;
        $this->format = $format;
        $this->host = $host;
        $this->method = 'GET';
    }

    /**
     * Set Request data
     * 
     * @param array  $features        Features
     * @param array  $settings        Settings
     * @param string $query           Query
     * @param bool   $queryUrlencoded Url encoded
     */
    public function setRequestData(array $features, array $settings, $query, $queryUrlencoded = false)
    {
        $this->setFeatures($features);
        $this->setSettings($settings);
        $this->setQuery($query, $queryUrlencoded);
    }

    /**
     * Set features
     * 
     * @param type $features 
     */
    public function setFeatures(array $features)
    {
        if (count($features) == 0) {
            throw \Exception('Not set any features');
        }

        foreach ($features as $feature) {
            $availFeature = $feature;
            if (false !== mb_stristr($feature, '_')) {
                $featureParts = explode('_', $feature);
                $availFeature = $featureParts[0];
            }

            if (false === in_array($availFeature, $this->availFeatures)) {
                throw \Exception(sprintf('Feature \'%s\' not available in API', $feature));
            }
        }

        $this->features = $features;
    }

    /**
     * Set settings
     * 
     * @param type $settings 
     */
    public function setSettings(array $settings)
    {
        foreach ($settings as $name => $value) {
            if (false === in_array($name, $this->availSettings)) {
                throw \Exception(sprintf('Setting \'%s\' not available in API', $name));
            }

            if ($name == 'lang') {
                if (strlen($value) > 2 || false == is_string($value)) {
                    throw \Exception(sprintf('Setting \'%s\' not correct', $name));
                }

                $this->settings[$name] = trim($value, '/');
            } else {
                if (false == is_integer($value)) {
                    throw \Exception(sprintf('Setting \'%s\' not correct', $name));
                }

                $this->settings[$name] = (int) $value;
            }
        }
    }

    /**
     * Set query
     * 
     * @param string $query      Query
     * @param bool   $urlencoded Url encoded
     */
    public function setQuery($query, $urlencoded = false)
    {
        $this->query = false === $urlencoded ? urlencode(trim($query, '/')) : trim($query, '/');
    }

    /**
     * Set HTTP Method
     * @param type $method 
     */
    public function setMethod($method)
    {
        if (false === in_array($method, $this->availMethods)) {
            throw \Exception('This method don\'t support');
        }

        $this->method = $method;
    }

    /**
     * Get content format
     * 
     * @return type 
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Get data from api wheather.com
     * 
     * @return SimpleXML|StdClass|false
     */
    public function getData()
    {
        $resource = $this->getDataResourse();

        $request = new BuzzRequest($this->method, $resource, $this->host);
        $response = new BuzzResponse();

        $client = new FileGetContents();

        // processing get data from TWC API
        $attempt = 0;
        do {
            if ($attempt) {
                sleep($attempt);
            }

            try {
                $client->send($request, $response);
            } catch (\Exception $e) {
                continue;
            }
        } while (!($response instanceof BuzzResponse) && ++$attempt < 5);

        if (!($response instanceof BuzzResponse) || !$response->isOk()) {
            return false;
        }

        // parse data
        switch($this->getFormat()){
            case 'json':
                $data = json_decode($response->getContent());
                break;
            case 'xml':
                $data = simplexml_load_string($response->getContent());
                break;
            default :
                $data = false;
        }

        return $data;
    }

    /**
     * Get resource for Request
     * 
     * @return type 
     */
    protected function getDataResourse()
    {
        if (!isset($this->query{0})) {
            throw \Exception("Don't set query! Use setQuery() method.");
        }

        // Features part of resource
        $featuresStr = implode('/', $this->features);

        // Settings part of resource
        $settingsStrArray = array();
        $settingsStr = null;
        if (count($this->settings) > 0) {
            foreach ($this->settings as $name => $value) {
                $settingsStrArray[] = $name . ':' . $value;
            }
            $settingsStr = implode('/', $settingsStrArray);
        }

        return '/api/' . $this->apiKey .
                '/' . $featuresStr .
                (null !== $settingsStr ? '/' . $settingsStr : '') .
                '/q/' . $this->query . '.' . $this->format;
    }

}

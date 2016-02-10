<?php

/**
 * Yii2 cURL wrapper
 * With RESTful support.
 */

namespace phantomd\filedaemon\components;

use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\web\HttpException;

/**
 * Class Curl
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Curl
{

    /**
     * @var string Holds response data right after sending a request.
     */
    public $response = null;

    /**
     * @var integer HTTP-Status Code
     */
    public $code = null;

    /**
     * @var array Curl info after request
     */
    public $info = null;

    /**
     * @var string Http error massge
     */
    public $error = null;

    /**
     * @var int Error number
     */
    public $errNo = null;

    /**
     * @var integer maximum symbols count of the request content, which should be taken to compose a
     * log and profile messages. Exceeding content will be truncated.
     */
    public $contentLoggingMaxSize = 2000;

    /**
     * @var array Custom options holder
     */
    private $_options = array();

    /**
     * @var array Default curl options
     */
    private $_defaultOptions = array(
        CURLOPT_USERAGENT      => 'Yii2-Curl-Agent',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
    );

    /**
     * Start performing GET-HTTP-Request
     *
     * @param string  $url Url
     * @param boolean $raw If response body contains JSON and should be decoded
     *
     * @return mixed response
     */
    public function get($url, $raw = true)
    {
        return $this->httpRequest('GET', $url, $raw);
    }

    /**
     * Start performing HEAD-HTTP-Request
     *
     * @param string $url Url
     *
     * @return mixed response
     */
    public function head($url)
    {
        return $this->httpRequest('HEAD', $url);
    }

    /**
     * Start performing POST-HTTP-Request
     *
     * @param string  $url Url
     * @param boolean $raw if response body contains JSON and should be decoded
     *
     * @return mixed response
     */
    public function post($url, $raw = true)
    {
        return $this->httpRequest('POST', $url, $raw);
    }

    /**
     * Start performing PUT-HTTP-Request
     *
     * @param string  $url Url
     * @param boolean $raw if response body contains JSON and should be decoded
     *
     * @return mixed response
     */
    public function put($url, $raw = true)
    {
        return $this->httpRequest('PUT', $url, $raw);
    }

    /**
     * Start performing DELETE-HTTP-Request
     *
     * @param string  $url Url
     * @param boolean $raw if response body contains JSON and should be decoded
     *
     * @return mixed response
     */
    public function delete($url, $raw = true)
    {
        return $this->httpRequest('DELETE', $url, $raw);
    }

    /**
     * Set curl option
     *
     * @param int $key Curl option code
     * @param mixed  $value Option value
     *
     * @return $this
     */
    public function setOption($key, $value)
    {
        //set value
        $this->_options[$key] = $value;

        //return self
        return $this;
    }

    /**
     * Set curl options
     *
     * @param mixed  $options Array of curl options
     *
     * @return $this
     */
    public function setOptions($options = [])
    {
        //set values
        if ($options) {
            foreach ($options as $key => $value) {
                $this->setOption($key, $value);
            }
        }
        //return self
        return $this;
    }

    /**
     * Unset a single curl option
     *
     * @param int $key Curl option code
     *
     * @return $this
     */
    public function unsetOption($key)
    {
        //reset a single option if its set already
        if (isset($this->_options[$key])) {
            unset($this->_options[$key]);
        }

        return $this;
    }

    /**
     * Unset all curl option, excluding default options.
     *
     * @return $this
     */
    public function unsetOptions()
    {
        //reset all options
        if (isset($this->_options)) {
            $this->_options = array();
        }

        return $this;
    }

    /**
     * Total reset of options, responses, etc.
     *
     * @return $this
     */
    public function reset()
    {
        //reset all options
        if (isset($this->_options)) {
            $this->_options = array();
        }

        //reset response & status code
        $this->response = null;
        $this->code     = null;
        $this->errNo    = null;
        $this->error    = null;
        $this->info     = null;

        return $this;
    }

    /**
     * Return a single option
     *
     * @return mixed // false if option is not set.
     */
    public function getOption($key)
    {
        //get merged options depends on default and user options
        $mergesOptions = $this->getOptions();

        //return value or false if key is not set.
        return isset($mergesOptions[$key]) ? $mergesOptions[$key] : false;
    }

    /**
     * Return merged curl options and keep keys!
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->_options + $this->_defaultOptions;
    }

    /**
     * Composes the log/profiling message token for the given HTTP request parameters.
     * This method should be used by transports during request sending logging.
     *
     * @param string $method request method name.
     * @param string $url request URL.
     * @param array $headers request headers.
     * @param string $content request content.
     *
     * @return string log token.
     */
    public function createRequestLogToken($method, $url, $headers, $content = '')
    {
        $token = microtime(true) . '  ' . strtoupper($method) . ' ' . $url;
        if (!empty($headers)) {
            $token .= "\n" . implode("\n", $headers);
        }
        if ($content !== null) {
            $token .= "\n\n" . \yii\helpers\StringHelper::truncate($content, $this->contentLoggingMaxSize);
        }
        return $token;
    }

    /**
     * Performs HTTP request
     *
     * @param string  $method Http method
     * @param string  $url Url
     * @param boolean $raw If response body contains JSON and should be decoded -> helper.
     *
     * @throws Exception if request failed
     * @throws HttpException
     *
     * @return mixed
     */
    private function httpRequest($method, $url, $raw = false)
    {
        //Init
        $body = '';

        //set request type and writer function
        $this->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($method));

        // set request url
        $this->setOption(CURLOPT_URL, $url);

        //check if method is head and set no body
        if ($method === 'HEAD') {
            $this->setOption(CURLOPT_NOBODY, true);
            $this->unsetOption(CURLOPT_WRITEFUNCTION);
        } else {
            $this->setOption(CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$body) {
                $body .= $data;
                return mb_strlen($data, '8bit');
            });
        }

        if (YII_DEBUG) {
            $token = $this->createRequestLogToken($method, $url, $this->getOption(CURLOPT_HTTPHEADER));
            //setup error reporting and profiling
            \Yii::info("Start sending cURL-Request: {$method} {$url}\n", __METHOD__);
            \Yii::beginProfile($token, __METHOD__);
        }
        /**
         * proceed curl
         */
        $curl = curl_init();
        curl_setopt_array($curl, $this->getOptions());
        $body = curl_exec($curl);

        $this->info     = curl_getinfo($curl);
        //retrieve response code
        $this->code     = $this->info['http_code'];
        $this->response = $body;
        $this->error    = curl_error($curl);
        $this->errNo    = curl_errno($curl);

        //stop curl
        curl_close($curl);

        //end yii debug profile
        YII_DEBUG && \Yii::endProfile($token, __METHOD__);

        //check if curl was successful
        if ($this->errNo || $this->response === false) {
            throw new Exception('Curl error: #' . $this->errNo . ' - ' . $this->error);
        }

        $return = true;
        //check responseCode and return data/status
        if ($this->code >= 200 && $this->code < 300) {
            // all between 200 && 300 is successful
            if ($this->getOption(CURLOPT_CUSTOMREQUEST) === 'HEAD') {
                $return = true;
            } else {
                $this->response = $raw ? $this->response : Json::decode($this->response);

                $return = $this->response;
            }
        } elseif ($this->code >= 400 && $this->code <= 510) {
            // client and server errors return false.
            $return = false;
        } elseif ($this->code === 0) {
            // server not response
            $return = false;
        } else {
            //any other status code or custom codes
            $return = true;
        }
        return $return;
    }

}

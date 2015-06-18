<?php
/**
 * @link https://github.com/borodulin/yii2-helpers
 * @copyright Copyright (c) 2015 Andrey Borodulin
 * @license https://github.com/borodulin/yii2-helpers/blob/master/LICENSE
 */

namespace conquer\helpers;

/**
 * 
 * @author Andrey Borodulin
 */
trait CurlTrait 
{
    private function defaultOpts(){
        return array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_HEADER => false,
                CURLOPT_HEADERFUNCTION => array($this, 'headerCallback'),
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
        );
    }
    
    /**
     * CURL Options
     * @see curl_setopt_array
     * @var array
     */
    private $_options;

    /**
     * Header recieved with self::headerCallback() function
     * @see CURLOPT_HEADERFUNCTION
     * @var string
     */
    private $_header;

    /**
     * Content
     * @see curl_exec
     * @var string
     */
    protected  $_content;
    
    /**
     * @see curl_getinfo
     * @var array
     */
    protected $_info;
    
    /**
     * Error code
     * @see curl_errno
     * @var integer
     */
    protected $_errorCode;
    
    /**
     * Error message
     * @see curl_error
     * @var string
     */
    protected $_errorMessage;
    
    /**
     * @see CURLOPT_HEADERFUNCTION
     * @param resource $ch
     * @param string $headerLine
     * @return number
     */
    public function headerCallback($ch, $headerLine)
    {
        $this->_header .= $headerLine;
        return strlen($headerLine);
    }
    
    /**
     * Returns the cookies of executed request
     * @return string|NULL
     */
    public function getCookies()
    {
        if(preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $this->header, $matches)){
            return implode('; ', $matches[1]);
        }
        return null;
    }
    
    /**
     * Checks if it is good http code
     * @return boolean
     */
    public function isHttpOK()
    {
        if(isset($this->_info['http_code']))
            return (strncmp($this->_info['http_code'],'2',1) == 0);
        else
            return false;
    }

    /**
     * Getter for CURL Options
     * Use curl_setopt_array() for the CURL resourse
     * @see curl_setopt_array()
     * @return array
     */
    public function getOptions()
    {
        foreach ($this->defaultOpts() as $k => $v){
            if(!isset($this->_options[$k]))
                $this->_options[$k] = $v;
        }
        // !important see headerCallback() function
        $this->_options[CURLOPT_HEADER] = false;
        return $this->_options;
    }
    
    /**
     * Setter for CURL Options
     * Warning! setoptions clears all previously setted options and post data
     * @see curl_setopt_array
     * @param array $options
     */
    public function setOptions(array $options)
    {
        foreach ($options as $k => $v){
            $this->_options[$k] = $v;
        }
    }
    
    /**
     * Adds post data to options 
     * @param mixed $postData
     */
    public function setPostData($postData)
    {
        if(empty($postData)){
            unset($this->_options[CURLOPT_POST]);
            unset($this->_options[CURLOPT_POSTFIELDS]);
        } else {
            $this->_options[CURLOPT_POST] = true;
            $this->_options[CURLOPT_POSTFIELDS] = $postData;
        }
    }
    
    /**
     * 
     * @return string
     */
    public function getHeader()
    {
        return $this->_header;
    }

    /**
     * Url getter
     * @see CURLOPT_URL
     * @var string
     */
    public function getUrl()
    {
        return isset($this->_options[CURLOPT_URL]) ? $this->_options[CURLOPT_URL] : null; 
    }
    
    /**
     * Url setter
     * @see CURLOPT_URL
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->_options[CURLOPT_URL] = $url;
    }
    
    /**
     * Executes the single curl
     * @return boolean
     */
    protected function curl_execute()
    {
        $ch = curl_init();
    
        curl_setopt_array($ch, $this->getOptions());
    
        $this->_content = curl_exec($ch);
    
        $this->_errorCode = curl_errno($ch);
    
        $this->_info = curl_getinfo($ch);
    
        if($this->_errorCode)
            $this->_errorMessage = curl_error($ch);
    
        curl_close($ch);         
    }
    
    /**
     * Executes parallels curls
     * @param CurlTrait[] $urls
     */
    protected static function curl_multi_exec($urls)
    {
        $nodes = array();
        /* @var $url CurlTrait */
        foreach ($urls as $url){
            $ch = curl_init();
            $nodes[] = ['ch'=>$ch, 'url'=>$url];
    
            curl_setopt_array($ch, $url->getOptions());
        }
        
        $mh = curl_multi_init();
        foreach ($nodes as $node){
            curl_multi_add_handle($mh, $node['ch']);
        }
        
        //execute the handles
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while($running > 0);
    
        foreach ($nodes as $node){
            /* @var $url Curl */
            $url = $node['url'];
    
            $ch = $node['ch'];
    
            $url->_content = curl_multi_getcontent($ch);
            
            $url->_errorCode = curl_errno($ch);
            if(!empty($url->_errorCode))
                $url->_errorMessage = curl_error($ch);

            $url->_info = curl_getinfo($ch);
        }
        
        //close the handles
        foreach ($nodes as $node){
            curl_multi_remove_handle($mh, $node['ch']);
        }
        curl_multi_close($mh);
    }
    
    public function getContent()
    {
        return $this->_content;
    }
    
    public function getErrorCode()
    {
        return $this->_errorCode;
    }
    
    public function getErorMessage()
    {
        return $this->_errorMessage;
    }
    
    public function getInfo()
    {
        return $this->_info;
    }
    
}
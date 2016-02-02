<?php
/**
 * @package API plugins
 * @copyright Copyright (C) 2009 2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link http://www.techjoomla.com
*/
defined('_JEXEC') or die( 'Restricted access' );
jimport('joomla.plugin.plugin');
class plgAPIRtsusers extends ApiPlugin
{
    const API_KEY = 'A0*cK3924-';
    public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config = array());
        ApiResource::addIncludePath(dirname(__FILE__).'/rtsusers');

        // Set the login resource to be public
        //$this->setResourceAccess('login', 'public','get');
        $this->setResourceAccess('getuser', 'public','post');
        //$this->setResourceAccess('users', 'public', 'post');
    }
    public function postRTSBox($server, $what, $args) {
        $post = array_merge($args, array("what" => $what, "key" => static::API_KEY));
        $post_string = "";
        foreach($post as $k => $v) { $post_string .= "$k=".urlencode($v)."&"; }
        rtrim($post_string, '&');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$server}/serve/serve_me.php");
        curl_setopt($ch, CURLOPT_POST, count($post));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        #error_log("Posted $post_string to {$this->_fqdn} \n".print_r(curl_getinfo($ch), true)."\n$result");
        if($result === false) {
            throw new \Exception("Curl Error! ".curl_error($ch));
        }
        curl_close($ch);
        return json_decode($result);
    }
}
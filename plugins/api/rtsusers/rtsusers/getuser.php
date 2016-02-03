<?php

/**
 * @package API plugins
 * @copyright Copyright (C) 2009 2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link http://www.techjoomla.com
 */
defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');
jimport('joomla.html.html');
jimport('joomla.application.component.controller');
jimport('joomla.application.component.model');
jimport('joomla.user.helper');
jimport('joomla.user.user');
jimport('joomla.application.component.helper');
JModelLegacy::addIncludePath(JPATH_SITE . 'components/com_api/models');
require_once JPATH_SITE . '/components/com_api/libraries/authentication/user.php';
require_once JPATH_SITE . '/components/com_api/libraries/authentication/login.php';
require_once JPATH_SITE . '/components/com_api/models/key.php';
require_once JPATH_SITE . '/components/com_api/models/keys.php';

class RtsusersApiResourceGetuser extends ApiResource {

    public function get() { $this->plugin->setResponse($this->ret(405, "Method GET unauthorized.", new stdClass())); }

    public function post() { $this->plugin->setResponse($this->keygen()); }
    
    public function findUser($server_name, $user_id, $key) {
        $db = JFactory::getDbo();
        $db->setQuery('SELECT * FROM #__rts_users WHERE rts_server = "'. $server_name . '" AND rts_user_id = '.(int)$user_id );
        $result = $db->loadResult();
        //error_log(print_r($result, true));
        // Check for a database error.
        if ($db->getErrorNum()) {
            error_log($db->getErrorMsg());
            return false;
        }
        $user = JFactory::getUser();
        if ($user->id && $result == $user->id) { 
            $this->setActiveSite($user, $server_name);
            return $user; 
        }
        
        $usr = JFactory::getUser($result);
        return $this->loginUser($usr, $server_name, $key);
    }
    public function setActiveSite($user, $server_name, $key) {
        JPluginHelper::importPlugin('user');
        $user->setParam('active_rts_site', $server_name);
        $user->setParam('active_rts_key', $key);
        $user->save();
        $session = JFactory::getSession();
        $session->set('user', $user);
        return $user;
    }
    public function loginUser($user, $server_name, $key) {
        $this->setActiveSite($user, $server_name, $key);
        JFactory::getApplication('site')->triggerEvent('onUserLogin', array(array('username'=>$user->username), array('action' => 'core.login.site')));
        return $user;
    }
    public function createUser($server, $fromRemote) {
        $mainframe = JFactory::getApplication('site');
        $mainframe->initialise();
        //$user = clone(JFactory::getUser());
        //$pathway = & $mainframe->getPathway();
        //$config = & JFactory::getConfig();
        //$authorize = & JFactory::getACL();
        //$document = & JFactory::getDocument();
        $usersConfig = JComponentHelper::getParams( 'com_users' );
 
        //if($usersConfig->get('allowUserRegistration') != '1') { throw new Exception("User registration not allowed."); }
        // Initialize new usertype setting

        $useractivation = $usersConfig->get('useractivation');

        $db = JFactory::getDBO();
        // Default group, 2=registered
        $defaultUserGroup = 2;
        $salt     = JUserHelper::genRandomPassword(32);
        $password_clear = $fromRemote->user->password;

        $crypted  = JUserHelper::getCryptedPassword($password_clear, $salt);
        $password = $crypted.':'.$salt;
        $instance = JUser::getInstance();
        $instance->set('id'         , 0);
        $instance->set('name'           , $fromRemote->user->user_name);
        $instance->set('username'       , $fromRemote->user->login_name);
        $instance->set('password'       , $password);
        $instance->set('password_clear' , $password_clear);
        $instance->set('email'          , $fromRemote->user->email);
        $instance->set('usertype'       , 'deprecated');
        $instance->set('groups'         , array($defaultUserGroup));
        // Here is possible set user profile details
        //$instance->set('profile'    , array('gender' =>  $fromRemote['gender']));

        // Email with activation link
        if($useractivation == 1)
        {
            $instance->set('block'    , 1);
            $instance->set('activation'    , JApplication::getHash(JUserHelper::genRandomPassword()));
        }

        if (!$instance->save()) { throw new Exception("{$fromRemote->user->email} is already in use."); }

        $join = date('Y-m-d H:i:s', $fromRemote->user->join_date);
        $last = date('Y-m-d H:i:s', $fromRemote->user->last_active);
        $db->setQuery("update #__users set registerDate='{$join}', lastvisitDate='{$last}', email='{$fromRemote->user->email}' where username='{$fromRemote->user->login_name}'");
        $db->query();

        $db->setQuery("SELECT id FROM #__users WHERE email='{$fromRemote->user->email}'");
        $db->query();
        $newUserID = $db->loadResult();

        $user = JFactory::getUser($newUserID);

        // Everything OK!               
        if ($user->id != 0)
        {
            $db->setQuery("insert into #__rts_users set user_id={$user->id}, rts_server='{$server}', rts_user_id={$fromRemote->user->id}");
            $db->query();
            // Auto registration
            if($useractivation == 0)
            {

                //$emailSubject = 'Email Subject for registration successfully';
                //$emailBody = 'Email body for registration successfully';                       
                //$return = JFactory::getMailer()->sendMail('sender email', 'sender name', $user->email, $emailSubject, $emailBody);

                // Your code here...
            }
            else if($useractivation == 1)
            {
                //$emailSubject = 'Email Subject for activate the account';
                //$emailBody = 'Email body for for activate the account';     
                //$user_activation_url = JURI::base().JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $user->activation, false);  // Append this URL in your email body
                //$return = JFactory::getMailer()->sendMail('sender email', 'sender name', $user->email, $emailSubject, $emailBody);                             

                // Your code here...
            }
            return $this->loginUser($user, $server, $fromRemote->user->sessionkey);
        }
        throw new Exception("Something went weird with the save...");
    }
    public function isRTSServer($server) {
        return preg_match("/^([^\.]*)\.ruletheseas\.com$/", $server);
    }
    public function ret($code, $message, stdClass $obj) {
        $obj->code = $code;
        $obj->message = $message;
        return $obj;
    }
    public function keygen() {
        $app = JFactory::getApplication('site');
        
        $user = $app->input->get('user', array(), 'ARRAY');
        $server = $app->input->get('server', '', 'string');
        $key = $app->input->get('key', '', 'string');
        
        try {
            $fromRemote = $this->getRemote($server, $user['id'], $key);
        } catch(Exception $ex) {
            return $this->ret(405, $ex->getMessage(), new stdClass());
        }
        
        if(!$this->isRTSServer($server)) { return $this->ret(405,"Unauthorized server. Sorry.", new stdClass()); }
        $u = $this->findUser($server, $user['id'], $fromRemote->user->sessionkey);
        if($u->id) {
            //$obj->user = $u;
        } else {
            try {
                //error_log(print_r($fromRemote, true));
                $u = $this->createUser($server, $fromRemote);
            } catch (Exception $ex) { return $this->ret(405, $ex->getMessage(), new stdClass()); }
        }
        $obj = new stdClass();
        $obj->user = $u;
        if($u->id) { 
            $obj->hash = $this->getUserHash($u); 
        }
        $obj->cookie = new stdClass();
        $obj->cookie->name = session_name();
        $obj->cookie->id = session_id();
        return $this->ret(200, "Great job!", $obj);
    }
    public function getRemote($server, $user_id, $key) {
        
        return $this->plugin->postRTSBox(
                $server, 'gpanel2', 
                array(
                    'mod' => 'Plugins\ServerInterop', 
                    'cmd' => 'SocialUser', 
                    'args[id]' => $user_id, 
                    'args[key]' => $key
                )
            );
    }
    public function getUserHash($user) {
        $kmodel = new ApiModelKey;
        $model = new ApiModelKeys;
        $key = null;
        // Get login user hash
        $kmodel->setState('user_id', $user->id);
        $list = $kmodel->getList();
        if ($list) { $log_hash = $list[count($list) - count($list)]; }
        if ($list && $log_hash && $log_hash->hash) { $key = $log_hash->hash; } elseif ($key == null || empty($key)) {
            // Create new key for user
            $data = array( 'userid' => $user->id, 'domain' => '', 'state' => 1, 'id' => '',
                'task' => 'save', 'c' => 'key', 'ret' => 'index.php?option=com_api&view=keys',
                'option' => 'com_api', JSession::getFormToken() => 1
            );
            $result = $kmodel->save($data);
            $key = $result->hash;
        }
        return $key;
    }
}

//
//class ApiAuthenticationLogin extends ApiAuthentication
//{
//	protected $auth_method     = null;
//	protected $domain_checking = null;
//
//	public function authenticate()
//	{
//		$app = JFactory::getApplication();
//
//		$username = $app->input->post->get('username','','STRING');
//		$password = $app->input->post->get('password','','STRING');
//
//		$user = $this->loadUserByCredentials( $username, $password );
//
//		// Remove username and password from request for when it gets logged
//		$uri = JFactory::getURI();
//		$uri->delVar('username');
//		$uri->delVar('password');
//
//		if ( $user === false ) {
//			// Errors are already set, just return
//			return false;
//		}
//
//		return $user->id;
//	}
//
//	public function loadUserByCredentials( $user, $pass )
//	{
//		jimport('joomla.user.authentication');
//
//		$authenticate = JAuthentication::getInstance();
//
//		$response = $authenticate->authenticate(array( 'username' => $user, 'password' => $pass ),$options = array());
//
//		if ($response->status ===JAuthentication::STATUS_SUCCESS) {
//			$instance = JUser::getInstance($response->username);
//			if ( $instance === false ) {
//				$this->setError( JError::getError() );
//				return false;
//			}
//
//		} else {
//			if ( isset( $response->error_message ) ) {
//				$this->setError( $response->error_message );
//			}else {
//				$this->setError( $response->getError() );
//			}
//
//			return false;
//		}
//
//		return $instance;
//	}
//}

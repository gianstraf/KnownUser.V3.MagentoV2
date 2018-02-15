<?php
namespace Queueit\KnownUser;

require_once( __DIR__ .'/IntegrationInfoProvider.php');
require_once( __DIR__ .'/../knownuserv3/Models.php');
require_once( __DIR__ .'/../knownuserv3/KnownUser.php');


class KnownUserHandler
{
    public function handleRequest($customerId, $secretKey,  $observer)
    {
        $action = $observer->getEvent()->getControllerAction();
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $action->getRequest();
 
        try
        {
            $queueittoken = $request->getQuery('queueittoken', '');
            $configProvider = new IntegrationInfoProvider();
            $configText =  $configProvider->getIntegrationInfo(true);
            $fullUrl = $this->getFullRequestUri();
            $currentUrlWithoutQueueitToken =  preg_replace ( "/([\\?&])(" ."queueittoken". "=[^&]*)/i" , "" ,  $fullUrl);
            
            $result = \QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig(
				$currentUrlWithoutQueueitToken, 
				$queueittoken, 
				$configText,
				$customerId, 
				$secretKey);

            if($result->doRedirect())
            {
                $response = $action->getResponse(); 
				
                $response->setHeader('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
                $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->setHeader('Pragma', 'no-cache');

                if(!$result->isAjaxResult)
                {
                    $response->setRedirect($result->redirectUrl)->sendResponse();
                }
                else
                {
                    $response->setHeader($result->getAjaxQueueRedirectHeaderKey(), $result->getAjaxRedirectUrl());
                    $response->sendResponse();
                }
				
                return;
            }

            if(!empty($queueittoken))
            {   
                $redirectUrl = $fullUrl;
                //Request can continue - we remove queueittoken form querystring parameter to avoid sharing of user specific token
                if(strpos($fullUrl,"&queueittoken=")!==false)
                {
                    $redirectUrl = str_replace("&queueittoken=".$queueittoken,"",$fullUrl);
                }
                else if(strpos($fullUrl,"?queueittoken=".$queueittoken."&")!==false)
                {
                    $redirectUrl =  str_replace("queueittoken=".$queueittoken."&","",  $fullUrl);
                }
                else if(strpos($fullUrl,"?queueittoken=".$queueittoken)!==false)
                {
                    $redirectUrl = str_replace("?queueittoken=".$queueittoken,"",  $fullUrl);
                }
                $action->getResponse()->setRedirect( $redirectUrl)->sendResponse();
                return;
            }
        }
        catch(\Exception $e)
        {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
                $logger = $objectManager->get("Psr\Log\LoggerInterface");
                $logger->debug("Queueit-knownUser: Exception while validation user request". $e);
          //log the exception
        }
    }
    private function getFullRequestUri()
    {
        // Get HTTP/HTTPS (the possible values for this vary from server to server)
        $myUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && !in_array(strtolower($_SERVER['HTTPS']),array('off','no'))) ? 'https' : 'http';
        // Get domain portion
        $myUrl .= '://'.$_SERVER['HTTP_HOST'];
        // Get path to script
        $myUrl .= $_SERVER['REQUEST_URI'];
        // Add path info, if any
        if (!empty($_SERVER['PATH_INFO'])) $myUrl .= $_SERVER['PATH_INFO'];
        return $myUrl; 
    }
      

}
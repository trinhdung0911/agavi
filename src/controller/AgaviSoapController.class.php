<?php

// +---------------------------------------------------------------------------+
// | This file is part of the Agavi package.                                   |
// | Copyright (c) 2003-2007 the Agavi Project.                                |
// | Based on the Mojavi3 MVC Framework, Copyright (c) 2003-2005 Sean Kerr.    |
// |                                                                           |
// | For the full copyright and license information, please view the LICENSE   |
// | file that was distributed with this source code. You can also view the    |
// | LICENSE file online at http://www.agavi.org/LICENSE.txt                   |
// |   vi: set noexpandtab:                                                    |
// |   Local Variables:                                                        |
// |   indent-tabs-mode: t                                                     |
// |   End:                                                                    |
// +---------------------------------------------------------------------------+

/**
 * AgaviSoapController handles SOAP requests.
 *
 * @package    agavi
 * @subpackage controller
 *
 * @author     David Zülke <dz@bitxtender.com>
 * @copyright  Authors
 * @copyright  The Agavi Project
 *
 * @since      0.9.0
 *
 * @version    $Id$
 */
class AgaviSoapController extends AgaviController
{
	/**
	 * @param      AgaviRequestDataHolder Additional request data for later use.
	 */
	protected $dispatchArguments = null;
	
	/**
	 * @param      SoapClient The soap client instance we use to access WSDL info.
	 */
	protected $soapClient = null;
	
	/**
	 * @param      SoapServer The soap server instance that handles the request.
	 */
	protected $soapServer = null;
	
	/**
	 * Get the soap client instance we use to access WSDL info.
	 *
	 * @return     SoapClient The soap client instance.
	 *
	 * @author     David Zülke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function getSoapClient()
	{
		return $this->soapClient;
	}
	
	/**
	 * Get the soap server instance we use to access WSDL info.
	 *
	 * @return     SoapServer The soap client instance.
	 *
	 * @author     David Zülke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function getSoapServer()
	{
		return $this->soapServer;
	}
	
	/**
	 * Initialize this controller.
	 *
	 * @param      AgaviContext An AgaviContext instance.
	 * @param      array        An array of initialization parameters.
	 *
	 * @author     David Zülke <dz@bitxtender.com>
	 * @since      0.9.0
	 */
	public function initialize(AgaviContext $context, array $parameters = array())
	{
		if(!AgaviConfig::get('core.use_routing')) {
			throw new AgaviInitializationException('Agavi SOAP support requires the Routing to be on, please enable "core.use_routing" in settings.xml.');
		}
		
		parent::initialize($context, $parameters);
	}
	
	/**
	 * Do any necessary startup work after initialization.
	 *
	 * This method is not called directly after initialize().
	 *
	 * @author     David Zülke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function startup()
	{
		// user-supplied "wsdl" and "options" parameters
		$wsdl = $this->getParameter('wsdl');
		if(!$wsdl) {
			// no wsdl was specified, that means we generate one from the annotations in routing.xml
			$wsdl = $this->context->getRouting()->getWsdlPath();
		}
		$this->setParameter('wsdl', $wsdl);
		
		// get the name of the class to use for the client, defaults to PHP's own "SoapClient"
		$soapClientClass = $this->getParameter('soap_client_class', 'SoapClient');
		$soapClientOptions = $this->getParameter('soap_client_options', array());
		// get the name of the class to use for the server, defaults to PHP's own "SoapServer"
		$soapServerClass = $this->getParameter('soap_server_class', 'SoapServer');
		$soapServerOptions = $this->getParameter('soap_server_options', array());
		// get the name of the class to use for handling soap calls, defaults to Agavi's "AgaviSoapControllerCallHandler"
		$soapHandlerClass = $this->getParameter('soap_handler_class', 'AgaviSoapControllerCallHandler');
		
		// create a client, so we can grab the functions and types defined in the wsdl (not possible from the server, duh)
		$this->soapClient = new $soapClientClass($wsdl, $soapClientOptions);
		
		if($this->getParameter('auto_classmap')) {
			// we have to create a classmap automatically.
			// to do that, we read the defined types, and set identical values for type and class name.
			$classmap = array();
			
			// with an optional prefix, of course.
			$prefix = $this->getParameter('auto_classmap_prefix', '');
			
			foreach($this->soapClient->__getTypes() as $definition) {
				if(preg_match('/^struct (\S+) \{$/m', $definition, $matches)) {
					$classmap[$matches[1]] = $prefix . $matches[1];
				}
			}
			
			if(isset($soapServerOptions['classmap'])) {
				$classmap = array_merge((array) $classmap, $soapServerOptions['classmap']);
			}
			
			$soapServerOptions['classmap'] = $classmap;
		}
		
		// create a server
		$this->soapServer = new $soapServerClass($wsdl, $soapServerOptions);
		
		// give it a class that handles method calls
		// that class uses __call
		// the class ctor gets the context as the first argument
		$this->soapServer->setClass($soapHandlerClass, $this->context);
		
		// please don't send a response automatically, we need to return it inside the __call overload so PHP's SOAP extension creates a SOAP response envelope with the data
		$this->setParameter('send_response', false);
	}
	/**
	 * Dispatch a request
	 *
	 * @param      AgaviRequestDataHolder A RequestDataHolder with additional
	 *                                    request arguments.
	 *
	 * @author     David Zülke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function dispatch(AgaviRequestDataHolder $arguments = null)
	{
		// Remember The Milk... err... the arguments given.
		$this->dispatchArguments = $arguments;
		
		// handle the request. the aforementioned __call will be run next
		// we use the input from the request as the argument, it contains the SOAP request
		$this->soapServer->handle($this->context->getRequest()->getInput());
	}
	
	/**
	 * A method that is called in the __call overload by the SOAP call handler.
	 *
	 * All it does is call parent::dispatch() to prevent an infinite loop.
	 *
	 * @author     David Zülke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function doDispatch()
	{
		try {
			return parent::dispatch($this->dispatchArguments);
		} catch(SoapFault $f) {
			return $f;
		}
	}
}

?>
<?php

// +---------------------------------------------------------------------------+
// | This file is part of the Agavi package.                                   |
// | Copyright (c) 2003-2006 the Agavi Project.                                |
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
 * AgaviFilterConfigHandler allows you to register filters with the system.
 *
 * @package    agavi
 * @subpackage config
 *
 * @author     David Zülke <dz@bitxtender.com>
 * @copyright  (c) Authors
 * @since      0.9.0
 *
 * @version    $Id$
 */
class AgaviFilterConfigHandler extends AgaviConfigHandler
{
	/**
	 * Execute this configuration handler.
	 *
	 * @param      string An absolute filesystem path to a configuration file.
	 * @param      string An optional context in which we are currently running.
	 *
	 * @return     string Data to be written to a cache file.
	 *
	 * @throws     <b>AgaviUnreadableException</b> If a requested configuration
	 *                                             file does not exist or is not
	 *                                             readable.
	 * @throws     <b>AgaviParseException</b> If a requested configuration file is
	 *                                        improperly formatted.
	 *
	 * @author     Sean Kerr <skerr@mojavi.org>
	 * @since      0.9.0
	 */
	public function execute($config, $context = null)
	{
		// parse the config file

		$configurations = $this->orderConfigurations(AgaviConfigCache::parseConfig($config, false, $this->getValidationFile(), $this->parser)->configurations, AgaviConfig::get('core.environment'), $context);
		
		$filters = array();
		
		foreach($configurations as $cfg) {
			if($cfg->hasChildren('filters')) {
				foreach($cfg->filters as $filter) {
					$name = $filter->getAttribute('name', uniqid(mt_rand()));
					
					if(!isset($filters[$name])) {
						$filters[$name] = array('params' => array(), 'enabled' => $this->literalize($filter->getAttribute('enabled', true)));
					} else {
						$filters[$name]['enabled'] = $this->literalize($filter->getAttribute('enabled', $filters[$name]['enabled']));
					}

					if($filter->hasAttribute('class')) {
						$filters[$name]['class'] = $filter->getAttribute('class');
					}
					
					$filters[$name]['params'] = $this->getItemParameters($filter, $filters[$name]['params']);
				}
			}
		}
		
		$data = array();

		foreach($filters as $name => $filter) {
			if(!isset($filter['class'])) {
				throw new AgaviConfigurationException('No class name specified for filter "' . $name . '" in ' . $config);
			}
			if($filter['enabled']) {
				$rc = new ReflectionClass($filter['class']);
				$if = 'AgaviI' . ucfirst(strtolower(substr(basename($config), 0, strpos(basename($config), '_filters')))) . 'Filter';
				if(!$rc->implementsInterface($if)) {
					throw new AgaviFactoryException('Filter "' . $name . '" does not implement interface "' . $if . '"');
				}
				$data[] = '$filter = new ' . $filter['class'] . '();';
				$data[] = '$filter->initialize($this->context, ' . var_export($filter['params'], true) . ');';
				$data[] = '$filters[] = $filter;';
			}
		}

		// compile data
		$retval = "<?php\n" .
				"// auto-generated by ".__CLASS__."\n" .
				"// date: %s GMT\n%s\n?>";

		$retval = sprintf($retval, gmdate('m/d/Y H:i:s'), implode("\n", $data));

		return $retval;

	}

}

?>
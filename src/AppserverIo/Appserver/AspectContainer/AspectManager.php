<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category   Server
 * @package    Appserver
 * @subpackage Application
 * @author     Bernhard Wick <bw@appserver.io>
 * @copyright  2014 TechDivision GmbH - <info@appserver.io>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.appserver.io/
 */

namespace AppserverIo\Appserver\AspectContainer;

use AppserverIo\Appserver\AspectContainer\Interfaces\AspectManagerInterface;
use AppserverIo\Doppelgaenger\AspectRegister;
use AppserverIo\Doppelgaenger\Config;
use AppserverIo\Doppelgaenger\Entities\Definitions\Advice;
use AppserverIo\Doppelgaenger\Entities\Definitions\Aspect;
use AppserverIo\Doppelgaenger\Entities\Definitions\Pointcut;
use AppserverIo\Doppelgaenger\Entities\PointcutExpression;
use AppserverIo\Doppelgaenger\Entities\Pointcuts\PointcutFactory;
use AppserverIo\Doppelgaenger\Entities\Pointcuts\PointcutPointcut;
use AppserverIo\Doppelgaenger\Parser\AspectParser;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Psr\Application\ManagerInterface;
use AppserverIo\Doppelgaenger\Entities\Annotations\Aspect as AspectAnnotation;

/**
 * AppserverIo\Appserver\AspectContainer\AspectManager
 *
 * Manager which enables the registration of aspects within a certain application context
 *
 * @category   Server
 * @package    Appserver
 * @subpackage Application
 * @author     Bernhard Wick <bw@appserver.io>
 * @copyright  2014 TechDivision GmbH - <info@appserver.io>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.appserver.io/
 */
class AspectManager implements AspectManagerInterface, ManagerInterface
{

    /**
     * The name of the file which might contain additional pointcuts/advices
     *
     * @var string
     */
    const CONFIG_FILE = 'pointcuts.xml';

    /**
     * The unique identifier to be registered in the application context.
     *
     * @var string
     */
    const IDENTIFIER = 'AspectManager';

    /**
     * The application instance
     *
     * @var \AppserverIo\Psr\Application\ApplicationInterface $application
     */
    protected $application;

    /**
     * The aspect register used for registering the found aspects of this application
     *
     * @var \AppserverIo\Doppelgaenger\AspectRegister $aspectRegister
     */
    protected $aspectRegister;

    /**
     * Path of the directory the webapps lie in
     *
     * @var string $webappPath
     */
    protected $webappPath;

    /**
     * Returns the application instance.
     *
     * @return string The application instance
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Getter for the $aspectRegister property
     *
     * @return \AppserverIo\Doppelgaenger\AspectRegister The aspect register
     */
    public function getAspectRegister()
    {
        return $this->aspectRegister;
    }

    /**
     * Returns the value with the passed name from the context.
     *
     * @param string $key The key of the value to return from the context.
     *
     * @return array<\AppserverIo\Doppelgaenger\Entities\Definitions\Aspect> The aspects found for the given key
     */
    public function getAttribute($key)
    {
        return $this->aspectRegister->lookupAspects($key);
    }

    /**
     * The managers unique identifier.
     *
     * @return string The unique identifier
     */
    public function getIdentifier()
    {
        return self::IDENTIFIER;
    }

    /**
     * Returns the absolute path to the web application.
     *
     * @return string The absolute path
     */
    public function getWebappPath()
    {
        return $this->webappPath;
    }

    /**
     * Has been automatically invoked by the container after the application
     * instance has been created.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    public function initialize(ApplicationInterface $application)
    {
        // register the aspects and tell the class loader it can fill the cache
        $this->registerAspects($application);
        $dgClassLoader = $application->search('DgClassLoader');
        $dgClassLoader->injectAspectRegister($this->getAspectRegister());
        $dgClassLoader->createCache();
    }

    /**
     * Inject the application instance.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    public function injectApplication(ApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Inject the aspect register
     *
     * @param \AppserverIo\Doppelgaenger\AspectRegister $aspectRegister The aspect register instance
     *
     * @return null
     */
    public function injectAspectRegister(AspectRegister $aspectRegister)
    {
        $this->aspectRegister = $aspectRegister;
    }

    /**
     * Injects the absolute path to the web application.
     *
     * @param string $webappPath The absolute path to this web application
     *
     * @return void
     */
    public function injectWebappPath($webappPath)
    {
        $this->webappPath = $webappPath;
    }

    /**
     * Returns a reflection class instance for the passed class name.
     *
     * @param string $className The class name to return the reflection instance for
     *
     * @return \AppserverIo\Lang\Reflection\ReflectionClass The reflection instance
     */
    public function getReflectionClass($className)
    {
        return $this->getApplication()->search('ProviderInterface')->getReflectionClass($className);
    }

    /**
     * Registers the message beans at startup.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    protected function registerAspects(ApplicationInterface $application)
    {
        // try both sources for pointcuts/aspects, XML and class files
        $this->registerAspectClasses($application);
        $this->registerAspectXml($application);
    }

    /**
     * Registers aspects written within source files which we might encounter
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    protected function registerAspectClasses(ApplicationInterface $application)
    {

        // build up our directory vars
        $webappPath = $this->getWebappPath() . DIRECTORY_SEPARATOR;
        $aspectDirectories = array(
            $webappPath . 'META-INF',
            $webappPath . 'WEB-INF',
            $webappPath . 'common'
        );

        // check if we've found a valid directories and get us some iterators
        $iterators = array();
        foreach ($aspectDirectories as $aspectDirectory) {

            if (is_dir($aspectDirectory) === true) {

                $iterators[] = new \RegexIterator(
                    new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($aspectDirectory)),
                    '/^(.+)\.php$/i'
                );
            }
        }

        // iterate all php files
        foreach ($iterators as $phpFiles) {
            foreach ($phpFiles as $phpFile) {

                try {

                    // cut off the META-INF directory and replace OS specific directory separators
                    $relativePathToPhpFile = str_replace(
                        DIRECTORY_SEPARATOR,
                        '\\',
                        str_replace($webappPath, '', $phpFile)
                    );

                    // now cut off the first two directory segments
                    $pregResult = preg_replace('%^\\\\.+?\\\\.+?\\\\%', '', '\\' . $relativePathToPhpFile);
                    $className = substr($pregResult, 0, -4);

                    // we need a reflection class to read the annotations
                    $reflectionClass = $this->getReflectionClass($className);

                    // if we found an aspect we have to register it using our aspect register class
                    if ($reflectionClass->hasAnnotation(AspectAnnotation::ANNOTATION)) {

                        $parser = new AspectParser($phpFile, new Config());
                        $this->aspectRegister->register(
                            $parser->getDefinition($reflectionClass->getShortName(), false)
                        );
                    }

                } catch (\Exception $e) { // if class can not be reflected continue with next class

                    // log an error message
                    $application->getInitialContext()->getSystemLogger()->error($e->__toString());

                    // proceed with the next class
                    continue;
                }
            }
        }
    }

    /**
     * Registers aspects written within source files which we might encounter
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    public function registerAspectXml(ApplicationInterface $application)
    {
        // check if we even have a XMl file to read from
        $xmlPath = $this->getWebappPath() . DIRECTORY_SEPARATOR . 'META-INF' . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
        if (is_readable($xmlPath)) {

            // load the aop config
            $config = new \SimpleXMLElement(file_get_contents($xmlPath));

            // create us an aspect
            // name of the aspect will be the application name
            $aspect = new Aspect();
            $aspect->setName($application->getName());

            // check if we got some pointcuts
            foreach ($config->xpath('/pointcuts/pointcut') as $key => $pointcutConfiguration) {

                // build up the pointcut and add it to the collection
                $pointcut = new Pointcut();
                $pointcut->setAspectName($aspect->getName());
                $pointcut->setName((string) $pointcutConfiguration->{'pointcut-name'});
                $pointcut->setPointcutExpression(new PointcutExpression((string) $pointcutConfiguration->{'pointcut-pattern'}));

                $aspect->getPointcuts()->add($pointcut);
            }

            // check if we got some advices
            foreach ($config->xpath('/pointcuts/advice') as $key => $adviceConfiguration) {

                // build up the advice and add it to the aspect
                $advice = new Advice();
                $advice->setAspectName((string) $adviceConfiguration->{'advice-aspect'});
                $advice->setName((string) $adviceConfiguration->{'advice-name'});
                $advice->setCodeHook((string) $adviceConfiguration->{'advice-type'});

                // there might be several pointcuts
                // we have to look them up within the pointcuts we got here and the ones we already have in our register
                $pointcutFactory = new PointcutFactory();
                foreach ($adviceConfiguration->{'pointcuts'} as $pointcutConfiguration) {

                    $pointcutName = (string) $pointcutConfiguration->{'pointcut'};
                    $pointcutPointcut = $pointcutFactory->getInstance(PointcutPointcut::TYPE . '(' . $pointcutName . ')');

                    // check if we just parsed the referenced pointcut
                    $pointcuts = array();
                    if ($pointcut = $aspect->getPointcuts()->get($pointcutName)) {

                        $pointcuts[] = $pointcut;

                    } else {
                        // or did we already know of it?

                        $pointcuts = $this->getAspectRegister()->lookupPointcuts($pointcutName);
                    }

                    $pointcutPointcut->setReferencedPointcuts($pointcuts);
                    $advice->getPointcuts()->add($pointcutPointcut);
                }

                // finally add the advice to our aspect (we will also add it without pointcuts of its own)
                $aspect->getAdvices()->add($advice);
            }

            // if the aspect contains pointcuts or advices it can be used
            if ($aspect->getPointcuts()->count() > 0 || $aspect->getAdvices()->count() > 0) {

                $this->getAspectRegister()->add($aspect);
            }
        }
    }
}

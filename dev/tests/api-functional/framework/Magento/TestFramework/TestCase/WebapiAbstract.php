<?php
/**
 * Generic test case for Web API functional tests.
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\TestFramework\TestCase;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Webapi\Model\Soap\Fault;

abstract class WebapiAbstract extends \PHPUnit_Framework_TestCase
{
    /** TODO: Reconsider implementation of fixture-management methods after implementing several tests */
    /**#@+
     * Auto tear down options in setFixture
     */
    const AUTO_TEAR_DOWN_DISABLED = 0;
    const AUTO_TEAR_DOWN_AFTER_METHOD = 1;
    const AUTO_TEAR_DOWN_AFTER_CLASS = 2;
    /**#@-*/

    /**#@+
     * Web API adapters that are used to perform actual calls.
     */
    const ADAPTER_SOAP = 'soap';
    const ADAPTER_REST = 'rest';
    /**#@-*/

    /**
     * Application cache model.
     *
     * @var \Magento\Framework\App\Cache
     */
    protected $_appCache;

    /**
     * The list of models to be deleted automatically in tearDown().
     *
     * @var array
     */
    protected $_modelsToDelete = [];

    /**
     * Namespace for fixtures is different for each test case.
     *
     * @var string
     */
    protected static $_fixturesNamespace;

    /**
     * The list of registered fixtures.
     *
     * @var array
     */
    protected static $_fixtures = [];

    /**
     * Fixtures to be deleted in tearDown().
     *
     * @var array
     */
    protected static $_methodLevelFixtures = [];

    /**
     * Fixtures to be deleted in tearDownAfterClass().
     *
     * @var array
     */
    protected static $_classLevelFixtures = [];

    /**
     * Original Magento config values.
     *
     * @var array
     */
    protected $_origConfigValues = [];

    /**
     * The list of instantiated Web API adapters.
     *
     * @var \Magento\TestFramework\TestCase\Webapi\AdapterInterface[]
     */
    protected $_webApiAdapters;

    /**
     * The list of available Web API adapters.
     *
     * @var array
     */
    protected $_webApiAdaptersMap = [
        self::ADAPTER_SOAP => 'Magento\TestFramework\TestCase\Webapi\Adapter\Soap',
        self::ADAPTER_REST => 'Magento\TestFramework\TestCase\Webapi\Adapter\Rest',
    ];

    /**
     * Initialize fixture namespaces.
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::_setFixtureNamespace();
    }

    /**
     * Run garbage collector for cleaning memory
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        //clear garbage in memory
        if (version_compare(PHP_VERSION, '5.3', '>=')) {
            gc_collect_cycles();
        }

        $fixtureNamespace = self::_getFixtureNamespace();
        if (isset(self::$_classLevelFixtures[$fixtureNamespace])
            && count(self::$_classLevelFixtures[$fixtureNamespace])
        ) {
            self::_deleteFixtures(self::$_classLevelFixtures[$fixtureNamespace]);
        }

        //ever disable secure area on class down
        self::_enableSecureArea(false);
        self::_unsetFixtureNamespace();
        parent::tearDownAfterClass();
    }

    /**
     * Call safe delete for models which added to delete list
     * Restore config values changed during the test
     *
     * @return void
     */
    protected function tearDown()
    {
        $fixtureNamespace = self::_getFixtureNamespace();
        if (isset(self::$_methodLevelFixtures[$fixtureNamespace])
            && count(self::$_methodLevelFixtures[$fixtureNamespace])
        ) {
            self::_deleteFixtures(self::$_methodLevelFixtures[$fixtureNamespace]);
        }
        $this->_callModelsDelete();
        $this->_restoreAppConfig();
        parent::tearDown();
    }

    /**
     * Perform Web API call to the system under test.
     *
     * @see \Magento\TestFramework\TestCase\Webapi\AdapterInterface::call()
     * @param array $serviceInfo
     * @param array $arguments
     * @param string|null $webApiAdapterCode
     * @return array|int|string|float|bool Web API call results
     */
    protected function _webApiCall($serviceInfo, $arguments = [], $webApiAdapterCode = null)
    {
        if (is_null($webApiAdapterCode)) {
            /** Default adapter code is defined in PHPUnit configuration */
            $webApiAdapterCode = strtolower(TESTS_WEB_API_ADAPTER);
        }
        return $this->_getWebApiAdapter($webApiAdapterCode)->call($serviceInfo, $arguments);
    }

    /**
     * Mark test to be executed for SOAP adapter only.
     */
    protected function _markTestAsSoapOnly($message = null)
    {
        if (TESTS_WEB_API_ADAPTER != self::ADAPTER_SOAP) {
            $this->markTestSkipped($message ? $message : "The test is intended to be executed for SOAP adapter only.");
        }
    }

    /**
     * Mark test to be executed for REST adapter only.
     */
    protected function _markTestAsRestOnly($message = null)
    {
        if (TESTS_WEB_API_ADAPTER != self::ADAPTER_REST) {
            $this->markTestSkipped($message ? $message : "The test is intended to be executed for REST adapter only.");
        }
    }

    /**
     * Set fixture to registry
     *
     * @param string $key
     * @param mixed $fixture
     * @param int $tearDown
     * @return void
     */
    public static function setFixture($key, $fixture, $tearDown = self::AUTO_TEAR_DOWN_AFTER_METHOD)
    {
        $fixturesNamespace = self::_getFixtureNamespace();
        if (!isset(self::$_fixtures[$fixturesNamespace])) {
            self::$_fixtures[$fixturesNamespace] = [];
        }
        self::$_fixtures[$fixturesNamespace][$key] = $fixture;
        if ($tearDown == self::AUTO_TEAR_DOWN_AFTER_METHOD) {
            if (!isset(self::$_methodLevelFixtures[$fixturesNamespace])) {
                self::$_methodLevelFixtures[$fixturesNamespace] = [];
            }
            self::$_methodLevelFixtures[$fixturesNamespace][] = $key;
        } else {
            if ($tearDown == self::AUTO_TEAR_DOWN_AFTER_CLASS) {
                if (!isset(self::$_classLevelFixtures[$fixturesNamespace])) {
                    self::$_classLevelFixtures[$fixturesNamespace] = [];
                }
                self::$_classLevelFixtures[$fixturesNamespace][] = $key;
            }
        }
    }

    /**
     * Get fixture by key
     *
     * @param string $key
     * @return mixed
     */
    public static function getFixture($key)
    {
        $fixturesNamespace = self::_getFixtureNamespace();
        if (array_key_exists($key, self::$_fixtures[$fixturesNamespace])) {
            return self::$_fixtures[$fixturesNamespace][$key];
        }
        return null;
    }

    /**
     * Call safe delete for model
     *
     * @param \Magento\Framework\Model\AbstractModel $model
     * @param bool $secure
     * @return \Magento\TestFramework\TestCase\WebapiAbstract
     */
    public static function callModelDelete($model, $secure = false)
    {
        if ($model instanceof \Magento\Framework\Model\AbstractModel && $model->getId()) {
            if ($secure) {
                self::_enableSecureArea();
            }
            $model->delete();
            if ($secure) {
                self::_enableSecureArea(false);
            }
        }
    }

    /**
     * Call safe delete for model
     *
     * @param \Magento\Framework\Model\AbstractModel $model
     * @param bool $secure
     * @return \Magento\TestFramework\TestCase\WebapiAbstract
     */
    public function addModelToDelete($model, $secure = false)
    {
        $this->_modelsToDelete[] = ['model' => $model, 'secure' => $secure];
        return $this;
    }

    /**
     * Get Web API adapter (create if requested one does not exist).
     *
     * @param string $webApiAdapterCode
     * @return \Magento\TestFramework\TestCase\Webapi\AdapterInterface
     * @throws \LogicException When requested Web API adapter is not declared
     */
    protected function _getWebApiAdapter($webApiAdapterCode)
    {
        if (!isset($this->_webApiAdapters[$webApiAdapterCode])) {
            if (!isset($this->_webApiAdaptersMap[$webApiAdapterCode])) {
                throw new \LogicException(
                    sprintf('Declaration of the requested Web API adapter "%s" was not found.', $webApiAdapterCode)
                );
            }
            $this->_webApiAdapters[$webApiAdapterCode] = new $this->_webApiAdaptersMap[$webApiAdapterCode]();
        }
        return $this->_webApiAdapters[$webApiAdapterCode];
    }

    /**
     * Set fixtures namespace
     *
     * @throws \RuntimeException
     */
    protected static function _setFixtureNamespace()
    {
        if (!is_null(self::$_fixturesNamespace)) {
            throw new \RuntimeException('Fixture namespace is already set.');
        }
        self::$_fixturesNamespace = uniqid();
    }

    /**
     * Unset fixtures namespace
     */
    protected static function _unsetFixtureNamespace()
    {
        $fixturesNamespace = self::_getFixtureNamespace();
        unset(self::$_fixtures[$fixturesNamespace]);
        self::$_fixturesNamespace = null;
    }

    /**
     * Get fixtures namespace
     *
     * @throws \RuntimeException
     * @return string
     */
    protected static function _getFixtureNamespace()
    {
        $fixtureNamespace = self::$_fixturesNamespace;
        if (is_null($fixtureNamespace)) {
            throw new \RuntimeException('Fixture namespace must be set.');
        }
        return $fixtureNamespace;
    }

    /**
     * Enable secure/admin area
     *
     * @param bool $flag
     * @return void
     */
    protected static function _enableSecureArea($flag = true)
    {
        /** @var $objectManager \Magento\TestFramework\ObjectManager */
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $objectManager->get('Magento\Framework\Registry')->unregister('isSecureArea');
        if ($flag) {
            $objectManager->get('Magento\Framework\Registry')->register('isSecureArea', $flag);
        }
    }

    /**
     * Call delete models from list
     *
     * @return \Magento\TestFramework\TestCase\WebapiAbstract
     */
    protected function _callModelsDelete()
    {
        if ($this->_modelsToDelete) {
            foreach ($this->_modelsToDelete as $key => $modelData) {
                /** @var $model \Magento\Framework\Model\AbstractModel */
                $model = $modelData['model'];
                $this->callModelDelete($model, $modelData['secure']);
                unset($this->_modelsToDelete[$key]);
            }
        }
        return $this;
    }

    /**
     * Check if all error messages are expected ones
     *
     * @param array $expectedMessages
     * @param array $receivedMessages
     */
    protected function _assertMessagesEqual($expectedMessages, $receivedMessages)
    {
        foreach ($receivedMessages as $message) {
            $this->assertContains($message, $expectedMessages, "Unexpected message: '{$message}'");
        }
        $expectedErrorsCount = count($expectedMessages);
        $this->assertCount($expectedErrorsCount, $receivedMessages, 'Invalid messages quantity received');
    }

    /**
     * Delete array of fixtures
     *
     * @param array $fixtures
     */
    protected static function _deleteFixtures($fixtures)
    {
        foreach ($fixtures as $fixture) {
            self::deleteFixture($fixture, true);
        }
    }

    /**
     * Delete fixture by key
     *
     * @param string $key
     * @param bool $secure
     * @return void
     */
    public static function deleteFixture($key, $secure = false)
    {
        $fixturesNamespace = self::_getFixtureNamespace();
        if (array_key_exists($key, self::$_fixtures[$fixturesNamespace])) {
            self::callModelDelete(self::$_fixtures[$fixturesNamespace][$key], $secure);
            unset(self::$_fixtures[$fixturesNamespace][$key]);
        }
    }

    /** TODO: Remove methods below if not used, otherwise fix them (after having some tests implemented)*/

    /**
     * Get application cache model
     *
     * @return \Magento\Framework\App\Cache
     */
    protected function _getAppCache()
    {
        if (null === $this->_appCache) {
            //set application path
            $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
            /** @var \Magento\Framework\App\Config\ScopeConfigInterface $config */
            $config = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
            $options = $config->getOptions();
            $currentCacheDir = $options->getCacheDir();
            $currentEtcDir = $options->getEtcDir();
            /** @var Filesystem $filesystem */
            $filesystem = $objectManager->get('Magento\Framework\Filesystem');
            $options->setCacheDir($filesystem->getDirectoryRead(DirectoryList::CACHE)->getAbsolutePath());
            $options->setEtcDir($filesystem->getDirectoryRead(DirectoryList::CONFIG)->getAbsolutePath());

            $this->_appCache = $objectManager->get('Magento\Framework\App\Cache');

            //revert paths options
            $options->setCacheDir($currentCacheDir);
            $options->setEtcDir($currentEtcDir);
        }
        return $this->_appCache;
    }

    /**
     * Clean config cache of application
     *
     * @return bool
     */
    protected function _cleanAppConfigCache()
    {
        return $this->_getAppCache()->clean(\Magento\Framework\App\Config::CACHE_TAG);
    }

    /**
     * Update application config data
     *
     * @param string $path              Config path with the form "section/group/node"
     * @param string|int|null $value    Value of config item
     * @param bool $cleanAppCache       If TRUE application cache will be refreshed
     * @param bool $updateLocalConfig   If TRUE local config object will be updated too
     * @param bool $restore             If TRUE config value will be restored after test run
     * @return \Magento\TestFramework\TestCase\WebapiAbstract
     * @throws \RuntimeException
     */
    protected function _updateAppConfig(
        $path,
        $value,
        $cleanAppCache = true,
        $updateLocalConfig = false,
        $restore = false
    ) {
        list($section, $group, $node) = explode('/', $path);

        if (!$section || !$group || !$node) {
            throw new \RuntimeException(
                sprintf('Config path must have view as "section/group/node" but now it "%s"', $path)
            );
        }

        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        /** @var $config \Magento\Backend\Model\Config */
        $config = $objectManager->create('Magento\Backend\Model\Config');
        $data[$group]['fields'][$node]['value'] = $value;
        $config->setSection($section)->setGroups($data)->save();

        if ($restore && !isset($this->_origConfigValues[$path])) {
            $this->_origConfigValues[$path] = (string)$objectManager->get(
                'Magento\Framework\App\Config\ScopeConfigInterface'
            )->getNode(
                $path,
                'default'
            );
        }

        //refresh local cache
        if ($cleanAppCache) {
            if ($updateLocalConfig) {
                $objectManager->get('Magento\Framework\App\Config\ReinitableConfigInterface')->reinit();
                $objectManager->get('Magento\Store\Model\StoreManagerInterface')->reinitStores();
            }

            if (!$this->_cleanAppConfigCache()) {
                throw new \RuntimeException('Application configuration cache cannot be cleaned.');
            }
        }

        return $this;
    }

    /**
     * Restore config values changed during tests
     */
    protected function _restoreAppConfig()
    {
        foreach ($this->_origConfigValues as $configPath => $origValue) {
            $this->_updateAppConfig($configPath, $origValue, true, true);
        }
    }

    /**
     * @param \Exception $e
     * @return array
     * <pre> ex.
     * 'message' => "No such entity with %fieldName1 = %value1, %fieldName2 = %value2"
     * 'parameters' => [
     *      "fieldName1" => "email",
     *      "value1" => "dummy@example.com",
     *      "fieldName2" => "websiteId",
     *      "value2" => 0
     * ]
     *
     * </pre>
     */
    public function processRestExceptionResult(\Exception $e)
    {
        $error = json_decode($e->getMessage(), true);
        //Remove line breaks and replace with space
        $error['message'] = trim(preg_replace('/\s+/', ' ', $error['message']));
        // remove trace and type, will only be present if server is in dev mode
        unset($error['trace']);
        unset($error['type']);
        return $error;
    }

    /**
     * Verify that SOAP fault contains necessary information.
     *
     * @param \SoapFault $soapFault
     * @param string $expectedMessage
     * @param string $expectedFaultCode
     * @param array $expectedErrorParams
     * @param array $expectedWrappedErrors
     * @param string $traceString
     */
    protected function checkSoapFault(
        $soapFault,
        $expectedMessage,
        $expectedFaultCode,
        $expectedErrorParams = [],
        $expectedWrappedErrors = [],
        $traceString = null
    ) {
        $this->assertContains($expectedMessage, $soapFault->getMessage(), "Fault message is invalid.");

        $errorDetailsNode = 'GenericFault';
        $errorDetails = isset($soapFault->detail->$errorDetailsNode) ? $soapFault->detail->$errorDetailsNode : null;
        if (!empty($expectedErrorParams) || !empty($expectedWrappedErrors)) {
            /** Check SOAP fault details */
            $this->assertNotNull($errorDetails, "Details must be present.");
            $this->_checkFaultParams($expectedErrorParams, $errorDetails);
            $this->_checkWrappedErrors($expectedWrappedErrors, $errorDetails);
        }

        if ($traceString) {
            /** Check error trace */
            $traceNode = Fault::NODE_DETAIL_TRACE;
            $mode = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get('Magento\Framework\App\State')
                ->getMode();
            if ($mode == \Magento\Framework\App\State::MODE_DEVELOPER) {
                /** Developer mode changes tested behavior and it cannot properly be tested for now */
                $this->assertContains(
                    $traceString,
                    $errorDetails->$traceNode,
                    'Trace Information is incorrect.'
                );
            } else {
                $this->assertNull($errorDetails, "Details are not expected.");
            }
        }

        /** Check SOAP fault code */
        $this->assertNotNull($soapFault->faultcode, "Fault code must not be empty.");
        $this->assertEquals($expectedFaultCode, $soapFault->faultcode, "Fault code is invalid.");
    }

    /**
     * Check additional error parameters.
     *
     * @param array $expectedErrorParams
     * @param \stdClass $errorDetails
     */
    protected function _checkFaultParams($expectedErrorParams, $errorDetails)
    {
        $paramsNode = Fault::NODE_DETAIL_PARAMETERS;
        if ($expectedErrorParams) {
            $paramNode = Fault::NODE_DETAIL_PARAMETER;
            $paramKey = Fault::NODE_DETAIL_PARAMETER_KEY;
            $paramValue = Fault::NODE_DETAIL_PARAMETER_VALUE;
            $actualParams = [];
            if (isset($errorDetails->$paramsNode->$paramNode)) {
                if (is_array($errorDetails->$paramsNode->$paramNode)) {
                    foreach ($errorDetails->$paramsNode->$paramNode as $param) {
                        $actualParams[$param->$paramKey] = $param->$paramValue;
                    }
                } else {
                    $param = $errorDetails->$paramsNode->$paramNode;
                    $actualParams[$param->$paramKey] = $param->$paramValue;
                }
            }
            $this->assertEquals(
                $expectedErrorParams,
                $actualParams,
                "Parameters in fault details are invalid."
            );
        } else {
            $this->assertFalse(isset($errorDetails->$paramsNode), "Parameters are not expected in fault details.");
        }
    }

    /**
     * Check additional wrapped errors.
     *
     * @param array $expectedWrappedErrors
     * @param \stdClass $errorDetails
     */
    protected function _checkWrappedErrors($expectedWrappedErrors, $errorDetails)
    {
        $wrappedErrorsNode = Fault::NODE_DETAIL_WRAPPED_ERRORS;
        if ($expectedWrappedErrors) {
            $wrappedErrorNode = Fault::NODE_DETAIL_WRAPPED_ERROR;
            $wrappedErrorNodeFieldName = 'fieldName';
            $wrappedErrorNodeValue = Fault::NODE_DETAIL_WRAPPED_ERROR_VALUE;
            $actualWrappedErrors = [];
            if (isset($errorDetails->$wrappedErrorsNode->$wrappedErrorNode)) {
                if (is_array($errorDetails->$wrappedErrorsNode->$wrappedErrorNode)) {
                    foreach ($errorDetails->$wrappedErrorsNode->$wrappedErrorNode as $error) {
                        $actualParameters = [];
                        foreach ($error->parameters->parameter as $parameter) {
                            $actualParameters[$parameter->key] = $parameter->value;
                        }
                        $actualWrappedErrors[] = [
                            'message' => $error->message,
                            'params' => $actualParameters,
                        ];
                    }
                } else {
                    $error = $errorDetails->$wrappedErrorsNode->$wrappedErrorNode;
                    $actualWrappedErrors[] = [
                        "fieldName" => $error->$wrappedErrorNodeFieldName,
                        "value" => $error->$wrappedErrorNodeValue,
                    ];
                }
            }
            $this->assertEquals(
                $expectedWrappedErrors,
                $actualWrappedErrors,
                "Wrapped errors in fault details are invalid."
            );
        } else {
            $this->assertFalse(
                isset($errorDetails->$wrappedErrorsNode),
                "Wrapped errors are not expected in fault details."
            );
        }
    }
}
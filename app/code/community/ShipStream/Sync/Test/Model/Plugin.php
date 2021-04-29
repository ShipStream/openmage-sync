<?php

/**
 * @loadSharedFixture ~MWE_Core/core.yaml
 * @loadSharedFixture ~MWE_Core/admin.yaml
 * @loadSharedFixture ~MWE_Core/catalog.yaml
 */
class ShipStream_Sync_Test_Model_Plugin extends MWE_Core_Test_Case
{
    public function setUp(): void
    {
        parent::setUp();
        $this->_cleanup();
        Mage::helper('plugin/user')->createUser(1);
        Mage::app()->getWebsite(1)->setConfig(MWE_Plugin_Model_Queue::XML_PATH_ENABLED, TRUE);
        Mage::app()->getStore(1)->setConfig('carriers/ups/active', 1);
        Mage::app()->getStock(1)->setConfig('carriers/ups/active', 1);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->_cleanup();
    }

    /**
     * @loadFixture ~MWE_Core/inventory.yaml
     * @loadExpectation plugin.yaml
     */
    public function testAutoFulfillment()
    {
        Mage::app()->getWebsite(1)->setConfig('plugin/phpunit_test/auto_fulfill', 'pending');
        $this->_getPluginInstance()->sync_orders();
        $this->assertEquals(self::expected('auto_fulfillment_queue')[0]['data'], $this->_getQueue()->getData('data'));
    }

    /**
     * @loadFixture ~MWE_Core/inventory.yaml
     * @loadExpectation plugin.yaml
     */
    public function testManualFulfillment()
    {
        Mage::app()->getWebsite(1)->setConfig('plugin/phpunit_test/auto_fulfill', '');
        $this->_getPluginInstance()->sync_orders();
        $this->assertEquals(self::expected('manual_fulfillment_queue')[0]['data'], $this->_getQueue()->getData('data'));
    }

    /**
     * @loadFixture ~MWE_Core/inventory.yaml
     * @loadExpectation plugin.yaml
     */
    public function testImportOrderEvent()
    {
        $this->_getPluginInstance()->importOrderEvent(new Varien_Object(['increment_id' => '100000111']));
        $this->assertEquals(self::expected('create_order_queue')[0]['data'], $this->_getQueue()->getData('data'));
    }

    /**
     * @loadFixture ~MWE_Core/inventory.yaml
     * @loadExpectation plugin.yaml
     */
    public function testImportShipmentEvent()
    {
        $this->_getPluginInstance()->importShipmentEvent(new Varien_Object(['increment_id' => '100000003']));
        $order = Mage::getModel('sales/order')->loadByIncrementId('100000003', 1);
        $this->assertNotNull($order->getId());
    }

    /**
     * Retrieve plugin queue object instance for "runPluginMethod" method
     *
     * @return null|MWE_Plugin_Model_Queue
     */
    protected function _getQueue()
    {
        $queueCollection = Mage::getResourceModel('plugin/queue_collection');
        $queue = $queueCollection->getItemByColumnValue('method', 'runPluginMethod'); /** @var $queue MWE_Plugin_Model_Queue */
        $this->assertNotNull($queue->getId());
        return $queue;
    }

    /**
     * Retrieve ShipStream_Sync_Plugin instance with mocked client API call method.
     * Fake API data is loaded without applying filtering for the specified API method.
     *
     * @return PHPUnit_Framework_MockObject_MockObject|ShipStream_Sync_Plugin
     */
    protected function _getPluginInstance()
    {
        /** @var $pluginMock ShipStream_Sync_Plugin|PHPUnit_Framework_MockObject_MockObject */
        $pluginMock = $this->getMockBuilder('ShipStream_Sync_Plugin')
            ->setConstructorArgs(array('phpunit_test'))
            ->setMethods(['_clientApi'])
            ->getMock();
        $pluginMock->expects($this->any())
            ->method('_clientApi')
            ->willReturnCallback(function($method, $args = array(), $canRetry = TRUE) {
                return $this->_getExpectedData($method, $args);
            });
        $pluginMock->setSubscription(new Varien_Object(['id' => 1, 'website_id' => 1, 'plugin_code' => 'phpunit_test']));
        return $pluginMock;
    }

    /**
     * @param string $method
     * @param string|int|array $args
     * @return mixed
     */
    protected function _getExpectedData($method, $args)
    {
        $data = self::expected($method)->getData();
        switch ($method) {
            case 'order.info':
            case 'shipment.info':
            case 'shipstream_order_shipment.info':
                return $data[0];
            case 'order_shipment.create':
                return $data[0]['increment_id'];
        }
        return $data;
    }

    protected function _cleanup()
    {
        Mage::getSingleton('plugin/observer')->cleanupTables();
        Mage::getConfig()->deleteConfig(MWE_Plugin_Helper_User::XML_PATH_USER_ID, 'websites', 1);
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
    }
}

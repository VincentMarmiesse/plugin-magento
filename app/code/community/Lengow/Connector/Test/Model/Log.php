<?php

class Lengow_Connector_Test_Model_Log extends EcomDev_PHPUnit_Test_Case
{

    /**
     * Test log model
     *
     * @test
     * @loadFixture
     * @doNotIndexAll
     */
    public function model()
    {
        $collection = Mage::getModel('lengow/log')->getCollection();
        foreach($collection as $log){
            $this->assertRegExp('/Test message/',$log->getMessage(), '[Log - Read] Message data should contain "Test message"');
        }
        $this->assertEquals(count($collection), 3, '[Log - Fixture] contain 3 messages');

        $data = array(
            'created_at' => '2015-09-01 23:59:59',
            'message' => 'Test message 4'
        );
        $model = Mage::getModel('lengow/log')->setData($data);
        $insertId = $model->save()->getId();
        $this->assertTrue($insertId > 0, '[Log - Insert] Insert data into log');

        $insertLog = Mage::helper('lengow_connector')->log('Test message by helper');
        $this->assertTrue((boolean)$insertLog, '[Log - Helper Insert] Insert message');

        $log = Mage::getModel('lengow/log')->load($insertLog->getId());
        $this->assertEquals($log->getId(), $insertLog->getId(), '[Log - Helper Insert] check data');
        $this->assertEquals($log->getMessage(), 'Test message by helper', '[Log - Helper Insert] check data');
    }

    /**
     * Test log
     *
     * @loadFixture write.yaml
     * @test
     */
    public function write()
    {
        $model = Mage::getModel('lengow/log');
        $model->write('Category', 'Test Message 1');
        $model->write('Category', 'Test Message 2');
        $model->write('Category', 'Test Message 3');

        $collection = Mage::getModel('lengow/log')->getCollection();

        $this->assertEquals('[Category] Test Message 1', $collection->getFirstItem()->getMessage());
        $this->assertEquals(count($collection), 3);
    }


    /**
     * Empty logs
     *
     * @test
     * @loadFixture empty_log.yaml
     * @doNotIndexAll
     */
    public function emptyLog()
    {
        $model = Mage::getModel('lengow/log');
        $model->write('Category', 'Test Message 4');
        $model->write('Category', 'Test Message 5');
        $model->write('Category', 'Test Message 6');

        $collection = Mage::getModel('lengow/log')->getCollection();
        $this->assertEquals(count($collection), 6);
        $model->cleanLog();

        $collection = Mage::getModel('lengow/log')->getCollection();
        $this->assertEquals(count($collection), 3);
    }

}
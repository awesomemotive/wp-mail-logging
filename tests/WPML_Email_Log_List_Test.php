<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Tests\Helper\WPML_UnitTestCase;
use No3x\WPML\WPML_Email_Log_List;

/**
 * Created by IntelliJ IDEA.
 * User: czoeller
 * Date: 02.08.16
 * Time: 18:49
 */
class WPML_Email_Log_List_Test extends WPML_UnitTestCase {

    private $logList;

    /**
     * @param WPML_Email_Log_List $logList
     */
    public function setLogList(WPML_Email_Log_List $logList)
    {
        $this->logList = $logList;
    }

    /**
     * @test
     */
    public function test_func() {
        $this->assertTrue(true);
    }

}
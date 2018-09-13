<?php

namespace No3x\WPML\Tests\Unit;

use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\ORM\DefaultQueryFactory;
use No3x\WPML\ORM\QueryFactory;
use No3x\WPML\Tests\Helper\WPML_IntegrationTestCase;
use No3x\WPML\WPML_PrivacyController;

/**
 * Class WPML_MailExtractor_Test tests the function of the privacy integration
 * @package No3x\WPML\Tests
 */
class WPML_PrivacyController_Test extends \PHPUnit_Framework_TestCase {

    const emailAddress = 'example@example.com';

    /** @var WPML_PrivacyController */
    private $privacyController;

    /** @var \No3x\WPML\ORM\Query|\PHPUnit_Framework_MockObject_MockObject $queryMock */
    private $queryMock;

    function setUp() {
        parent::setUp();

        $this->queryMock = self::getMockBuilder('No3x\WPML\ORM\Query')
            ->disableOriginalConstructor()
            ->setMethods(['find'])
            ->getMock()
        ;

        WPML_Mail::setQueryFactory(new QueryMockFactory($this->queryMock));

        $this->privacyController = new WPML_PrivacyController(null);
    }

    public function testQueryMockFactory() {
        $this->assertInstanceOf('No3x\WPML\ORM\Query', WPML_Mail::query());
    }

    private function mockMail($id) {
        $mail1 = self::getMockBuilder('No3x\WPML\ORM\Query')
            ->disableOriginalConstructor()
            ->setMethods(['get_mail_id', 'to_array', 'delete'])
            ->getMock()
        ;

        $mail1->expects(self::any())
            ->method('get_mail_id')
            ->willReturn($id)
        ;

        $mail1->expects(self::any())
            ->method('to_array')
            ->willReturn(['mail_id' => $id])
        ;

        $mail1->expects(self::any())
            ->method('delete')
            ->willReturn(true)
        ;

        return $mail1;
    }

    private function setupPrivacyControllerDataQuery() {

        $mail1 = $this->mockMail(1);
        $mail2 = $this->mockMail(2);

        $data = [$mail1, $mail2];

        $this->queryMock->expects(self::once())
            ->method('find')
            ->willReturn($data)
        ;

        $idsOfMocks = [1, 2];

        return $idsOfMocks;
    }

    public function testExport() {
        $idsExpected = $this->setupPrivacyControllerDataQuery();

        $export = $this->privacyController->export(self::emailAddress);

        $idsActual = [
            $export['data'][0]['data'][0]['value'],
            $export['data'][1]['data'][0]['value']
        ];

        $this->assertEquals($idsExpected, $idsActual);
        $this->assertTrue($export['done']);
    }

    public function testErasure() {
        $this->setupPrivacyControllerDataQuery();

        $erase = $this->privacyController->erase(self::emailAddress);

        $this->assertTrue($erase['done']);
        $this->assertTrue($erase['items_removed']);
        $this->assertFalse($erase['items_retained']);
    }


}

class QueryMockFactory implements QueryFactory {

    private $queryMock;

    public function __construct($queryMock) {
        $this->queryMock = $queryMock;
    }

    public function buildQuery($modelClass) {
        return $this->queryMock;
    }
}

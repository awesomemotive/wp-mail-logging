<?php

namespace No3x\WPML\Tests;

use No3x\WPML\Model\WPML_Mail;
use No3x\WPML\ORM\QueryFactory;
use No3x\WPML\Tests\Helper\WPML_UnitTestCase;
use No3x\WPML\WPML_PrivacyController;

/**
 * Class WPML_MailExtractor_Test tests the function of the privacy integration
 * @package No3x\WPML\Tests
 */
class WPML_PrivacyController_Test extends WPML_UnitTestCase {

    /** @var WPML_PrivacyController */
    private $privacyController;

    /** @var \No3x\WPML\ORM\Query|\PHPUnit_Framework_MockObject_MockObject $queryMock */
    private $queryMock;

    function setUp() {
        parent::setUp();

        $this->queryMock = self::getMockBuilder('No3x\WPML\ORM\Query')
            ->disableOriginalConstructor()
            ->setMethods(['search', 'find'])
            ->getMock()
        ;

        WPML_Mail::setQueryFactory(new QueryMockFactory($this->queryMock));

        $this->privacyController = new WPML_PrivacyController();
    }

    public function testQueryMockFactory() {
        $this->assertInstanceOf('No3x\WPML\ORM\Query', WPML_Mail::query());
    }

    public function testExport() {

        $email_address = 'example@example.com';

        $this->queryMock->expects(self::exactly(2))
            ->method('search')
            ->with($email_address)
            ->willReturn($this->queryMock->search($email_address))
        ;

        $this->queryMock->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $this->privacyController->export($email_address);

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

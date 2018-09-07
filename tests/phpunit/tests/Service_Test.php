<?php

namespace No3x\WPML\Tests;

class Builder {
    private $name = "default name";
    private $email = "default@example.com";

    public function withName($name) {
        $this->name = $name;
        return $this;
    }

    public function withEmail($email) {
        $this->email = $email;
        return $this;
    }

    public function build() {
        return new Person($this->name, $this->email);
    }
}

class Person {
    public $name;
    public $email;

    public function __construct($name, $email) {
        $this->name = $name;
        $this->email = $email;
    }
}

class Service {
    private $builder;

    public function __construct(Builder $builder) {
        $this->builder = $builder;
    }

    public function doSomething($email) {
        $person = $this->builder->withEmail($email)->build();
        // Yes, json_encode is the only functionality to test in this simplified example
        return json_encode($person);
    }
}

class Service_Test extends \PHPUnit_Framework_TestCase {

    /** @var Service */
    private $sut;

    /** @var Builder|\PHPUnit_Framework_MockObject_MockObject $builderMock */
    private $builderMock;

    function setUp() {
        parent::setUp();

        $this->builderMock = self::getMockBuilder('Builder')
            ->disableOriginalConstructor()
            ->setMethods(['withEmail', 'build'])
            ->getMock()
        ;

        $this->sut = new Service($this->builderMock);
    }

    public function test_doSomething() {

        $email = "example@example.com";
        $expectedPerson = new Person("P", $email);

        // test the builder is called with the right parameter (simplified in this example email only)
        $this->builderMock->expects(self::once())
            ->method('withEmail')
            ->with($email)
        ;

        // But don't care what the builder is building and specify return
        $this->builderMock->expects(self::once())
            ->method('build')
            ->willReturn($expectedPerson)
        ;

        // This builder is actually called in other class that is omitted in this example
        $actualJson = $this->sut->doSomething($email);

        // Test
        // Yes, verify functionality with same implementation 'json_encode' in this example
        $this->assertEquals(json_encode($expectedPerson), $actualJson);
    }
}

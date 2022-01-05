<?php

namespace MhsDesign\FusionTypeHints\Tests\Functional;

use MhsDesign\FusionTypeHints\Fusion\RuntimeTypeException;
use MhsDesign\FusionTypeHints\Fusion\TypedRuntime;
use MhsDesign\FusionTypeHints\Tests\Functional\Fixtures\DummyClass;
use Neos\Flow\Mvc\Controller\ControllerContext;
use PHPUnit\Framework\TestCase;
use Neos\Fusion\Core\Parser;

class TestRuntimeTypeHints extends TestCase
{
    public function correctTypePasses()
    {
        yield 'simpleValueString' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                root.@type = 'string'
                root = "hello"
                Fusion,
            'output' => 'hello'
        ];

        $dummyClassObject = new DummyClass();
        $dummyClassEscapedName = addslashes(DummyClass::class);

        yield 'objectInstanceCheck' => [
            'context' => [
                'foo' => $dummyClassObject
            ],
            'fusion' => sprintf(<<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion
                root = Neos.Fusion:Value {
                    foo = ${foo}
                    foo.@type = "%s"
                    value = ${this.foo.bar + ' hello'}
                }
                Fusion, $dummyClassEscapedName),
            'output' => 'bar hello'
        ];

        yield 'returnValueCheck' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion
                root = Neos.Fusion:Value {
                    value = "hello"
                    @return = 'string'
                }
                Fusion,
            'output' => 'hello'
        ];

        yield 'nullAbleString' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion
                root = Neos.Fusion:Join {
                    foo = null
                    foo.@type = '?string'
                    bar = "bar"
                    bar.@type = '?string'
                }
                Fusion,
            'output' => 'bar'
        ];

        yield 'arrayOfStrings' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion
                root = Neos.Fusion:Tag {
                    attributes = Neos.Fusion:DataStructure {
                        a = "a"
                        b = "b"
                        c = "c"
                    }
                    attributes.@type = 'string[]'
                }
                Fusion,
            'output' => '<div a="a" b="b" c="c"></div>'
        ];
    }

    public function nestedSubkeysOfDataStructure()
    {
        yield 'noTypeInNestedKey' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion
                root = Neos.Fusion:Tag {
                    attributes = Neos.Fusion:DataStructure {
                        a {
                            e = "e"
                            f = "f"
                        }
                        b = "b"
                        c = "c"
                    }
                    attributes.@type = 'array'
                }
                Fusion,
            'output' => '<div a="e f" b="b" c="c"></div>'
        ];

        yield 'typesOfNestedKeyWithExplicitDataStructure' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion
                root = Neos.Fusion:Tag {
                    attributes = Neos.Fusion:DataStructure {
                        a = Neos.Fusion:DataStructure {
                            e = "e"
                            f = "f"
                        }
                        a.@type = 'string[]'
                        b = "b"
                        c = "c"
                    }
                    attributes.@type = 'array'
                }
                Fusion,
            'output' => '<div a="e f" b="b" c="c"></div>'
        ];

        // TODO: fails - related to issues #3441 and #3513
        yield 'typesOfNestedKeyWithAssumedSubkeys' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion
                root = Neos.Fusion:Tag {
                    attributes = Neos.Fusion:DataStructure {
                        a {
                            e = "e"
                            f = "f"
                        }
                        a.@type = 'string[]'
                        b = "b"
                        c = "c"
                    }
                }
                Fusion,
            'output' => '<div a="e f" b="b" c="c"></div>'
        ];
    }

    public function wrongTypeThrows()
    {
        yield 'simpleValueStringWhenIntExpected' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                root.@type = 'string'
                root = 123456
                Fusion,
            'message' => "Runtime Type checking for 'root': Assertion failed because integer literal 123456 is incompatible with string"
        ];

        yield 'requiredArgumentCheck' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                include: resource://Neos.Fusion/Private/Fusion/Root.fusion

                prototype(Foo.Bar:Required) < prototype(Neos.Fusion:Component) {
                    name.@type = 'string'

                    renderer = afx`
                        {props.name}
                    `
                }

                root = Foo.Bar:Required
                Fusion,
            'message' => "Runtime Type checking for 'root<Foo.Bar:Required>/name': Assertion failed because NULL is incompatible with string"
        ];

        // TODO: Bug https://github.com/attitude/duck-types-php/pull/1
        yield 'nullableIntHas' => [
            'context' => [],
            'fusion' => <<<'Fusion'
                root = 123
                root.@type = '?string'
                Fusion,
            'message' => "Runtime Type checking for 'root': Assertion failed because integer literal 123 is incompatible with ?string"
        ];
    }

    /**
     * @test
     * @dataProvider nestedSubkeysOfDataStructure
     * @dataProvider correctTypePasses
     */
    public function correctFusionTypeWorks(array $fusionContext, string $fusionCode, string $expectedOutput)
    {
        $runtime = $this->getTypedRuntimeForFusionCode($fusionCode);
        $runtime->pushContextArray($fusionContext);

        $renderedFusion = $runtime->render('root');

        self::assertSame(trim($expectedOutput), trim($renderedFusion), 'Rendered Fusion didnt match expected.');
    }

    /**
     * @test
     * @dataProvider wrongTypeThrows
     */
    public function wrongFusionTypeWorks(array $fusionContext, string $fusionCode, string $expectedMessage)
    {
        self::expectException(RuntimeTypeException::class);
        self::expectExceptionMessage($expectedMessage);

        $runtime = $this->getTypedRuntimeForFusionCode($fusionCode);
        $runtime->pushContextArray($fusionContext);

        $runtime->render('root');

        self::assertTrue(true);
    }

    protected function getTypedRuntimeForFusionCode(string $fusionCode): TypedRuntime
    {
        $controllerContext = $this->getMockBuilder(ControllerContext::class)->disableOriginalConstructor()->getMock();
        $fusionAst = (new Parser())->parse($fusionCode);
        $runtime = new TypedRuntime($fusionAst, $controllerContext);
        // TODO: Temp. fix #3548
        $runtime->pushContext('somethingSoContextIsNotEmpty', 'bar');
        return $runtime;
    }
}

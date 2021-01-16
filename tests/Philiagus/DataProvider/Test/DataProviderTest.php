<?php
/*
 * This file is part of philiagus/dataprovider
 *
 * (c) Andreas Bittner <php@philiagus.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/** @noinspection PhpUnusedPrivateFieldInspection */
declare(strict_types=1);

namespace Philiagus\DataProvider\Test;

use Philiagus\DataProvider\DataProvider;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{

    public function provideTestDataProvideProvidesCases(): array
    {
        $types = [
            "TYPE_SCALAR" => [
                DataProvider::TYPE_SCALAR,
                function ($value) {
                    return is_scalar($value);
                },
            ],
            "TYPE_ARRAY" => [
                DataProvider::TYPE_ARRAY,
                function ($value) {
                    return is_array($value);
                },
            ],
            "TYPE_ARRAY_MAP" => [
                DataProvider::TYPE_ARRAY_MAP,
                function ($value) {
                    return is_array($value) &&
                        array_keys($value) !== range(0, count($value) - 1);
                },
            ],
            "TYPE_BOOLEAN" => [
                DataProvider::TYPE_BOOLEAN,
                function ($value) {
                    return is_bool($value);
                }],
            "TYPE_FLOAT" => [
                DataProvider::TYPE_FLOAT,
                function ($value) {
                    return is_float($value);
                }],
            "TYPE_INTEGER" => [
                DataProvider::TYPE_INTEGER,
                function ($value) {
                    return is_integer($value);
                }],
            "TYPE_OBJECT" => [
                DataProvider::TYPE_OBJECT,
                function ($value) {
                    return is_object($value);
                }],
            "TYPE_RESOURCE" => [
                DataProvider::TYPE_RESOURCE,
                function ($value) {
                    return is_resource($value);
                }],
            "TYPE_STRING" => [
                DataProvider::TYPE_STRING,
                function ($value) {
                    return is_string($value);
                }],
            "TYPE_NULL" => [
                DataProvider::TYPE_NULL,
                function ($value) {
                    return $value === null;
                }],
            "TYPE_NAN" => [
                DataProvider::TYPE_NAN,
                function ($value) {
                    return is_float($value) && is_nan($value);
                }],
            "TYPE_INFINITE" => [
                DataProvider::TYPE_INFINITE,
                function ($value) {
                    return is_float($value) && is_infinite($value);
                }],
            "TYPE_ARRAY_EMPTY" => [
                DataProvider::TYPE_ARRAY_EMPTY,
                function ($value) {
                    return $value === [];
                }],
            "TYPE_ARRAY_LIST" => [
                DataProvider::TYPE_ARRAY_LIST,
                function ($value) {
                    return is_array($value) && array_keys($value) === range(0, count($value) - 1);
                }],
            "TYPE_ARRAY_MAP_INTEGER" => [
                DataProvider::TYPE_ARRAY_MAP_INTEGER,
                function ($value) {
                    if (!is_array($value)) return false;
                    foreach ($value as $key => $_) {
                        if (!is_integer($key)) return false;
                    }

                    return true;
                }],
            "TYPE_ARRAY_MAP_STRING" => [
                DataProvider::TYPE_ARRAY_MAP_STRING,
                function ($value) {
                    if (!is_array($value)) return false;
                    foreach ($value as $key => $_) {
                        if (!is_string($key)) return false;
                    }

                    return true;
                }],
            "TYPE_ARRAY_MAP_INTEGER_STRING" => [
                DataProvider::TYPE_ARRAY_MAP_INTEGER_STRING,
                function ($value) {
                    if (!is_array($value)) return false;
                    $foundInteger = false;
                    $foundString = false;
                    foreach ($value as $key => $_) {
                        $foundInteger = $foundInteger || is_integer($key);
                        $foundString = $foundString || is_string($key);
                    }

                    return $foundInteger && $foundString;
                }],
        ];

        $cases = $types;
        foreach ($types as $name1 => [$mask1, $closure1]) {
            foreach ($types as $name2 => [$mask2, $closure2]) {
                if ($name1 === $name2) continue;
                $cases["$name1 | $name2"] = [
                    $mask1 | $mask2,
                    function ($value) use ($closure1, $closure2) {
                        return $closure1($value) || $closure2($value);
                    },
                ];
                foreach ($types as $name3 => [$mask3, $closure3]) {
                    if ($name1 === $name3 || $name2 === $name3) continue;
                    $cases["$name1 | $name2 | $name3"] = [
                        $mask1 | $mask2 | $mask3,
                        function ($value) use ($closure1, $closure2, $closure3) {
                            return $closure1($value) || $closure2($value) || $closure3($value);
                        },
                    ];
                }
            }
        }

        return $cases;
    }

    /**
     * @param int $types
     * @param \Closure $validation
     *
     * @dataProvider provideTestDataProvideProvidesCases
     */
    public function testDataProvideProvidesCases(int $types, \Closure $validation): void
    {
        $provider = new DataProvider($types);
        $cases = $provider->provide(false);
        self::assertNotEmpty($cases, "Provider did not provide any cases for $types");
        foreach ($cases as $name => $value) {
            self::assertTrue($validation($value), "Case $name did not match requirements");
        }
    }

    public function testThatProvidingAsPHPUnitDataProviderJustWrapsIntoArray(): void
    {
        // reduced to scalar to avoid issues with objects not being the same and NaN not being the same
        $provider = new DataProvider(DataProvider::TYPE_SCALAR & ~DataProvider::TYPE_NAN);
        $casesInArray = $provider->provide(true);
        $casesSimple = $provider->provide(false);
        foreach ($casesSimple as $name => $case) {
            self::assertSame([$case], $casesInArray[$name]);
        }
    }

    public function testFilteringMappingAndCustomCasesPerformsInOrder(): void
    {
        $provider = new DataProvider(DataProvider::TYPE_INTEGER | DataProvider::TYPE_STRING);
        self::assertTrue(
            array_reduce($provider->provide(false),
                function ($carry, $value) {
                    return $carry && (is_int($value) || is_string($value));
                },
                true
            )
        );
        $expect = $provider->provide(false);
        self::assertSame($expect, $provider->provide(false));

        // filter out integers
        $filter = function ($value) {
            return is_int($value);
        };

        $expect = array_filter($expect, $filter);
        $provider->filter($filter);
        self::assertSame($expect, $provider->provide(false));

        // add an object
        $provider->addCase('my case', null);
        $expect['custom NULL: my case'] = null;
        self::assertSame($expect, $provider->provide(false));

        // map values
        $map = function ($value) {
            if ($value === null) return 'null';

            return (string) $value;
        };
        $expect = array_map(
            $map,
            $expect
        );
        $provider->map($map);
        self::assertSame($expect, $provider->provide(false));

        // add value
        $expect['custom string: my case 2'] = 'some string';
        $provider->addCase('my case 2', 'some string');
        self::assertSame($expect, $provider->provide(false));
    }

    public function provideIsSameCases(): array
    {
        $elements = [
            'null' => null,
            'NaN' => NAN,
            'INF' => INF,
            '-INF' => -INF,
            'int 0' => 0,
            'string 0' => '0',
            'int 1' => 1,
            'string 1' => '1',
            'exception 1' => new \Exception('Exception 1'),
            'exception 2' => new \Exception('Exception 2'),
            'array' => ['1', 2, NAN],
            'array 2' => ['2', 5, INF],
            'array 3' => ['key' => 'value'],
            'object' => new class() {
                private $nan = NAN;
                private $float = 1.3;
                private $integer = 15;
                private $string = 'string';
                private $object;
                private $array = [1, 2, '-INF'];
                private $self;

                public function __construct()
                {
                    $this->object = new \stdClass();
                    $this->object->nan = NAN;
                    $this->object->float = 1.3;
                    $this->object->integer = 15;
                    $this->object->string = 'string';
                    $this->object->array = [INF, NAN, '-INF' => -INF];
                    $this->self = $this;
                }
            },
        ];

        $cases = [];
        foreach ($elements as $name => $value) {
            $cases["$name same as $name"] = [$value, $value, true];
            foreach ($elements as $name2 => $value2) {
                if ($name === $name2) continue;
                $cases["$name not same as $name2"] = [$value, $value2, false];
            }
        }

        return $cases;
    }

    /**
     * @param $a
     * @param $b
     * @param bool $expected
     *
     * @dataProvider provideIsSameCases
     */
    public function testIsSame($a, $b, bool $expected): void
    {
        self::assertSame($expected, DataProvider::isSame($a, $b));
    }

    public function provideEqualsCases(): array
    {
        usleep(2);
        $createStdClass = function (string $stringValue = 'string') {
            $obj = new \stdClass();
            $obj->inf = INF;
            $obj->nan = NAN;
            $obj->string = $stringValue;
            $obj->int = 500;
            $obj->dateTime = new \DateTime('@0');
            $obj->subObj = new class() {
                private $inf = INF;
                private $n_inf = -INF;
                private $nan = NAN;
                private $integer = 100;
                private $string = 'glubb';
            };

            return $obj;
        };
        $all = [
            [
                'NAN' => NAN,
            ],
            [
                'INF' => INF,
            ],
            [
                '-INF' => -INF,
            ],
            [
                'null' => null,
                'false' => false,
                '0' => 0,
                '0.0' => 0.0,
            ],
            [
                'string 100' => '100',
                '100' => 100,
            ],
            [
                'stdClass' => $createStdClass(),
                'stdClass2' => $createStdClass(),
            ],
            [
                'stdClass4' => $createStdClass('other value'),
            ],
            [
                'stdClass3' => (object) [
                    'a' => 'f',
                    'foo' => 'bar',
                    'ten' => 10,
                ],
            ],
            [
                'array' => [1, false, NAN],
                'array 2' => [2 => NAN, 0 => 1, 1 => false],
            ],
            [
                'array 3' => ['key' => 'value', 'somekey' => 'somevalue', 'anotherkey' => 4],
                'array 4' => ['somekey' => 'somevalue', 'anotherkey' => 4, 'key' => 'value'],
            ],
        ];

        $cases = [];
        foreach ($all as $groupId => $group) {
            foreach ($group as $name => $case) {
                foreach ($all as $groupId2 => $group2) {
                    foreach ($group2 as $name2 => $case2) {
                        if ($groupId === $groupId2) {
                            $cases["$name == $name2"] = [$case, $case2, true];
                        } else {
                            $cases["$name != $name2"] = [$case, $case2, false];
                        }
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * @param $a
     * @param $b
     * @param $equals
     *
     * @dataProvider provideEqualsCases
     */
    public function testIsEqual($a, $b, bool $equals): void
    {
        self::assertSame($equals, DataProvider::isEqual($a, $b));
    }

}
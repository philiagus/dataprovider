<?php
/*
 * This file is part of philiagus/dataprovider
 *
 * (c) Andreas Eicher <php@philiagus.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Philiagus\DataProvider;

class DataProvider
{

    private const CALLBACK_FILTER = 1,
        CALLBACK_MAP = 2,
        CALLBACK_ADD_CASE = 3;

    public const TYPE_BOOLEAN = 1 << 0,
        TYPE_FLOAT = 1 << 1,
        TYPE_INTEGER = 1 << 2,
        TYPE_OBJECT = 1 << 3,
        TYPE_RESOURCE = 1 << 4,
        TYPE_STRING = 1 << 5,
        TYPE_NULL = 1 << 6,
        TYPE_NAN = 1 << 7,
        TYPE_INFINITE = 1 << 8,
        TYPE_ARRAY_EMPTY = 1 << 9,
        TYPE_ARRAY_LIST = 1 << 10,
        TYPE_ARRAY_MAP_INTEGER = 1 << 11,
        TYPE_ARRAY_MAP_STRING = 1 << 12,
        TYPE_ARRAY_MAP_INTEGER_STRING = 1 << 13;

    public const TYPE_ARRAY_MAP = self::TYPE_ARRAY_MAP_INTEGER | self::TYPE_ARRAY_MAP_STRING | self::TYPE_ARRAY_MAP_INTEGER_STRING,
        TYPE_ARRAY = self::TYPE_ARRAY_LIST | self::TYPE_ARRAY_MAP | self::TYPE_ARRAY_EMPTY,
        TYPE_SCALAR = self::TYPE_BOOLEAN | self::TYPE_INTEGER | self::TYPE_FLOAT | self::TYPE_STRING | self::TYPE_NAN | self::TYPE_INFINITE;

    public const TYPE_ALL = PHP_INT_MAX;

    /**
     * @var mixed[]
     */
    private array $callbacks = [];

    /**
     * @var array<string, mixed>
     */
    private array $customCases = [];

    /**
     * Create a new data provider object, providing only the specified types by default
     *
     * @param int $types
     */
    public function __construct(private readonly int $types = self::TYPE_ALL)
    {
    }


    /**
     * Checks that $a and $b are the same
     * For the purpose of this comparison the following values are equal if:
     * - NAN
     *      - NAN == NAN
     * - arrays
     *      - have the same keys in the same order
     *      - the values of every key are the same following these rules
     * - objects, integer, float, string, null, resources
     *      - follow normal === rules
     *
     * @param $a
     * @param $b
     *
     * @return bool
     */
    public static function isSame($a, $b): bool
    {
        if (gettype($a) === gettype($b)) {
            if (is_float($a) && is_nan($a)) {
                return is_nan($b);
            }
            if (is_null($a) || is_scalar($a) || is_object($a) || is_resource($a)) {
                return $a === $b;
            }
            if (is_array($a)) {
                if (array_keys($a) !== array_keys($b)) {
                    return false;
                }
                foreach ($a as $key => $value) {
                    if (!self::isSame($value, $b[$key])) {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Checks that $a and $b are equal
     * For the purpose of this comparison the following values are equal if:
     * - NAN
     *      - NAN == NAN
     * - arrays
     *      - have the same keys no matter the order
     *      - the values of every key are equal following these rules
     * - objects
     *      - of the same class
     *      - have the same property names
     *      - properties with the same name contain equal value following these rules
     * - integer, float, string, null, resources
     *      - follow normal == rules
     *
     * @param $a
     * @param $b
     *
     * @return bool
     */
    public static function isEqual($a, $b): bool
    {
        if (is_float($a) && is_nan($a)) {
            return is_float($b) && is_nan($b);
        }
        if (is_object($a) === is_object($b)) {
            if (is_null($a) || is_scalar($a) || is_resource($a)) {
                return $a == $b;
            }
            if (is_array($a)) {
                if (!is_array($b)) return false;
                if (count($a) !== count($b)) return false;
                foreach ($a as $key => $value) {
                    if (!array_key_exists($key, $b) || !self::isEqual($value, $b[$key])) {
                        return false;
                    }
                }

                return true;
            }
            if (is_object($a) && is_object($b)) {
                if (get_class($a) !== get_class($b)) return false;
                $reflectionA = new \ReflectionObject($a);
                $reflectionB = new \ReflectionObject($b);
                do {
                    $propertiesB = [];
                    foreach ($reflectionB->getProperties() as $property) {
                        $propertiesB[$property->getName()] = $property;
                    }

                    foreach ($reflectionA->getProperties() as $propertyA) {
                        $propertyB = $propertiesB[$propertyA->getName()] ?? null;
                        if ($propertyB === null) {
                            return false;
                        }
                        $valueA = $propertyA->getValue($a);
                        $valueB = $propertyB->getValue($b);
                        if (!self::isEqual($valueA, $valueB)) {
                            return false;
                        }
                    }
                } while (
                    ($reflectionA = $reflectionA->getParentClass()) &&
                    ($reflectionB = $reflectionB->getParentClass())
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Provides the case elements as key => value pair array
     * If $wrapIntoArrayForPHPUnitProvider is true the values of the array are provided
     * as arrays with one element, being the test element. This is used to ease the
     * use of the data provider in context of PHPUnit data providers
     *
     * @param bool $wrapIntoArrayForPHPUnitProvider
     *
     * @return array
     */
    public function provide(bool $wrapIntoArrayForPHPUnitProvider = true): array
    {
        $cases = [];

        if ($this->types & self::TYPE_BOOLEAN) {
            $cases['boolean: true'] = true;
            $cases['boolean: false'] = false;
        }

        if ($this->types & self::TYPE_FLOAT) {
            $cases += [
                'float: PHP_INT_MIN - .5' => PHP_INT_MIN - .5,
                'float: -666.0' => -666.0,
                'float: -1.5' => -1.5,
                'float: -1.0' => -1.0,
                'float: -.5' => -.5,
                'float: -1 / 3' => -1 / 3,
                'float: -.3333' => -.3333,
                'float: -0.0' => -0.0,
                'float: 0.0' => 0.0,
                'float: .3333' => .3333,
                'float: 1 / 3' => 1 / 3,
                'float: .5' => .5,
                'float: 1.0' => 1.0,
                'float: 1.5' => 1.5,
                'float: M_PI' => M_PI,
                'float: 666.0' => 666.0,
                'float: PHP_INT_MAX + .5' => PHP_INT_MAX + .5,
            ];
        }

        if ($this->types & self::TYPE_INTEGER) {
            $cases += [
                'integer: PHP_INT_MIN' => PHP_INT_MIN,
                'integer: -666' => -666,
                'integer: -1' => -1,
                'integer: 0' => 0,
                'integer: 1' => 1,
                'integer: 666' => 666,
                'integer: PHP_INT_MAX' => PHP_INT_MAX,
            ];
        }

        if ($this->types & self::TYPE_OBJECT) {
            $cases['object: stdClass'] = new \stdClass();
            $cases['object: \Exception'] = new \Exception();
            $cases['object: \DateTime'] = new \DateTime();
            $cases['object: DataProvider'] = new self();
        }

        if ($this->types & self::TYPE_RESOURCE) {
            if (defined('STDIN') && is_resource(STDIN)) {
                $cases['resource: STDIN'] = STDIN;
            }
        }

        if ($this->types & self::TYPE_STRING) {
            $cases += [
                'string: <empty>' => '',
                'string: hello world' => 'hello world',
                'string: 100' => '100',
                'string: true' => 'true',
                'string: false' => 'false',
                'string: null' => 'null',
                'string: INF' => 'INF',
            ];

        }

        if ($this->types & self::TYPE_NULL) {
            $cases['null'] = null;
        }

        if ($this->types & self::TYPE_NAN) {
            $cases['NaN'] = NAN;
        }

        if ($this->types & self::TYPE_INFINITE) {
            $cases['infinite: INF'] = INF;
            $cases['infinite: -INF'] = -INF;
        }

        if ($this->types & self::TYPE_ARRAY_EMPTY) {
            $cases['array: empty'] = [];
        }

        if ($this->types & self::TYPE_ARRAY_LIST) {
            $cases['array list: mixed types'] = [1, true, 'string', 1.0, ['array element']];
            foreach ((new self(~self::TYPE_ARRAY))->provide(false) as $name => $element) {
                $cases['array list: single ' . $name] = [$element];
            }
        }

        if ($this->types & self::TYPE_ARRAY_MAP_INTEGER) {
            foreach ((new self(~self::TYPE_ARRAY))->provide(false) as $name => $element) {
                $cases['array map integer: single element key 1, value ' . $name] = [1 => $element];
            }
            foreach ((new self(self::TYPE_INTEGER))->provide(false) as $name => $element) {
                if ($element === 0) continue;
                $cases["array map integer: single element key $name"] = [$element => 'element'];
            }
            $cases['array map integer: multiple keys 0, 5, 19'] = [0 => 'element 1', 5 => 'element 5', 19 => 'element 19'];
            $cases['array map integer: multiple keys 0, -4, 10, -100, 99'] = [
                0 => 'element 0',
                -4 => 'element -4',
                10 => 'element 10',
                -100 => 'element -100',
                99 => 'element 99',
            ];
            $cases['array map integer: mixed types'] = [
                9 => 1,
                12 => true,
                16 => 'string',
                20 => 1.0,
                25 => ['array element'],
            ];
        }

        if ($this->types & self::TYPE_ARRAY_MAP_STRING) {
            $cases['array map string: mixed types'] = [
                'element 1' => 1,
                'element 2' => true,
                'element 3' => 'string',
                'element 4' => 1.0,
                'element 5' => ['array element'],
            ];
            foreach ((new self(~self::TYPE_ARRAY))->provide(false) as $name => $element) {
                $cases['array map string: single element, value ' . $name] = ['key' => $element];
            }
            foreach ((new self(self::TYPE_STRING))->provide(false) as $name => $element) {
                if (is_numeric($element)) continue;
                $cases["array map string: single element key $name"] = [$element => 'element'];
            }
            $cases['array map string: multiple elements'] = ['key1' => 'value1', 'a' => 'b', 'something' => 'something else', 'foo' => 'bar'];
            $cases['array map string: empty key'] = ['' => 'empty key'];
            $cases['array map string: nullbyte key'] = ["\0" => 'nullbyte key'];
        }

        if ($this->types & self::TYPE_ARRAY_MAP_INTEGER_STRING) {
            $cases['array map integer string'] = [1 => 1, 'boolean' => true, 'string' => 'string', 2 => 2.0];

        }

        $cases += $this->customCases;

        foreach ($this->callbacks as [$type, $data]) {
            switch ($type) {
                case self::CALLBACK_FILTER:
                    $cases = array_filter($cases, $data);
                    break;
                case self::CALLBACK_MAP:
                    $cases = array_map($data, $cases);
                    break;
                case self::CALLBACK_ADD_CASE:
                    [$name, $value] = $data;
                    $type = gettype($value);
                    $cases["custom $type: $name"] = $value;
            }
        }

        if ($wrapIntoArrayForPHPUnitProvider) {
            $cases = array_map(
                function ($value) {
                    return [$value];
                },
                $cases
            );
        }

        return $cases;
    }

    /**
     * Add a custom case to the list of cases
     * filter, map and addCase are performed in the order they are called
     *
     * @param string $name
     * @param $value
     *
     * @return $this
     */
    public function addCase(string $name, $value): self
    {
        $this->callbacks[] = [self::CALLBACK_ADD_CASE, [$name, $value]];

        return $this;
    }

    /**
     * Sets a filter to be used on the cases.
     * The closure will be provided with one argument, being the value of the case
     * Return false to remove this case from the list of cases
     *
     * filter, map and addCase are performed in the order they are called
     *
     * @param \Closure $filter
     *
     * @return $this
     */
    public function filter(\Closure $filter): self
    {
        $this->callbacks[] = [self::CALLBACK_FILTER, $filter];

        return $this;
    }

    /**
     * Sets a map to be used on the cases. Return the modified value to change a value
     *
     * filter, map and addCase are performed in the order they are called
     *
     * @param \Closure $map
     *
     * @return $this
     */
    public function map(\Closure $map): self
    {
        $this->callbacks[] = [self::CALLBACK_MAP, $map];

        return $this;
    }

}

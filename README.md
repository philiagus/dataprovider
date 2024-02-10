# philiagus/dataprovider

A data provider to easily test type assertion cases.

# Is it tested?
Tested on the following PHP Version: PHP8.0 - PHP8.3

100% test covered. Test coverage generated on PHP8.3

# How do I get it?

The code is available via composer `composer require philiagus/dataprovider`.

If you are only using this code in the context of testing, adding the package to `require-dev` using the  `--dev` option is recommended.

See the composer documentation for more information on that.

# Why do I need it?

Sometimes a method is defined to take a mixed set of arguments, such as `integer|float`.

A unit test should then make sure, that no other type of value is accepted. That is to say, that any `string` for example will cause a defined Exception or result in a defined behaviour.

In PHPUnit this is most times done with a dataProvider.

```php

use Philiagus\DataProvider\DataProvider;
use PHPUnit\Framework\TestCase;

class MyClassTest extends TestCase
{
    public function provideCases(): array
    {
        // create a dataprovider only providing things that are neither string nor integer
        $provider = new DataProvider(~(DataProvider::TYPE_INTEGER | DataProvider::TYPE_STRING));
        return $provider->provide();
    }
    
    /**
     * @param mixed $value
     * @dataProvider provideCases 
     */
    public function testMethod($value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MyClass::method($value);
    }
}
```

# What can it do?

## The constructor argument

In most cases we only want to use certain types as arguments for the test. That's why the argument type has been elevated to the highest level, being provided right when creating the DataProvider.

For ease of use some things that PHP treats as the same have been split into individual cases.

These constants are a bitmask, so you can bit-operator them together, such as `DataProvider::TYPE_BOOLEAN | DataProvider::TYPE_FLOAT` to get boolean and float cases.

The `DataProvider::TYPE_ALL` contains all cases and is the default of the DataProvider.

| Constant                                      | What it does                                                                                                                                                                                                                                   |
|-----------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `DataProvider::TYPE_BOOLEAN`                  | Provides the two fundamental bool cases `true` and `false`                                                                                                                                                                                     |
| `DataProvider::TYPE_FLOAT`                    | Provides positive and negative float values but excludes INF, -INF and NAN                                                                                                                                                                     |  
| `DataProvider::TYPE_INTEGER`                  | Provides positive and negative integer values (including 0)                                                                                                                                                                                    |    
| `DataProvider::TYPE_OBJECT`                   | Provides various objects, such as \stdClass, \Exception and \DateTime                                                                                                                                                                          |  
| `DataProvider::TYPE_RESOURCE`                 | Provides a resource, if it is able to. As what provides a resource might change with future versions of PHP this currently uses STDIN as a resource and excludes it from the test list if STDIN is not a resource in your current environment. |
| `DataProvider::TYPE_STRING`                   | Provides various strings, including empty string                                                                                                                                                                                               |
| `DataProvider::TYPE_NULL`                     | Provides `null` as case                                                                                                                                                                                                                        |
| `DataProvider::TYPE_NAN`                      | Provides `NAN` as a case                                                                                                                                                                                                                       |
| `DataProvider::TYPE_INFINITE`                 | Provides both `INF` and `-INF`                                                                                                                                                                                                                 |
| `DataProvider::TYPE_ARRAY_EMPTY`              | Provides an empty array                                                                                                                                                                                                                        |  
| `DataProvider::TYPE_ARRAY_LIST`               | Provides multiple arrays with a single and multiple elements, with a sequential index, starting at 0                                                                                                                                           |
| `DataProvider::TYPE_ARRAY_MAP_INTEGER`        | Provides multiple arrays with single and multiple elements, with not sequential indizies or index not starting at 0                                                                                                                            |
| `DataProvider::TYPE_ARRAY_MAP_STRING`         | Provides multiple arrays with single and multiple elements, each having string keys                                                                                                                                                            |
| `DataProvider::TYPE_ARRAY_MAP_INTEGER_STRING` | Provides arrays with mixed keys (so string and integer keys)                                                                                                                                                                                   |

There are also some compound constants

| Constant                       | What it includes                                                                                                                                                                                                             |
|--------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `DataProvider::TYPE_ARRAY_MAP` | <ul><li>`DataProvider::TYPE_ARRAY_MAP_INTEGER`</li><li>`DataProvider::TYPE_ARRAY_MAP_STRING`</li><li>`DataProvider::TYPE_ARRAY_MAP_INTEGER_STRING`</li></ul>                                                                 |
| `DataProvider::TYPE_ARRAY`     | <ul><li>`DataProvider::TYPE_ARRAY_LIST`</li><li>`DataProvider::TYPE_ARRAY_MAP`</li><li>`DataProvider::TYPE_ARRAY_EMPTY`</li></ul>                                                                                            |
| `DataProvider::TYPE_SCALAR`    | <ul><li>`DataProvider::TYPE_BOOLEAN`</li><li>`DataProvider::TYPE_INTEGER`</li><li>`DataProvider::TYPE_FLOAT`</li><li>`DataProvider::TYPE_STRING`</li><li>`DataProvider::TYPE_NAN`</li><li>`DataProvider::TYPE_INFINITE`</ul> |

## The methods `filter`, `map` and `addCase`

The method `filter(\Closure $filter)` allows you to define a filter. The closure will receive a single argument. If the closure returns `true`, the case will be left in the list of cases. If it returns `false`, the case will be filtered out.

The method `map(\Closure $map)` allows to alter and map the values provided. The closure receives the individual value as single argument. Return the altered value. The name of the case will not be altered.

The method `addCase(string $name, $value)` allows you to add your own case.

These methods are applied in the same order that you apply them to the DataProvider. So first calling filter, then map and then addCase will only map on the filtered values and the added case will not be filtered nor mapped.

## The static methods `isEqual` and `isSame`

The `isEqual($a, $b)` and `isSame($a, $b)` return `true` if the two values are equal/same following the rules of the DataProvider.

Normally, `NAN != NAN`, as these two things are not the same - obviously. But in the context of this DataProvider it is not uncommon to desire equality between two `NAN`.

An example:

```php

use Philiagus\DataProvider\DataProvider;
use PHPUnit\Framework\TestCase;

class MyClass {

    public static function wrap($value): array
    {
        return [$value];
    }    

}

class MyClassTest extends TestCase
{
    public function provideCases(): array
    {
        // create a dataprovider only providing things that are neither string nor integer
        $provider = new DataProvider(~(DataProvider::TYPE_INTEGER | DataProvider::TYPE_STRING));
        return $provider->provide();
    }
    
    /**
     * @param mixed $value
     * @dataProvider provideCases 
     */
    public function testMethod($value): void
    {
        $expected = [$value];
        $result = MyClass::wrap($value);
        self::assertSame($expected, $result); // this would only work for not NAN
        
        self::assertTrue(DataProvider::isSame($expected, $result)); // this works, as for the DataProvider NAN === NAN
    }
}
```

**WARNING**: Neither `isEquals` nor `isSame` currently provide recursion safety and will thus break with max recursion level reached. This might be added in the future, as soon as DataProvider requires PHP >= 7.4.

### Specific rules of `isEquals`
For the purpose of this comparison the following values are equal if:
- NAN
     - NAN == NAN
- arrays
     - have the same keys no matter the order
     - the values of every key are equal following these rules
- objects
     - of the same class
     - have the same property names
     - properties with the same name contain equal value following these rules
- integer, float, string, null, resources
     - follow normal == rules
    
### Specific rules of `isSame`
For the purpose of this comparison the following values are equal if:
- NAN
     - NAN == NAN
- arrays
     - have the same keys in the same order
     - the values of every key are the same following these rules
- objects, integer, float, string, null, resources
     - follow normal === rules

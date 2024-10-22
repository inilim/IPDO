<?php

declare(strict_types=1);

use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\Test\TestCase;
use Inilim\IPDO\DTO\QueryParamDTO;

final class QueryParamDTOTest extends TestCase
{
    const REGEX_SPACE = '\s+';

    public function testQuery(): void
    {
        $values = [
            [
                'query' => '{item1}{item2}',
                'values' => [
                    'item1' => 1,
                    'item2' => [1, 2, 3],
                ],
                // ' :78f8_319  ( :78f8_320, :78f8_321, :78f8_322 ) '
                'expectingRegex'  => '#^' . self::REGEX_SPACE .
                    // ::***_****
                    '\:[a-z\d]{4}\_\d{3,4}' . self::REGEX_SPACE .
                    '\(' . self::REGEX_SPACE .
                    // ( :***_****,:***_****:***_**** )
                    '\:[a-z\d]{4}\_\d{3,4},\:[a-z\d]{4}\_\d{3,4},\:[a-z\d]{4}\_\d{3,4}' . self::REGEX_SPACE .
                    '\)' . self::REGEX_SPACE .
                    '$#',
            ],
        ];

        foreach ($values as $idx => $value) {
            $dto = new QueryParamDTO($value['query'], $value['values']);
            $this->assertMatchesRegularExpression($value['expectingRegex'], $dto->query, \strval($idx));
        }
    }

    public function testAbsenceOfCurlyBraces(): void
    {
        $query = '{item1}{item2} {item3} {item4}{item5}{item6}';
        $values = [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, 2, 3, 4, 5],
        ];

        $dto = new QueryParamDTO($query, $values);
        $this->assertSame(strpos($dto->query, '{'), false);
        $this->assertSame(strpos($dto->query, '}'), false);
    }

    public function test_the_erroneous_presence_of_curly_braces(): void
    {
        $query = '{item1} {item2} {item3}{item4}{item5} {item6}';
        $values = [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, 2, 3, 4, 5],
        ];

        $dto = new QueryParamDTO($query, $values);
        $this->assertNotSame(strpos($dto->query, '{') !== false, true);
        $this->assertNotSame(strpos($dto->query, '}') !== false, true);
    }

    public function testCountBracketsFromQuery(): void
    {
        $values = [
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => true,
                    'item6' => [1, 2, 3, 4, 5],
                ],
                'expectingOpen'  => 1,
                'expectingClose' => 1,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => ['1', '2', '3', '4', '5'],
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expectingOpen'  => 2,
                'expectingClose' => 2,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expectingOpen'  => 2,
                'expectingClose' => 2,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expectingOpen'  => 2,
                'expectingClose' => 2,
            ],
        ];

        foreach ($values as $i => $subValues) {
            $dto = new QueryParamDTO($subValues['query'], $subValues['values']);
            $this->assertSame(\substr_count($dto->query, '('), $subValues['expectingOpen'], \strval($i));
            $this->assertSame(\substr_count($dto->query, ')'), $subValues['expectingClose'], \strval($i));
        }
    }

    public function testCountHolesFromQuery(): void
    {
        $values = [
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => true,
                    'item6' => [1, 2, 3, 4, 5],
                ],
                'expecting' => 10,
            ],
            [
                'query' => '-- :comment
                {item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => true,
                    'item6' => [1, 2, 3, 4, 5],
                ],
                'expecting' => 11,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => ['1', '2', '3', '4', '5'],
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expecting' => 14,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expecting' => 15,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expecting' => 11,
            ],
        ];

        foreach ($values as $i => $subValues) {
            $dto = new QueryParamDTO($subValues['query'], $subValues['values']);
            $this->assertSame(\substr_count($dto->query, ':'), $subValues['expecting'], \strval($i));
        }
    }

    public function testCountValuesAfterPrepare(): void
    {
        $values = [
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => true,
                    'item6' => [1, 2, 3, 4, 5],
                ],
                'expecting' => 10,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => ['1', '2', '3', '4', '5'],
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expecting' => 14,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expecting' => 15,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => '2',
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expecting' => 11,
            ],
        ];

        foreach ($values as $i => $subValues) {
            $dto = new QueryParamDTO($subValues['query'], $subValues['values']);
            $this->assertSame(\sizeof($dto->values), $subValues['expecting'], \strval($i));
        }
    }

    public function testBadParamsMultiValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, [2], 3, 4, 5],
        ]);
    }

    public function testBadParamsBadObj(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new \stdClass,
            'item5' => true,
            'item6' => [1, 2, 3, 4, 5],
        ]);
    }

    public function testBadParamsMultiValueBadObj(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, new \stdClass, 3, 4, 5],
        ]);
    }

    public function testBadParamsMultiValueEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, [], 3, 4, 5],
        ]);
    }

    public function testBadParamsEmptyValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, []);
    }

    public function testBadParamsNotFoundValueFromQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, 2, 3, 4, 5],
        ]);
    }

    public function testBadParamsEmptyHolesFromQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, 2, 3, 4, 5],
        ]);
    }

    public function testBadParamsNotFoundValueFromValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
        ]);
    }

    public function testBadParamsInNullGiven(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, [
            'item1' => 1,
            'item2' => '2',
            'item3' => 3.0,
            'item4' => new ByteParamDTO('byte'),
            'item5' => true,
            'item6' => [1, null, 3, 4, 5],
        ]);
    }
}

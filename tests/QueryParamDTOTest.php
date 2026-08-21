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
                // ' :78f8_319  :78f8_320, :78f8_321, :78f8_322 '
                'expectingRegex'  => '#^' . self::REGEX_SPACE .
                    // ::***_****
                    '\:[a-z\d]{4}\_\d{3,4}' . self::REGEX_SPACE .
                    //  :***_****,:***_****:***_**** 
                    '\:[a-z\d]{4}\_\d{3,4},\:[a-z\d]{4}\_\d{3,4},\:[a-z\d]{4}\_\d{3,4}' . self::REGEX_SPACE .
                    '$#',
            ],
        ];

        foreach ($values as $idx => $value) {
            $dto = new QueryParamDTO($value['query'], $value['values']);
            $this->assertMatchesRegularExpression($value['expectingRegex'], $dto->query, \strval($idx));
        }
    }

    public function test_count_commas_after_prepare(): void
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
                'expecting' => 4,
            ],
            [
                'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
                'values' => [
                    'item1' => 1,
                    'item2' => ['1', '2'],
                    'item3' => 3.0,
                    'item4' => new ByteParamDTO('byte'),
                    'item5' => false,
                    'item6' => ['1', '2', '3', '4', '5'],
                ],
                'expecting' => 5,
            ],
        ];

        foreach ($values as $i => $subValues) {
            $dto = new QueryParamDTO($subValues['query'], $subValues['values']);
            $this->assertSame(\substr_count($dto->query, ','), $subValues['expecting'], \strval($i));
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
        $this->assertSame(false, \strpos($dto->query, '{'));
        $this->assertSame(false, \strpos($dto->query, '}'));
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
            $this->assertSame(\count($dto->values), $subValues['expecting'], \strval($i));
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

    /**
     * SQL-запрос без плейсхолдеров `{...}` и пустой массив параметров.
     * Ожидается, что исключение не выбрасывается, запрос остаётся как есть,
     * а значения (values) остаются пустыми.
     */
    public function testPlainQueryWithoutPlaceholders(): void
    {
        $dto = new QueryParamDTO('SELECT * FROM users', []);

        $this->assertSame([], $dto->values);
        $this->assertSame('SELECT * FROM users', $dto->query);
    }

    /**
     * Один и тот же плейсхолдер встречается дважды со скалярным значением.
     * Ожидается, что оба вхождения заменяются на два уникальных именованных
     * параметра, а фигурные скобки в запросе не остаются.
     */
    public function testDuplicateScalarPlaceholder(): void
    {
        $dto = new QueryParamDTO('{item}{item}', ['item' => 1]);

        $this->assertCount(2, $dto->values);
        $this->assertSame(false, \strpos($dto->query, '{'));
        $this->assertSame(false, \strpos($dto->query, '}'));
        $this->assertSame(2, \substr_count($dto->query, ':'));
    }

    /**
     * Дублированный плейсхолдер с объектом ByteParamDTO.
     * Ожидается, что объект клонируется для каждого вхождения (значения не
     * ссылаются на исходный объект), а фигурные скобки в запросе не остаются.
     */
    public function testDuplicateByteParamDTO(): void
    {
        $byte = new ByteParamDTO('byte');
        $dto = new QueryParamDTO('{item}{item}', ['item' => $byte]);

        $this->assertCount(2, $dto->values);
        foreach ($dto->values as $value) {
            $this->assertInstanceOf(ByteParamDTO::class, $value);
            $this->assertNotSame($byte, $value);
        }
        $this->assertSame(false, \strpos($dto->query, '{'));
        $this->assertSame(false, \strpos($dto->query, '}'));
    }

    /**
     * Оператор IN с массивом объектов ByteParamDTO.
     * Ожидается, что каждый объект клонируется в отдельный именованный параметр,
     * в запросе остаётся одна запятая между ними, а фигурные скобки убраны.
     */
    public function testInOperatorWithByteParamDTO(): void
    {
        $dto = new QueryParamDTO('{item}', [
            'item' => [new ByteParamDTO('a'), new ByteParamDTO('b')],
        ]);

        $this->assertCount(2, $dto->values);
        foreach ($dto->values as $value) {
            $this->assertInstanceOf(ByteParamDTO::class, $value);
        }
        $this->assertSame(false, \strpos($dto->query, '{'));
        $this->assertSame(false, \strpos($dto->query, '}'));
        $this->assertSame(1, \substr_count($dto->query, ','));
    }

    /**
     * Дублированный плейсхолдер с неподдерживаемым объектом (stdClass).
     * Ожидается InvalidArgumentException с сообщением, начинающимся с "IPDO:".
     */
    public function testDuplicateBadObjectThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#^IPDO\:#');

        new QueryParamDTO('{item}{item}', [
            'item' => new \stdClass,
        ]);
    }
}

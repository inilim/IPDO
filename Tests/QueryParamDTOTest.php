<?php

declare(strict_types=1);

use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\Test\TestCase;
use Inilim\IPDO\DTO\QueryParamDTO;
use Inilim\IPDO\Exception\IPDOException;

final class QueryParamDTOTest extends TestCase
{
    // public function testQuery(): void
    // {
    //     $values = [
    //         [
    //             'query' => 'SELECT * FROM test_table WHERE id = {item1} AND statuses IN {item2}',
    //             'values' => [
    //                 'item1' => 1,
    //                 'item2' => [1, 2, 3, 4, 5],
    //             ],
    //             'expectingRegex'  => "#SELECT * FROM test_table WHERE id =\s+\:[a-z0-9]{4}\_[0-9]{3,4}\s+AND statuses IN\s+\(\s+(\:[a-z0-9]{4}\_[0-9]{3,4})\s+\)\s+#",
    //         ],
    //         [
    //             'query' => '{item1}{item2}{item3}{item4}{item5}{item6}',
    //             'values' => [
    //                 'item1' => 1,
    //                 'item2' => ['1', '2', '3', '4', '5'],
    //                 'item3' => 3.0,
    //                 'item4' => new ByteParamDTO('byte'),
    //                 'item5' => false,
    //                 'item6' => ['1', '2', '3', '4', '5'],
    //             ],
    //             'expectingOpen'  => 2,
    //             'expectingClose' => 2,
    //         ],
    //         [
    //             'query' => '{item1}{item2}{item3}{item4}{item5}{item6}{item6}',
    //             'values' => [
    //                 'item1' => 1,
    //                 'item2' => '2',
    //                 'item3' => 3.0,
    //                 'item4' => new ByteParamDTO('byte'),
    //                 'item5' => false,
    //                 'item6' => ['1', '2', '3', '4', '5'],
    //             ],
    //             'expectingOpen'  => 2,
    //             'expectingClose' => 2,
    //         ],
    //         [
    //             'query' => '{item1}{item2}{item3}{item4}{item5}{item6}{item6}',
    //             'values' => [
    //                 'item1' => 1,
    //                 'item2' => '2',
    //                 'item3' => 3.0,
    //                 'item4' => new ByteParamDTO('byte'),
    //                 'item5' => false,
    //                 'item6' => ['1', '2', '3', '4', '5'],
    //             ],
    //             'expectingOpen'  => 2,
    //             'expectingClose' => 2,
    //         ],
    //     ];
    // }

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

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, []);
    }

    public function testBadParamsNotFoundValueFromQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);

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

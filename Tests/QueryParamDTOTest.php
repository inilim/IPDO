<?php

declare(strict_types=1);

use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\Test\TestCase;
use Inilim\IPDO\DTO\QueryParamDTO;
use Inilim\IPDO\Exception\IPDOException;

final class QueryParamDTOTest extends TestCase
{
    public function tjhfh(): void
    {
        $valuesBefore = [
            'class'         => 1,
            'method'        => 2,
            'params'        => 3,
            'execute_after' => 4,
            'key_not_found' => 'dawkkjawhdlajkhwdjhwdjkj',
            'created_at' => [11, 22, 33, 44, 55],
        ];

        $valuesBefore = [
            'class'         => 1,
            'method'        => 2,
            'params'        => 3,
            'execute_after' => 4,
            'created_at'    => 11,
        ];

        $query = 'INSERT INTO tasks (class, method, params, execute_after, created_at)
                VALUES ({class}, {method}, {params}, {execute_after}, {execute_after}, ({created_at}))';

        $dto = new QueryParamDTO($query, $valuesBefore);


        $this->assertSame($dto->values, $valuesBefore);
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

        foreach ($values as $subValues) {
            $dto = new QueryParamDTO($subValues['query'], $subValues['values']);
            $this->assertSame(\sizeof($dto->values), $subValues['expecting']);
        }
    }

    // public function testQueryAfterPrepare(): void
    // {

    // }


    public function testBadParamsMultiValue(): void
    {
        $this->expectException(IPDOException::class);

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

    public function testBadParamsEmptyValues(): void
    {
        $this->expectException(IPDOException::class);

        $query = '{item1}{item2}{item3}{item4}{item5}{item6}';

        new QueryParamDTO($query, []);
    }

    public function testBadParamsNotFoundValueFromQuery(): void
    {
        $this->expectException(IPDOException::class);

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
        $this->expectException(IPDOException::class);

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
        $this->expectException(IPDOException::class);

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
        $this->expectException(IPDOException::class);

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
